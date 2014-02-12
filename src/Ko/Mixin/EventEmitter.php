<?php
namespace Ko\Mixin;

/**
 * Class EventEmitter
 *
 * @package Ko\Mixin
 * @author Nikolay Bondarenko <misterionkell@gmail.com>
 * @version 1.0
 */
trait EventEmitter
{
    private $subscribers = [];

    /**
     * @param string $name
     * @param callable $fn
     */
    public function on($name, callable $fn)
    {
        $eventName = 'event:' . $name;
        if (!isset($this->subscribers[$eventName])) {
            $this->subscribers[$eventName] = [];
        }

        $this->subscribers[$eventName][] = $fn;
    }

    public function emit($name)
    {
        $eventName = 'event:' . $name;
        foreach ($this->subscribers[$eventName] as $fn) {
            $fn();
        }
    }
} 