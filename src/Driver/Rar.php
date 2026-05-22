<?php

declare(strict_types=1);

/**
 * Copyright 2008-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category  Horde
 * @copyright 2008-2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Compress
 */

namespace Horde\Compress\Driver;

use Horde\Compress\Base;
use Horde\Compress\Exception;
use Horde\Compress\Translation;

/**
 * RAR archive reader (decompression/listing only).
 *
 * @category  Horde
 * @copyright 2008-2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Compress
 */
class Rar extends Base
{
    public const BLOCK_START = "\x52\x61\x72\x21\x1a\x07\x00";

    private const METHODS = [
        0x30 => 'Store',
        0x31 => 'Fastest',
        0x32 => 'Fast',
        0x33 => 'Normal',
        0x34 => 'Good',
        0x35 => 'Best',
    ];

    public function canDecompress(): bool
    {
        return true;
    }

    public function decompress(string $data, array $params = []): array
    {
        $blockStart = strpos($data, self::BLOCK_START);
        if ($blockStart === false) {
            throw new Exception(Translation::t("Invalid RAR data."));
        }

        $dataLen = strlen($data);
        $position = $blockStart + 7;
        $entries = [];

        while ($position < $dataLen) {
            if ($position + 7 > $dataLen) {
                throw new Exception(Translation::t("Invalid RAR data."));
            }

            $headType = ord(substr($data, $position + 2, 1));
            $headFlags = unpack('vFlags', substr($data, $position + 3, 2));
            $headFlags = $headFlags['Flags'];
            $headSize = unpack('vSize', substr($data, $position + 5, 2));
            $headSize = $headSize['Size'];

            $position += 7;
            $headSize -= 7;

            switch ($headType) {
                case 0x73:
                    $position += $headSize;
                    break;

                case 0x74:
                    $info = unpack(
                        'VPacked/VUnpacked/COS/VCRC32/VTime/CVersion/CMethod/vLength/vAttrib',
                        substr($data, $position)
                    );
                    $year = (($info['Time'] >> 25) & 0x7f) + 80;
                    $name = substr($data, $position + 25, $info['Length']);
                    $unicode = strpos($name, "\0");
                    if ($unicode !== false) {
                        $name = substr($name, 0, $unicode);
                    }

                    $entries[] = [
                        'name' => $name,
                        'size' => $info['Unpacked'],
                        'csize' => $info['Packed'],
                        'date' => mktime(
                            (($info['Time'] >> 11) & 0x1f),
                            (($info['Time'] >> 5) & 0x3f),
                            (($info['Time'] << 1) & 0x3e),
                            (($info['Time'] >> 21) & 0x07),
                            (($info['Time'] >> 16) & 0x1f),
                            $year < 1900 ? $year + 1900 : $year
                        ),
                        'method' => self::METHODS[$info['Method']] ?? 'Unknown',
                        'attr' => (($info['Attrib'] & 0x10) ? 'D' : '-')
                                . (($info['Attrib'] & 0x20) ? 'A' : '-')
                                . (($info['Attrib'] & 0x03) ? 'S' : '-')
                                . (($info['Attrib'] & 0x02) ? 'H' : '-')
                                . (($info['Attrib'] & 0x01) ? 'R' : '-'),
                    ];

                    $position += $headSize + $info['Packed'];
                    break;

                default:
                    if ($headSize === -7) {
                        throw new Exception(Translation::t("Invalid RAR data."));
                    }
                    $position += $headSize;
                    break;
            }
        }

        return $entries;
    }
}
