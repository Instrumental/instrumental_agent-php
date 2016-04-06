<?php
 
/**
 * Author: Abu Ashraf Masnun
 * URL: http://masnun.me
 */
 
class WorkerThreads extends Thread
{
    private $workerId;
 
    public function __construct($id)
    {
        $this->workerId = $id;
    }
 
    public function run()
    {
        sleep(rand(0, 3));
        echo "Worker {$this->workerId} ran" . PHP_EOL;
    }
}
 
// Worker pool
$workers = [];
 
// Initialize and start the threads
foreach (range(0, 5) as $i) {
    $workers[$i] = new WorkerThreads($i);
    $workers[$i]->start();
}
 
// Let the threads come back
foreach (range(0, 5) as $i) {
    $workers[$i]->join();
}
