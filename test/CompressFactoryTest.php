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

use Horde\Compress\CompressFactory;
use Horde\Compress\CompressorInterface;
use Horde\Compress\Driver\Dbx;
use Horde\Compress\Driver\Gzip;
use Horde\Compress\Driver\Rar;
use Horde\Compress\Driver\Tar;
use Horde\Compress\Driver\Zip;
use Horde\Compress\Exception;
use Horde\Compress\Tnef\TnefDecoder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * Tests the CompressFactory.
 */
#[CoversClass(CompressFactory::class)]
class CompressFactoryTest extends TestCase
{
    private CompressFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new CompressFactory();
    }

    /**
     * @return array<string, array{0: string, 1: class-string}>
     */
    public static function driverProvider(): array
    {
        return [
            'zip'  => ['zip', Zip::class],
            'tar'  => ['tar', Tar::class],
            'gzip' => ['gzip', Gzip::class],
            'rar'  => ['rar', Rar::class],
            'dbx'  => ['dbx', Dbx::class],
            'tnef' => ['tnef', TnefDecoder::class],
            'ZIP uppercase'  => ['ZIP', Zip::class],
            'Tar mixed case' => ['Tar', Tar::class],
        ];
    }

    #[DataProvider('driverProvider')]
    public function testCreateByName(string $name, string $expectedClass): void
    {
        $driver = $this->factory->create($name);
        $this->assertInstanceOf($expectedClass, $driver);
        $this->assertInstanceOf(CompressorInterface::class, $driver);
    }

    public function testCreateByFullyQualifiedClassName(): void
    {
        $driver = $this->factory->create(Zip::class);
        $this->assertInstanceOf(Zip::class, $driver);
    }

    public function testCreateWithLogger(): void
    {
        $logger = new NullLogger();
        $driver = $this->factory->create('zip', $logger);
        $this->assertInstanceOf(Zip::class, $driver);
    }

    public function testCreateThrowsOnUnknownDriver(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Compress driver not found');
        $this->factory->create('nonexistent');
    }
}
