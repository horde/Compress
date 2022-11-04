<?php
/**
 * Copyright 2017 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL-2.1). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @author     Jan Schneider <jan@horde.org>
 * @category   Horde
 * @license    http://www.horde.org/licenses/lgpl21 LGPL-2.1
 * @package    Compress
 * @subpackage UnitTests
 */
namespace Horde\Compress;
use Horde_Test_Case;
use \Horde_Compress;

/**
 * Tests the TAR compressor.
 *
 * @author     Jan Schneider <jan@horde.org>
 * @category   Horde
 * @copyright  2017 Horde LLC
 * @license    http://www.horde.org/licenses/lgpl21 LGPL-2.1
 * @package    Compress
 * @subpackage UnitTests
 */
class TarTest extends Horde_Test_Case
{
    protected $testdata;

    public function setup(): void
    {
        $this->testdata = str_repeat("0123456789ABCDE", 1000);
    }

    public function testTarCreateString()
    {
        $compress = Horde_Compress::factory('Tar');

        $tar_data = $compress->compress(array(array(
            'data' => $this->testdata,
            'name' => 'test.txt',
            'time' => 1000000000
        )));

        $this->assertNotEmpty($tar_data);

        return $tar_data;
    }

    /**
     * @depends testTarCreateString
     */
    public function testTarUntarString($tar_data)
    {
        $this->_testTarUntar($tar_data);
    }

    public function testTarCreateStream()
    {
        $compress = Horde_Compress::factory('Tar');

        $fd = fopen('php://temp', 'r+');
        fwrite($fd, $this->testdata);

        $tar_data = $compress->compress(array(array(
            'data' => $fd,
            'name' => 'test.txt',
            'time' => 1000000000
        )), array(
            'stream' => true
        ));

        $this->assertNotEmpty($tar_data);
        $this->assertIsResource($tar_data);

        return stream_get_contents($tar_data);
    }

    /**
     * @depends testTarCreateStream
     */
    public function testTarUntarStream($tar_data)
    {
        $this->_testTarUntar($tar_data);
    }

    protected function _testTarUntar($tar_data)
    {
        $compress = Horde_Compress::factory('Tar');
        $list = $compress->decompress($tar_data);
        $this->assertEquals(
            array(array(
                'attr' => '----------',
                'date' => 1000000000,
                'name' => 'test.txt',
                'size' => 15000,
                'type' => 'File',
                'data' => $this->testdata
            )),
            $list
        );
    }

    public function testTarDirectory()
    {
        $compress = Horde_Compress::factory('Tar');

        $tar_data = $compress->compressDirectory(
            __DIR__ . '/fixtures/directory'
        );

        $this->assertNotEmpty($tar_data);

        $list = $compress->decompress($tar_data);
        usort($list, function ($a, $b) {
            return strcmp($a['name'], $b['name']);
        });
        $this->assertCount(3, $list);

        $fixtures = array(
            'one.txt' => array(4, "One\n"),
            'sub/three.txt' => array(6, "Three\n"),
            'two.bin' => array(2, "\x02\x0a")
        );
        foreach ($fixtures as $key => $testValues) {
            $found = false;
            foreach ($list as $file) {
                if ($file['name'] == $key) {
                    $found = true;
                    $this->assertEquals($testValues[0], $file['size']);
                    $this->assertEquals($testValues[1], $file['data']);
                }
            }
            if (!$found) {
                $this->fail($key . ' not found.');
            }
        }
    }
}
