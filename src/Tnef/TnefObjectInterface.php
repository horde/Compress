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
 * Interface for objects that can be encapsulated within a TNEF file.
 *
 * @category  Horde
 * @copyright 2002-2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Compress
 */
interface TnefObjectInterface
{
    /**
     * Set a TNEF attribute on this object.
     *
     * @param int   $attribute The attribute descriptor.
     * @param mixed $value     The value from the TNEF stream.
     * @param int   $size      The byte length of the data.
     */
    public function setTnefAttribute(int $attribute, mixed $value, int $size): void;

    /**
     * Set a MAPI attribute on this object.
     *
     * @param int   $type  The attribute type descriptor.
     * @param int   $name  The attribute name descriptor.
     * @param mixed $value The attribute value.
     */
    public function setMapiAttribute(int $type, int $name, mixed $value): void;

    /**
     * Output the data for this object as an array.
     *
     * @return array{type: string, subtype: string, name: string, stream: string}
     */
    public function toArray(): array;
}
