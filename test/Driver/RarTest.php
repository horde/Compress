<?php

declare(strict_types=1);

/**
 * Copyright 2013-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL-2.1). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category   Horde
 * @license    http://www.horde.org/licenses/lgpl21 LGPL-2.1
 * @package    Compress
 */

namespace Horde\Compress\Test\Driver;

use Horde\Compress\Driver\Rar;
use Horde\Compress\Exception;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Tests the modern Rar driver.
 */
#[CoversClass(Rar::class)]
class RarTest extends TestCase
{
    private Rar $rar;

    protected function setUp(): void
    {
        $this->rar = new Rar();
    }

    public function testCanCompress(): void
    {
        $this->assertFalse($this->rar->canCompress());
    }

    public function testCanDecompress(): void
    {
        $this->assertTrue($this->rar->canDecompress());
    }

    public function testDecompressInvalidDataThrowsException(): void
    {
        $this->expectException(Exception::class);
        $this->rar->decompress('invalid data');
    }

    public function testDecompressWithBlockStartButTruncatedThrowsException(): void
    {
        $this->expectException(Exception::class);
        $this->rar->decompress(Rar::BLOCK_START . '12');
    }

    public function testCompressThrowsException(): void
    {
        $this->expectException(Exception::class);
        $this->rar->compress('data');
    }

    public function testCompressFilesThrowsException(): void
    {
        $this->expectException(Exception::class);
        $this->rar->compressFiles([['data' => 'test', 'name' => 'test.txt', 'time' => time()]]);
    }

    public function testDecompressValidRarHeader(): void
    {
        $header = Rar::BLOCK_START;
        // Archive header block (type 0x73, minimal size=13 with 6 bytes reserved)
        $archiveBlock = $this->buildHeaderBlock(0x73, 0, str_repeat("\x00", 6));
        // File header block (type 0x74)
        $fileName = 'test.txt';
        $fileData = str_repeat('A', 10);
        // The RAR file header body after base header:
        // V=Packed, V=Unpacked, C=OS, V=CRC32, V=Time, C=Version, C=Method, v=Length, v=Attrib
        // That's 23 bytes, but code reads name from offset 25, so 2 pad bytes needed
        $fileHeaderBody = pack(
            'VVCVVCCvv',
            strlen($fileData),  // packed size
            strlen($fileData),  // unpacked size
            0x00,               // OS
            crc32($fileData),   // CRC32
            0x4A552000,         // Time (some DOS timestamp)
            20,                 // Version
            0x30,               // Method: Store
            strlen($fileName),  // filename length
            0x20                // Attrib
        ) . "\x00\x00" . $fileName;
        $fileBlock = $this->buildHeaderBlock(0x74, 0, $fileHeaderBody);

        $data = $header . $archiveBlock . $fileBlock . $fileData;
        $result = $this->rar->decompress($data);

        $this->assertCount(1, $result);
        $this->assertEquals('test.txt', $result[0]['name']);
        $this->assertEquals(strlen($fileData), $result[0]['size']);
        $this->assertEquals('Store', $result[0]['method']);
    }

    /**
     * Build a minimal RAR header block.
     */
    private function buildHeaderBlock(int $type, int $flags, string $body): string
    {
        $headSize = 7 + strlen($body);
        $block = pack('v', 0)           // CRC (fake)
            . chr($type)                 // type
            . pack('v', $flags)          // flags
            . pack('v', $headSize);      // size
        return $block . $body;
    }
}
