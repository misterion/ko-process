<?php
namespace Ko;

class Semaphore
{
    /**
     * @var resource
     */
    protected $mutex;

    /**
     * @var string
     */
    protected $file;

    /**
     * @var bool
     */
    protected $isAcquired;

    public function __construct($key = null)
    {
        $this->isAcquired = false;

        if ($key) {
            $semKey = $key;
        } else {
            $this->file = tempnam(sys_get_temp_dir(), 's');
            $semKey = ftok($this->file, 'a');
        }

        $this->mutex = sem_get($semKey, 1); //auto_release = 1 by default
        if (!$this->mutex) {
            throw new \RuntimeException('Unable to create the semaphore');
        }
    }

    public function __destruct()
    {
        if ($this->isAcquired()) {
            $this->release();
        }

        if (file_exists($this->file)) {
            unlink($this->file);
        }
    }

    /**
     * Return TRUE is semaphore acquired.
     *
     * @return boolean
     */
    public function isAcquired()
    {
        return $this->isAcquired;
    }

    /**
     * Release a semaphore.
     *
     * @return bool
     */
    public function release()
    {
        $this->isAcquired = !sem_release($this->mutex);

        return $this->isAcquired;
    }

    /**
     * Lock and execute given callable.
     *
     * @param callable $callable
     *
     * @return mixed
     */
    public function lockExecute(callable $callable)
    {
        $this->acquire();
        $result = $callable();
        $this->release();

        return $result;
    }

    /**
     * Acquire a semaphore.
     *
     * @return bool
     */
    public function acquire()
    {
        $this->isAcquired = sem_acquire($this->mutex);

        return $this->isAcquired;
    }
}