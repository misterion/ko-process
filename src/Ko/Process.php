<?php
namespace Ko;

/**
 * Class Process
 *
 * @package Ko
 * @author Nikolay Bondarenko <misterionkell@gmail.com>
 * @version 1.0
 */
class Process
{
    use Mixin\ProcessTitle;
    use Mixin\EventEmitter;

    protected $pid;

    /**
     * @var int
     */
    protected $status;

    /**
     * @var int
     */
    protected $exitStatus;

    /**
     * @var callable
     */
    protected $callable;

    public function __construct(callable $callable)
    {
        $this->pid = 0;
        $this->callable = $callable;
    }

    /**
     * Execute callable.
     */
    public function run()
    {
        /** @var callable $callable */
        $callable = $this->callable;
        $callable($this);
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
     * Return the process ID.
     *
     * @return integer
     */
    public function getPid()
    {
        return $this->pid;
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
     * Returns the return code of a terminated child.
     *
     * @return int
     */
    public function getExitStatus()
    {
        return $this->exitStatus;
    }

    /**
     * Suspends execution of the current process until a child has exited.
     *
     * @return $this
     */
    public function wait()
    {
        $this->internalWait();
        $event = (pcntl_wifexited($this->status) && ($this->exitStatus === 0))
            ? 'success'
            : 'error';

        $this->emit($event);

        return $this;
    }

    protected function internalWait()
    {
        if (0 === $this->pid) {
            return;
        }

        pcntl_waitpid($this->pid, $this->status);
        $this->exitStatus = pcntl_wexitstatus($this->status);
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
        $this->on('error', $callable);
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
        $this->on('success', $callable);
        return $this;
    }
}