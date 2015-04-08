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
 * Class SignalHandlerTest
 *
 * @package Ko
 * @copyright 2014 Nikolay Bondarenko. All rights reserved.
 * @author Nikolay Bondarenko <misterionkell@gmail.com>
 * @version 1.0
 * @since 0.4
 *
 * @small
 * @runInSeparateProcess
 * @backupGlobals enabled
 */
class SignalHandlerTest extends \PHPUnit_Framework_TestCase
{
    use PHPMock;

    /**
     * @var SignalHandler
     */
    protected $handler;

    protected function setUp()
    {
        $this->handler = new SignalHandler();
    }

    public function testHasHandlerIfNotHandlers()
    {
        $this->assertFalse($this->handler->hasHandler(SIGTERM));
    }

    public function testRegisterHandler()
    {
        $this->getFunctionMock(__NAMESPACE__, 'pcntl_signal')
            ->expects($this->once())
            ->willReturn(true);

        $this->assertEquals($this->handler, $this->handler->registerHandler(SIGTERM, function() {}));
        $this->assertTrue($this->handler->hasHandler(SIGTERM));
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testThrowErrorIfPcntlSignalFails()
    {
        $this->getFunctionMock(__NAMESPACE__, 'pcntl_signal')
            ->expects($this->once())
            ->with($this->equalTo(SIGTERM), $this->equalTo([$this->handler, 'handleSignal']))
            ->willReturn(false);

        $this->handler->registerHandler(SIGTERM, function(){});
    }

    public function testHasEmptyInternalQueue()
    {
        $this->assertCount(0, $this->handler);
    }

    public function testHandleSignal()
    {
        $this->handler->handleSignal(SIGTERM);
        $this->assertCount(1, $this->handler);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testRegisterNonCallable()
    {
        $this->handler->registerHandler(SIGTERM, 'badHandler');
    }

    public function testHandlerWasCalledWithDispatch()
    {
        $callableWasCalled = false;
        $callable = function() use(&$callableWasCalled){
            $callableWasCalled = true;
        };

        $this->getFunctionMock(__NAMESPACE__, 'pcntl_signal_dispatch')
            ->expects($this->once())
            ->willReturnCallback(function(){
                $this->handler->handleSignal(SIGTERM);
                return true;
            });

        $this->handler->registerHandler(SIGTERM, $callable)
            ->dispatch();

        $this->assertTrue($callableWasCalled);
    }

    public function testHandlerWasCalledOneTimePerDispatch()
    {
        $callableWasCalled = 0;
        $callable = function() use(&$callableWasCalled){
            $callableWasCalled++;
        };

        $mock = $this->getFunctionMock(__NAMESPACE__, 'pcntl_signal_dispatch');
        $mock->expects($this->at(0))
            ->willReturnCallback(function(){
                $this->handler->handleSignal(SIGTERM);
                return true;
            });

        $mock->expects($this->at(0))
            ->willReturnCallback(function(){
                return true;
            });

        $this->handler->registerHandler(SIGTERM, $callable)
            ->dispatch()
            ->dispatch();

        $this->assertEquals(1, $callableWasCalled);
    }
}
