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
use Horde_Compress_Exception;
use Horde_Compress_Rar;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Tests the legacy Horde_Compress_Rar driver.
 */
#[CoversClass(Horde_Compress_Rar::class)]
class RarLegacyTest extends TestCase
{
    public function testInvalidRarData(): void
    {
        $this->expectException(Horde_Compress_Exception::class);

        $compress = Horde_Compress::factory('Rar');
        $compress->decompress('1234');
    }

    public function testInvalidRarDataWithBlockStart(): void
    {
        $this->expectException(Horde_Compress_Exception::class);

        $compress = Horde_Compress::factory('Rar');
        $compress->decompress(Horde_Compress_Rar::BLOCK_START . '1234');
    }

    public function testInvalidRarDataWithBlockStartAndExtra(): void
    {
        $compress = Horde_Compress::factory('Rar');
        // BLOCK_START + 7 bytes of garbage makes a 14-byte file.
        // The parser enters the loop but reads garbage headers.
        // It either throws or returns empty depending on the garbage values.
        $result = $compress->decompress(Horde_Compress_Rar::BLOCK_START . '1234567');
        $this->assertIsArray($result);
    }
}
