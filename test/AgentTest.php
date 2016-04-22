<?php
require "lib/instrumental.php";

class AgentTest extends \PHPUnit_Framework_TestCase
{
    public function setUp()
    {
      // clear the test server command file
      fopen("test/server_commands_received", 'w');
      exec("php test/TestServer.php &> test/server.log &");
      sleep(1);
    }

    public function factoryAgent()
    {
      $I = new Instrumental();
      $I->setHost("127.0.0.1");
      $I->setPort(4040);
      $I->setApiKey("test");
      $I->setEnabled(true);
      return $I;
    }

    public function testHandlesDisconnect()
    {
        $I = $this->factoryAgent();

        $expectedData =
          "/hello version ruby\/instrumental_agent\/0.0.1 hostname [^ ]+ pid \d+ runtime 7.0.5 platform Darwin [^ ]+ [^ ]+ Darwin Kernel Version [^ ]+: [^ ]+ [^ ]+ [^ ]+ [^ ]+:[^ ]+:[^ ]+ [^ ]+ [^ ]+; root:xnu-[^ ]+~1\/RELEASE_X86_64 x86_64\n" .
          "authenticate test\n" .
          "increment php.increment 2.2 [0-9]+ 1\n/";

        $ret = $I->increment('php.increment', 2.2);
        $this->assertEquals(2.2, $ret);
        sleep(2);

        $pid = file_get_contents("test/server.pid");
        echo exec("ps aux | grep php | grep TestServe[r]") . "\n";
        echo exec("kill $pid") . "\n";
        echo exec("ps aux | grep php | grep TestServe[r]") . "\n";
        sleep(2);

        for($i=1; $i<10000; ++$i) {
          $ret = $I->increment('php.increment', 3);
        }
        // TODO: have agent reconnect?
        $this->assertEquals(null, $ret);

        $this->assertRegExp($expectedData, file_get_contents("test/server_commands_received"));
    }

    public function testHandlesNoConnection()
    {
        $I = $this->factoryAgent();
        $I->setPort(666);

        $expectedData =
          "/^$/";

        $ret = $I->increment('php.increment', 2.2);
        $this->assertEquals(null, $ret);
        sleep(2);

        $this->assertRegExp($expectedData, file_get_contents("test/server_commands_received"));
    }

    public function testSendsIncrementCallsCorrectly()
    {
        $I = $this->factoryAgent();
        $expectedData =
          "/hello version ruby\/instrumental_agent\/0.0.1 hostname [^ ]+ pid \d+ runtime 7.0.5 platform Darwin [^ ]+ [^ ]+ Darwin Kernel Version [^ ]+: [^ ]+ [^ ]+ [^ ]+ [^ ]+:[^ ]+:[^ ]+ [^ ]+ [^ ]+; root:xnu-[^ ]+~1\/RELEASE_X86_64 x86_64\n" .
          "authenticate test\n" .
          "increment php.increment 2.2 [0-9]+ 1\n/";

        $ret = $I->increment('php.increment', 2.2);
        $this->assertEquals(2.2, $ret);
        sleep(2);

        $this->assertRegExp($expectedData, file_get_contents("test/server_commands_received"));
    }

    public function testSendsIncrementCallsCorrectlyWithTime()
    {
        $I = $this->factoryAgent();
        $expectedData =
          "/hello version ruby\/instrumental_agent\/0.0.1 hostname [^ ]+ pid \d+ runtime 7.0.5 platform Darwin [^ ]+ [^ ]+ Darwin Kernel Version [^ ]+: [^ ]+ [^ ]+ [^ ]+ [^ ]+:[^ ]+:[^ ]+ [^ ]+ [^ ]+; root:xnu-[^ ]+~1\/RELEASE_X86_64 x86_64\n" .
          "authenticate test\n" .
          "increment php.increment 2 123 1\n/";

        $ret = $I->increment('php.increment', 2, 123);
        $this->assertEquals(2, $ret);
        sleep(2);

        $this->assertRegExp($expectedData, file_get_contents("test/server_commands_received"));
    }

    public function testSendsIncrementCallsCorrectlyWithDateTime()
    {
        $I = $this->factoryAgent();
        $expectedData =
          "/hello version ruby\/instrumental_agent\/0.0.1 hostname [^ ]+ pid \d+ runtime 7.0.5 platform Darwin [^ ]+ [^ ]+ Darwin Kernel Version [^ ]+: [^ ]+ [^ ]+ [^ ]+ [^ ]+:[^ ]+:[^ ]+ [^ ]+ [^ ]+; root:xnu-[^ ]+~1\/RELEASE_X86_64 x86_64\n" .
          "authenticate test\n" .
          "increment php.increment 2 15 1\n/";

        $ret = $I->increment('php.increment', 2, new DateTime("1970-01-01 00:00:15"));
        $this->assertEquals(2, $ret);
        sleep(2);

        $this->assertRegExp($expectedData, file_get_contents("test/server_commands_received"));
    }

    public function testSendsIncrementCallsCorrectlyWithTimeAndCount()
    {
        $I = $this->factoryAgent();
        $expectedData =
          "/hello version ruby\/instrumental_agent\/0.0.1 hostname [^ ]+ pid \d+ runtime 7.0.5 platform Darwin [^ ]+ [^ ]+ Darwin Kernel Version [^ ]+: [^ ]+ [^ ]+ [^ ]+ [^ ]+:[^ ]+:[^ ]+ [^ ]+ [^ ]+; root:xnu-[^ ]+~1\/RELEASE_X86_64 x86_64\n" .
          "authenticate test\n" .
          "increment php.increment 2 123 456\n/";

        $ret = $I->increment('php.increment', 2, 123, 456);
        $this->assertEquals(2, $ret);
        sleep(2);

        $this->assertRegExp($expectedData, file_get_contents("test/server_commands_received"));
    }

    public function testDoesntSendIncrementWithInvalidMetric()
    {
        $I = $this->factoryAgent();
        $expectedData =
          "/hello version ruby\/instrumental_agent\/0.0.1 hostname [^ ]+ pid \d+ runtime 7.0.5 platform Darwin [^ ]+ [^ ]+ Darwin Kernel Version [^ ]+: [^ ]+ [^ ]+ [^ ]+ [^ ]+:[^ ]+:[^ ]+ [^ ]+ [^ ]+; root:xnu-[^ ]+~1\/RELEASE_X86_64 x86_64\n" .
          "authenticate test\n" .
          "increment agent.invalid_metric 1 [0-9]+ 1\n" .
          "increment agent.invalid_metric 1 [0-9]+ 1\n" .
          "increment agent.invalid_metric 1 [0-9]+ 1\n" .
          "increment agent.invalid_metric 1 [0-9]+ 1\n/";

        $ret = $I->increment('bad metric');
        $this->assertEquals(null, $ret);
        $ret = $I->increment(' badmetric');
        $this->assertEquals(null, $ret);
        $ret = $I->increment('badmetric ');
        $this->assertEquals(null, $ret);
        $ret = $I->increment('bad(metric');
        $this->assertEquals(null, $ret);
        sleep(2);

        $this->assertRegExp($expectedData, file_get_contents("test/server_commands_received"));
    }

    public function testDoesntSendIncrementWithInvalidValue()
    {
        $I = $this->factoryAgent();
        $expectedData =
          "/hello version ruby\/instrumental_agent\/0.0.1 hostname [^ ]+ pid \d+ runtime 7.0.5 platform Darwin [^ ]+ [^ ]+ Darwin Kernel Version [^ ]+: [^ ]+ [^ ]+ [^ ]+ [^ ]+:[^ ]+:[^ ]+ [^ ]+ [^ ]+; root:xnu-[^ ]+~1\/RELEASE_X86_64 x86_64\n" .
          "authenticate test\n" .
          "increment agent.invalid_value 1 [0-9]+ 1\n" .
          "increment agent.invalid_value 1 [0-9]+ 1\n" .
          "increment agent.invalid_value 1 [0-9]+ 1\n" .
          "increment agent.invalid_value 1 [0-9]+ 1\n/";

        $ret = $I->increment('bad.value', "a");
        $this->assertEquals(null, $ret);
        $ret = $I->increment('bad.value', "");
        $this->assertEquals(null, $ret);
        $ret = $I->increment('bad.value', FALSE);
        $this->assertEquals(null, $ret);
        $ret = $I->increment('bad.value', $I);
        $this->assertEquals(null, $ret);
        sleep(2);

        $this->assertRegExp($expectedData, file_get_contents("test/server_commands_received"));
    }

    public function testSendsGaugeCallsCorrectly()
    {
        $I = $this->factoryAgent();
        $expectedData =
          "/hello version ruby\/instrumental_agent\/0.0.1 hostname [^ ]+ pid \d+ runtime 7.0.5 platform Darwin [^ ]+ [^ ]+ Darwin Kernel Version [^ ]+: [^ ]+ [^ ]+ [^ ]+ [^ ]+:[^ ]+:[^ ]+ [^ ]+ [^ ]+; root:xnu-[^ ]+~1\/RELEASE_X86_64 x86_64\n" .
          "authenticate test\n" .
          "gauge php.gauge 2 [0-9]+ 1\n/";

        $ret = $I->gauge('php.gauge', 2);
        $this->assertEquals(2, $ret);
        sleep(2);

        $this->assertRegExp($expectedData, file_get_contents("test/server_commands_received"));
    }

    public function testSendsGaugeCallsCorrectlyWithDateTime()
    {
        $I = $this->factoryAgent();
        $expectedData =
          "/hello version ruby\/instrumental_agent\/0.0.1 hostname [^ ]+ pid \d+ runtime 7.0.5 platform Darwin [^ ]+ [^ ]+ Darwin Kernel Version [^ ]+: [^ ]+ [^ ]+ [^ ]+ [^ ]+:[^ ]+:[^ ]+ [^ ]+ [^ ]+; root:xnu-[^ ]+~1\/RELEASE_X86_64 x86_64\n" .
          "authenticate test\n" .
          "gauge php.gauge 2 15 1\n/";

        $ret = $I->gauge('php.gauge', 2, new DateTime("1970-01-01 00:00:15"));
        $this->assertEquals(2, $ret);
        sleep(2);

        $this->assertRegExp($expectedData, file_get_contents("test/server_commands_received"));
    }

    public function testSendsNoticeCallsCorrectly()
    {
        $I = $this->factoryAgent();
        $expectedData =
          "/hello version ruby\/instrumental_agent\/0.0.1 hostname [^ ]+ pid \d+ runtime 7.0.5 platform Darwin [^ ]+ [^ ]+ Darwin Kernel Version [^ ]+: [^ ]+ [^ ]+ [^ ]+ [^ ]+:[^ ]+:[^ ]+ [^ ]+ [^ ]+; root:xnu-[^ ]+~1\/RELEASE_X86_64 x86_64\n" .
          "authenticate test\n" .
          "notice [0-9]+ 0 this is a test php notice\n/";

        $ret = $I->notice("this is a test php notice");
        $this->assertEquals("this is a test php notice", $ret);
        sleep(2);

        $this->assertRegExp($expectedData, file_get_contents("test/server_commands_received"));
    }

    public function testSendsNoticeCallsCorrectlyWithTimeAndDuration()
    {
        $I = $this->factoryAgent();
        $expectedData =
          "/hello version ruby\/instrumental_agent\/0.0.1 hostname [^ ]+ pid \d+ runtime 7.0.5 platform Darwin [^ ]+ [^ ]+ Darwin Kernel Version [^ ]+: [^ ]+ [^ ]+ [^ ]+ [^ ]+:[^ ]+:[^ ]+ [^ ]+ [^ ]+; root:xnu-[^ ]+~1\/RELEASE_X86_64 x86_64\n" .
          "authenticate test\n" .
          "notice 123 456 this is a test php notice\n/";

        $ret = $I->notice("this is a test php notice", 123, 456);
        $this->assertEquals("this is a test php notice", $ret);
        sleep(2);

        $this->assertRegExp($expectedData, file_get_contents("test/server_commands_received"));
    }

    public function testSendsNoticeCallsCorrectlyWithDateTime()
    {
        $I = $this->factoryAgent();
        $expectedData =
          "/hello version ruby\/instrumental_agent\/0.0.1 hostname [^ ]+ pid \d+ runtime 7.0.5 platform Darwin [^ ]+ [^ ]+ Darwin Kernel Version [^ ]+: [^ ]+ [^ ]+ [^ ]+ [^ ]+:[^ ]+:[^ ]+ [^ ]+ [^ ]+; root:xnu-[^ ]+~1\/RELEASE_X86_64 x86_64\n" .
          "authenticate test\n" .
          "notice 15 0 this is a test php notice\n/";

        $ret = $I->notice("this is a test php notice", new DateTime("1970-01-01 00:00:15"));
        $this->assertEquals("this is a test php notice", $ret);
        sleep(2);

        $this->assertRegExp($expectedData, file_get_contents("test/server_commands_received"));
    }

    public function testDoeasntSendNoticeCallWithInvalidMessage()
    {
        $I = $this->factoryAgent();
        $expectedData =
          "/^$/";

        $ret = $I->notice("Bad\nNotice");
        $this->assertEquals(null, $ret);
        $ret = $I->notice("Bad\nNotice");
        $this->assertEquals(null, $ret);
        sleep(2);

        $this->assertRegExp($expectedData, file_get_contents("test/server_commands_received"));
    }

    public function testSendsTimeCallsCorrectly()
    {
        $I = $this->factoryAgent();
        $expectedData =
          "/hello version ruby\/instrumental_agent\/0.0.1 hostname [^ ]+ pid \d+ runtime 7.0.5 platform Darwin [^ ]+ [^ ]+ Darwin Kernel Version [^ ]+: [^ ]+ [^ ]+ [^ ]+ [^ ]+:[^ ]+:[^ ]+ [^ ]+ [^ ]+; root:xnu-[^ ]+~1\/RELEASE_X86_64 x86_64\n" .
          "authenticate test\n" .
          "gauge php.time 1.0[0-9]+ [0-9]+ 1\n/";

        $ret = $I->time("php.time", function(){
          sleep(1);
          return "foo";
        });
        $this->assertEquals("foo", $ret);
        sleep(2);

        $this->assertRegExp($expectedData, file_get_contents("test/server_commands_received"));
    }

    public function testSendsTimeMsCallsCorrectly()
    {
        $I = $this->factoryAgent();
        $expectedData =
          "/hello version ruby\/instrumental_agent\/0.0.1 hostname [^ ]+ pid \d+ runtime 7.0.5 platform Darwin [^ ]+ [^ ]+ Darwin Kernel Version [^ ]+: [^ ]+ [^ ]+ [^ ]+ [^ ]+:[^ ]+:[^ ]+ [^ ]+ [^ ]+; root:xnu-[^ ]+~1\/RELEASE_X86_64 x86_64\n" .
          "authenticate test\n" .
          "gauge php.time_ms 10[0-9][0-9].[0-9]+ [0-9]+ 1\n/";

        $ret = $I->timeMs("php.time_ms", function(){
          sleep(1);
          return "foo";
        });
        $this->assertEquals("foo", $ret);
        sleep(2);

        $this->assertRegExp($expectedData, file_get_contents("test/server_commands_received"));
    }

    public function testSendsTimeHandlesUserExceptionsCorrectly()
    {
        $I = $this->factoryAgent();
        $expectedData =
          "/hello version ruby\/instrumental_agent\/0.0.1 hostname [^ ]+ pid \d+ runtime 7.0.5 platform Darwin [^ ]+ [^ ]+ Darwin Kernel Version [^ ]+: [^ ]+ [^ ]+ [^ ]+ [^ ]+:[^ ]+:[^ ]+ [^ ]+ [^ ]+; root:xnu-[^ ]+~1\/RELEASE_X86_64 x86_64\n" .
          "authenticate test\n" .
          "gauge php.time 1.0[0-9]+ [0-9]+ 1\n/";

        $ret = null;
        try {
          $I->time("php.time", function(){
            sleep(1);
            throw new Exception('Test Exception.');
          });
        } catch (Exception $e) {
          $ret = $e;
        }
        $this->assertEquals("Test Exception.", $ret->getMessage());
        sleep(2);

        $this->assertRegExp($expectedData, file_get_contents("test/server_commands_received"));
    }

    public function testSendsTimeMsHandlesUserExceptionsCorrectly()
    {
        $I = $this->factoryAgent();
        $expectedData =
          "/hello version ruby\/instrumental_agent\/0.0.1 hostname [^ ]+ pid \d+ runtime 7.0.5 platform Darwin [^ ]+ [^ ]+ Darwin Kernel Version [^ ]+: [^ ]+ [^ ]+ [^ ]+ [^ ]+:[^ ]+:[^ ]+ [^ ]+ [^ ]+; root:xnu-[^ ]+~1\/RELEASE_X86_64 x86_64\n" .
          "authenticate test\n" .
          "gauge php.time_ms 10[0-9][0-9].[0-9]+ [0-9]+ 1\n/";

        $ret = null;
        try {
          $I->timeMs("php.time_ms", function(){
            sleep(1);
            throw new Exception('Test Exception.');
          });
        } catch (Exception $e) {
          $ret = $e;
        }
        $this->assertEquals("Test Exception.", $ret->getMessage());
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
