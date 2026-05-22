<?php

declare(strict_types=1);

namespace Horde\Compress\Test\Tnef;

use Horde\Compress\Tnef\File;
use Horde\Compress\Tnef\Date;
use Horde\Compress\Tnef\TnefDecoder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(File::class)]
class FileTest extends TestCase
{
    public function testToArrayDefaults(): void
    {
        $file = new File();
        $result = $file->toArray();

        $this->assertEquals('', $result['type']);
        $this->assertEquals('', $result['subtype']);
        $this->assertEquals('', $result['name']);
        $this->assertEquals(0, $result['size']);
        $this->assertEquals('', $result['stream']);
    }

    public function testSetTnefAttributeFilename(): void
    {
        $file = new File();
        $file->setTnefAttribute(TnefDecoder::AFILENAME, 'path/to/document.pdf', 20);

        $this->assertEquals('document.pdf', $file->name);
    }

    public function testSetTnefAttributeAttachData(): void
    {
        $file = new File();
        $content = 'binary attachment data';
        $file->setTnefAttribute(TnefDecoder::ATTACHDATA, $content, strlen($content));

        $this->assertEquals($content, $file->content);
        $this->assertEquals(strlen($content), $file->size);
    }

    public function testSetTnefAttributeMetafile(): void
    {
        $file = new File();
        $meta = 'metafile data';
        $file->setTnefAttribute(TnefDecoder::ATTACHMETAFILE, $meta, strlen($meta));

        $this->assertEquals($meta, $file->metafile);
    }

    public function testSetTnefAttributeCreateDate(): void
    {
        $file = new File();
        $dateData = pack('v6', 2024, 6, 15, 14, 30, 0);
        $file->setTnefAttribute(TnefDecoder::ATTACHCREATEDATE, $dateData, 12);

        $this->assertInstanceOf(Date::class, $file->created);
        $this->assertEquals(2024, $file->created->date->year);
    }

    public function testSetMapiAttributeLongFilename(): void
    {
        $file = new File();
        $file->setMapiAttribute(
            TnefDecoder::MAPI_STRING,
            TnefDecoder::MAPI_ATTACH_LONG_FILENAME,
            'dir/report.docx'
        );

        $this->assertEquals('report.docx', $file->name);
    }

    public function testSetMapiAttributeMimeTag(): void
    {
        $file = new File();
        $file->setMapiAttribute(
            TnefDecoder::MAPI_STRING,
            TnefDecoder::MAPI_ATTACH_MIME_TAG,
            "image/jpeg\x00"
        );

        $this->assertEquals('image', $file->type);
        $this->assertEquals('jpeg', $file->subtype);
    }

    public function testToArrayWithData(): void
    {
        $file = new File();
        $file->setTnefAttribute(TnefDecoder::AFILENAME, 'photo.jpg', 9);
        $file->setTnefAttribute(TnefDecoder::ATTACHDATA, 'JPEG DATA', 9);
        $file->setMapiAttribute(
            TnefDecoder::MAPI_STRING,
            TnefDecoder::MAPI_ATTACH_MIME_TAG,
            "image/jpeg\x00"
        );

        $result = $file->toArray();
        $this->assertEquals('image', $result['type']);
        $this->assertEquals('jpeg', $result['subtype']);
        $this->assertEquals('photo.jpg', $result['name']);
        $this->assertEquals(9, $result['size']);
        $this->assertEquals('JPEG DATA', $result['stream']);
    }
}
