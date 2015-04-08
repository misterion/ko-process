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
 * Class ProcessTest
 *
 * @category Tests
 * @package Ko
 * @author Nikolay Bondarenko <misterionkell@gmail.com>
 * @version 1.1
 *
 * @small
 * @runInSeparateProcess
 */
class ProcessTest extends \PHPUnit_Framework_TestCase
{
    use PHPMock;

    public function setUp()
    {
        $this->getTestResultObject()
            ->setTimeoutForSmallTests(1);

        //INFO This part of code fix https://bugs.php.net/bug.php?id=68541 issue
        $this->getFunctionMock(__NAMESPACE__, 'pcntl_signal')
            ->expects($this->any())
            ->willReturnCallback(function($signal, $callable) {
                return \pcntl_signal($signal, $callable);
            });
    }

    public function testRunExecuteCallable()
    {
        $wasCalled = false;
        $p = new Process(function () use (&$wasCalled) {
            $wasCalled = true;
        });
        $p->run();

        $this->assertTrue($wasCalled);
    }

    public function testSetPid()
    {
        $p = new Process(function () {
        });
        $p->setPid(1);

        $this->assertEquals(1, $p->getPid());
    }

    public function testHasEmptyPidAfterCreation()
    {
        $p = new Process(function () {
        });
        $this->assertEquals(0, $p->getPid());
    }

    public function testHasPidAfterFork()
    {
        $m = new ProcessManager();
        $process = $m->fork(
            function () {
            }
        );

        $this->assertNotEmpty($process->getPid());
    }

    public function testErrorStatusAfterFork()
    {
        $m = new ProcessManager();
        $process = $m->fork(
            function () {
                exit(-1);
            }
        );
        $process->wait();

        $this->assertNotEquals(0, $process->getExitCode());
    }

    public function testSuccessStatusAfterFork()
    {
        $m = new ProcessManager();
        $process = $m->fork(
            function () {
            }
        );
        $process->wait();

        $this->assertEquals(0, $process->getStatus());
    }

    public function testSuccessCallback()
    {
        $wasCalled = false;

        $m = new ProcessManager();
        $process = $m->fork(
            function () {
            }
        );
        $process->onSuccess(
            function () use (&$wasCalled) {
                $wasCalled = true;
            }
        );
        $process->wait();

        $this->assertTrue($wasCalled);
    }

    public function testFailedCallback()
    {
        $wasCalled = false;

        $m = new ProcessManager();
        $process = $m->fork(
            function () {
                exit(-1);
            }
        );
        $process->onError(
            function () use (&$wasCalled) {
                $wasCalled = true;
            }
        );
        $process->wait();

        $this->assertTrue($wasCalled);
    }

    public function testGetRightExitCode()
    {
        $m = new ProcessManager();
        $process = $m->fork(
            function () {
                exit(5);
            }
        )->wait();

        $this->assertEquals(5, $process->getExitCode());
    }

    public function testProcessCanTerminateOnSigTerm()
    {
        $m = new ProcessManager();
        $process = $m->fork(
            function (Process &$p) {
                while (!$p->isShouldShutdown()) {
                    $p->dispatch();
                    usleep(100);
                }
            }
        );

        $process->kill();
        $process->wait();

        $this->assertFalse($m->hasAlive());
    }

    public function testDeprecatedDispatchSignalsStillAlive()
    {
        $mock = $this->getMockBuilder('\Ko\Process')
            ->disableOriginalConstructor()
            ->setMethods(['dispatch'])
            ->getMock();

        $mock->expects($this->once())
            ->method('dispatch');

        /** @var Process $mock */
        $mock->dispatchSignals();
    }

    public function testLazyCreateSharedMemory()
    {
        $process = new Process(function () {});
        $this->assertInstanceOf('\Ko\SharedMemory', $process->getSharedMemory());
    }

    public function testDoNotCreateSharedMemoryInAlreadySet()
    {
        $sharedMemory = new SharedMemory();

        $process = new Process(function () {});
        $process->setSharedMemory($sharedMemory);

        $this->assertEquals($sharedMemory, $process->getSharedMemory());
    }

    /**
     * @return Process
     */
    public function testIsNotReadyAfterCreation()
    {
        $process = new Process(function () {});
        $this->assertFalse($process->isReady());

        return $process;
    }

    /**
     * @param Process $process
     * @return Process
     *
     * @depends testIsNotReadyAfterCreation
     */
    public function testSetReady(Process $process)
    {
        $this->assertEquals($process, $process->setReady(true));
        $this->assertTrue($process->isReady());

        return $process;
    }

    /**
     * @param Process $process
     * @return Process
     *
     * @depends testSetReady
     */
    public function testSuccessWaitReady(Process $process)
    {
        $this->assertEquals($process, $process->waitReady());
        return $process;
    }

    /**
     * @param Process $process
     *
     * @return Process
     *
     * @depends testSuccessWaitReady
     */
    public function testSetProcessNotReady(Process $process)
    {
        $this->assertEquals($process, $process->setReady(false));
        $this->assertFalse($process->isReady());

        return $process;
    }

    /**
     * @param Process $process
     * @return Process
     *
     * @depends testSetProcessNotReady
     *
     * @expectedException \RuntimeException
     */
    public function testFailedWaitReady(Process $process)
    {
        $process->waitReady();
    }

    public function testDispatch()
    {
        $mock = $this->getMock('Ko\SignalHandler', ['dispatch']);
        $mock->expects($this->once())
            ->method('dispatch');

        /**@var SignalHandler $mock */
        $process = new Process(function () {});
        $process->setSignalHandler($mock);
        $process->dispatch();
    }

    public function testImplementsCountableInterface()
    {
        $process = new Process(function () {});
        $this->assertInstanceOf('\Countable', $process);
    }

    public function testImplementsArrayAccessInterface()
    {
        $process = new Process(function () {});
        $this->assertInstanceOf('\ArrayAccess', $process);
    }

    /**
     * The main reason is no ant exceptions during test case.
     *
     * @return Process
     */
    public function testArrayAccessSet()
    {
        $process = new Process(function () {});
        $process['a'] = 1;
        $process[0] = 2;

        $this->assertCount(2, $process);

        return $process;
    }

    /**
     * @depends testArrayAccessSet
     * @param Process $process
     *
     * @return Process
     */
    public function testArrayAccessGet(Process $process)
    {
        $this->assertTrue(isset($process['a']));
        $this->assertEquals(1, $process['a']);
        $this->assertTrue(isset($process[0]));
        $this->assertEquals(2, $process[0]);

        return $process;
    }

    /**
     * @depends testArrayAccessGet
     * @param Process $process
     */
    public function testArrayAccessUnset(Process $process)
    {
        unset($process['a']);
        $this->assertFalse(isset($process['a']));
        $this->assertEquals(null, $process['a']);
    }
}