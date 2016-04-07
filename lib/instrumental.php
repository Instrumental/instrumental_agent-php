<?php
require __DIR__ . '/../vendor/autoload.php';
// use Monolog\Logger;
// use Monolog\Handler\StreamHandler;

class Instrumental // extends Thread
{


    const MAX_BUFFER = 5000;
    
    function __construct()
    {
        // $this->pool  = new Pool(1);
        // $this->jobs  = [];
        $this->queue = new SplQueue();
        $this->dns_resolutions = 0;
        $this->host = "collector.instrumentalapp.com.";
        $this->port = 8001;
        $this->address = $this->getIpv4AddressForHost();
        $this->tcp_client = stream_socket_client("tcp://{$this->address}:{$this->port}", $errno, $errorMessage);
        // $this->log = new Logger('name');
        // $this->log->pushHandler(new StreamHandler('/tmp/development.log', Logger::DEBUG));

    }

    function puts($message)
    {
        // $this->log->addError('Bar');
        error_log("$message\n", 3, "logs/development.log");
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

    public function getQueue()
    {
        return $this->queue;
    }

    public function setQueueFullWarning($bool)
    {
        $this->queueFullWarning = $bool;
    }

    public function gauge($metric, $value, $time = null, $count = 1)
    {
        if($time)
        {
            // do nothing
        } else
        {
            $time = time();
        }
        
        if($this->is_valid_metric($metric, $value, $time, (int)$count) &&
           $this->send_command("gauge", $metric, $value, $time, (int)$count))
        {
            return value;
        } else
        {
            return null;
        }
        
    //   end
    // rescue Exception => e
    //   report_exception(e)
    //   nil
    // end
    }

    public function is_valid_metric($metric, $value, $time, $count)
    {
        
        $valid_metric = preg_match("/^([\d\w\-_]+\.)*[\d\w\-_]+$/i", $metric);
        $valid_value  = preg_match("/^-?\d+(\.\d+)?(e-\d+)?$/", (string)$value);

        if($valid_metric && $valid_value)
        {
            return TRUE;
        }

        if($valid_metric)
        {
            report_invalid_metric(metric);
        }

        if($valid_value)
        {
            report_invalid_value(metric, value);
        }
         
        return FALSE;
    }

    public function send_command(...$args)
    {
        $cmd = join(" ", $args);
        if($this->getEnabled())
        {
            //$this->start_connection_worker(); // TODO should immediately return
                                              // if already running

            $this->jobs->push(new Worker($this->tcp_client, $cmd))
            $this->pool->submit();

            
            // if($this->getQueue()->count() < self::MAX_BUFFER)
            // {
            //     $this->setQueueFullWarning(FALSE);
            //     $this->queue_message($cmd);
            // }
            // TODO QUEUE FULL
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
}