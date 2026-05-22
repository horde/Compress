<?php

declare(strict_types=1);

/**
 * Copyright 2011-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL-2.1). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category   Horde
 * @license    http://www.horde.org/licenses/lgpl21 LGPL-2.1
 * @package    Compress
 */

namespace Horde\Compress\Test;

use Horde_Compress;
use Horde_Compress_Zip;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Depends;
use PHPUnit\Framework\TestCase;

/**
 * Tests the legacy Horde_Compress_Zip driver.
 */
#[CoversClass(Horde_Compress_Zip::class)]
class ZipLegacyTest extends TestCase
{
    private string $testdata;

    protected function setUp(): void
    {
        $this->testdata = str_repeat("0123456789ABCDE", 1000);
    }

    public function testZipCreateString(): string
    {
        $compress = Horde_Compress::factory('Zip');

        $zip_data = $compress->compress([[
            'data' => $this->testdata,
            'name' => 'test.txt',
            'time' => 1000000000,
        ]]);

        $this->assertNotEmpty($zip_data);

        return $zip_data;
    }

    #[Depends('testZipCreateString')]
    public function testZipUnzipString(string $zip_data): void
    {
        $this->assertZipUnzip($zip_data);
    }

    public function testZipCreateStream(): string
    {
        $compress = Horde_Compress::factory('Zip');

        $fd = fopen('php://temp', 'r+');
        fwrite($fd, $this->testdata);

        $zip_data = $compress->compress([[
            'data' => $fd,
            'name' => 'test.txt',
            'time' => 1000000000,
        ]], [
            'stream' => true,
        ]);

        $this->assertNotEmpty($zip_data);
        $this->assertIsResource($zip_data);

        return stream_get_contents($zip_data);
    }

    #[Depends('testZipCreateStream')]
    public function testZipUnzipStream(string $zip_data): void
    {
        $this->assertZipUnzip($zip_data);
    }

    private function assertZipUnzip(string $zip_data): void
    {
        $compress = Horde_Compress::factory('Zip');
        $list = $compress->decompress(
            $zip_data,
            ['action' => Horde_Compress_Zip::ZIP_LIST]
        );
        $this->assertEquals(
            [[
                'attr' => '-A---',
                'crc' => 'd72299ec',
                'csize' => 62,
                'date' => 1000000000,
                '_dataStart' => 38,
                'name' => 'test.txt',
                'method' => 'Deflated',
                '_method' => 8,
                'size' => 15000,
                'type' => 'binary',
            ]],
            $list
        );

        $data = $compress->decompress(
            $zip_data,
            [
                'action' => Horde_Compress_Zip::ZIP_DATA,
                'info' => $list,
                'key' => 0,
            ]
        );
        $this->assertEquals($this->testdata, $data);
    }

    public function testDatesBefore1980(): void
    {
        $compress = Horde_Compress::factory('Zip');

        $zip_data = $compress->compress([[
            'data' => $this->testdata,
            'name' => 'test.txt',
            'time' => mktime(0, 0, 0, 12, 31, 1987),
        ]]);

        $this->assertNotEmpty($zip_data);
    }

    public function testZipDirectory(): void
    {
        $compress = Horde_Compress::factory('Zip');

        $zip_data = $compress->compressDirectory(
            __DIR__ . '/fixtures/directory'
        );

        $this->assertNotEmpty($zip_data);

        $list = $compress->decompress(
            $zip_data,
            ['action' => Horde_Compress_Zip::ZIP_LIST]
        );
        usort($list, fn($a, $b) => strcmp($a['name'], $b['name']));
        $this->assertCount(3, $list);

        $fixtures = [
            'one.txt' => [4, "One\n"],
            'sub/three.txt' => [6, "Three\n"],
            'two.bin' => [2, "\x02\x0a"],
        ];
        foreach ($fixtures as $key => $testValues) {
            $found = false;
            for ($i = 0; $i < 3; $i++) {
                $file = $list[$i];
                if ($file['name'] == $key) {
                    $found = true;
                    $this->assertEquals($testValues[0], $file['size']);
                    $data = $compress->decompress(
                        $zip_data,
                        [
                            'action' => Horde_Compress_Zip::ZIP_DATA,
                            'info' => $list,
                            'key' => $i,
                        ]
                    );
                    $this->assertEquals($testValues[1], $data);
                }
            }
            if (!$found) {
                $this->fail($key . ' not found.');
            }
        }
    }
}
