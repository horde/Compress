<?php

/**
 * Copyright 2011-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL-2.1). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @author     Michael Slusarz <slusarz@horde.org>
 * @category   Horde
 * @license    http://www.horde.org/licenses/lgpl21 LGPL-2.1
 * @package    Compress
 * @subpackage UnitTests
 */

namespace Horde\Compress;

use Horde_Test_Case;
use Horde_Compress;
use Horde_Compress_Zip;

/**
 * Tests the ZIP compressor.
 *
 * @author     Michael Slusarz <slusarz@horde.org>
 * @category   Horde
 * @copyright  2011-2017 Horde LLC
 * @license    http://www.horde.org/licenses/lgpl21 LGPL-2.1
 * @package    Compress
 * @subpackage UnitTests
 * @coversNothing
 */
class ZipTest extends Horde_Test_Case
{
    protected $testdata;

    public function setup(): void
    {
        $this->testdata = str_repeat("0123456789ABCDE", 1000);
    }

    public function testZipCreateString()
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

    /**
     * @depends testZipCreateString
     */
    public function testZipUnzipString($zip_data)
    {
        $this->_testZipUnzip($zip_data);
    }

    public function testZipCreateStream()
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

    /**
     * @depends testZipCreateStream
     */
    public function testZipUnzipStream($zip_data)
    {
        $this->_testZipUnzip($zip_data);
    }

    protected function _testZipUnzip($zip_data)
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

    public function testDatesBefore1980()
    {
        $compress = Horde_Compress::factory('Zip');

        $zip_data = $compress->compress([[
            'data' => $this->testdata,
            'name' => 'test.txt',
            'time' => mktime(0, 0, 0, 12, 31, 1987),
        ]]);

        $this->assertNotEmpty($zip_data);
    }

    public function testZipDirectory()
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
        usort($list, function ($a, $b) {
            return strcmp($a['name'], $b['name']);
        });
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
