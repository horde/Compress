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

namespace Horde\Compress\Tnef;

/**
 * Shared binary buffer reading methods for TNEF parsing.
 *
 * @category  Horde
 * @copyright 2002-2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Compress
 */
trait BufferReaderTrait
{
    /**
     * Pop specified number of bytes from the buffer.
     */
    protected function readBytes(string &$data, int $bytes): ?string
    {
        if (strlen($data) < $bytes) {
            return null;
        }

        $value = substr($data, 0, $bytes);
        $data = substr_replace($data, '', 0, $bytes);

        return $value;
    }

    /**
     * Pop specified number of bits from the buffer and return as integer.
     */
    protected function readInt(string &$data, int $bits): ?int
    {
        $bytes = $bits / 8;

        if (strlen($data) < $bytes) {
            return null;
        }

        $value = ord($data[0]);
        if ($bytes >= 2) {
            $value += (ord($data[1]) << 8);
        }
        if ($bytes >= 4) {
            $value += (ord($data[2]) << 16) + (ord($data[3]) << 24);
        }
        $data = substr_replace($data, '', 0, (int) $bytes);

        return $value;
    }
}
