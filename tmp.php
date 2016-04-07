<?php
class MyWork extends Threaded {

    public function __construct($message) {
        $this->message = $message;
    }
    
    public function run() {
        echo($this->message);
    }
}

class MyWorker extends Worker {
    
    public function __construct($something) {
        $this->something = $something;
    }
    
    public function run() {
        /**  **/
    }
}

$pool = new Pool(8, \MyWorker::class, ["string for each worker"]);
$pool->submit(new MyWork("mywork string"));
var_dump($pool);