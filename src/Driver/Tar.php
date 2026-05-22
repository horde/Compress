<?php

declare(strict_types=1);

/**
 * Copyright 2002-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category  Horde
 * @copyright 2002-2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Compress
 */

namespace Horde\Compress\Driver;

use Horde\Compress\Base;
use Horde\Compress\Exception;
use Horde\Compress\Translation;
use SplFileInfo;

/**
 * TAR archive compression and decompression driver.
 *
 * @category  Horde
 * @copyright 2002-2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Compress
 */
class Tar extends Base
{
    private const TYPES = [
        0x0  => 'Unix file',
        0x30 => 'File',
        0x31 => 'Link',
        0x32 => 'Symbolic link',
        0x33 => 'Character special file',
        0x34 => 'Block special file',
        0x35 => 'Directory',
        0x36 => 'FIFO special file',
        0x37 => 'Contiguous file',
    ];

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
        $this->tmp = fopen('php://temp', 'r+');

        foreach ($files as $file) {
            $name = str_replace('\\', '/', $file['name']);
            $prefix = '';
            if (strlen($name) > 99) {
                $prefix = $name;
                $name = '';
                if (strlen($prefix) > 154) {
                    $name = substr($prefix, 154);
                    $prefix = substr($prefix, 0, 154);
                }
            }

            $ftime = $file['time'] ?? null;

            if (is_resource($file['data'])) {
                fseek($file['data'], 0, SEEK_END);
                $length = ftell($file['data']);
            } else {
                $length = strlen($file['data']);
            }

            $spl = $file['spl'] ?? null;
            if ($spl instanceof SplFileInfo) {
                $isLink = $spl->isLink();
                $link = $isLink ? $this->getRelativeLink($spl) : '';
                $owner = '';
                $group = '';
                if (function_exists('posix_getpwuid')) {
                    $posix = posix_getpwuid($spl->getOwner());
                    $owner = !empty($posix['name']) ? $posix['name'] : '';
                }
                if (function_exists('posix_getgrgid')) {
                    $posix = posix_getgrgid($spl->getGroup());
                    $group = !empty($posix['name']) ? $posix['name'] : '';
                }
            } else {
                $isLink = false;
                $link = '';
                $owner = '';
                $group = '';
            }

            $header
                = pack('a99', $name) . "\0"
                . $this->formatNumber($file, 'getPerms')
                . $this->formatNumber($file, 'getOwner')
                . $this->formatNumber($file, 'getGroup')
                . sprintf("%011o\0", $isLink ? 0 : $length)
                . sprintf("%011o\0", $ftime)
                . '        '
                . ($isLink ? '1' : '0')
                . pack('a99', $link) . "\0"
                . "ustar\0" . "00"
                . pack('a31', $owner) . "\0"
                . pack('a31', $group) . "\0"
                . pack('a16', '')
                . pack('a154', $prefix) . "\0";
            $header = pack('a512', $header);
            $checksum = array_sum(array_map('ord', str_split($header)));
            $header = substr($header, 0, 148)
                . sprintf("%06o\0 ", $checksum)
                . substr($header, 156);

            fwrite($this->tmp, $header);

            if (is_resource($file['data'])) {
                rewind($file['data']);
                stream_copy_to_stream($file['data'], $this->tmp);
            } else {
                fwrite($this->tmp, $file['data']);
            }

            fwrite($this->tmp, str_repeat("\0", 512 - ($length % 512)));
        }

        fwrite($this->tmp, str_repeat("\0", 1024));
        rewind($this->tmp);

        if (!empty($params['stream'])) {
            $out = $this->tmp;
            $this->tmp = null;
            return stream_get_contents($out);
        }

        $out = stream_get_contents($this->tmp);
        fclose($this->tmp);
        $this->tmp = null;

        return $out;
    }

    public function decompress(string $data, array $params = []): array
    {
        $dataLen = strlen($data);
        $position = 0;
        $entries = [];

        while ($position < $dataLen) {
            $info = @unpack(
                'Z100filename/Z8mode/Z8uid/Z8gid/Z12size/Z12mtime/Z8checksum/Ctypeflag/Z100link/Z6magic/Z2version/Z32uname/Z32gname/Z8devmajor/Z8devminor',
                substr($data, $position)
            );
            if (!$info) {
                throw new Exception(Translation::t("Unable to decompress data."));
            }

            $position += 512;
            $size = octdec($info['size']);
            $contents = substr($data, $position, $size);
            $position += (int) (ceil($size / 512) * 512);

            if ($info['filename']) {
                $file = [
                    'attr' => null,
                    'data' => null,
                    'date' => octdec($info['mtime']),
                    'name' => trim($info['filename']),
                    'size' => $size,
                    'type' => self::TYPES[$info['typeflag']] ?? null,
                ];

                if ($info['typeflag'] == 0
                    || $info['typeflag'] == 0x30
                    || $info['typeflag'] == 0x35
                ) {
                    $file['data'] = $contents;
                    $mode = hexdec(substr($info['mode'], 4, 3));
                    $file['attr']
                        = (($info['typeflag'] == 0x35) ? 'd' : '-')
                        . (($mode & 0x400) ? 'r' : '-')
                        . (($mode & 0x200) ? 'w' : '-')
                        . (($mode & 0x100) ? 'x' : '-')
                        . (($mode & 0x040) ? 'r' : '-')
                        . (($mode & 0x020) ? 'w' : '-')
                        . (($mode & 0x010) ? 'x' : '-')
                        . (($mode & 0x004) ? 'r' : '-')
                        . (($mode & 0x002) ? 'w' : '-')
                        . (($mode & 0x001) ? 'x' : '-');
                }

                $entries[] = $file;
            }
        }

        return $entries;
    }

    private function getRelativeLink(SplFileInfo $spl): string
    {
        $ds = DIRECTORY_SEPARATOR;
        $from = explode($ds, rtrim($spl->getPathname(), $ds));
        $to = explode($ds, rtrim($spl->getRealPath(), $ds));
        while (count($from) && count($to) && ($from[0] === $to[0])) {
            array_shift($from);
            array_shift($to);
        }
        return str_repeat('..' . $ds, count($from)) . implode($ds, $to);
    }

    private function formatNumber(array $file, string $method): string
    {
        if (isset($file['spl']) && $file['spl'] instanceof SplFileInfo) {
            return sprintf("%07o\0", $file['spl']->$method());
        }
        return pack('a8', '');
    }
}
