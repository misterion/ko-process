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
 * Class SignalHandler
 *
 * A wrapper under the pcntl `pcntl_signal_dispatch` and `pcntl_signal`. This class solves specific issues then signal
 * handlers are not re-entrant. Though, that the "receiving" signals produced after the call pcntl_signal_dispatch,
 * the callback function in pcntl_signal a signal handler, and if out of it kill the current process and start a new
 * (even through the screen), this process is considered to be caused is of a signal handler and unable to receive
 * other signals, as handlers are not re-entrant (code signal handler to get another or the same signal can not).
 *
 * <example>
 *      $handler = new SignalHandler();
 *      $handler->registerHandler(SIGTERM, function() {
 *          echo 'Hello from SIGTERM';
 *      })->registerHandler(SIGCHLD, function() {
 *          echo 'Hello from SIGCHLD';
 *      });
 *      $handler->dispatch();
 *
 * </example>
 *
 * @package Ko
 * @author Nikolay Bondarenko <misterionkell@gmail.com>
 * @copyright 2015 Nikolay Bondarenko. All rights reserved.
 * @version 1.0
 * @since 0.4
 */
class SignalHandler implements \Countable
{
    /**
     * @var []
     */
    private $handlers;

    /**
     * @var \SplQueue
     */
    private $signalQueue;

    public function __construct()
    {
        $this->handlers = [];

        $this->signalQueue = new \SplQueue();
        $this->signalQueue->setIteratorMode(\SplQueue::IT_MODE_DELETE);
    }

    /**
     * Register given callable with pcntl signal.
     *
     * @param int $signal The pcntl signal.
     * @param callable $handler The signal handler.
     *
     * @return $this
     * @throws \RuntimeException If could not register handler with pcntl_signal.
     */
    public function registerHandler($signal, callable $handler)
    {
        if (!isset($this->handlers[$signal])) {
            $this->handlers[$signal] = [];

            if (!pcntl_signal($signal, [$this, 'handleSignal'])) {
                throw new \RuntimeException(sprintf('Could not register signal %d with pcntl_signal', $signal));
            };
        };

        $this->handlers[$signal][] = $handler;
        return $this;
    }

    /**
     * Return true is ant handler registered with given signal.
     *
     * @param int $signal The pcntl signal.
     *
     * @return bool
     */
    public function hasHandler($signal)
    {
        return !empty($this->handlers[$signal]);
    }

    /**
     * Enqueue pcntl signal to dispatch in feature.
     *
     * @param int $signal The pcntl signal.
     *
     * @return void
     */
    public function handleSignal($signal)
    {
        $this->signalQueue->enqueue($signal);
    }

    /**
     * Execute `pcntl_signal_dispatch` and process all registered handlers.
     *
     * @return $this
     */
    public function dispatch()
    {
        pcntl_signal_dispatch();

        foreach ($this->signalQueue as $signal) {
            foreach ($this->handlers[$signal] as &$callable) {
                $callable($signal);
            }
        }

        return $this;
    }

    /**
     * Return count of queued signals.
     *
     * @link http://php.net/manual/en/countable.count.php
     * @return int The custom count as an integer.
     * </p>
     * <p>
     * The return value is cast to an integer.
     */
    public function count()
    {
        return count($this->signalQueue);
    }
}