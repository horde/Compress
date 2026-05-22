<?php

declare(strict_types=1);

namespace Horde\Compress\Test\Tnef;

use Horde\Compress\Exception;
use Horde\Compress\Tnef\Date;
use Horde_Date;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Date::class)]
class DateTest extends TestCase
{
    public function testParsesValidDate(): void
    {
        // 6 x uint16 LE: year=2024, month=3, day=15, hour=10, minute=30, second=45
        $data = pack('v6', 2024, 3, 15, 10, 30, 45);

        $date = new Date($data);
        $this->assertInstanceOf(Horde_Date::class, $date->date);
        $this->assertEquals(2024, $date->date->year);
        $this->assertEquals(3, $date->date->month);
        $this->assertEquals(15, $date->date->mday);
        $this->assertEquals(10, $date->date->hour);
        $this->assertEquals(30, $date->date->min);
        $this->assertEquals(45, $date->date->sec);
    }

    public function testParsesMinimalDate(): void
    {
        $data = pack('v6', 2000, 1, 1, 0, 0, 0);

        $date = new Date($data);
        $this->assertInstanceOf(Horde_Date::class, $date->date);
        $this->assertEquals(2000, $date->date->year);
    }

    public function testExtraDataIsIgnored(): void
    {
        // 12 bytes for the date + extra bytes
        $data = pack('v6', 2023, 12, 25, 8, 0, 0) . "\xFF\xFF\xFF\xFF";

        $date = new Date($data);
        $this->assertEquals(2023, $date->date->year);
        $this->assertEquals(12, $date->date->month);
        $this->assertEquals(25, $date->date->mday);
    }
}
