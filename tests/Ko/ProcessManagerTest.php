<?php
namespace Ko;

/**
 * Class ProcessManagerTest
 *
 * @category Tests
 * @package Ko
 * @author Nikolay Bondarenko <misterionkell@gmail.com>
 * @version 1.0
 *
 * @small
 */
class ProcessManagerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var ProcessManager
     */
    protected $manager;

    public function setUp()
    {
        $this->manager = new ProcessManager();

        $this->getTestResultObject()
            ->setTimeoutForSmallTests(1);
    }

    public function tearDown()
    {
        unset($this->manager);
    }

    public function testForkReturnProcess()
    {
        $process = $this->manager->fork(
            function (Process $process) {
            }
        );

        $this->assertInstanceOf('Ko\Process', $process);
    }

    public function testSpawnReturnProcess()
    {
        $process = $this->manager->spawn(
            function (Process $process) {
            }
        );

        $this->assertInstanceOf('Ko\Process', $process);
    }

    public function testReSpawnIfProcessFailExit()
    {
        $process = $this->manager->spawn(
            function (Process $process) {
                usleep(5000);
                exit(-1);
            }
        );

        $pid = $process->getPid();
        $process->wait();

        $pid2 = $process->getPid();
        $this->assertNotEquals($pid, $pid2);
    }

    public function testNotReSpawnIfProcessSuccessExit()
    {
        $process = $this->manager->spawn(
            function (Process $process) {
                usleep(5000);
            }
        );

        $process->wait();
        $this->assertFalse($this->manager->hasAlive());
    }

    public function testHasAliveAfterFork()
    {
        $this->manager->fork(
            function (Process $process) {
                usleep(5000);
            }
        );

        $this->assertTrue($this->manager->hasAlive());
    }

    public function testHasNoAliveAfterWait()
    {
        $this->manager->fork(
            function (Process $process) {
                usleep(5000);
            }
        );

        $this->manager->wait();
        $this->assertFalse($this->manager->hasAlive());
    }

    public function testCountableInterface()
    {
        $this->manager->fork(
            function (Process $process) {
                usleep(5000);
            }
        );

        $this->assertCount(1, $this->manager);
        $this->assertEquals(1, $this->manager->getProcessCount());
    }

    public function testShutdownHandlerWasCalledOnSigTerm()
    {
        $process = $this->manager->fork(function(Process $p) {
            $m = new ProcessManager();
            $m->onShutdown(function() use (&$p){
                $sm = $p->getSharedMemory();
                $sm['wasCalled'] = true;
            })->fork(function() {
                usleep(100000);
            });
            $m->wait();
        })->kill()->wait();

        $sm = $process->getSharedMemory();
        $this->assertTrue($sm['wasCalled']);
    }
}
 