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

    /**
     *
     * @var Process[]
     */
    protected $children;

    /**
     * @var int
     */
    protected $maxChildren;

    public function __construct()
    {
        $this->children = [];
        $this->maxChildren = 1;

        $this->setupSignalHandlers();
    }

    protected function setupSignalHandlers()
    {
        pcntl_signal(SIGCHLD, function($signal) {
            while(($pid = pcntl_waitpid(-1, $status, WNOHANG)) > 0 ) {
                unset($this->children[$pid]);
            }
        });
    }

    /**
     * Set the max forked process count.
     *
     * @param int $maxChildren
     */
    public function setMaxChildren($maxChildren)
    {
        $this->maxChildren = $maxChildren;
    }

    /**
     * Get the max forked process count.
     *
     * @return int
     */
    public function getMaxChildren()
    {
        return $this->maxChildren;
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
        $p = new Process($callable);

        $pid = pcntl_fork();
        if (-1 === $pid) {
            throw new \RuntimeException('Failure on pcntl_fork');
        }

        if ($pid) {
            $p->setPid($pid);
            $this->children[$pid] = $p;
            return $p;
        }

        $p->setPid(getmypid());
        $p->run();

        exit(0);
    }

    /**
     * Suspends execution of the current process until all children has exited, or until a signal is delivered whose
     * action is to terminate the current process.
     */
    public function wait()
    {
        foreach ($this->children as $child) {
            $child->wait();
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
}