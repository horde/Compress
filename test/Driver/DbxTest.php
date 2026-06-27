<?php

declare(strict_types=1);

namespace Horde\Compress\Test\Driver;

use Horde\Compress\Driver\Dbx;
use Horde\Compress\Exception;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Dbx::class)]
class DbxTest extends TestCase
{
    private Dbx $dbx;

    protected function setUp(): void
    {
        $this->dbx = new Dbx();
    }

    public function testCanDecompress(): void
    {
        $this->assertTrue($this->dbx->canDecompress());
    }

    public function testCanCompress(): void
    {
        $this->assertFalse($this->dbx->canCompress());
    }

    public function testCompressThrows(): void
    {
        $this->expectException(Exception::class);
        $this->dbx->compress('data');
    }

    public function testDecompressEmptyPosition(): void
    {
        // Build a minimal DBX file where headerInfo['position'] == 0
        // meaning no messages to read
        $data = str_repeat("\x00", 0xC4);
        // At 0xC4: position=0, DataLength=0, HeaderLength=12, FlagCount=0
        $data .= pack('VVvv', 0, 0, 12, 0);

        $result = $this->dbx->decompress($data);
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testDecompressMinimalMessage(): void
    {
        $data = $this->buildMinimalDbx();
        $result = $this->dbx->decompress($data);

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertArrayHasKey('info', $result[0]);
        $this->assertArrayHasKey('content', $result[0]);
        $this->assertStringContainsString('Hello DBX', $result[0]['content']);
    }

    public function testDecompressExtractsSubject(): void
    {
        $data = $this->buildMinimalDbx(subject: 'Test Email Subject');
        $result = $this->dbx->decompress($data);

        $this->assertCount(1, $result);
        $this->assertEquals('Test Email Subject', $result[0]['info']['Subject']);
    }

    public function testDecompressMultipleMessages(): void
    {
        $data = $this->buildDbxWithMultipleMessages();
        $result = $this->dbx->decompress($data);

        $this->assertCount(2, $result);
        $this->assertStringContainsString('Message 1', $result[0]['content']);
        $this->assertStringContainsString('Message 2', $result[1]['content']);
    }

    /**
     * Build a minimal valid DBX binary with one message.
     *
     * DBX structure:
     *   0x30: 4 bytes → pointer to index (when headerInfo.position > 0)
     *   0xC4: 12 bytes → headerInfo {position, DataLength, HeaderLength, FlagCount}
     *   index: 24 bytes header + 12 bytes per item
     *   messageInfo: 12 bytes header + flags + data
     *   messageBody: 528-byte records
     */
    private function buildMinimalDbx(string $subject = 'Test Subject'): string
    {
        // We'll place structures at specific offsets:
        // 0x30: index pointer
        // 0xC4: header info
        // 0x200: index structure
        // 0x300: message info
        // 0x400: message body

        $indexPos = 0x200;
        $msgInfoPos = 0x300;
        $msgBodyPos = 0x400;

        // Start with zeroed buffer (large enough)
        $data = str_repeat("\x00", 0x600);

        // At 0x30: pointer to the index
        $this->writeAt($data, 0x30, pack('V', $indexPos));

        // At 0xC4: header info {position (must be > 0), DataLength, HeaderLength, FlagCount}
        $this->writeAt($data, 0xC4, pack('VVvv', 0xC4, 100, 12, 1));

        // Build message info block at $msgInfoPos
        // Header: position, DataLength, HeaderLength, FlagCount (as shorts)
        // We'll put 2 flags: Subject (0x08) and position (0x04)
        $flagCount = 2;
        $subjectNullTerminated = $subject . "\x00";
        $positionValue = $msgBodyPos;

        // Data buffer: subject string + position int
        $dataBuffer = $subjectNullTerminated . pack('V', $positionValue);
        $subjectOffset = 0;
        $positionOffset = strlen($subjectNullTerminated);

        // Flags buffer: each flag is 4 bytes (mask in low byte, offset in high 3 bytes)
        $flagsBuffer = pack('V', 0x08 | ($subjectOffset << 8))   // Subject at offset 0
                     . pack('V', 0x04 | ($positionOffset << 8));  // position

        $dataLength = strlen($flagsBuffer) + strlen($dataBuffer) + 12;
        $msgInfoHeader = pack('VVvv', $msgInfoPos, $dataLength, 12, $flagCount);

        $this->writeAt($data, $msgInfoPos, $msgInfoHeader . $flagsBuffer . $dataBuffer);

        // Build index at $indexPos
        // Index header: FilePos, Unknown1, PrevIndex, NextIndex, Count, Unknown (24 bytes)
        $itemCount = 1;
        $indexHeader = pack(
            'VVVVVV',
            $indexPos,   // FilePos (must match)
            0,           // Unknown1
            0,           // PrevIndex (none)
            0,           // NextIndex (none)
            $itemCount << 8, // Count (shifted)
            0            // Unknown
        );
        // Index items: 12 bytes each (HeaderPos, ChildIndex, Unknown)
        $indexItem = pack('VVV', $msgInfoPos, 0, 0);

        $this->writeAt($data, $indexPos, $indexHeader . $indexItem);

        // Build message body at $msgBodyPos
        // Record: FilePos(4), Unknown(4), ItemSize(4), NextItem(4), Content(512)
        $content = 'Hello DBX World!';
        $bodyRecord = pack('VVVV', $msgBodyPos, 0, strlen($content), 0)
                    . str_pad($content, 512, "\x00");

        $this->writeAt($data, $msgBodyPos, $bodyRecord);

        // Need at least one indexItemsCount > 0 at 0xC4 for readMessage
        // readMessage reads: $indexItemsCount = unpack('S', substr($data, 0xC4, 4))[1]
        // Our headerInfo at 0xC4 already has position=0xC4 whose low 2 bytes are 0xC4, 0x00
        // which gives indexItemsCount = 0xC4 = 196 > 0, so the loop will execute

        return $data;
    }

    private function buildDbxWithMultipleMessages(): string
    {
        $indexPos = 0x200;
        $msgInfo1Pos = 0x300;
        $msgInfo2Pos = 0x380;
        $msgBody1Pos = 0x500;
        $msgBody2Pos = 0x720;

        $data = str_repeat("\x00", 0x940);

        // 0x30: index pointer
        $this->writeAt($data, 0x30, pack('V', $indexPos));

        // 0xC4: header info
        $this->writeAt($data, 0xC4, pack('VVvv', 0xC4, 100, 12, 1));

        // Message 1 info
        $subject1 = "Subject 1\x00";
        $dataBuffer1 = $subject1 . pack('V', $msgBody1Pos);
        $flagsBuffer1 = pack('V', 0x08 | (0 << 8))
                      . pack('V', 0x04 | (strlen($subject1) << 8));
        $dataLength1 = strlen($flagsBuffer1) + strlen($dataBuffer1) + 12;
        $msgInfo1 = pack('VVvv', $msgInfo1Pos, $dataLength1, 12, 2) . $flagsBuffer1 . $dataBuffer1;
        $this->writeAt($data, $msgInfo1Pos, $msgInfo1);

        // Message 2 info
        $subject2 = "Subject 2\x00";
        $dataBuffer2 = $subject2 . pack('V', $msgBody2Pos);
        $flagsBuffer2 = pack('V', 0x08 | (0 << 8))
                      . pack('V', 0x04 | (strlen($subject2) << 8));
        $dataLength2 = strlen($flagsBuffer2) + strlen($dataBuffer2) + 12;
        $msgInfo2 = pack('VVvv', $msgInfo2Pos, $dataLength2, 12, 2) . $flagsBuffer2 . $dataBuffer2;
        $this->writeAt($data, $msgInfo2Pos, $msgInfo2);

        // Index with 2 items
        $indexHeader = pack('VVVVVV', $indexPos, 0, 0, 0, 2 << 8, 0);
        $indexItems = pack('VVV', $msgInfo1Pos, 0, 0) . pack('VVV', $msgInfo2Pos, 0, 0);
        $this->writeAt($data, $indexPos, $indexHeader . $indexItems);

        // Message bodies
        $content1 = 'Message 1 content';
        $body1 = pack('VVVV', $msgBody1Pos, 0, strlen($content1), 0) . str_pad($content1, 512, "\x00");
        $this->writeAt($data, $msgBody1Pos, $body1);

        $content2 = 'Message 2 content';
        $body2 = pack('VVVV', $msgBody2Pos, 0, strlen($content2), 0) . str_pad($content2, 512, "\x00");
        $this->writeAt($data, $msgBody2Pos, $body2);

        return $data;
    }

    private function writeAt(string &$data, int $offset, string $bytes): void
    {
        for ($i = 0; $i < strlen($bytes); $i++) {
            $data[$offset + $i] = $bytes[$i];
        }
    }
}
