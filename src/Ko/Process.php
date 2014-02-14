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
        return $this->sharedMemory;
    }

    /**
     * @param \Ko\SharedMemory $sharedMemory
     */
    public function setSharedMemory($sharedMemory)
    {
        $this->sharedMemory = $sharedMemory;
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
        $this->sharedMemory['started'] = true;

        pcntl_signal(SIGTERM, function () {
            $this->shouldShutdown = true;
        });

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

        $this->emit('exit', $this->pid);
        $this->emit($event);

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

    public function kill($signal = SIGTERM)
    {
        posix_kill($this->pid, $signal);
    }
}