<?php

declare(strict_types=1);

namespace Horde\Compress\Test\Tnef;

use Horde\Compress\Tnef\TnefObject;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TnefObject::class)]
class TnefObjectTest extends TestCase
{
    public function testDefaultToArrayReturnsEmptyFields(): void
    {
        $obj = new class extends TnefObject {};

        $result = $obj->toArray();
        $this->assertEquals('', $result['type']);
        $this->assertEquals('', $result['subtype']);
        $this->assertEquals('', $result['name']);
        $this->assertEquals('', $result['stream']);
    }

    public function testSetTnefAttributeIsNoOp(): void
    {
        $obj = new class extends TnefObject {};
        $obj->setTnefAttribute(0x18004, 'test', 4);

        $result = $obj->toArray();
        $this->assertEquals('', $result['name']);
    }

    public function testSetMapiAttributeIsNoOp(): void
    {
        $obj = new class extends TnefObject {};
        $obj->setMapiAttribute(0x001e, 0x0070, 'value');

        $result = $obj->toArray();
        $this->assertEquals('', $result['name']);
    }

    public function testConstructorWithData(): void
    {
        $obj = new class (null, 'binary data', ['key' => 'val']) extends TnefObject {
            public function getData(): ?string
            {
                return $this->data;
            }

            public function getOptions(): array
            {
                return $this->options;
            }
        };

        $this->assertEquals('binary data', $obj->getData());
        $this->assertEquals(['key' => 'val'], $obj->getOptions());
    }
}
