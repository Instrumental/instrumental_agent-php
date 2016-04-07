class Worker extends Collectable {
  public $val;

    public function __construct($message, $tcp_client){
        $this->tcp_client = $tcp_client;
        $this->message    = $message
    }

    public function run(){
        fwrite($this->tcp_client, $this->message);
        //        $this->setGarbage();
    }
}