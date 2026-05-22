<?php

declare(strict_types=1);

/**
 * Copyright 2000-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * Official ZIP file format: https://support.pkware.com/display/PKZIP/APPNOTE
 *
 * @category  Horde
 * @copyright 2000-2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Compress
 */

namespace Horde\Compress\Driver;

use Horde\Compress\Base;
use Horde\Compress\Exception;
use Horde\Compress\Translation;
use stdClass;

/**
 * ZIP archive compression and decompression driver.
 *
 * @category  Horde
 * @copyright 2000-2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Compress
 */
class Zip extends Base
{
    /** Decompress action: return file listing. */
    public const ZIP_LIST = 1;

    /** Decompress action: return file data for a specific entry. */
    public const ZIP_DATA = 2;

    private const CTRL_DIR_HEADER = "\x50\x4b\x01\x02";
    private const CTRL_DIR_END = "\x50\x4b\x05\x06\x00\x00\x00\x00";
    private const FILE_HEADER = "\x50\x4b\x03\x04";

    private const METHODS = [
        0x0 => 'None',
        0x1 => 'Shrunk',
        0x2 => 'Super Fast',
        0x3 => 'Fast',
        0x4 => 'Normal',
        0x5 => 'Maximum',
        0x6 => 'Imploded',
        0x8 => 'Deflated',
    ];

    /** @var list<string> */
    private array $ctrlDir = [];

    /** @var resource|null */
    private $tmp;

    public function canCompress(): bool
    {
        return true;
    }

    public function canDecompress(): bool
    {
        return true;
    }

    public function compress(string $data, array $params = []): string
    {
        return $this->compressFiles([['data' => $data, 'name' => 'data', 'time' => time()]], $params);
    }

    public function compressFiles(array $files, array $params = []): string
    {
        if (!function_exists('gzdeflate')) {
            throw new Exception(Translation::t("This server can't compress zip files."));
        }

        $this->ctrlDir = [];
        $this->tmp = fopen('php://temp', 'r+');

        foreach ($files as $file) {
            $this->addToZipFile($file);
        }

        $dir = implode('', $this->ctrlDir);

        fseek($this->tmp, 0, SEEK_END);
        $offset = ftell($this->tmp);

        fwrite(
            $this->tmp,
            $dir . self::CTRL_DIR_END
            . pack('v', count($this->ctrlDir))
            . pack('v', count($this->ctrlDir))
            . pack('V', strlen($dir))
            . pack('V', $offset)
            . "\x00\x00"
        );

        rewind($this->tmp);

        if (!empty($params['stream'])) {
            $out = stream_get_contents($this->tmp);
            fclose($this->tmp);
            $this->tmp = null;
            return $out;
        }

        $out = stream_get_contents($this->tmp);
        fclose($this->tmp);
        $this->tmp = null;

        return $out;
    }

    /**
     * Decompresses ZIP data.
     *
     * @param string $data   The ZIP archive data.
     * @param array  $params Parameters:
     *   - action: (int) ZIP_LIST or ZIP_DATA.
     *   - info: (array) Required for ZIP_DATA — the file list from ZIP_LIST.
     *   - key: (int) Required for ZIP_DATA — index into info array.
     */
    public function decompress(string $data, array $params = []): array
    {
        if (!isset($params['action'])) {
            return $this->getZipInfo($data);
        }

        switch ($params['action']) {
            case self::ZIP_LIST:
                return $this->getZipInfo($data);

            case self::ZIP_DATA:
                return [
                    'data' => $this->getZipData($data, $params['info'], $params['key']),
                ];
        }

        return [];
    }

    /**
     * Checks if data is a valid ZIP file.
     */
    public function checkZipData(string $data): bool
    {
        return strpos($data, self::FILE_HEADER) !== false;
    }

    private function getZipInfo(string $data): array
    {
        $entries = [];
        $fhStart = strpos($data, self::CTRL_DIR_HEADER);

        do {
            if (strlen($data) < $fhStart + 31) {
                throw new Exception(Translation::t("Invalid ZIP data"));
            }
            $info = unpack(
                'vMethod/VTime/VCRC32/VCompressed/VUncompressed/vLength',
                substr($data, $fhStart + 10, 20)
            );

            if (!isset(self::METHODS[$info['Method']])) {
                throw new Exception(Translation::t("Invalid ZIP data"));
            }

            $name = substr($data, $fhStart + 46, $info['Length']);

            $entries[$name] = [
                'attr' => null,
                'crc' => sprintf("%08s", dechex($info['CRC32'])),
                'csize' => $info['Compressed'],
                'date' => null,
                '_dataStart' => null,
                'name' => $name,
                'method' => self::METHODS[$info['Method']],
                '_method' => $info['Method'],
                'size' => $info['Uncompressed'],
                'type' => null,
            ];

            $entries[$name]['date'] = mktime(
                (($info['Time'] >> 11) & 0x1f),
                (($info['Time'] >> 5) & 0x3f),
                (($info['Time'] << 1) & 0x3e),
                (($info['Time'] >> 21) & 0x0f),
                (($info['Time'] >> 16) & 0x1f),
                ((($info['Time'] >> 25) & 0x7f) + 1980)
            );

            if (strlen($data) < $fhStart + 43) {
                throw new Exception(Translation::t("Invalid ZIP data"));
            }
            $info = unpack(
                'vInternal/VExternal',
                substr($data, $fhStart + 36, 6)
            );

            $entries[$name]['type'] = ($info['Internal'] & 0x01) ? 'text' : 'binary';
            $entries[$name]['attr']
                = (($info['External'] & 0x10) ? 'D' : '-')
                . (($info['External'] & 0x20) ? 'A' : '-')
                . (($info['External'] & 0x03) ? 'S' : '-')
                . (($info['External'] & 0x02) ? 'H' : '-')
                . (($info['External'] & 0x01) ? 'R' : '-');
        } while (($fhStart = strpos($data, self::CTRL_DIR_HEADER, $fhStart + 46)) !== false);

        $fhStart = strpos($data, self::FILE_HEADER);
        $dataLen = strlen($data);

        do {
            if ($dataLen < $fhStart + 34) {
                throw new Exception(Translation::t("Invalid ZIP data"));
            }
            $info = unpack(
                'vMethod/VTime/VCRC32/VCompressed/VUncompressed/vLength/vExtraLength',
                substr($data, $fhStart + 8, 25)
            );
            $name = substr($data, $fhStart + 30, $info['Length']);
            if (isset($entries[$name])) {
                $entries[$name]['_dataStart'] = $fhStart + 30
                    + $info['Length'] + $info['ExtraLength'];
            }
        } while (
            $dataLen > $fhStart + 30 + $info['Length']
            && ($fhStart = strpos($data, self::FILE_HEADER, $fhStart + 30 + $info['Length'])) !== false
        );

        return array_values($entries);
    }

    private function getZipData(string $data, array $info, int $key): string
    {
        if (($info[$key]['_method'] == 0x8) && function_exists('gzinflate')) {
            $result = @gzinflate(
                substr($data, $info[$key]['_dataStart'], $info[$key]['csize'])
            );
            return $result !== false ? $result : '';
        }

        if ($info[$key]['_method'] == 0x0) {
            return substr($data, $info[$key]['_dataStart'], $info[$key]['csize']);
        }

        return '';
    }

    private function unix2DosTime(?int $unixtime = null): int
    {
        $timearray = ($unixtime === null) ? getdate() : getdate($unixtime);

        if ($timearray['year'] < 1980) {
            $timearray['year']    = 1980;
            $timearray['mon']     = 1;
            $timearray['mday']    = 1;
            $timearray['hours']   = 0;
            $timearray['minutes'] = 0;
            $timearray['seconds'] = 0;
        }

        return (($timearray['year'] - 1980) << 25)
            | ($timearray['mon'] << 21)
            | ($timearray['mday'] << 16)
            | ($timearray['hours'] << 11)
            | ($timearray['minutes'] << 5)
            | ($timearray['seconds'] >> 1);
    }

    private function addToZipFile(array $file): void
    {
        $name = str_replace('\\', '/', $file['name']);
        $ftime = $file['time'] ?? null;

        $dtime = sprintf('%08s', dechex($this->unix2DosTime($ftime)));
        $hexdtime = chr(hexdec($dtime[6] . $dtime[7]))
            . chr(hexdec($dtime[4] . $dtime[5]))
            . chr(hexdec($dtime[2] . $dtime[3]))
            . chr(hexdec($dtime[0] . $dtime[1]));

        if (is_resource($file['data'])) {
            $zdata = fopen('php://temp', 'r+');

            $params = new stdClass();
            stream_filter_register(
                'horde_compress_filter_crc32',
                'Horde_Stream_Filter_Crc32'
            );
            $filter = stream_filter_prepend(
                $file['data'],
                'horde_compress_filter_crc32',
                STREAM_FILTER_READ,
                $params
            );
            $filter2 = stream_filter_append(
                $zdata,
                'zlib.deflate',
                STREAM_FILTER_WRITE
            );

            rewind($file['data']);
            stream_copy_to_stream($file['data'], $zdata);

            $crc = $params->crc32;
            $uncLen = ftell($file['data']);

            stream_filter_remove($filter2);
            stream_filter_remove($filter);

            fseek($zdata, 0, SEEK_END);
            $cLen = ftell($zdata);
        } else {
            $uncLen = strlen($file['data']);
            $crc = crc32($file['data']);
            $zdata = gzdeflate($file['data']);
            $cLen = strlen($zdata);
        }

        $common
            = "\x14\x00"
            . "\x00\x00"
            . "\x08\x00"
            . $hexdtime
            . pack('V', $crc)
            . pack('V', $cLen)
            . pack('V', $uncLen)
            . pack('v', strlen($name))
            . pack('v', 0);

        fseek($this->tmp, 0, SEEK_END);
        $oldOffset = ftell($this->tmp);

        fwrite($this->tmp, self::FILE_HEADER . $common . $name);

        if (is_resource($zdata)) {
            rewind($zdata);
            stream_copy_to_stream($zdata, $this->tmp);
        } else {
            fwrite($this->tmp, $zdata);
        }

        $this->ctrlDir[]
            = self::CTRL_DIR_HEADER
            . "\x00\x00"
            . $common
            . pack('v', 0)
            . pack('v', 0)
            . pack('v', 0)
            . pack('V', 32)
            . pack('V', $oldOffset)
            . $name;
    }
}
