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

use Horde\Compress\Driver\Gzip;
use Horde\Compress\Exception;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;

/**
 * Tests the modern Gzip driver.
 */
#[CoversClass(Gzip::class)]
#[RequiresPhpExtension('zlib')]
class GzipTest extends TestCase
{
    private Gzip $gzip;

    protected function setUp(): void
    {
        $this->gzip = new Gzip();
    }

    public function testCanCompress(): void
    {
        $this->assertFalse($this->gzip->canCompress());
    }

    public function testCanDecompress(): void
    {
        $this->assertTrue($this->gzip->canDecompress());
    }

    public function testDecompressValidGzipData(): void
    {
        $original = 'Hello, World! This is a test of gzip decompression.';
        $compressed = gzencode($original);

        $result = $this->gzip->decompress($compressed);
        $this->assertCount(1, $result);
        $this->assertEquals($original, $result[0]['data']);
        $this->assertEquals(strlen($original), $result[0]['size']);
    }

    public function testDecompressWithFilename(): void
    {
        $original = 'Data with filename header';
        $compressed = gzencode($original, 9, FORCE_GZIP);

        $result = $this->gzip->decompress($compressed);
        $this->assertEquals($original, $result[0]['data']);
    }

    public function testDecompressInvalidDataThrowsException(): void
    {
        $this->expectException(Exception::class);
        $this->gzip->decompress('not gzip data at all');
    }

    public function testCompressThrowsException(): void
    {
        $this->expectException(Exception::class);
        $this->gzip->compress('data');
    }

    public function testCompressFilesThrowsException(): void
    {
        $this->expectException(Exception::class);
        $this->gzip->compressFiles([['data' => 'test', 'name' => 'test.txt', 'time' => time()]]);
    }

    public function testDecompressLargeData(): void
    {
        $original = str_repeat('The quick brown fox jumps over the lazy dog. ', 1000);
        $compressed = gzencode($original);

        $result = $this->gzip->decompress($compressed);
        $this->assertEquals($original, $result[0]['data']);
        $this->assertEquals(strlen($original), $result[0]['size']);
    }

    public function testDecompressBinaryData(): void
    {
        $original = random_bytes(256);
        $compressed = gzencode($original);

        $result = $this->gzip->decompress($compressed);
        $this->assertEquals($original, $result[0]['data']);
    }

    public function testDecompressWithFextraFlag(): void
    {
        $original = 'FEXTRA test data';
        $gzipData = $this->buildGzipWithFlags($original, 0x04, extra: "XY\x00\x00");

        $result = $this->gzip->decompress($gzipData);
        $this->assertEquals($original, $result[0]['data']);
    }

    public function testDecompressWithFnameFlag(): void
    {
        $original = 'FNAME test data';
        $gzipData = $this->buildGzipWithFlags($original, 0x08, filename: 'test.txt');

        $result = $this->gzip->decompress($gzipData);
        $this->assertEquals($original, $result[0]['data']);
    }

    public function testDecompressWithFcommentFlag(): void
    {
        $original = 'FCOMMENT test data';
        $gzipData = $this->buildGzipWithFlags($original, 0x10, comment: 'A comment');

        $result = $this->gzip->decompress($gzipData);
        $this->assertEquals($original, $result[0]['data']);
    }

    public function testDecompressWithFhcrcFlag(): void
    {
        $original = 'FHCRC test data';
        $gzipData = $this->buildGzipWithFlags($original, 0x02, headerCrc: true);

        $result = $this->gzip->decompress($gzipData);
        $this->assertEquals($original, $result[0]['data']);
    }

    public function testDecompressWithAllFlags(): void
    {
        $original = 'All flags combined test';
        $flags = 0x02 | 0x04 | 0x08 | 0x10;
        $gzipData = $this->buildGzipWithFlags(
            $original,
            $flags,
            extra: "EX\x00\x00",
            filename: 'combined.txt',
            comment: 'All flags',
            headerCrc: true,
        );

        $result = $this->gzip->decompress($gzipData);
        $this->assertEquals($original, $result[0]['data']);
    }

    /**
     * Build a raw gzip stream with specific flags set.
     *
     * Gzip format (RFC 1952):
     *   \x1f\x8b  - magic
     *   CM (1)    - compression method (8 = deflate)
     *   FLG (1)   - flags
     *   MTIME (4) - modification time
     *   XFL (1)   - extra flags
     *   OS (1)    - operating system
     *   [FEXTRA: XLEN(2) + data(XLEN)]
     *   [FNAME: null-terminated string]
     *   [FCOMMENT: null-terminated string]
     *   [FHCRC: CRC16(2)]
     *   <deflate data>
     *   CRC32(4)  - of uncompressed data
     *   ISIZE(4)  - size of uncompressed data mod 2^32
     */
    private function buildGzipWithFlags(
        string $data,
        int $flags,
        ?string $extra = null,
        ?string $filename = null,
        ?string $comment = null,
        bool $headerCrc = false,
    ): string {
        $header = "\x1f\x8b"    // magic
            . "\x08"            // CM = deflate
            . chr($flags)       // FLG
            . "\x00\x00\x00\x00" // MTIME
            . "\x00"            // XFL
            . "\xff";           // OS = unknown

        if ($flags & 0x04) {
            $extraData = $extra ?? '';
            $header .= pack('v', strlen($extraData)) . $extraData;
        }

        if ($flags & 0x08) {
            $header .= ($filename ?? '') . "\x00";
        }

        if ($flags & 0x10) {
            $header .= ($comment ?? '') . "\x00";
        }

        if ($flags & 0x02) {
            $crc16 = crc32($header) & 0xFFFF;
            $header .= pack('v', $crc16);
        }

        $deflated = gzdeflate($data);
        $crc32 = crc32($data);
        $isize = strlen($data);

        return $header . $deflated . pack('V', $crc32) . pack('V', $isize);
    }
}
