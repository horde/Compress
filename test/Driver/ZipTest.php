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

use Horde\Compress\Driver\Zip;
use Horde\Compress\Exception;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Depends;
use PHPUnit\Framework\TestCase;

/**
 * Tests the modern Zip driver.
 */
#[CoversClass(Zip::class)]
class ZipTest extends TestCase
{
    private Zip $zip;
    private string $testdata;

    protected function setUp(): void
    {
        $this->zip = new Zip();
        $this->testdata = str_repeat("0123456789ABCDE", 1000);
    }

    public function testCanCompress(): void
    {
        $this->assertTrue($this->zip->canCompress());
    }

    public function testCanDecompress(): void
    {
        $this->assertTrue($this->zip->canDecompress());
    }

    public function testCompressAndDecompressSingleFile(): void
    {
        $archive = $this->zip->compressFiles([
            ['data' => $this->testdata, 'name' => 'test.txt', 'time' => 1000000000],
        ]);

        $this->assertNotEmpty($archive);

        $list = $this->zip->decompress($archive, ['action' => Zip::ZIP_LIST]);
        $this->assertCount(1, $list);
        $this->assertEquals('test.txt', $list[0]['name']);
        $this->assertEquals(15000, $list[0]['size']);
        $this->assertEquals('Deflated', $list[0]['method']);

        $result = $this->zip->decompress($archive, [
            'action' => Zip::ZIP_DATA,
            'info' => $list,
            'key' => 0,
        ]);
        $this->assertEquals($this->testdata, $result['data']);
    }

    public function testCompressMultipleFiles(): void
    {
        $archive = $this->zip->compressFiles([
            ['data' => 'hello', 'name' => 'a.txt', 'time' => 1000000000],
            ['data' => 'world', 'name' => 'b.txt', 'time' => 1000000000],
        ]);

        $list = $this->zip->decompress($archive, ['action' => Zip::ZIP_LIST]);
        $this->assertCount(2, $list);

        $names = array_column($list, 'name');
        $this->assertContains('a.txt', $names);
        $this->assertContains('b.txt', $names);
    }

    public function testCompressRawString(): void
    {
        $archive = $this->zip->compress('hello world');
        $list = $this->zip->decompress($archive, ['action' => Zip::ZIP_LIST]);
        $this->assertCount(1, $list);
        $this->assertEquals('data', $list[0]['name']);
    }

    public function testCompressDirectory(): void
    {
        $archive = $this->zip->compressDirectory(__DIR__ . '/../fixtures/directory');

        $list = $this->zip->decompress($archive, ['action' => Zip::ZIP_LIST]);
        $this->assertCount(3, $list);

        $names = array_column($list, 'name');
        sort($names);
        $this->assertEquals(['one.txt', 'sub/three.txt', 'two.bin'], $names);
    }

    public function testDecompressWithoutActionReturnsListing(): void
    {
        $archive = $this->zip->compressFiles([
            ['data' => 'test', 'name' => 'file.txt', 'time' => time()],
        ]);

        $list = $this->zip->decompress($archive);
        $this->assertCount(1, $list);
        $this->assertEquals('file.txt', $list[0]['name']);
    }

    public function testInvalidZipDataThrowsException(): void
    {
        $this->expectException(Exception::class);
        $this->zip->decompress('not a zip file', ['action' => Zip::ZIP_LIST]);
    }

    public function testCheckZipData(): void
    {
        $archive = $this->zip->compressFiles([
            ['data' => 'test', 'name' => 'test.txt', 'time' => time()],
        ]);

        $this->assertTrue($this->zip->checkZipData($archive));
        $this->assertFalse($this->zip->checkZipData('not a zip'));
    }

    public function testEmptyFileInArchive(): void
    {
        $archive = $this->zip->compressFiles([
            ['data' => '', 'name' => 'empty.txt', 'time' => time()],
        ]);

        $list = $this->zip->decompress($archive, ['action' => Zip::ZIP_LIST]);
        $this->assertCount(1, $list);
        $this->assertEquals(0, $list[0]['size']);
    }

    public function testDatesBefore1980AreClamped(): void
    {
        $archive = $this->zip->compressFiles([
            ['data' => 'old', 'name' => 'old.txt', 'time' => 0],
        ]);

        $list = $this->zip->decompress($archive, ['action' => Zip::ZIP_LIST]);
        $this->assertCount(1, $list);
        $this->assertGreaterThanOrEqual(mktime(0, 0, 0, 1, 1, 1980), $list[0]['date']);
    }

    public function testBackslashesInPathConvertedToForwardSlashes(): void
    {
        $archive = $this->zip->compressFiles([
            ['data' => 'test', 'name' => 'path\\to\\file.txt', 'time' => time()],
        ]);

        $list = $this->zip->decompress($archive, ['action' => Zip::ZIP_LIST]);
        $this->assertEquals('path/to/file.txt', $list[0]['name']);
    }

    public function testCompressWithStreamResource(): void
    {
        $original = str_repeat("stream data for zip test\n", 100);
        $fd = fopen('php://temp', 'r+');
        fwrite($fd, $original);

        $archive = $this->zip->compressFiles([
            ['data' => $fd, 'name' => 'streamed.txt', 'time' => 1000000000],
        ]);
        fclose($fd);

        $this->assertNotEmpty($archive);

        $list = $this->zip->decompress($archive, ['action' => Zip::ZIP_LIST]);
        $this->assertCount(1, $list);
        $this->assertEquals('streamed.txt', $list[0]['name']);
        $this->assertEquals(strlen($original), $list[0]['size']);

        $result = $this->zip->decompress($archive, [
            'action' => Zip::ZIP_DATA,
            'info' => $list,
            'key' => 0,
        ]);
        $this->assertEquals($original, $result['data']);
    }

    public function testCompressMixedStringAndStreamData(): void
    {
        $stringData = 'plain string content';
        $streamData = str_repeat("stream content\n", 50);
        $fd = fopen('php://temp', 'r+');
        fwrite($fd, $streamData);

        $archive = $this->zip->compressFiles([
            ['data' => $stringData, 'name' => 'string.txt', 'time' => 1000000000],
            ['data' => $fd, 'name' => 'stream.txt', 'time' => 1000000000],
        ]);
        fclose($fd);

        $list = $this->zip->decompress($archive, ['action' => Zip::ZIP_LIST]);
        $this->assertCount(2, $list);

        foreach ($list as $i => $entry) {
            $content = $this->zip->decompress($archive, [
                'action' => Zip::ZIP_DATA,
                'info' => $list,
                'key' => $i,
            ]);
            if ($entry['name'] === 'string.txt') {
                $this->assertEquals($stringData, $content['data']);
            } else {
                $this->assertEquals($streamData, $content['data']);
            }
        }
    }
}
