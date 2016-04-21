<?php
// ob_implicit_flush(1);

require "lib/instrumental.php";

exec("php test/TestServer.php &> test/server.log &");
sleep(2);

class AgentTest extends \PHPUnit_Framework_TestCase
{
    public function testSendsGaugeCallsCorrectly()
    {
        $expectedData =
          "/hello version ruby\/instrumental_agent\/0.0.1 hostname [^ ]+ pid \d+ runtime 7.0.5 platform Darwin [^ ]+ [^ ]+ Darwin Kernel Version [^ ]+: [^ ]+ [^ ]+ [^ ]+ [^ ]+:[^ ]+:[^ ]+ [^ ]+ [^ ]+; root:xnu-[^ ]+~1\/RELEASE_X86_64 x86_64\n" .
          "authenticate test\n" .
          "gauge php.gauge 2 [0-9]+ 1\n/";

        $I = new Instrumental();
        $I->setHost("127.0.0.1");
        $I->setPort(4040);
        $I->setApiKey("test");
        $I->setEnabled(true);

        $ret = $I->gauge('php.gauge', 2);
        $this->assertEquals(2, $ret);
        sleep(2);

        $this->assertRegExp($expectedData, file_get_contents("test/server_commands_received"));
    }

    public function testSendsIncrementCallsCorrectly()
    {
        $expectedData =
          "/hello version ruby\/instrumental_agent\/0.0.1 hostname [^ ]+ pid \d+ runtime 7.0.5 platform Darwin [^ ]+ [^ ]+ Darwin Kernel Version [^ ]+: [^ ]+ [^ ]+ [^ ]+ [^ ]+:[^ ]+:[^ ]+ [^ ]+ [^ ]+; root:xnu-[^ ]+~1\/RELEASE_X86_64 x86_64\n" .
          "authenticate test\n" .
          "increment php.increment 2 [0-9]+ 1\n/";

        $I = new Instrumental();
        $I->setHost("127.0.0.1");
        $I->setPort(4040);
        $I->setApiKey("test");
        $I->setEnabled(true);

        $ret = $I->increment('php.increment', 2);
        $this->assertEquals(2, $ret);
        sleep(2);

        $this->assertRegExp($expectedData, file_get_contents("test/server_commands_received"));
    }

    public function testSendsNoticeCallsCorrectly()
    {
        $expectedData =
          "/hello version ruby\/instrumental_agent\/0.0.1 hostname [^ ]+ pid \d+ runtime 7.0.5 platform Darwin [^ ]+ [^ ]+ Darwin Kernel Version [^ ]+: [^ ]+ [^ ]+ [^ ]+ [^ ]+:[^ ]+:[^ ]+ [^ ]+ [^ ]+; root:xnu-[^ ]+~1\/RELEASE_X86_64 x86_64\n" .
          "authenticate test\n" .
          "notice [0-9]+ 0 this is a test php notice\n/";

        $I = new Instrumental();
        $I->setHost("127.0.0.1");
        $I->setPort(4040);
        $I->setApiKey("test");
        $I->setEnabled(true);

        $ret = $I->notice("this is a test php notice");
        $this->assertEquals("this is a test php notice", $ret);
        sleep(2);

        $this->assertRegExp($expectedData, file_get_contents("test/server_commands_received"));
    }

    // public function testHostnameResolution()
    // {
    //     $expectedData = "127.0.0.1";
    //
    //     $I = new Instrumental();
    //
    //     $address = $I->ipv4_address_for_host('localhost', 80);
    //
    //     $this->assertEquals($expectedData, $address);
    // }
}
