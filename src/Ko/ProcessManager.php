<?php
namespace Ko;

declare(ticks = 1);

/**
 * Class ProcessManager
 *
 * @package Ko
 * @author Nikolay Bondarenko <misterionkell@gmail.com>
 * @version 1.0
 */
class ProcessManager implements \Countable
{
    use Mixin\ProcessTitle;
    use Mixin\EventEmitter {
        on as protected internalOn;
        emit as protected internalEmit;
    }

    const WAIT_IDLE = 1000;
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

    public function __construct()
    {
        $this->children = [];
        $this->spawnWatch = [];
        $this->sigTerm = false;

        $this->setupSignalHandlers();
    }

    protected function setupSignalHandlers()
    {
        pcntl_signal(SIGCHLD, function () {
            while (($pid = pcntl_waitpid(-1, $status, WNOHANG)) > 0) {
                $this->childProcessDie($pid);
            }
        });

        pcntl_signal(SIGTERM, function () {
            $this->sigTerm = true;
            $this->internalEmit('shutdown');

            foreach ($this->children as $process) {
                $process->kill();
            }
        });
    }

    protected function childProcessDie($pid)
    {
        unset($this->children[$pid]);
        if (!isset($this->spawnWatch[$pid])) {
            return;
        }

        if (!$this->sigTerm) {
            $p = $this->spawnWatch[$pid];
            if (!$p->isSuccessExit()) {
                $this->internalSpawn($this->spawnWatch[$pid]);
            }
        }

        unset($this->spawnWatch[$pid]);
    }

    protected function internalSpawn(Process $p)
    {
        $p = $this->internalFork($p);
        $this->spawnWatch[$p->getPid()] = $p;

        return $p;
    }

    protected function internalFork(Process $p)
    {
        $sm = new SharedMemory();
        $p->setSharedMemory($sm);

        $pid = pcntl_fork();
        if (-1 === $pid) {
            throw new \RuntimeException('Failure on pcntl_fork');
        }

        if ($pid) {
            $p->setPid($pid);
            $this->waitProcessRunning($p);

            $this->children[$pid] = $p;

            return $p;
        }

        $p->setPid(getmypid());
        $p->run();

        exit(0);
    }

    protected function waitProcessRunning(Process $p)
    {
        $x = 0;
        $sm = $p->getSharedMemory();

        while ($x++ < 100) {
            usleep(self::WAIT_IDLE);
            if ($sm['started'] === true) {
                return;
            }
        }

        throw new \RuntimeException('Wait process running timeout for child pid ' . $p->getPid());
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
        $p->on('exit', function ($pid) {
            $this->childProcessDie($pid);
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
        return count($this);
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
}