<?php
namespace Ko;

/**
 * Class ProcessManagerTest
 *
 * @category Tests
 * @package Ko
 * @author Nikolay Bondarenko <misterionkell@gmail.com>
 * @version 1.0
 */
class ProcessManagerTest extends \PHPUnit_Framework_TestCase
{
    public function testFork()
    {
        $m = new ProcessManager();
        $process = $m->fork(function(Process $process) {});

        $this->assertInstanceOf('Ko\Process', $process);
    }

    public function testHasAlive()
    {
        $m = new ProcessManager();
        $m->fork(function(Process $process) {
            usleep(5000);
        });

        $this->assertTrue($m->hasAlive());
    }

    public function testCountable()
    {
        $m = new ProcessManager();
        $m->fork(function(Process $process) {
            usleep(5000);
        });

        $this->assertCount(1, $m);
        $this->assertEquals(1, $m->getProcessCount());
    }
}
 