<?php

declare(strict_types=1);

/**
 * Copyright 2003-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category  Horde
 * @copyright 2003-2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Compress
 */

namespace Horde\Compress;

use Psr\Log\LoggerInterface;

/**
 * Factory for creating compress driver instances.
 *
 * @category  Horde
 * @copyright 2003-2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Compress
 */
class CompressFactory
{
    /** @var array<string, class-string<CompressorInterface>> */
    private const DRIVERS = [
        'zip'  => Driver\Zip::class,
        'tar'  => Driver\Tar::class,
        'gzip' => Driver\Gzip::class,
        'rar'  => Driver\Rar::class,
        'dbx'  => Driver\Dbx::class,
        'tnef' => Tnef\TnefDecoder::class,
    ];

    /**
     * Creates a compress driver instance.
     *
     * @param string $driver The driver name (zip, tar, gzip, rar, dbx, tnef)
     *                       or a fully-qualified class name.
     * @param LoggerInterface|null $logger PSR-3 logger instance.
     *
     * @return CompressorInterface
     * @throws Exception If the driver class cannot be found.
     */
    public function create(string $driver, ?LoggerInterface $logger = null): CompressorInterface
    {
        $key = strtolower($driver);

        if (isset(self::DRIVERS[$key])) {
            $class = self::DRIVERS[$key];
            return new $class($logger);
        }

        if (class_exists($driver)) {
            return new $driver($logger);
        }

        throw new Exception('Compress driver not found: ' . $driver);
    }
}
