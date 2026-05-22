<?php

declare(strict_types=1);

/**
 * Copyright 2011-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category  Horde
 * @copyright 2011-2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Compress
 */

namespace Horde\Compress;

use FilesystemIterator;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Abstract base class for compress drivers.
 *
 * @category  Horde
 * @copyright 2011-2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Compress
 */
abstract class Base implements CompressorInterface
{
    protected LoggerInterface $logger;

    public function __construct(?LoggerInterface $logger = null)
    {
        $this->logger = $logger ?? new NullLogger();
    }

    public function canCompress(): bool
    {
        return false;
    }

    public function canDecompress(): bool
    {
        return false;
    }

    public function compress(string $data, array $params = []): string
    {
        throw new Exception(Translation::t("This driver does not support compression."));
    }

    public function compressFiles(array $files, array $params = []): string
    {
        throw new Exception(Translation::t("This driver does not support compression."));
    }

    public function compressDirectory(string $directory, array $params = []): string
    {
        if (!$this->canCompress()) {
            throw new Exception(Translation::t("This driver does not support compression."));
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $directory,
                FilesystemIterator::CURRENT_AS_FILEINFO
                | FilesystemIterator::SKIP_DOTS
            )
        );

        $regexp = '/^' . preg_quote($directory . '/', '/') . '/';
        $files = [];

        foreach ($iterator as $file) {
            $content = '';
            foreach ($file->openFile() as $line) {
                $content .= $line;
            }
            $files[] = [
                'name' => preg_replace($regexp, '', $file->getPathName()),
                'data' => $content,
                'time' => $file->getMTime(),
            ];
        }

        return $this->compressFiles($files, $params);
    }

    public function decompress(string $data, array $params = []): array
    {
        throw new Exception(Translation::t("This driver does not support decompression."));
    }
}
