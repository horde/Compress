<?php

declare(strict_types=1);

namespace Horde\Compress\Test\Tnef;

use Horde\Compress\Tnef\TnefDecoder;
use Horde\Compress\Tnef\MessageData;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TnefDecoder::class)]
class TnefDecoderTest extends TestCase
{
    private TnefDecoder $decoder;

    protected function setUp(): void
    {
        if (!class_exists('Horde_Mapi')) {
            $this->markTestSkipped('Horde_Mapi is not available');
        }
        $this->decoder = new TnefDecoder();
    }

    public function testCanCompress(): void
    {
        $this->assertFalse($this->decoder->canCompress());
    }

    public function testCanDecompress(): void
    {
        $this->assertTrue($this->decoder->canDecompress());
    }

    public function testDecompressInvalidSignatureReturnsEmpty(): void
    {
        $result = $this->decoder->decompress('not a tnef file at all');
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testDecompressEmptyDataReturnsEmpty(): void
    {
        $result = $this->decoder->decompress('');
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testGetFilesInitiallyEmpty(): void
    {
        $this->assertEmpty($this->decoder->getFiles());
    }

    public function testGetAttachmentsInitiallyEmpty(): void
    {
        $this->assertEmpty($this->decoder->getAttachments());
    }

    public function testGetMsgInfoInitiallyNull(): void
    {
        $this->assertNull($this->decoder->getMsgInfo());
    }

    public function testDecompressWinmailDat(): void
    {
        $data = file_get_contents(__DIR__ . '/../fixtures/winmail2.dat');
        $result = $this->decoder->decompress($data);

        $this->assertNotEmpty($result);
        $this->assertEquals('text', $result[0]['type']);
        $this->assertEquals('calendar', $result[0]['subtype']);
        $this->assertEquals('Test Meeting', $result[0]['name']);
    }

    public function testDecompressWinmailDatSetsMessageInfo(): void
    {
        $data = file_get_contents(__DIR__ . '/../fixtures/winmail2.dat');
        $this->decoder->decompress($data);

        $msgInfo = $this->decoder->getMsgInfo();
        $this->assertInstanceOf(MessageData::class, $msgInfo);
    }

    public function testDecompressWinmailDatPopulatesFiles(): void
    {
        $data = file_get_contents(__DIR__ . '/../fixtures/winmail2.dat');
        $this->decoder->decompress($data);

        $files = $this->decoder->getFiles();
        $this->assertNotEmpty($files);
    }

    public function testDecompressAttachments(): void
    {
        $data = base64_decode(file_get_contents(__DIR__ . '/../fixtures/TnefAttachments.txt'));
        $result = $this->decoder->decompress($data);

        $this->assertNotEmpty($result);
        // Should contain RTF and JPEG attachment
        $this->assertEquals('application', $result[0]['type']);
        $this->assertEquals('rtf', $result[0]['subtype']);
        $this->assertGreaterThanOrEqual(2, count($result));
        $this->assertEquals('image', $result[1]['type']);
        $this->assertEquals('jpeg', $result[1]['subtype']);
        $this->assertEquals('hasselhoff_birthday.jpg', $result[1]['name']);
        $this->assertEquals(80051, $result[1]['size']);
    }

    public function testDecompressMultipleAttachments(): void
    {
        $data = base64_decode(file_get_contents(__DIR__ . '/../fixtures/TnefAttachmentsMultiple.txt'));
        $result = $this->decoder->decompress($data);

        $this->assertGreaterThanOrEqual(3, count($result));
        $this->assertEquals('application', $result[0]['type']);
        $this->assertEquals('rtf', $result[0]['subtype']);
        $this->assertEquals('Lighthouse.jpg', $result[1]['name']);
        $this->assertEquals('Penguins.jpg', $result[2]['name']);
    }

    public function testDecompressMeetingRequest(): void
    {
        $data = base64_decode(file_get_contents(__DIR__ . '/../fixtures/TnefMeetingRequest.txt'));
        $result = $this->decoder->decompress($data);

        $this->assertNotEmpty($result);
        $this->assertEquals('text', $result[0]['type']);
        $this->assertEquals('calendar', $result[0]['subtype']);
        $this->assertEquals('Meeting', $result[0]['name']);
    }

    public function testSetCurrentObject(): void
    {
        $data = file_get_contents(__DIR__ . '/../fixtures/winmail2.dat');
        // Just verify it doesn't throw — the method is used internally
        $this->decoder->decompress($data);
        $files = $this->decoder->getFiles();
        $this->assertNotEmpty($files);
    }
}
