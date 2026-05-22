<?php

declare(strict_types=1);

/**
 * Copyright 2002-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @see       https://tools.ietf.org/html/rfc1952
 * @category  Horde
 * @copyright 2002-2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Compress
 */

namespace Horde\Compress\Driver;

use Horde\Compress\Base;
use Horde\Compress\Exception;
use Horde\Compress\Translation;

/**
 * Gzip decompression driver.
 *
 * @category  Horde
 * @copyright 2002-2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Compress
 */
class Gzip extends Base
{
    private const FLAG_FTEXT    = 0x01;
    private const FLAG_FHCRC    = 0x02;
    private const FLAG_FEXTRA   = 0x04;
    private const FLAG_FNAME    = 0x08;
    private const FLAG_FCOMMENT = 0x10;

    public function canDecompress(): bool
    {
        return true;
    }

    public function decompress(string $data, array $params = []): array
    {
        if (!function_exists('gzinflate')) {
            throw new Exception(Translation::t("This server can't uncompress gzip files."));
        }

        $position = 0;
        $info = @unpack('CCM/CFLG/VTime/CXFL/COS', substr($data, $position + 2));
        if (!$info) {
            throw new Exception(Translation::t("Unable to decompress data."));
        }
        $position += 10;

        if ($info['FLG'] & self::FLAG_FEXTRA) {
            $xlen = unpack('vLength', substr($data, $position, 2));
            $position += $xlen['Length'] + 2;
        }

        if ($info['FLG'] & self::FLAG_FNAME) {
            $filenamePos = strpos($data, "\x0", $position);
            $position = $filenamePos + 1;
        }

        if ($info['FLG'] & self::FLAG_FCOMMENT) {
            $commentPos = strpos($data, "\x0", $position);
            $position = $commentPos + 1;
        }

        if ($info['FLG'] & self::FLAG_FHCRC) {
            $position += 2;
        }

        $result = @gzinflate(substr($data, $position, strlen($data) - $position));
        if ($result === false || $result === '') {
            throw new Exception(Translation::t("Unable to decompress data."));
        }

        return [
            ['data' => $result, 'name' => '', 'size' => strlen($result)],
        ];
    }
}
