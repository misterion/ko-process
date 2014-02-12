<?php
namespace Ko;

/**
 * Class ProcessTest
 *
 * @category Tests
 * @package Ko
 * @author Nikolay Bondarenko <misterionkell@gmail.com>
 * @version 1.0
 */
class ProcessTest extends \PHPUnit_Framework_TestCase
{
    public function testRun()
    {
        $wasCalled = false;
        $p = new Process(function() use (&$wasCalled) {
            $wasCalled = true;
        });
        $p->run();

        $this->assertTrue($wasCalled);
    }

    public function testSetPid()
    {
        $p = new Process(function() {});
        $p->setPid(1);

        $this->assertEquals(1, $p->getPid());
    }

    public function testHasEmptyPid()
    {
        $p = new Process(function() {});
        $this->assertEquals(0, $p->getPid());
    }

    public function testHasPidAfterFork()
    {
        $m = new ProcessManager();
        $process = $m->fork(function() {});

        $this->assertNotEmpty($process->getPid());
    }

    public function testErrorStatusAfterFork()
    {
        $m = new ProcessManager();
        $process = $m->fork(function() {
            exit(-1);
        });
        $process->wait();

        $this->assertNotEquals(0, $process->getStatus());
    }

    public function testSuccessStatusAfterFork()
    {
        $m = new ProcessManager();
        $process = $m->fork(function() {});
        $process->wait();

        $this->assertEquals(0, $process->getStatus());
    }

    public function testSuccessCallback()
    {
        $wasCalled = false;

        $m = new ProcessManager();
        $process = $m->fork(function() {});
        $process->onSuccess(function() use (&$wasCalled) {
            $wasCalled = true;
        });
        $process->wait();

        $this->assertTrue($wasCalled);
    }

    public function testFailedCallback()
    {
        $wasCalled = false;

        $m = new ProcessManager();
        $process = $m->fork(function() {
            exit(-1);
        });
        $process->onError(function() use (&$wasCalled) {
            $wasCalled = true;
        });
        $process->wait();

        $this->assertTrue($wasCalled);
    }

    public function testGetExitCode()
    {
        $m = new ProcessManager();
        $process = $m->fork(function() {
            exit(5);
        })->wait();

        $this->assertEquals(5, $process->getExitStatus());
    }
}