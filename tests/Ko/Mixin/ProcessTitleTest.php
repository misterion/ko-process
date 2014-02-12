<?php
namespace Ko\Mixin;

/**
 * Class ProcessTitleTest
 *
 * @category Tests
 * @package Ko\Mixin
 * @author Nikolay Bondarenko <misterionkell@gmail.com>
 * @version 1.0
 */
class ProcessTitleTest extends \PHPUnit_Framework_TestCase
{
    public function testSetProcessTitle()
    {
        /** @var \Ko\Mixin\ProcessTitle $obj */
        $obj = $this->getObjectForTrait('Ko\Mixin\ProcessTitle');
        $obj->setProcessTitle('test title');

        $this->assertEquals('test title', $obj->getProcessTitle());
    }
}
 