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

use Horde\Compress\Exception;
use Horde_Date;
use Horde_Date_Exception;

/**
 * Parses a date encapsulated in TNEF data.
 *
 * @category  Horde
 * @copyright 2002-2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Compress
 */
class Date extends TnefObject
{
    public ?Horde_Date $date = null;

    public function __construct(string $data)
    {
        $year = $this->readInt($data, 16);
        $month = $this->readInt($data, 16);
        $day = $this->readInt($data, 16);
        $hour = $this->readInt($data, 16);
        $minute = $this->readInt($data, 16);
        $second = $this->readInt($data, 16);

        try {
            $this->date = new Horde_Date(
                sprintf(
                    '%04d-%02d-%02d %02d:%02d:%02d',
                    $year,
                    $month,
                    $day,
                    $hour,
                    $minute,
                    $second
                )
            );
        } catch (Horde_Date_Exception $e) {
            throw new Exception($e);
        }
    }
}
