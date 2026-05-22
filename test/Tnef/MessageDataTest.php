<?php

declare(strict_types=1);

namespace Horde\Compress\Test\Tnef;

use Horde\Compress\Tnef\MessageData;
use Horde\Compress\Tnef\Date;
use Horde\Compress\Tnef\TnefDecoder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MessageData::class)]
class MessageDataTest extends TestCase
{
    public function testDefaultProperties(): void
    {
        $msg = new MessageData();
        $this->assertNull($msg->subject);
        $this->assertNull($msg->dateSent);
        $this->assertNull($msg->from);
        $this->assertNull($msg->fromName);
    }

    public function testSetTnefAttributeSubject(): void
    {
        $msg = new MessageData();
        $msg->setTnefAttribute(TnefDecoder::ASUBJECT, "  Test Subject  ", 16);

        $this->assertEquals('Test Subject', $msg->subject);
    }

    public function testSetTnefAttributeDateSent(): void
    {
        $msg = new MessageData();
        $dateData = pack('v6', 2025, 1, 20, 9, 0, 0);
        $msg->setTnefAttribute(TnefDecoder::ADATESENT, $dateData, 12);

        $this->assertInstanceOf(Date::class, $msg->dateSent);
        $this->assertEquals(2025, $msg->dateSent->date->year);
    }

    public function testSetTnefAttributeDateReceivedSetsDateSentIfNotSet(): void
    {
        $msg = new MessageData();
        $dateData = pack('v6', 2025, 2, 10, 15, 45, 0);
        $msg->setTnefAttribute(TnefDecoder::ADATERECEIVED, $dateData, 12);

        $this->assertInstanceOf(Date::class, $msg->dateSent);
        $this->assertEquals(2025, $msg->dateSent->date->year);
        $this->assertEquals(2, $msg->dateSent->date->month);
    }

    public function testSetTnefAttributeDateReceivedDoesNotOverwriteDateSent(): void
    {
        $msg = new MessageData();
        $sentData = pack('v6', 2025, 1, 1, 0, 0, 0);
        $recvData = pack('v6', 2025, 1, 2, 0, 0, 0);

        $msg->setTnefAttribute(TnefDecoder::ADATESENT, $sentData, 12);
        $msg->setTnefAttribute(TnefDecoder::ADATERECEIVED, $recvData, 12);

        $this->assertEquals(1, $msg->dateSent->date->mday);
    }

    public function testSetMapiAttributeConversationTopic(): void
    {
        $msg = new MessageData();
        $msg->setMapiAttribute(
            TnefDecoder::MAPI_UNICODE_STRING,
            TnefDecoder::MAPI_CONVERSATION_TOPIC,
            'Meeting Notes'
        );

        $this->assertEquals('Meeting Notes', $msg->subject);
    }

    public function testSetMapiAttributeEmailAddr(): void
    {
        $msg = new MessageData();
        $msg->setMapiAttribute(
            TnefDecoder::MAPI_STRING,
            TnefDecoder::MAPI_SENT_REP_EMAIL_ADDR,
            'user@example.com'
        );

        $this->assertEquals('user@example.com', $msg->from);
    }

    public function testSetMapiAttributeSentRepName(): void
    {
        $msg = new MessageData();
        $msg->setMapiAttribute(
            TnefDecoder::MAPI_STRING,
            TnefDecoder::MAPI_SENT_REP_NAME,
            'John Doe'
        );

        $this->assertEquals('John Doe', $msg->fromName);
    }
}
