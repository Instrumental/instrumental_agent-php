<?php
require __DIR__ . '/../vendor/autoload.php';
class Instrumental
{
    const MAX_BUFFER = 5000;
    const RESOLUTION_FAILURES_BEFORE_WAITING = 3;
    const RESOLUTION_WAIT = 30;
    const RESOLVE_TIMEOUT = 1;

    function __construct()
    {
        $this->queue = new SplQueue();
        $this->dns_resolutions = 0;
        $this->last_connect_at = 0;
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