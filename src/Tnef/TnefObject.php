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

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Abstract base class for objects encapsulated in a TNEF file.
 *
 * @category  Horde
 * @copyright 2002-2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Compress
 */
abstract class TnefObject implements TnefObjectInterface
{
    use BufferReaderTrait;

    protected LoggerInterface $logger;
    protected ?string $data;
    protected array $options;

    public function __construct(?LoggerInterface $logger = null, ?string $data = null, array $options = [])
    {
        $this->logger = $logger ?? new NullLogger();
        $this->data = $data;
        $this->options = $options;
    }

    public function setTnefAttribute(int $attribute, mixed $value, int $size): void {}

    public function setMapiAttribute(int $type, int $name, mixed $value): void {}

    public function toArray(): array
    {
        return ['type' => '', 'subtype' => '', 'name' => '', 'stream' => ''];
    }
}
