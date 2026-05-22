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

use Horde_Compress;
use Horde_Compress_Exception;
use Horde_Compress_Tnef;
use Horde_Icalendar;
use Horde_Mapi_Exception;
use Horde_Mime_Part;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Tests the legacy Horde_Compress_Tnef driver.
 */
#[CoversClass(Horde_Compress_Tnef::class)]
class TnefLegacyTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists('Horde_Mapi')) {
            $this->markTestSkipped('Horde_Mapi is not available');
        }
    }

    public function testItipReply(): void
    {
        $eml = file_get_contents(__DIR__ . '/fixtures/Itip_Reply.eml');
        $mime = Horde_Mime_Part::parseMessage($eml);
        $winmail = $mime->getPart(2)->getContents();
        $tnef = Horde_Compress::factory('Tnef');
        $tnef_data = $tnef->decompress($winmail);

        $iCal = new Horde_Icalendar();
        $this->assertTrue($iCal->parsevCalendar($tnef_data[0]['stream']));

        $this->assertEquals('REPLY', $iCal->getAttribute('METHOD'));
        $components = $iCal->getComponents();
        $this->assertNotEmpty($components);

        $iTip = current($components);
        $this->assertEquals(
            'Infoveranstaltung VDI/VDE Jena "Digitalisierung Medizintechnik"',
            $iTip->getAttribute('SUMMARY')
        );
        $this->assertEquals('J.Meier@medizin.uni-leipzig.de', $iTip->getAttribute('ATTENDEE'));
        $params = $iTip->getAttribute('ATTENDEE', true);
        $this->assertNotEmpty($params);
        $this->assertEquals('REQ-PARTICIPANT', $params[0]['ROLE']);
        $this->assertEquals('ACCEPTED', $params[0]['PARTSTAT']);
    }

    public function testvTodo(): void
    {
        $tnef = Horde_Compress::factory('Tnef');
        $mime = Horde_Mime_Part::parseMessage(file_get_contents(__DIR__ . '/fixtures/tnef_task.eml'));
        try {
            $tnef_data = $tnef->decompress($mime->getPart(2)->getContents());
        } catch (Horde_Mapi_Exception $e) {
            $this->markTestSkipped('Horde_Mapi is not available');
        }

        $iCal = new Horde_Icalendar();
        $this->assertTrue($iCal->parsevCalendar($tnef_data[0]['stream']));

        $this->assertEquals('REQUEST', $iCal->getAttribute('METHOD'));
        $components = $iCal->getComponents();
        $this->assertNotEmpty($components);

        $vTodo = current($components);
        $this->assertEquals('Test Task', $vTodo->getAttribute('SUMMARY'));
        $this->assertEquals('EDF71E6FA6FB69A79D79FE1D6DCDBBD300000000DFD9B6FB', $vTodo->getAttribute('UID'));
        $this->assertEquals('Michael Rubinsky <mrubinsk@horde.org>', $vTodo->getAttribute('ATTENDEE'));
        $params = $vTodo->getAttribute('ATTENDEE', true);
        $this->assertNotEmpty($params);
        $this->assertEquals('REQ-PARTICIPANT', $params[0]['ROLE']);
        $this->assertEquals('mailto: mike@theupstairsroom.com', $vTodo->getAttribute('ORGANIZER'));
    }

    public function testMeetingInvitation(): void
    {
        $tnef = Horde_Compress::factory('Tnef');
        $data = base64_decode(file_get_contents(__DIR__ . '/fixtures/TnefMeetingRequest.txt'));
        try {
            $tnef_data = $tnef->decompress($data);
        } catch (Horde_Compress_Exception $e) {
            if (($prev = $e->getPrevious()) && ($prev instanceof Horde_Mapi_Exception)) {
                $this->markTestSkipped('Horde_Mapi is not available');
            }
            throw $e;
        }
        $this->assertEquals('text', $tnef_data[0]['type']);
        $this->assertEquals('calendar', $tnef_data[0]['subtype']);
        $this->assertEquals('Meeting', $tnef_data[0]['name']);
    }

    public function testMeetingTnef(): void
    {
        $winmail = file_get_contents(__DIR__ . '/fixtures/winmail2.dat');
        $tnef = Horde_Compress::factory('Tnef');
        $tnef_data = $tnef->decompress($winmail);

        $this->assertEquals('text', $tnef_data[0]['type']);
        $this->assertEquals('calendar', $tnef_data[0]['subtype']);
        $this->assertEquals('Test Meeting', $tnef_data[0]['name']);

        $iCal = new Horde_Icalendar();
        $this->assertTrue($iCal->parsevCalendar($tnef_data[0]['stream']));

        $components = $iCal->getComponents();
        $this->assertNotEmpty($components);

        $iTip = current($components);
        $this->assertEquals('Test Meeting', $iTip->getAttribute('SUMMARY'));
        $this->assertEquals('This is a test meeting.', $iTip->getAttribute('DESCRIPTION'));
        $this->assertEquals('mailto:mike@theupstairsroom.com', $iTip->getAttribute('ORGANIZER'));
        $this->assertEquals('mrubinsk@horde.org', $iTip->getAttribute('ATTENDEE'));
        $params = $iTip->getAttribute('ATTENDEE', true);
        $this->assertNotEmpty($params);
        $this->assertEquals('REQ-PARTICIPANT', $params[0]['ROLE']);
        $this->assertEquals('NEEDS-ACTION', $params[0]['PARTSTAT']);
        $this->assertEquals('TRUE', $params[0]['RSVP']);
    }

    public function testAttachments(): void
    {
        $data = base64_decode(file_get_contents(__DIR__ . '/fixtures/TnefAttachments.txt'));
        $tnef = Horde_Compress::factory('Tnef');
        $tnef_data = $tnef->decompress($data);
        $this->assertEquals('application', $tnef_data[0]['type']);
        $this->assertEquals('rtf', $tnef_data[0]['subtype']);
        $this->assertEquals('image', $tnef_data[1]['type']);
        $this->assertEquals('jpeg', $tnef_data[1]['subtype']);
        $this->assertEquals('hasselhoff_birthday.jpg', $tnef_data[1]['name']);
        $this->assertEquals(80051, $tnef_data[1]['size']);
    }

    public function testMultipleAttachments(): void
    {
        $data = base64_decode(file_get_contents(__DIR__ . '/fixtures/TnefAttachmentsMultiple.txt'));
        $tnef = Horde_Compress::factory('Tnef');
        $tnef_data = $tnef->decompress($data);
        $this->assertEquals('application', $tnef_data[0]['type']);
        $this->assertEquals('rtf', $tnef_data[0]['subtype']);
        $this->assertEquals('image', $tnef_data[1]['type']);
        $this->assertEquals('jpeg', $tnef_data[1]['subtype']);
        $this->assertEquals('Lighthouse.jpg', $tnef_data[1]['name']);
        $this->assertEquals('image', $tnef_data[2]['type']);
        $this->assertEquals('jpeg', $tnef_data[2]['subtype']);
        $this->assertEquals('Penguins.jpg', $tnef_data[2]['name']);
    }
}
