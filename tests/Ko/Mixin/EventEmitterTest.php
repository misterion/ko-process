<?php
namespace Ko\Mixin;

/**
 * Class EventEmitterTest
 *
 * @category Tests
 * @package Ko\Mixin
 * @author Nikolay Bondarenko <misterionkell@gmail.com>
 * @version 1.0
 */
class EventEmitterTest extends \PHPUnit_Framework_TestCase
{
    public function testEmit()
    {
        $emittedData = '';

        /** @var \Ko\Mixin\EventEmitter $obj */
        $obj = $this->getObjectForTrait('Ko\Mixin\EventEmitter');
        $obj->on('test', function($args) use (&$emittedData) {
            $emittedData = $args;
        });

        $obj->emit('test', 'someData');
        $this->assertEquals('someData', $emittedData);
    }

    public function testEmitUnexistingEvent()
    {
        /** @var \Ko\Mixin\EventEmitter $obj */
        $obj = $this->getObjectForTrait('Ko\Mixin\EventEmitter');
        $obj->emit('test');
    }
}