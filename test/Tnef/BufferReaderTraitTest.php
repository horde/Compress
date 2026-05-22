<?php

declare(strict_types=1);

namespace Horde\Compress\Test\Tnef;

use Horde\Compress\Tnef\BufferReaderTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(BufferReaderTrait::class)]
class BufferReaderTraitTest extends TestCase
{
    private object $reader;

    protected function setUp(): void
    {
        $this->reader = new class {
            use BufferReaderTrait;

            public function pubReadBytes(string &$data, int $bytes): ?string
            {
                return $this->readBytes($data, $bytes);
            }

            public function pubReadInt(string &$data, int $bits): ?int
            {
                return $this->readInt($data, $bits);
            }
        };
    }

    public function testReadBytesConsumesData(): void
    {
        $data = 'ABCDEFGH';
        $result = $this->reader->pubReadBytes($data, 4);
        $this->assertEquals('ABCD', $result);
        $this->assertEquals('EFGH', $data);
    }

    public function testReadBytesReturnsNullOnUnderflow(): void
    {
        $data = 'AB';
        $result = $this->reader->pubReadBytes($data, 5);
        $this->assertNull($result);
        $this->assertEquals('AB', $data);
    }

    public function testReadBytesZeroLength(): void
    {
        $data = 'ABC';
        $result = $this->reader->pubReadBytes($data, 0);
        $this->assertEquals('', $result);
        $this->assertEquals('ABC', $data);
    }

    public function testReadInt8Bit(): void
    {
        $data = "\x42\xFF";
        $result = $this->reader->pubReadInt($data, 8);
        $this->assertEquals(0x42, $result);
        $this->assertEquals("\xFF", $data);
    }

    public function testReadInt16BitLittleEndian(): void
    {
        $data = "\x01\x02\xFF";
        $result = $this->reader->pubReadInt($data, 16);
        $this->assertEquals(0x0201, $result);
        $this->assertEquals("\xFF", $data);
    }

    public function testReadInt32BitLittleEndian(): void
    {
        $data = "\x78\x9f\x3e\x22rest";
        $result = $this->reader->pubReadInt($data, 32);
        $this->assertEquals(0x223e9f78, $result);
        $this->assertEquals('rest', $data);
    }

    public function testReadIntReturnsNullOnUnderflow(): void
    {
        $data = "\x01";
        $result = $this->reader->pubReadInt($data, 16);
        $this->assertNull($result);
        $this->assertEquals("\x01", $data);
    }

    public function testReadInt32Underflow(): void
    {
        $data = "\x01\x02\x03";
        $result = $this->reader->pubReadInt($data, 32);
        $this->assertNull($result);
    }

    public function testSequentialReads(): void
    {
        $data = "\x01\x00\x02\x00\x03\x00";
        $this->assertEquals(1, $this->reader->pubReadInt($data, 16));
        $this->assertEquals(2, $this->reader->pubReadInt($data, 16));
        $this->assertEquals(3, $this->reader->pubReadInt($data, 16));
        $this->assertEquals('', $data);
    }
}
