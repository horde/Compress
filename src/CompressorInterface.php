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

/**
 * Interface for compression/archive drivers.
 *
 * @category  Horde
 * @copyright 2013-2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Compress
 */
interface CompressorInterface
{
    /**
     * Whether this driver supports compressing data.
     */
    public function canCompress(): bool;

    /**
     * Whether this driver supports decompressing data.
     */
    public function canDecompress(): bool;

    /**
     * Compresses raw data.
     *
     * @param string $data   The data to compress.
     * @param array  $params Driver-specific parameters.
     *
     * @return string The compressed data.
     * @throws Exception
     */
    public function compress(string $data, array $params = []): string;

    /**
     * Compresses an array of file entries.
     *
     * Each entry is an associative array with keys:
     *  - data: (string) File contents.
     *  - name: (string) File path/name within the archive.
     *  - time: (int) Modification timestamp.
     *
     * @param array<array{data: string, name: string, time: int}> $files
     * @param array $params Driver-specific parameters.
     *
     * @return string The compressed archive data.
     * @throws Exception
     */
    public function compressFiles(array $files, array $params = []): string;

    /**
     * Recursively compresses a directory.
     *
     * @param string $directory The directory path.
     * @param array  $params    Driver-specific parameters.
     *
     * @return string The compressed archive data.
     * @throws Exception
     */
    public function compressDirectory(string $directory, array $params = []): string;

    /**
     * Decompresses data.
     *
     * @param string $data   The compressed data.
     * @param array  $params Driver-specific parameters.
     *
     * @return array The decompressed data as an array of file entries.
     * @throws Exception
     */
    public function decompress(string $data, array $params = []): array;
}
