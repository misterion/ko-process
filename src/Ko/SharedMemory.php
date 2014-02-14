<?php
namespace Ko;

class SharedMemory implements \ArrayAccess, \Countable
{
    /**
     * @var resource
     */
    protected $id;

    /**
     * @var string
     */
    protected $file;

    /**
     * @var int
     */
    protected $size;

    /**
     * @var Semaphore
     */
    protected $mutex;

    /**
     * @var array
     */
    protected $keyMapper;

    /**
     * @var int
     */
    protected $keyIndex;

    public function __construct($memorySize = 1024)
    {
        $this->keyMapper = [];
        $this->keyIndex = 1;

        $this->size = $memorySize;

        $this->file = tempnam(sys_get_temp_dir(), 's');
        $key = ftok($this->file, 'a');

        $this->id = shm_attach($key, $this->size);
        if (false === $this->id) {
            throw new \RuntimeException('Unable to create the shared memory segment');
        }

        $this->mutex = new Semaphore($key);
    }

    public function __destruct()
    {
        if (is_resource($this->id)) {
            shm_remove($this->id);
        }

        unlink($this->file);
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
        return $this->mutex->lockExecute(function() use ($offset) {
            return shm_has_var($this->id, $this->getKey($offset));
        });
    }

    protected function getKey($offset)
    {
        if (isset($this->keyMapper[$offset])) {
            return $this->keyMapper[$offset];
        }

        $this->keyMapper[$offset] =  $this->keyIndex++;
        return $this->keyMapper[$offset];
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
        return $this->mutex->lockExecute(function() use ($offset) {
            $key = $this->getKey($offset);
            if(shm_has_var($this->id, $key)) {
                return shm_get_var($this->id, $key);
            }

            return null;
        });
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
        $this->mutex->lockExecute(function() use ($offset, $value) {
            shm_put_var($this->id, $this->getKey($offset), $value);
        });
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
        $this->mutex->lockExecute(function() use ($offset) {
            if (shm_remove_var($this->id, $this->getKey($offset))) {
                unset($this->keyMapper[$offset]);
            };
        });
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
        return count($this->keyMapper);
    }


}