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
 * Class ProcessManager
 *
 * @package Ko
 * @author Nikolay Bondarenko <misterionkell@gmail.com>
 * @copyright 2015 Nikolay Bondarenko. All rights reserved.
 * @version 1.0
 *
 * @SuppressWarnings(PHPMD.ExitExpression)
 */
class ProcessManager implements \Countable
{
    use Mixin\ProcessTitle;
    use Mixin\EventEmitter {
        on as protected internalOn;
        emit as protected internalEmit;
    }

    /**
     * @var Process[]
     */
    protected $children;

    /**
     * @var Process[]
     */
    protected $spawnWatch;

    /**
     * @var bool
     */
    protected $sigTerm;

    /**
     * @var SignalHandler
     */
    protected $signalHandler;

    public function __construct()
    {
        $this->children = [];
        $this->spawnWatch = [];
        $this->sigTerm = false;

        $this->setupSignalHandlers();
    }

    protected function setupSignalHandlers()
    {
        $this->signalHandler = new SignalHandler();
        $this->signalHandler->registerHandler(SIGCHLD, function() {
            $this->handleSigChild();
        });

        $this->signalHandler->registerHandler(SIGTERM, function() {
            $this->handleSigTerm();
        });
    }

    /**
     * @internal
     */
    public function handleSigChild()
    {
        while (($pid = pcntl_waitpid(-1, $status, WNOHANG)) > 0) {
            $this->childProcessDie($pid, $status);
        }

        //INFO Fix issue https://github.com/misterion/ko-process/pull/15
        gc_collect_cycles();
    }

    /**
     * @internal
     */
    public function handleSigTerm()
    {
        $this->sigTerm = true;

        foreach ($this->children as $process) {
            $process->kill();
        }
        
        $this->internalEmit('shutdown');
    }

    protected function childProcessDie($pid, $status)
    {
        unset($this->children[$pid]);
        if (!isset($this->spawnWatch[$pid])) {
            return;
        }

        if ($this->sigTerm) {
            unset($this->spawnWatch[$pid]);
            return;
        }

        $p = $this->spawnWatch[$pid];
        unset($this->spawnWatch[$pid]);

        $p->setStatus($status);

        if ($p->isSuccessExit()) {
            return;
        }

        $this->internalSpawn($p);
    }

    protected function internalSpawn(Process $p)
    {
        $p = $this->internalFork($p);
        $this->spawnWatch[$p->getPid()] = $p;

        return $p;
    }

    /**
     * @param Process $p
     *
     * @return Process
     */
    protected function internalFork(Process $p)
    {
        $p->setReady(false);

        $pid = pcntl_fork();
        if (-1 === $pid) {
            throw new \RuntimeException('Failure on pcntl_fork');
        }

        if ($pid) {
            $this->children[$pid] = $p;

            return $p->setPid($pid)
                ->waitReady();
        }

        $p->setPid(getmypid())
            ->run();

        exit(0);
    }

    /**
     * Forks the currently running process.
     *
     * @param callable $callable Callable with prototype like function(Ko\Process $p) {}
     *
     * @return Process
     * @throws \RuntimeException
     */
    public function fork(callable $callable)
    {
        return $this->internalFork($this->createProcess($callable));
    }

    protected function createProcess(callable $callable)
    {
        $p = new Process($callable);
        $p->setSharedMemory(new SharedMemory());
        $p->on('exit', function ($pid) use ($p) {
            $this->childProcessDie($pid, $p->getStatus());
        });

        return $p;
    }

    /**
     * @param callable $callable Callable with prototype like function(Ko\Process $p) {}
     *
     * @return Process
     * @throws \RuntimeException
     */
    public function spawn(callable $callable)
    {
        return $this->internalSpawn($this->createProcess($callable));
    }

    /**
     * Suspends execution of the current process until all children has exited, or until a signal is delivered whose
     * action is to terminate the current process.
     */
    public function wait()
    {
        while ($this->hasAlive()) {
            $this->dispatch();
            usleep(100000);
        }
    }

    /**
     * Return TRUE is manager has alive children processes.
     *
     * @return bool
     */
    public function hasAlive()
    {
        return count($this->children) > 0;
    }

    /**
     * Return count of currently running child process.
     *
     * @return int
     */
    public function getProcessCount()
    {
        return count($this->children);
    }

    /**
     * Return count of currently running child process.
     *
     * @return int
     */
    public function count()
    {
        return count($this->children);
    }

    /**
     * Master process shutdown handler. Shutdown handler called right after the SIGTERM catches by this class.
     *
     * @param callable $callable
     *
     * @return $this
     */
    public function onShutdown(callable $callable)
    {
        $this->internalOn('shutdown', $callable);
        return $this;
    }

    /**
     * Forks the currently running process to detach from console.
     *
     * @return $this
     * @throws \RuntimeException
     */
    public function demonize()
    {
        $pid = pcntl_fork();
        if (-1 === $pid) {
            throw new \RuntimeException('Failure on pcntl_fork');
        }

        if ($pid) {
            exit(0);
        }

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
        $this->signalHandler->dispatch();
        return $this;
    }
}
