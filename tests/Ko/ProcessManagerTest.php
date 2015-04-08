<?php
/**
 * The MIT License
 *
 * Copyright (c) 2014 Nikolay Bondarenko
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 *
 * PHP version 5.4
 *
 * @package Ko
 * @author Nikolay Bondarenko
 * @copyright 2015 Nikolay Bondarenko. All rights reserved.
 * @license MIT http://opensource.org/licenses/MIT
 */
namespace Ko;

use phpmock\phpunit\PHPMock;

/**
 * Class ProcessManagerTest
 *
 * @category Tests
 * @package Ko
 * @author Nikolay Bondarenko <misterionkell@gmail.com>
 * @version 1.1
 *
 * @small
 * @runInSeparateProcess
 */
class ProcessManagerTest extends \PHPUnit_Framework_TestCase
{
    use PHPMock;

    /**
     * @var ProcessManager
     */
    protected $manager;

    public function setUp()
    {
        //INFO This part of code fix https://bugs.php.net/bug.php?id=68541 issue
        $this->getFunctionMock(__NAMESPACE__, 'pcntl_signal')
            ->expects($this->any())
            ->willReturnCallback(function($signal, $callable) {
                return \pcntl_signal($signal, $callable);
            });

        //INFO This part of code fix https://bugs.php.net/bug.php?id=68541 issue
        $this->getFunctionMock(__NAMESPACE__, 'pcntl_signal_dispatch')
            ->expects($this->any())
            ->willReturnCallback(function() {
                return \pcntl_signal_dispatch();
            });

        $this->getTestResultObject()
            ->setTimeoutForSmallTests(1);

        $this->manager = new ProcessManager();
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

    public function testDeprecatedDispatchSignalsStillAlive()
    {
        $mock = $this->getMock('\Ko\ProcessManager', ['dispatch']);
        $mock->expects($this->once())
            ->method('dispatch');

        /** @var ProcessManager $mock */
        $mock->dispatchSignals();
    }

    public function testSigTermHandler()
    {

    }
}