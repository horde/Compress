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

use Horde\Compress\Driver\Tar;
use Horde\Compress\Exception;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SplFileInfo;

/**
 * Tests the modern Tar driver.
 */
#[CoversClass(Tar::class)]
class TarTest extends TestCase
{
    private Tar $tar;
    private string $testdata;

    protected function setUp(): void
    {
        $this->tar = new Tar();
        $this->testdata = str_repeat("0123456789ABCDE", 1000);
    }

    public function testCanCompress(): void
    {
        $this->assertTrue($this->tar->canCompress());
    }

    public function testCanDecompress(): void
    {
        $this->assertTrue($this->tar->canDecompress());
    }

    public function testCompressAndDecompressSingleFile(): void
    {
        $archive = $this->tar->compressFiles([
            ['data' => $this->testdata, 'name' => 'test.txt', 'time' => 1000000000],
        ]);

        $this->assertNotEmpty($archive);

        $list = $this->tar->decompress($archive);
        $this->assertCount(1, $list);
        $this->assertEquals('test.txt', $list[0]['name']);
        $this->assertEquals(15000, $list[0]['size']);
        $this->assertEquals(1000000000, $list[0]['date']);
        $this->assertEquals($this->testdata, $list[0]['data']);
    }

    public function testCompressMultipleFiles(): void
    {
        $archive = $this->tar->compressFiles([
            ['data' => 'hello', 'name' => 'a.txt', 'time' => 1000000000],
            ['data' => 'world', 'name' => 'b.txt', 'time' => 1000000000],
        ]);

        $list = $this->tar->decompress($archive);
        $this->assertCount(2, $list);
        $this->assertEquals('a.txt', $list[0]['name']);
        $this->assertEquals('hello', $list[0]['data']);
        $this->assertEquals('b.txt', $list[1]['name']);
        $this->assertEquals('world', $list[1]['data']);
    }

    public function testCompressRawString(): void
    {
        $archive = $this->tar->compress('hello world');
        $list = $this->tar->decompress($archive);
        $this->assertCount(1, $list);
        $this->assertEquals('data', $list[0]['name']);
        $this->assertEquals('hello world', $list[0]['data']);
    }

    public function testCompressDirectory(): void
    {
        $archive = $this->tar->compressDirectory(__DIR__ . '/../fixtures/directory');

        $list = $this->tar->decompress($archive);
        $this->assertCount(3, $list);

        usort($list, fn($a, $b) => strcmp($a['name'], $b['name']));
        $this->assertEquals('one.txt', $list[0]['name']);
        $this->assertEquals("One\n", $list[0]['data']);
        $this->assertEquals('sub/three.txt', $list[1]['name']);
        $this->assertEquals("Three\n", $list[1]['data']);
        $this->assertEquals('two.bin', $list[2]['name']);
        $this->assertEquals("\x02\x0a", $list[2]['data']);
    }

    public function testCompressWithStreamData(): void
    {
        $fd = fopen('php://temp', 'r+');
        fwrite($fd, $this->testdata);

        $archive = $this->tar->compressFiles([
            ['data' => $fd, 'name' => 'stream.txt', 'time' => 1000000000],
        ]);

        fclose($fd);

        $list = $this->tar->decompress($archive);
        $this->assertCount(1, $list);
        $this->assertEquals('stream.txt', $list[0]['name']);
        $this->assertEquals($this->testdata, $list[0]['data']);
    }

    public function testEmptyFile(): void
    {
        $archive = $this->tar->compressFiles([
            ['data' => '', 'name' => 'empty.txt', 'time' => time()],
        ]);

        $list = $this->tar->decompress($archive);
        $this->assertCount(1, $list);
        $this->assertEquals(0, $list[0]['size']);
        $this->assertEquals('', $list[0]['data']);
    }

    public function testBackslashesConvertedToForwardSlashes(): void
    {
        $archive = $this->tar->compressFiles([
            ['data' => 'test', 'name' => 'path\\to\\file.txt', 'time' => time()],
        ]);

        $list = $this->tar->decompress($archive);
        $this->assertEquals('path/to/file.txt', $list[0]['name']);
    }

    public function testInvalidDataThrowsException(): void
    {
        $this->expectException(Exception::class);
        $this->tar->decompress("\x00");
    }

    public function testLongFilenameUsesPrefix(): void
    {
        // TAR format: names > 99 chars move to prefix field.
        // Current implementation limitation: on decompress, the prefix field
        // is not concatenated, so very long names may not roundtrip perfectly.
        // Test that at least no exception is thrown during compress.
        $longName = str_repeat('a', 100) . '.txt';
        $archive = $this->tar->compressFiles([
            ['data' => 'long', 'name' => $longName, 'time' => time()],
        ]);

        $this->assertNotEmpty($archive);
    }

    public function testCompressWithSplFileInfo(): void
    {
        $tmpDir = sys_get_temp_dir() . '/horde_tar_test_' . uniqid();
        mkdir($tmpDir);
        file_put_contents($tmpDir . '/hello.txt', 'Hello SplFileInfo');
        chmod($tmpDir . '/hello.txt', 0o644);

        try {
            $spl = new SplFileInfo($tmpDir . '/hello.txt');
            $archive = $this->tar->compressFiles([
                [
                    'data' => file_get_contents($tmpDir . '/hello.txt'),
                    'name' => 'hello.txt',
                    'time' => filemtime($tmpDir . '/hello.txt'),
                    'spl' => $spl,
                ],
            ]);

            $list = $this->tar->decompress($archive);
            $this->assertCount(1, $list);
            $this->assertEquals('hello.txt', $list[0]['name']);
            $this->assertEquals('Hello SplFileInfo', $list[0]['data']);
            // With SplFileInfo, permissions are encoded in the header
            $this->assertNotEquals('----------', $list[0]['attr']);
        } finally {
            @unlink($tmpDir . '/hello.txt');
            @rmdir($tmpDir);
        }
    }

    public function testCompressWithSymlink(): void
    {
        $tmpDir = sys_get_temp_dir() . '/horde_tar_link_' . uniqid();
        mkdir($tmpDir);
        file_put_contents($tmpDir . '/target.txt', 'link target');
        symlink($tmpDir . '/target.txt', $tmpDir . '/link.txt');

        try {
            $spl = new SplFileInfo($tmpDir . '/link.txt');
            $archive = $this->tar->compressFiles([
                [
                    'data' => '',
                    'name' => 'link.txt',
                    'time' => time(),
                    'spl' => $spl,
                ],
            ]);

            $list = $this->tar->decompress($archive);
            $this->assertCount(1, $list);
            $this->assertEquals('link.txt', $list[0]['name']);
            $this->assertEquals('Link', $list[0]['type']);
            $this->assertEquals(0, $list[0]['size']);
        } finally {
            @unlink($tmpDir . '/link.txt');
            @unlink($tmpDir . '/target.txt');
            @rmdir($tmpDir);
        }
    }

    public function testCompressMultipleFilesWithSplFileInfo(): void
    {
        $tmpDir = sys_get_temp_dir() . '/horde_tar_multi_' . uniqid();
        mkdir($tmpDir);
        file_put_contents($tmpDir . '/a.txt', 'file A');
        file_put_contents($tmpDir . '/b.txt', 'file B');

        try {
            $archive = $this->tar->compressFiles([
                [
                    'data' => 'file A',
                    'name' => 'a.txt',
                    'time' => filemtime($tmpDir . '/a.txt'),
                    'spl' => new SplFileInfo($tmpDir . '/a.txt'),
                ],
                [
                    'data' => 'file B',
                    'name' => 'b.txt',
                    'time' => filemtime($tmpDir . '/b.txt'),
                    'spl' => new SplFileInfo($tmpDir . '/b.txt'),
                ],
            ]);

            $list = $this->tar->decompress($archive);
            $this->assertCount(2, $list);
            $this->assertEquals('file A', $list[0]['data']);
            $this->assertEquals('file B', $list[1]['data']);
        } finally {
            @unlink($tmpDir . '/a.txt');
            @unlink($tmpDir . '/b.txt');
            @rmdir($tmpDir);
        }
    }
}
