<?php
/**
 * Copyright 2011-2017 Horde LLC (http://www.horde.org/)
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
use \Horde_Compress;

/**
 * Tests the RAR compressor.
 *
 * @author     Michael Slusarz <slusarz@horde.org>
 * @category   Horde
 * @copyright  2011-2017 Horde LLC
 * @license    http://www.horde.org/licenses/lgpl21 LGPL-2.1
 * @package    Compress
 * @subpackage UnitTests
 */
class RarTest extends Horde_Test_Case
{
    public function testInvalidRarData()
    {
        $this->expectException('Horde_Compress_Exception');

        $compress = Horde_Compress::factory('Rar');
        $compress->decompress('1234');
        
        $compress->decompress(Horde_Compress_Rar::BLOCK_START . '1234');

        $compress->decompress(Horde_Compress_Rar::BLOCK_START . '1234567');
    }

}
