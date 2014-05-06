<?php
use Ko\Process;
use Ko\ProcessManager;

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
        $process = $this->manager->spawn(function () {});

        $this->assertInstanceOf('Ko\Process', $process);
    }

    public function testReSpawnIfProcessFailExit()
    {
        $process = $this->manager->spawn(function () {
            usleep(5000);
            exit(-1);
        });

        $pid = $process->getPid();
        $process->wait();

        $pid2 = $process->getPid();
        $this->assertNotEquals($pid, $pid2);
    }

    public function testNotReSpawnIfProcessSuccessExit()
    {
        $process = $this->manager->spawn(function () {
            usleep(5000);
        });

        $process->wait();
        $this->assertFalse($this->manager->hasAlive());
    }

    public function testHasAliveAfterFork()
    {
        $this->manager->fork(function () {
            usleep(5000);
        });

        $this->assertTrue($this->manager->hasAlive());
    }

    public function testHasNoAliveAfterWait()
    {
        $this->manager->fork(function () {
            usleep(5000);
        });

        $this->manager->wait();
        $this->assertFalse($this->manager->hasAlive());
    }

    public function testCountableInterface()
    {
        $this->manager->fork(function () {
            usleep(5000);
        });

        $this->assertCount(1, $this->manager);
        $this->assertEquals(1, $this->manager->getProcessCount());
    }

    public function testShutdownHandlerWasCalledOnSigTerm()
    {
        $process = $this->manager->fork(function(Process $p) {
            $sm = $p->getSharedMemory();

            $m = new ProcessManager();
            $m->onShutdown(function() use (&$sm){
                $sm['wasCalled'] = true;
            })->fork(function() {
                usleep(300000);
            });

            $sm['ready'] = true;
            $m->wait();
        });

        $this->waitReady($process);

        $process->kill();
        $this->manager->wait();

        $sm = $process->getSharedMemory();
        $this->assertTrue($sm['wasCalled']);
    }

    private function waitReady(Process $process)
    {
        $x = 100;
        $sm = $process->getSharedMemory();
        while ($x-- > 0) {
            if (isset($sm['ready'])) {
                break;
            }
            usleep(1000);
        }
    }

    public function testDemonize()
    {
        if (!$this->manager->isProcessTitleSupported()) {
            $this->markTestSkipped('This test need ProcessTitle to worl');
        }

        $title = 'testDemonize_' . mt_rand(0, PHP_INT_MAX);

        system('php ' . __DIR__ . '/Fixtures/demonize.php ' . $title . '  > /dev/null 2>/dev/null&');
        sleep(1);

        $this->assertFalse($this->processExistsByTitle($title));
        $this->assertTrue($this->processExistsByTitle($title . '_afterDemonize'));
    }

    private function processExistsByTitle($title)
    {
        return is_numeric(exec('ps aux|grep -w ' . $title . ' |grep -v grep |awk \'{print $2}\'', $out));
    }

    public function testReSpawnedProcessHasPersistSharedMemorySegment()
    {
        $process = $this->manager->spawn(function(Process $p) {
            $sm = $p->getSharedMemory();
            $sm['spawnCount'] += 1;

            $exitCode = ($sm['spawnCount'] == 1)
                ? -1
                : 0;

            exit ($exitCode);
        });

        $this->manager->wait();
        $this->assertEquals(2, $process->getSharedMemory()['spawnCount']);
    }
}