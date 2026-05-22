<?php

declare(strict_types=1);

/**
 * Copyright 2013-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category  Horde
 * @copyright 2013-2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Compress
 */

namespace Horde\Compress;

use Horde\Translation\Autodetect;

/**
 * Translation wrapper for the Compress package.
 *
 * @category  Horde
 * @copyright 2013-2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Compress
 */
class Translation extends Autodetect
{
    protected static string $pearDirectory = '@data_dir@';
    protected static string $domain = 'Horde_Compress';
    protected static string $directory = '';
}
