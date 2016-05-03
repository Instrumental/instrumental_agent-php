<?php

class AgentTest extends \PHPUnit_Framework_TestCase
{
    const HELLO_REGEX = "hello version php\/instrumental_agent\/0.0.1 hostname [^ ]+ pid \d+ runtime 7.0.5 platform Darwin_[^\s]+RELEASE_X86_64_x86_64\n";

    public function setUp()
    {
      // clear the test server command file
      fopen("test/server_commands_received", 'w');
      $this->setResponse("ok");
      exec("php test/TestServer.php &> test/server.log &");
      sleep(1);
    }

    public function setResponse($command)
    {
      $fp = fopen("test/server_command_to_send", 'w');
      fwrite($fp, "$command\n");
    }

    public function factoryAgent()
    {
      $I = new \Instrumental\Agent();
      $I->setHost("localhost");
      $I->setPort(4040);
      $I->setApiKey("test");
      $I->setEnabled(true);
      return $I;
    }

    public function testHandlesDisconnect()
    {
        $I = $this->factoryAgent();

        $expectedData =
          "/" . self::HELLO_REGEX .
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

        // Send enough through the socket that we can tell we're disconnected.
        for($i=1; $i<=1000; ++$i) {
          $ret = $I->increment('php.increment', $i);
        }
        $this->assertEquals(1000, $ret);

        $this->assertRegExp($expectedData, file_get_contents("test/server_commands_received"));

        // this should get queued and sent when it can reconnect
        $ret = $I->increment('php.increment', 3.1);
        $this->assertEquals(3.1, $ret);



        $expectedData =
          "/" . self::HELLO_REGEX .
          "/";

        $this->setUp();
        $this->setResponse("fail");

        // Queue's on failed hello
        $ret = $I->increment('php.increment', 3.2);
        $this->assertEquals(3.2, $ret);
        sleep(2);

        $this->assertRegExp($expectedData, file_get_contents("test/server_commands_received"));




        $expectedData =
          "/" . self::HELLO_REGEX .
          "authenticate test\n" .
          "/";

        $this->setUp();
        $this->setResponse("ok\nfail");

        // Queue's on failed auth
        $ret = $I->increment('php.increment', 3.3);
        $this->assertEquals(3.3, $ret);
        sleep(2);

        $this->assertRegExp($expectedData, file_get_contents("test/server_commands_received"));




        $expectedData =
          "/" . self::HELLO_REGEX .
          "authenticate test\n" .
          "(increment php.increment [0-9]+ [0-9]+ 1\n)+" .
          "increment php.increment 3.1 [0-9]+ 1\n" .
          "increment php.increment 3.2 [0-9]+ 1\n" .
          "increment php.increment 3.3 [0-9]+ 1\n" .
          "increment php.increment 3.4 [0-9]+ 1\n" .
          "/";

        $this->setUp();
        $this->setResponse("ok");

        $ret = $I->increment('php.increment', 3.4);
        $this->assertEquals(3.4, $ret);
        sleep(2);

        $this->assertRegExp($expectedData, file_get_contents("test/server_commands_received"));
    }

    public function testIncrementGaugeNoticeReturnsNullIfQueueIsFull()
    {
        $I = $this->factoryAgent();
        $I->setPort(666);
        for($i=1; $i<=$I::MAX_BUFFER-1; ++$i) {
          $ret = $I->increment('php.increment', $i);
        }
        $this->assertEquals(1, $I->increment("test.queue_almost_full"));
        $this->assertEquals(null, $I->increment("test.queue_full"));
    }

    public function testTimeAndTimeMsReturnsBlockResultIfQueueIsFull()
    {
        $I = $this->factoryAgent();
        $I->setPort(666);
        for($i=1; $i<=$I::MAX_BUFFER-1; ++$i) {
          $ret = $I->increment('php.increment', $i);
        }

        // last message that fits in the queue
        $ret = $I->time("test", function() {return "time result";});
        $this->assertEquals("time result", $ret);

        // queue is full
        $ret = $I->timeMs("test", function() {return "timeMs result";});
        $this->assertEquals("timeMs result", $ret);
    }

    public function testIncrementGaugeNoticeReturnNullIfDisabled()
    {
        $I = $this->factoryAgent();
        $I->setEnabled(FALSE);
        $this->assertEquals(null, $I->increment("test"));
        $this->assertEquals(null, $I->gauge("test", 1));
        $this->assertEquals(null, $I->notice("test"));
    }

    public function testTimeAndTimeMsReturnBlockResultIfDisabled()
    {
        $I = $this->factoryAgent();
        $I->setEnabled(FALSE);
        $ret = $I->time("test", function() {return "time result";});
        $this->assertEquals("time result", $ret);
        $ret = $I->timeMs("test", function() {return "timeMs result";});
        $this->assertEquals("timeMs result", $ret);
    }

    public function testHandlesNoConnection()
    {
        $I = $this->factoryAgent();
        $I->setPort(666);

        $expectedData =
          "/^$/";

        $ret = $I->increment('php.increment', 2.2);
        $this->assertEquals(2.2, $ret);
        sleep(2);

        $this->assertRegExp($expectedData, file_get_contents("test/server_commands_received"));


        $I->setPort(4040);

        $expectedData =
          "/" . self::HELLO_REGEX .
          "authenticate test\n" .
          "increment php.increment 2.2 [0-9]+ 1\n" .
          "increment php.increment 2.3 [0-9]+ 1\n" .
          "/";

        $ret = $I->increment('php.increment', 2.3);
        $this->assertEquals(2.3, $ret);
        sleep(2);

        $this->assertRegExp($expectedData, file_get_contents("test/server_commands_received"));
    }

    public function testSendsIncrementCallsCorrectly()
    {
        $I = $this->factoryAgent();
        $expectedData =
          "/" . self::HELLO_REGEX .
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
          "/" . self::HELLO_REGEX .
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
          "/" . self::HELLO_REGEX .
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
          "/" . self::HELLO_REGEX .
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
          "/" . self::HELLO_REGEX .
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
          "/" . self::HELLO_REGEX .
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
          "/" . self::HELLO_REGEX .
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
          "/" . self::HELLO_REGEX .
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
          "/" . self::HELLO_REGEX .
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
          "/" . self::HELLO_REGEX .
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
          "/" . self::HELLO_REGEX .
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
          "/" . self::HELLO_REGEX .
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
          "/" . self::HELLO_REGEX .
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
          "/" . self::HELLO_REGEX .
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
          "/" . self::HELLO_REGEX .
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

    public function testSendsTimeHandlesUserErrorsCorrectly()
    {
        $I = $this->factoryAgent();
        $expectedData =
          "/" . self::HELLO_REGEX .
          "authenticate test\n" .
          "gauge php.time 1.0[0-9]+ [0-9]+ 1\n/";

        $ret = null;
        try {
          $I->time("php.time", function(){
            sleep(1);
            $foo = "test" . new \Instrumental\Agent();
          });
        } catch (Exception $e) {
          $ret = $e;
        }
        // The agent error handling doesn't affect the user error, the underlying error handling takes effect.
        $this->assertEquals("Object of class Instrumental\Agent could not be converted to string", $ret->getMessage());
        sleep(2);

        $this->assertRegExp($expectedData, file_get_contents("test/server_commands_received"));
    }
}
