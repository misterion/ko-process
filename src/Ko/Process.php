<?php
/**
 * The MIT License
 *
 * Copyright (c) 2015 Nikolay Bondarenko <misterionkell@gmail.com>
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

/**
 * Class Process
 *
 * @package Ko
 * @author Nikolay Bondarenko <misterionkell@gmail.com>
 * @copyright 2015 Nikolay Bondarenko. All rights reserved.
 * @version 1.0
 */
class Process implements \ArrayAccess, \Countable
{
    use Mixin\ProcessTitle;
    use Mixin\EventEmitter {
        on as protected internalOn;
        emit as protected internalEmit;
    }

    /**
     * Shared memory internal variables used to mark process as completely initialized.
     *
     * @internal
     */
    const STARTED_MARKER = '__started';

    /**
     * Wait ready timeout in milliseconds.
     *
     * @internal
     */
    const WAIT_IDLE = 1000;

    /**
     * Posix process id.
     *
     * @var int
     */
    protected $pid;

    /**
     * @var int
     */
    protected $status;

    /**
     * @var int
     */
    protected $exitCode;

    /**
     * @var callable
     */
    protected $callable;

    /**
     * @var bool
     */
    protected $shouldShutdown;
    /**
     * @var bool
     */
    protected $running;

    /**
     * @var SharedMemory
     */
    protected $sharedMemory;

    /**
     * @var SignalHandler
     */
    protected $signalHandler;

    public function __construct(callable $callable)
    {
        $this->pid = 0;
        $this->callable = $callable;
        $this->shouldShutdown = false;
    }

    /**
     * @return \Ko\SharedMemory
     */
    public function getSharedMemory()
    {
        if ($this->sharedMemory === null) {
            $this->setSharedMemory(new SharedMemory());
        }

        return $this->sharedMemory;
    }

    /**
     * @param \Ko\SharedMemory $sharedMemory
     *
     * @return $this
     */
    public function setSharedMemory(SharedMemory $sharedMemory)
    {
        $this->sharedMemory = $sharedMemory;
        return $this;
    }

    /**
     * @return SignalHandler
     */
    public function getSignalHandler()
    {
        if ($this->signalHandler === null) {
            $this->setSignalHandler(new SignalHandler());
        }

        return $this->signalHandler;
    }

    /**
     * @param SignalHandler $signalHandler
     *
     * @return $this
     */
    public function setSignalHandler(SignalHandler $signalHandler)
    {
        $this->signalHandler = $signalHandler;
        return $this;
    }

    /**
     * @return boolean
     */
    public function isShouldShutdown()
    {
        return $this->shouldShutdown;
    }

    /**
     * Execute callable.
     */
    public function run()
    {
        $signalHandler = $this->getSignalHandler();
        $signalHandler->registerHandler(SIGTERM, function () {
            $this->shouldShutdown = true;
        });

        $this->sharedMemory[self::STARTED_MARKER] = true;

        /** @var callable $callable */
        $callable = $this->callable;
        $callable($this);
    }

    /**
     * Return the process ID.
     *
     * @return integer
     */
    public function getPid()
    {
        return $this->pid;
    }

    /**
     * Set the process ID.
     *
     * @param integer $pid
     *
     * @return $this
     */
    public function setPid($pid)
    {
        $this->pid = $pid;

        return $this;
    }

    /**
     * Return the status parameter supplied to a successful call to wait()
     *
     * @return mixed
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * Set status parameter
     *
     * @param int $status
     */
    public function setStatus($status)
    {
        $this->status = $status;
    }

    /**
     * Returns the return code of a terminated child.
     *
     * @return int
     */
    public function getExitCode()
    {
        return $this->exitCode;
    }

    /**
     * Suspends execution of the current process until a child has exited.
     *
     * @return $this
     */
    public function wait()
    {
        $this->internalWait();
        $event = $this->isSuccessExit()
            ? 'success'
            : 'error';

        $this->internalEmit('exit', $this->pid);
        $this->internalEmit($event);

        return $this;
    }

    protected function internalWait()
    {
        if (0 === $this->pid) {
            return;
        }

        pcntl_waitpid($this->pid, $this->status);
    }

    /**
     * Check exit code and return TRUE if process was ended successfully.
     *
     * @return bool
     */
    public function isSuccessExit()
    {
        $this->exitCode = pcntl_wexitstatus($this->status);
        return (pcntl_wifexited($this->status) && ($this->exitCode === 0));
    }

    /**
     * Setup error handler.
     *
     * @param callable $callable
     *
     * @return $this
     */
    public function onError(callable $callable)
    {
        $this->internalOn('error', $callable);
        return $this;
    }

    /**
     * Setup success handler.
     *
     * @param callable $callable
     *
     * @return $this
     */
    public function onSuccess(callable $callable)
    {
        $this->internalOn('success', $callable);
        return $this;
    }

    /**
     * Send a signal to a process.
     *
     * @param int $signal
     *
     * @return $this;
     */
    public function kill($signal = SIGTERM)
    {
        posix_kill($this->pid, $signal);
        return $this;
    }

    /**
     * Calls signal handlers for pending signals.
     *
     * @return $this;
     *
     * @deprecated Use ProcessManager::dispatch()
     */
    public function dispatchSignals()
    {
        return $this->dispatch();
    }

    /**
     * Calls signal handlers for pending signals.
     *
     * @return $this
     */
    public function dispatch()
    {
        $this->getSignalHandler()
            ->dispatch();

        return $this;
    }

    /**
     * Mark process as completely initialized. Any object, which need to handle Process life time should use this
     * flag to build they internal login about process handling.
     *
     * @param bool $value
     *
     * @return $this
     */
    public function setReady($value)
    {
        $this[self::STARTED_MARKER] = $value;
        return $this;
    }

    /**
     * Return True is process is ready.
     *
     * @return bool
     *
     * @see setReady
     */
    public function isReady()
    {
        return (bool)$this[self::STARTED_MARKER];
    }

    /**
     * Wait Process is completely initialized.
     *
     * @return $this;
     *
     * @limitation This method should not to be called from child process.
     * @throw \RuntimeException If time out occurred during wait;
     */
    public function waitReady()
    {
        $x = 0;
        while ($x++ < 100) {
            usleep(self::WAIT_IDLE);
            if ($this[self::STARTED_MARKER] === true) {
                return $this;
            }
        }

        throw new \RuntimeException('Wait process running timeout for child pid ' . $this->getPid());
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Whether a offset exists
     * @link http://php.net/manual/en/arrayaccess.offsetexists.php
     *
     * @param mixed $offset <p>
     * An offset to check for.
     * </p>
     *
     * @return boolean true on success or false on failure.
     * </p>
     * <p>
     * The return value will be casted to boolean if non-boolean was returned.
     */
    public function offsetExists($offset)
    {
        $sm = $this->getSharedMemory();
        return $sm->offsetExists($offset);
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Offset to retrieve
     * @link http://php.net/manual/en/arrayaccess.offsetget.php
     *
     * @param mixed $offset <p>
     * The offset to retrieve.
     * </p>
     *
     * @return mixed Can return all value types.
     */
    public function offsetGet($offset)
    {
        $sm = $this->getSharedMemory();
        return $sm->offsetGet($offset);
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Offset to set
     * @link http://php.net/manual/en/arrayaccess.offsetset.php
     *
     * @param mixed $offset <p>
     * The offset to assign the value to.
     * </p>
     * @param mixed $value <p>
     * The value to set.
     * </p>
     *
     * @return void
     */
    public function offsetSet($offset, $value)
    {
        $sm = $this->getSharedMemory();
        $sm->offsetSet($offset, $value);
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Offset to unset
     * @link http://php.net/manual/en/arrayaccess.offsetunset.php
     *
     * @param mixed $offset <p>
     * The offset to unset.
     * </p>
     *
     * @return void
     */
    public function offsetUnset($offset)
    {
        $sm = $this->getSharedMemory();
        $sm->offsetUnset($offset);
    }

    /**
     * (PHP 5 &gt;= 5.1.0)<br/>
     * Count elements of an object
     * @link http://php.net/manual/en/countable.count.php
     * @return int The custom count as an integer.
     * </p>
     * <p>
     * The return value is cast to an integer.
     */
    public function count()
    {
        $sm = $this->getSharedMemory();
        return $sm->count();
    }
}
