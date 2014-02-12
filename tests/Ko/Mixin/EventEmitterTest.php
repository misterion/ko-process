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
        $wasEmitted = false;

        /** @var \Ko\Mixin\EventEmitter $obj */
        $obj = $this->getObjectForTrait('Ko\Mixin\EventEmitter');
        $obj->on('test', function() use (&$wasEmitted) {
            $wasEmitted = true;
        });

        $obj->emit('test');

        $this->assertTrue($wasEmitted);
    }
}