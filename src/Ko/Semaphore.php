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
 * Class Semaphore
 *
 * @package Ko
 * @author Nikolay Bondarenko <misterionkell@gmail.com>
 * @copyright 2015 Nikolay Bondarenko. All rights reserved.
 * @version 1.0
 */
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

    /**
     * Creator process pid
     *
     * @var int
     */
    protected $pid;

    public function __construct($key = null)
    {
        $this->pid = getmypid();

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

        if ($this->pid === getmypid()) {
            $this->remove();
        }
    }

    /**
     * Remove semaphore.
     */
    public function remove()
    {
        if (is_resource($this->mutex)) {
            sem_remove($this->mutex);
        }

        if (file_exists($this->file)) {
            unlink($this->file);
        }
    }

    /**
     * Return TRUE if semaphore acquired in this process.
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
        $this->isAcquired = $this->acquire();
        $result = $callable();
        $this->isAcquired = $this->release();

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