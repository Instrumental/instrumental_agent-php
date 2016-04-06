<?php
class Instrumental
{
    const MAX_BUFFER = 5000;
    
    function __construct()
    {
        $this->queue = new SplQueue();
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
            $this->start_connection_worker(); // TODO should immediately return
                                              // if already running
                
            if($this->getQueue()->count() < self::MAX_BUFFER)
            {
                $this->setQueueFullWarning(FALSE);
                $this->queue_message($cmd);
            }
            // TODO QUEUE FULL
        }
                      
    }

    public function start_connection_worker()
    {
        return false;
    }

    public function queue_message($message)
    {
        if($this->getEnabled())
        {
            $this->getQueue()->enqueue($message);
        }
    }
    
}