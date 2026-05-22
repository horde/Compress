<?php

declare(strict_types=1);

/**
 * Copyright 2013-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL-2.1). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category   Horde
 * @license    http://www.horde.org/licenses/lgpl21 LGPL-2.1
 * @package    Compress
 */

namespace Horde\Compress\Test;

use Horde\Compress\Base;
use Horde\Compress\CompressorInterface;
use Horde\Compress\Exception;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Tests the abstract Base class.
 */
#[CoversClass(Base::class)]
class BaseTest extends TestCase
{
    private Base $base;

    protected function setUp(): void
    {
        $this->base = new class extends Base {};
    }

    public function testImplementsCompressorInterface(): void
    {
        $this->assertInstanceOf(CompressorInterface::class, $this->base);
    }

    public function testCanCompressReturnsFalseByDefault(): void
    {
        $this->assertFalse($this->base->canCompress());
    }

    public function testCanDecompressReturnsFalseByDefault(): void
    {
        $this->assertFalse($this->base->canDecompress());
    }

    public function testCompressThrowsException(): void
    {
        $this->expectException(Exception::class);
        $this->base->compress('data');
    }

    public function testCompressFilesThrowsException(): void
    {
        $this->expectException(Exception::class);
        $this->base->compressFiles([]);
    }

    public function testDecompressThrowsException(): void
    {
        $this->expectException(Exception::class);
        $this->base->decompress('data');
    }

    public function testCompressDirectoryThrowsWhenCannotCompress(): void
    {
        $this->expectException(Exception::class);
        $this->base->compressDirectory('/tmp');
    }

    public function testConstructorWithNullLoggerUsesNullLogger(): void
    {
        $base = new class extends Base {
            public function getLogger(): LoggerInterface
            {
                return $this->logger;
            }
        };

        $this->assertInstanceOf(NullLogger::class, $base->getLogger());
    }

    public function testConstructorWithExplicitLogger(): void
    {
        $logger = new NullLogger();
        $base = new class($logger) extends Base {
            public function getLogger(): LoggerInterface
            {
                return $this->logger;
            }
        };

        $this->assertSame($logger, $base->getLogger());
    }
}
