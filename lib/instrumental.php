<?php
require __DIR__ . '/../vendor/autoload.php';
// use Monolog\Logger;
// use Monolog\Handler\StreamHandler;

class Instrumental // extends Thread
{


    const MAX_BUFFER = 5000;
    const RESOLUTION_FAILURES_BEFORE_WAITING = 3;
    const RESOLUTION_WAIT = 30;
    const RESOLVE_TIMEOUT = 1;

    function __construct()
    {
        $this->puts("__construct");
        // $this->pool  = new Pool(1);
        // $this->jobs  = [];
        // $this->queue = new SplQueue();
        $this->dns_resolutions = 0;
        $this->host = "collector.instrumentalapp.com.";
        $this->port = 8000;
        $this->last_connect_at = 0;
        $this->socket = null;
        // $this->address = $this->getIpv4AddressForHost();
        // $this->log = new Logger('name');
        // $this->log->pushHandler(new StreamHandler('/tmp/development.log', Logger::DEBUG));

    }

    public function setHost($host)
    {
      $this->host = $host;
    }

    public function setPort($port)
    {
      $this->port = $port;
    }

    public function connect()
    {
        $this->puts("connect");
        $this->socket = @stream_socket_client("tcp://{$this->host}:{$this->port}", $errno, $errorMessage, 10);
        if(!$this->socket)
        {
          $this->puts("Connection error $errno : $errorMessage");
          return FALSE;
        }

        $version = "0.0.1";
        $hostname = gethostname();
        $pid = getmypid();
        $runtime = phpversion();
        $platform = php_uname();
        $cmd = "hello version ruby/instrumental_agent/$version hostname $hostname pid $pid runtime $runtime platform $platform\n";

        $this->socket_send($cmd);
        $this->puts("connect fgets");
        $line = fgets($this->socket, 1024);
        $this->puts("connect $line");
        if($line != "ok\n")
        {
          $this->puts("Sending hello failed.");
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
          return FALSE;
        }
        return TRUE;
    }

    function puts($message)
    {
        // $this->log->addError('Bar');
        echo "$message\n";
        // flush();
        ob_flush();
        // error_log("$message\n", 3, "logs/development.log");
    }

    public function setApiKey($api_key)
    {
        $this->api_key = $api_key;
    }

    public function setEnabled($enabled)
    {
        $this->enabled = $enabled;
    }

    public function getEnabled()
    {
        return $this->enabled;
    }

    public function getSocket()
    {
        return $this->socket;
    }

    public function getQueue()
    {
        return $this->queue;
    }

    public function setQueueFullWarning($bool)
    {
        $this->queueFullWarning = $bool;
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
          $this->puts("Exception caught: " . $e->getMessage());
        } catch (Exception $ex) {}
      } finally {
        restore_error_handler();
      }
      return $ret;
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
      // TODO: figure out if there's a way to re-raise errors/warnings
      $result = null;
      $exception = null;
      $this->handleErrors(function() use ($metric, $function, $multiplier, &$result, &$exception) {
        $this->puts("time");
        $start = microtime(TRUE);

        restore_error_handler();
        try {
          $result = $function();
        } catch (Exception $e) {
          // $this->puts("time catch exception: " . print_r($e, TRUE));
          $exception = $e;
        }
        $this->setupErrorHandler();

        $finish = microtime(TRUE);
        $duration = $finish - $start;
        $this->gauge($metric, $duration * $multiplier, $start);
      });
      // $this->puts("time exception: " . print_r($exception, TRUE));
      if($exception)
      {
        throw $exception;
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
        $cmd = join(" ", $args) . "\n";
        if($this->getEnabled())
        {
            return $this->socket_send($cmd);
        }
        return TRUE;
    }

    public function socket_send($message)
    {
      $this->puts("socket_send");
      if(!$this->socket)
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
    }

    // public function start_connection_worker()
    // {
    //     if($this->getEnabled())
    //     {
    //         $this->disconnect();
    //         $address = $this->getIpv4AddressForHost();
    //         $this->puts("have an address" . $address);
    //         if($address)
    //         {
    //             $this->setPid = getmypid();
    //             $this->setFailureCount = 0;
    //             // $this->setWorker = new WorkerThread();
    //             $this->puts("about to start thread");
    //             $this->start();
    //         }
    //     }
    // }

    public function getIpv4AddressForHost()
    {
        // $this->dns_resolutions = $this->dns_resolutions + 1;
        // TODO timeouts and waiting?
        // Set timeout and retries to 1 to have a maximum execution
        // time of 1 second for the DNS lookup:
        // tried this but it didn't appear to change the behavior
        // putenv('RES_OPTIONS=retrans:1 retry:1 timeout:1 attempts:1');
        return gethostbyname($this->host);
    }

    public function disconnect()
    {
        if($this->is_connected())
        {
            // TODO should have time out for flushing
            // TODO does this really flush?
            fflush($this->socket);
            fclose($this->socket);
            $this->socket = null;
        }
    }

    public function is_connected()
    {
        if($this->socket)
        {
            // TODO is this really returning a boolean for timing out?
            $timed_out = stream_get_meta_data($this->socket)[0];
            $this->socket && $timed_out;
        } else
        {
            return FALSE;
        }
    }

    // worker loop
    public function run()
    {
        $this->puts("beginning of run");
        // TODO should have connection timeout
        $this->socket = stream_socket_client("tcp://{$this->address}:{$this->port}", $errno, $errorMessage);

        $hello_options = [
            "php/instrumental_agent/{$this->version}",
            $this->getHostname(),
            $this->getPid(),
            $this->getPhpRuntime()
        ];

        $this->send_with_reply_timeout(join(" ", $hello_options));
        $this->send_with_reply_timeout("authenticate {$this->api_key}");
        $this->puts("end of run");
    }


    public function queue_message($message)
    {
        if($this->getEnabled())
        {
            // while ($this->queue->isEmpty()) {
            //     $this->queue->enqueue("asdf");
            //     sleep(1);
            //     $this->puts("current queue " . spl_object_hash($this->queue) . print_r($this->queue));
            // }
            $this->puts("queue message called ". $message);
            $this->getQueue()->enqueue($message);
            $this->getQueue()->enqueue("asdf");
            // $this->queue->enqueue("no method");
            // $fuck = new SplQueue();
            // $fuck->enqueue("fuuuuuuuuuck");
            // $this->queue = $fuck;
            // $this->puts("fuck queue " . print_r($fuck));
            // $this->puts("current queue size " . $this->queue->count());
            // $this->puts("current queue " . print_r($this->queue));
            // $this->puts("current queue " . print_r($this->getQueue()));
            // $this->puts("queue equality " . print_r($this->getQueue() === $this->queue));
        }
    }

    public function ipv4_address_for_host($host, $port, $moment_to_connect = null)
    {
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
            $this->puts("Couldn't resolve address for #{host}:#{port}", "warn");
            $this->report_exception($e);
            return null;
        }
    }
}