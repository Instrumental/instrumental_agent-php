<?php
require __DIR__ . '/../vendor/autoload.php';

class Instrumental
{

    // TODO: Review these constants for applicability in a blocking world
    const MAX_BUFFER = 5000;
    const SEND_REPLY_TIMEOUT = 1; // NOTE: setting this low since the agent is synchronous
    const RESOLUTION_FAILURES_BEFORE_WAITING = 3;
    const RESOLUTION_WAIT = 30;
    const RESOLVE_TIMEOUT = 1;
    const CONNECT_TIMEOUT = 1;

    function __construct()
    {
        $this->puts("__construct");
        $this->queue = new SplQueue();
        $this->dns_resolutions = 0;
        $this->host = "collector.instrumentalapp.com.";
        $this->port = 8000;
        $this->last_connect_at = 0;
        $this->socket = null;
        $this->queue_full_warning = null;
        $this->is_enabled = TRUE;
    }

    public function setHost($host)
    {
      $this->host = $host;
    }

    public function setPort($port)
    {
      $this->port = $port;
    }

    public function setApiKey($api_key)
    {
        $this->api_key = $api_key;
    }

    public function setEnabled($enabled)
    {
        $this->is_enabled = $enabled;
    }

    public function connect()
    {
        $this->puts("connect");

        $host = $this->ipv4_address_for_host($this->host, $this->port);
        if(!$host)
        {
          return FALSE;
        }

        // NOTE: This timeout doesn't apply to DNS, but does apply to the actual connecting
        $this->socket = @stream_socket_client("tcp://{$host}:{$this->port}", $errno, $errorMessage, self::CONNECT_TIMEOUT);
        $this->puts("connect after stream_socket_client, stream_socket_get_name: " . stream_socket_get_name($this->socket, TRUE));
        stream_set_timeout($this->socket, self::SEND_REPLY_TIMEOUT, 0);
        if(!$this->is_connected())
        {
          $this->puts("Connection error $errno : $errorMessage");
          $this->disconnect(); // TODO: delay retry?
          return FALSE;
        }

        $version = "0.0.1";
        $hostname = gethostname();
        $pid = getmypid();
        $runtime = phpversion();
        $platform = php_uname();
        $cmd = "hello version ruby/instrumental_agent/$version hostname $hostname pid $pid runtime $runtime platform $platform\n";

        // $this->puts("Sleeping. Enable packet loss to test.");
        // sleep(10);
        // $this->puts("Resuming.");

        // NOTE: dropping packets didn't appear to affect sending
        $this->socket_send($cmd);
        $this->puts("connect fgets");
        // NOTE: dropping packets did affect fgets, and SEND_REPLY_TIMEOUT did apply
        $line = fgets($this->socket, 1024);
        $this->puts("connect $line");
        if($line != "ok\n")
        {
          $this->puts("Sending hello failed.");
          $this->disconnect(); // TODO: delay retry?
          return FALSE;
        }

        $cmd = "authenticate $this->api_key\n";
        $this->socket_send($cmd);
        $this->puts("connect fgets");
        $line = fgets($this->socket, 1024);
        $this->puts("connect $line");
        if($line != "ok\n")
        {
          $this->puts("Authentication failed.");
          $this->disconnect(); // TODO: delay retry?
          return FALSE;
        }
        return TRUE;
    }

    function puts($message)
    {
        // $this->log->addError('Bar');
        echo time() . " $message\n";
        // flush();
        ob_flush();
        // error_log("$message\n", 3, "logs/development.log");
    }

    function exception_error_handler($errno, $errstr, $errfile, $errline ) {
        throw new ErrorException($errstr, $errno, 0, $errfile, $errline);
    }

    public function setupErrorHandler()
    {
      set_error_handler(array("Instrumental", "exception_error_handler"));
    }

    public function handleErrors($function)
    {
      $ret = null;
      try {
        $this->setupErrorHandler();
        $ret = $function();
      } catch (Exception $e) {
        try {
          $this->report_exception($e);
        } catch (Exception $ex) {}
      } finally {
        restore_error_handler();
      }
      return $ret;
    }

    public function report_exception($e)
    {
      $this->puts("Exception caught: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    }

    public function gauge($metric, $value, $time = null, $count = 1)
    {
        return $this->handleErrors(function() use ($metric, $value, $time, $count) {
          $this->puts("gauge");
          if($time)
          {
              if($time instanceOf DateTimeInterface)
              {
                $time = $time->getTimestamp();
              } else
              {
                $time = (int)$time;
              }
          } else
          {
              $time = time();
          }

          if($this->is_valid_metric($metric, $value, $time, (int)$count) &&
             $this->send_command("gauge", $metric, $value, $time, (int)$count))
          {
              return $value;
          } else
          {
              return null;
          }
        });
    }

    public function increment($metric, $value = 1, $time = null, $count = 1)
    {
        return $this->handleErrors(function() use (&$metric, &$value, &$time, &$count) {
          $this->puts("increment");
          if($time)
          {
              if($time instanceOf DateTimeInterface)
              {
                $time = $time->getTimestamp();
              } else
              {
                $time = (int)$time;
              }
          } else
          {
              $time = time();
          }
          $this->puts("increment2");

          if($this->is_valid_metric($metric, $value, $time, (int)$count) &&
             $this->send_command("increment", $metric, $value, $time, (int)$count))
          {
              $this->puts("increment3");
              return $value;
          } else
          {
              $this->puts("increment4");
              return null;
          }
        });
    }

    public function notice($note, $time = null, $duration = 0)
    {
        return $this->handleErrors(function() use ($note, $time, $duration) {
          $this->puts("notice");
          if($time)
          {
              if($time instanceOf DateTimeInterface)
              {
                $time = $time->getTimestamp();
              } else
              {
                $time = (int)$time;
              }
          } else
          {
              $time = time();
          }

          if($this->is_valid_note($note) &&
             $this->send_command("notice", $time, (int)$duration, $note))
          {
              return $note;
          } else
          {
              return null;
          }
        });
    }

    public function time($metric, $function, $multiplier = 1)
    {
      $result = null;
      $user_exception = null;
      $this->handleErrors(function() use ($metric, $function, $multiplier, &$result, &$user_exception) {
        $this->puts("time");
        $start = microtime(TRUE);

        restore_error_handler();
        try {
          $result = $function();
        } catch (Exception $e) {
          // $this->puts("time catch exception: " . print_r($e, TRUE));
          $user_exception = $e;
        }
        $this->setupErrorHandler();

        $finish = microtime(TRUE);
        $duration = $finish - $start;
        $this->gauge($metric, $duration * $multiplier, $start);
      });
      // $this->puts("time exception: " . print_r($user_exception, TRUE));
      if($user_exception)
      {
        throw $user_exception;
      }
      return $result;
    }

    public function timeMs($metric, $function)
    {
      return $this->time($metric, $function, 1000);
    }

    public function is_valid_note($note)
    {
      $this->puts("is_valid_note");
      return preg_match("/[\n\r]/", $note) === 0;
    }

    public function is_valid_metric($metric, $value, $time, $count)
    {
        $valid_metric = preg_match("/^([\d\w\-_]+\.)*[\d\w\-_]+$/i", $metric);
        $this->puts("valid_metric: $valid_metric");
        $valid_value  = preg_match("/^-?\d+(\.\d+)?(e-\d+)?$/", print_r($value, TRUE));
        $this->puts("valid_value: $valid_value");

        if($valid_metric && $valid_value)
        {
            return TRUE;
        }

        if(!$valid_metric)
        {
            $this->report_invalid_metric($metric);
        }

        if(!$valid_value)
        {
            $this->report_invalid_value($metric, $value);
        }

        return FALSE;
    }

    public function report_invalid_metric($metric)
    {
      $this->increment("agent.invalid_metric");
      $this->puts("Invalid metric " . print_r($metric, TRUE));
    }

    public function report_invalid_value($metric, $value)
    {
      $this->increment("agent.invalid_value");
      $this->puts("Invalid value " . print_r($value, TRUE) . " for " . print_r($metric, TRUE));
    }

    public function send_command(...$args)
    {
        $this->puts("send_command");
        if($this->is_enabled)
        {
            $cmd = join(" ", $args) . "\n";
            if($this->queue->count() < self::MAX_BUFFER)
            {
                $ret = $this->queue_message($cmd);
                $this->send_queued_messages();
                return $ret;
            } else {
                if(!$this->queue_full_warning)
                {
                    $this->queue_full_warning = TRUE;
                    $this->puts("Queue full(" . $this->queue->count() . "), dropping commands...");
                }
                $this->puts("Dropping command, queue full(" . $this->queue->count() . "): " . trim($cmd));
                return null;
            }
        }
        return FALSE;
    }

    public function send_queued_messages()
    {
        while($this->queue->count() > 0)
        {
            $cmd = $this->queue->bottom();
            $ret = $this->socket_send($cmd);
            if($ret)
            {
              $this->queue->dequeue();
            } else {
              return $ret;
            }
        }
    }

    public function socket_send($message)
    {
      $ret = $this->handleErrors(function() use ($message) {
        $this->puts("socket_send socket: " . print_r($this->socket, TRUE));
        if(!$this->is_connected())
        {
          if(!$this->connect())
          {
            return FALSE;
          }
        }
        $this->puts("socket_send message: $message");
        $ret = @fwrite($this->socket, $message);
        if($ret)
        {
          return TRUE;
        } else
        {
          $this->puts("error writing to socket");
          return FALSE;
        }
      });
      // Caught an exception, assume we're disconnected
      if($ret === null)
      {
        $this->disconnect();
      }

      return $ret;
    }

    public function disconnect()
    {
        $ret = $this->handleErrors(function() {
            $this->puts("disconnect");
            if($this->is_connected())
            {
                $this->puts("Disconnecting...");
                // NOTE: In testing, fflush returned immediately when all packets were being dropped
                fflush($this->socket);
                $this->puts("disconnect after fflush");
                // NOTE: In testing, fclose returned immediately when all packets were being dropped
                fclose($this->socket);
                $this->puts("disconnect after fclose");
                return TRUE;
            }
            return FALSE;
        });
        if($ret === null)
        {
            $this->puts("Error closing socket");
        }
        $this->socket = null;
    }

    public function is_connected()
    {
        if($this->socket)
        {
            $this->puts("is_connected");
            $this->puts("stream_get_meta_data:\n" . print_r(stream_get_meta_data($this->socket), TRUE));

            $read   = array($this->socket);
            $write  = NULL;
            $except = NULL;
            $this->puts("stream_select:\n" . print_r(stream_select($read, $write, $except, 0), TRUE));

            // TODO is this really returning a boolean for timing out?
            // $timed_out = stream_get_meta_data($this->socket)[0];
            // $this->socket && $timed_out;
            return TRUE;
        } else
        {
            return FALSE;
        }
    }

    public function queue_message($message)
    {
        if($this->is_enabled)
        {
            $this->puts("queue message called ". $message);
            $this->queue->enqueue($message);
            return $message;
        }
    }

    public function ipv4_address_for_host($host, $port, $moment_to_connect = null)
    {
        $this->puts("ipv4_address_for_host");
        try {
            if($moment_to_connect)
            {
                // do nothing
            } else
            {
                $moment_to_connect = time();
            }
            $this->dns_resolutions   = $this->dns_resolutions + 1;
            $time_since_last_connect = $moment_to_connect - $this->last_connect_at;
            if($this->dns_resolutions < self::RESOLUTION_FAILURES_BEFORE_WAITING || $time_since_last_connect >= self::RESOLUTION_WAIT)
            {
                $this->last_connect_at = $moment_to_connect;
                $resolver = new Net_DNS2_Resolver();
                $resolver->timeout = self::RESOLVE_TIMEOUT;
                $address = $resolver->query($host)->answer[0]->address;
                $this->dns_resolutions = 0;
                return $address;
            }
        } catch (Exception $e) {
            $this->puts("Couldn't resolve address for $host:$port", "warn");
            $this->report_exception($e);
            return null;
        }
    }
}