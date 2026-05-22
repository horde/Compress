<?php

declare(strict_types=1);

/**
 * Copyright 2002-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @author    Michael J Rubinsky <mrubinsk@horde.org>
 * @category  Horde
 * @copyright 2002-2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Compress
 */

namespace Horde\Compress\Tnef;

use Horde\Compress\Exception;
use Psr\Log\LoggerInterface;
use Horde_Date;
use Horde_Date_Exception;
use Horde_Date_Recurrence;
use Horde_Icalendar;
use Horde_Mail_Rfc822_List;
use Horde_Mapi;
use Horde_Mapi_Exception;
use InvalidArgumentException;

/**
 * Object to parse and represent iCalendar data encapsulated in a TNEF file.
 *
 * @author    Michael J Rubinsky <mrubinsk@horde.org>
 * @category  Horde
 * @copyright 2002-2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Compress
 */
class Icalendar extends TnefObject
{
    public const PART_ACTION    = 'NEEDS-ACTION';
    public const PART_TENTATIVE = 'TENTATIVE';
    public const PART_DECLINE   = 'DECLINE';
    public const PART_ACCEPTED  = 'ACCEPTED';

    /**
     * ICalendar METHOD.
     */
    private ?string $method = null;

    /**
     * Event summary.
     */
    private ?string $summary = null;

    /**
     * Event location.
     */
    private ?string $location = null;

    /**
     * Event URL.
     */
    private ?string $url = null;

    /**
     * Start date/time in UTC.
     */
    private ?Horde_Date $startUtc = null;

    /**
     * End date/time in UTC.
     */
    private ?Horde_Date $endUtc = null;

    /**
     * Duration in minutes.
     */
    private ?int $duration = null;

    /**
     * Whether this is an all-day event.
     */
    private bool $allday = false;

    /**
     * Organizer email.
     */
    private ?string $organizer = null;

    /**
     * Last modifier name.
     */
    private ?string $lastModifier = null;

    /**
     * The "From" attribute used by iTip responses.
     */
    private ?string $from = null;

    /**
     * Unique identifier.
     */
    private ?string $uid = null;

    /**
     * Recurrence data.
     */
    private array $recurrence = [];

    /**
     * Meeting request type.
     */
    private ?int $type = null;

    /**
     * Creation date.
     */
    private ?Horde_Date $created = null;

    /**
     * Modification date.
     */
    private ?Horde_Date $modified = null;

    /**
     * Cache of the iCalendar text.
     */
    private ?string $content = null;

    /**
     * List of required attendees parsed from the MAPI object.
     */
    private ?string $requiredAttendees = null;

    /**
     * The current PARTSTAT property for this meeting request.
     */
    private ?string $partStat = null;

    /**
     * The description/body of the meeting request.
     */
    private ?string $description = null;

    /**
     * RSVP property.
     */
    private bool $rsvp = false;

    /**
     * MIME type.
     */
    public string $mimeType = 'text/calendar';

    /**
     * Accessor.
     */
    public function __get(string $property): mixed
    {
        if ($property === 'content') {
            if (empty($this->content)) {
                $this->buildItip();
            }
            return $this->content;
        }

        throw new InvalidArgumentException('Invalid property access.');
    }

    /**
     * Output the data for this object in an array.
     *
     * @return array{type: string, subtype: string, name: string, stream: string}
     */
    public function toArray(): array
    {
        return $this->buildItip();
    }

    /**
     * Set the METHOD parameter, used to help generate the PART-STAT attribute.
     */
    public function setMethod(string $method, ?string $class = null): void
    {
        $this->method = $method;

        match ($class) {
            TnefDecoder::IPM_MEETING_RESPONSE_TENT => (function () {
                $this->partStat = self::PART_TENTATIVE;
                $this->rsvp = false;
            })(),
            TnefDecoder::IPM_MEETING_RESPONSE_NEG => (function () {
                $this->partStat = self::PART_DECLINE;
                $this->rsvp = false;
            })(),
            TnefDecoder::IPM_MEETING_RESPONSE_POS => (function () {
                $this->partStat = self::PART_ACCEPTED;
                $this->rsvp = false;
            })(),
            TnefDecoder::IPM_MEETING_REQUEST => (function () {
                $this->partStat = self::PART_ACTION;
                $this->rsvp = true;
            })(),
            default => null,
        };
    }

    /**
     * Allow this object to set any TNEF attributes it needs to know about,
     * ignore any it doesn't care about.
     */
    public function setTnefAttribute(int $attribute, mixed $value, int $size): void
    {
        switch ($attribute) {
            case TnefDecoder::ABODY:
                $this->description = $value;
                break;
        }
    }

    /**
     * Allow this object to set any MAPI attributes it needs to know about,
     * ignore any it doesn't care about.
     *
     * @throws Exception
     */
    public function setMapiAttribute(int $type, int $name, mixed $value, ?string $ns = null): void
    {
        // First check for pidTag* properties - these will have no namespace.
        if (empty($ns)) {
            switch ($name) {
                case TnefDecoder::MAPI_CONVERSATION_TOPIC:
                    $this->summary = $value;
                    break;
                case TnefDecoder::MAPI_SENT_REP_SMTP_ADDR:
                    $this->from = $value;
                    break;
                case TnefDecoder::MAPI_LAST_MODIFIER_NAME:
                    // Sender SMTP is more appropriate, but not present in all
                    // meeting request MAPI objects (it's normally taken from the
                    // parent MAPI mail message object). Since this class doesn't
                    // (currently) have access to the parent MIME
                    // part (since this isn't necessarily from an email), this is the
                    // only hope of obtaining an ORGANIZER.
                    $this->lastModifier = $value;
                    break;
                case TnefDecoder::MAPI_CREATION_TIME:
                    try {
                        $this->created = new Horde_Date(Horde_Mapi::filetimeToUnixtime($value), 'UTC');
                    } catch (Horde_Mapi_Exception $e) {
                        throw new Exception($e->getMessage(), $e->getCode(), $e);
                    } catch (Horde_Date_Exception $e) {
                        throw new Exception($e->getMessage(), $e->getCode(), $e);
                    }
                    break;
                case TnefDecoder::MAPI_MODIFICATION_TIME:
                    try {
                        $this->modified = new Horde_Date(Horde_Mapi::filetimeToUnixtime($value), 'UTC');
                    } catch (Horde_Mapi_Exception $e) {
                        throw new Exception($e->getMessage(), $e->getCode(), $e);
                    } catch (Horde_Date_Exception $e) {
                        throw new Exception($e->getMessage(), $e->getCode(), $e);
                    }
                    break;
                case TnefDecoder::MAPI_RESPONSE_REQUESTED:
                    $this->rsvp = (bool) $value;
                    break;
                case TnefDecoder::MAPI_TAG_RTF_COMPRESSED:
                    // We may already have a description from the TNEF attBODY attribute
                    if (empty($this->description)) {
                        $this->description = $value;
                    }
                    break;
            }
        } elseif ($ns === TnefDecoder::PSETID_APPOINTMENT) {
            switch ($name) {
                case TnefDecoder::MAPI_APPOINTMENT_LOCATION:
                    $this->location = $value;
                    break;
                case TnefDecoder::MAPI_APPOINTMENT_URL:
                    $this->url = $value;
                    break;
                case TnefDecoder::MAPI_APPOINTMENT_START_WHOLE:
                    try {
                        $this->startUtc = new Horde_Date(Horde_Mapi::filetimeToUnixtime($value), 'UTC');
                    } catch (Horde_Mapi_Exception $e) {
                        throw new Exception($e->getMessage(), $e->getCode(), $e);
                    } catch (Horde_Date_Exception $e) {
                        throw new Exception($e->getMessage(), $e->getCode(), $e);
                    }
                    break;
                case TnefDecoder::MAPI_APPOINTMENT_END_WHOLE:
                    try {
                        $this->endUtc = new Horde_Date(Horde_Mapi::filetimeToUnixtime($value), 'UTC');
                    } catch (Horde_Mapi_Exception $e) {
                        throw new Exception($e->getMessage(), $e->getCode(), $e);
                    } catch (Horde_Date_Exception $e) {
                        throw new Exception($e->getMessage(), $e->getCode(), $e);
                    }
                    break;
                case TnefDecoder::MAPI_APPOINTMENT_DURATION:
                    $this->duration = (int) $value;
                    break;
                case TnefDecoder::MAPI_APPOINTMENT_SUBTYPE:
                    $this->allday = (bool) $value;
                    break;
                case TnefDecoder::MAPI_ORGANIZER_ALIAS:
                    $this->organizer = $value;
                    break;
                case TnefDecoder::MAPI_TO_ATTENDEES:
                    // Don't even ask. Why, Microsoft, why??
                    $value = str_replace(['(', ')'], ['<', '>'], $value);
                    $this->requiredAttendees = $value;
                    break;
                case TnefDecoder::MAPI_APPOINTMENT_RECUR:
                    $this->recurrence['recur'] = $this->parseRecurrence($value);
                    break;
                case TnefDecoder::MAPI_RECURRING:
                    // ?? Reset $this->recurrence?
                    break;
                case TnefDecoder::MAPI_RECURRENCE_TYPE:
                    $this->recurrence['type'] = $value;
                    break;
                case TnefDecoder::MAPI_RESPONSE_STATUS:
                    // Don't think we need this, it seems more geared towards writing
                    // a TNEF. Indicates the response status of an ATTENDEE. Putting
                    // this here for reference, see MS-OXOCAL 2.2.1.11
                    break;
            }
        } elseif ($ns === TnefDecoder::PSETID_MEETING) {
            switch ($name) {
                case TnefDecoder::MAPI_ENTRY_CLEANID:
                case TnefDecoder::MAPI_ENTRY_UID:
                    // Still not 100% sure about where a suitable UID comes from;
                    // These attributes are all said to contain it, at various times.
                    // The "Clean" UID is supposed to only be in appointments that
                    // are exceptions to a recurring series, though I have a number
                    // of examples where that is not the case. Also, in some cases
                    // some of these attributes seem to be set here multiple times,
                    // sometimes with non-empty and then empty values, so never set
                    // $this->uid if it is already set, or if $value is empty.
                    if (empty($this->uid) && !empty($value)) {
                        $this->uid = Horde_Mapi::getUidFromGoid(bin2hex($value));
                    }
                    break;
                case TnefDecoder::MAPI_MEETING_REQUEST_TYPE:
                    $this->type = (int) $value;
                    break;
            }
        } else {
            $this->logger->notice(sprintf('Unknown namespace GUID: %s', $ns));
        }
    }

    /**
     * Parse recurrence properties.
     *
     * @throws Exception
     */
    private function parseRecurrence(string $value): Horde_Date_Recurrence
    {
        $deleted = [];
        $modified = [];

        // both are 0x3004 (version strings);
        $this->readInt($value, 16);
        $this->readInt($value, 16);

        $freq = $this->readInt($value, 16);
        $pattern = $this->readInt($value, 16);
        $calendarType = $this->readInt($value, 16);
        $firstDt = $this->readInt($value, 32);
        $period = $this->readInt($value, 32);

        // Only used for tasks, otherwise value must be zero.
        $flag = $this->readInt($value, 32);

        // TypeSpecific field
        $day = null;
        $n = null;
        switch ($pattern) {
            case TnefDecoder::PATTERN_DAY:
                // Nothing here to see, move along.
                break;
            case TnefDecoder::PATTERN_WEEK:
                // Bits: 0/unused, 1/Saturday, 2/Friday, 3/Thursday, 4/Wednesday,
                // 5/Tuesday, 6/Monday, 7/Sunday.
                $day = $this->readInt($value, 8);
                // ??
                $this->readInt($value, 24);
                break;
            case TnefDecoder::PATTERN_MONTH:
            case TnefDecoder::PATTERN_MONTH_END:
                // Day of month on which the recurrence falls.
                $day = $this->readInt($value, 32);
                break;
            case TnefDecoder::PATTERN_MONTH_NTH:
                // Bits: 0/unused, 1/Saturday, 2/Friday, 3/Thursday, 4/Wednesday,
                // 5/Tuesday, 6/Monday, 7/Sunday.
                // For Nth Weekday of month
                $day = $this->readInt($value, 8);
                $this->readInt($value, 24);
                $n = $this->readInt($value, 32);
                break;
        }

        $end = $this->readInt($value, 32);
        $count = $this->readInt($value, 32);
        $fdow = $this->readInt($value, 32);
        $deletedCount = $this->readInt($value, 32);
        for ($i = 0; $i < $deletedCount; $i++) {
            $deleted[] = $this->readInt($value, 32);
        }
        $modifiedCount = $this->readInt($value, 32);
        for ($i = 0; $i < $modifiedCount; $i++) {
            $modified[] = $this->readInt($value, 32);
        }

        // What Timezone are these in?
        try {
            $startDate = new Horde_Date(Horde_Mapi::filetimeToUnixtime($this->readInt($value, 32)));
            $endDate = new Horde_Date(Horde_Mapi::filetimeToUnixtime($this->readInt($value, 32)));
        } catch (Horde_Mapi_Exception $e) {
            throw new Exception($e->getMessage(), $e->getCode(), $e);
        } catch (Horde_Date_Exception $e) {
            throw new Exception($e->getMessage(), $e->getCode(), $e);
        }

        $rrule = new Horde_Date_Recurrence($startDate);
        switch ($pattern) {
            case TnefDecoder::PATTERN_DAY:
                $rrule->setRecurType(Horde_Date_Recurrence::RECUR_DAILY);
                break;
            case TnefDecoder::PATTERN_WEEK:
                $rrule->setRecurType(Horde_Date_Recurrence::RECUR_WEEKLY);
                break;
            case TnefDecoder::PATTERN_MONTH:
            case TnefDecoder::PATTERN_MONTH_END:
                $rrule->setRecurType(Horde_Date_Recurrence::RECUR_MONTHLY_DATE);
                break;
            case TnefDecoder::PATTERN_MONTH_NTH:
                $rrule->setRecurType(Horde_Date_Recurrence::RECUR_MONTHLY_WEEKDAY);
                break;
            default:
                if ($freq === TnefDecoder::RECUR_YEARLY) {
                    $rrule->setRecurType(Horde_Date_Recurrence::RECUR_YEARLY);
                }
        }

        switch ($end) {
            case TnefDecoder::RECUR_END_N:
                $rrule->setRecurCount($count);
                break;
            case TnefDecoder::RECUR_END_DATE:
                $rrule->setRecurEnd($endDate);
                break;
        }

        return $rrule;
    }

    /**
     * Generate an iTip from embedded TNEF MEETING data.
     *
     * @return array{type: string, subtype: string, name: string, stream: string}
     */
    private function buildItip(): array
    {
        $iCal = new Horde_Icalendar();

        // METHOD
        if ($this->type) {
            match ($this->type) {
                TnefDecoder::MAPI_MEETING_INITIAL,
                TnefDecoder::MAPI_MEETING_FULL_UPDATE => $this->method = 'REQUEST',
                TnefDecoder::MAPI_MEETING_INFO => $this->method = 'PUBLISH',
                default => null,
            };
        }
        $iCal->setAttribute('METHOD', $this->method);

        // VEVENT
        $vEvent = Horde_Icalendar::newComponent('vevent', $iCal);
        if (empty($this->endUtc)) {
            return ['type' => '', 'subtype' => '', 'name' => '', 'stream' => ''];
        }
        $end = clone $this->endUtc;
        $end->sec++;
        if ($this->allday) {
            $vEvent->setAttribute('DTSTART', $this->startUtc, ['VALUE' => 'DATE']);
            $vEvent->setAttribute('DTEND', $end, ['VALUE' => 'DATE']);
        } else {
            $vEvent->setAttribute('DTSTART', $this->startUtc);
            $vEvent->setAttribute('DTEND', $end);
        }
        $vEvent->setAttribute('DTSTAMP', $_SERVER['REQUEST_TIME']);
        $vEvent->setAttribute('UID', $this->uid);
        if ($this->created) {
            $vEvent->setAttribute('CREATED', $this->created);
        }
        if ($this->modified) {
            $vEvent->setAttribute('LAST-MODIFIED', $this->modified);
        }

        // SUMMARY and DESCRIPTION
        $vEvent->setAttribute('SUMMARY', $this->summary);
        if ($this->description) {
            $vEvent->setAttribute('DESCRIPTION', trim($this->description));
        }

        // ORGANIZER
        $email = null;
        if (!$this->organizer && $this->lastModifier) {
            $email = $this->lastModifier;
        } elseif ($this->organizer) {
            $email = $this->organizer;
        }
        if (!empty($email)) {
            $vEvent->setAttribute('ORGANIZER', 'mailto:' . $email);
        }

        // ATTENDEE
        if (empty($this->requiredAttendees) && $this->method === 'REPLY') {
            $this->requiredAttendees = $this->from;
        }

        if (!empty($this->requiredAttendees)) {
            $list = new Horde_Mail_Rfc822_List($this->requiredAttendees);
            foreach ($list as $attendee) {
                $params = ['ROLE' => 'REQ-PARTICIPANT'];
                if (!empty($this->partStat)) {
                    $params['PARTSTAT'] = $this->partStat;
                }
                if ($this->rsvp) {
                    $params['RSVP'] = 'TRUE';
                }
                $vEvent->setAttribute('ATTENDEE', $attendee->bare_address, $params);
            }
        }

        // LOCATION
        if ($this->location) {
            $vEvent->setAttribute('LOCATION', $this->location);
        }

        // URL
        if ($this->url) {
            $vEvent->setAttribute('URL', $this->url);
        }

        // RECUR
        if (!empty($this->recurrence['recur'])) {
            $rrule = $this->recurrence['recur']->toRRule20($iCal);
            $vEvent->setAttribute('RRULE', $rrule);
        }

        $iCal->addComponent($vEvent);
        $this->content = $iCal->exportvCalendar();

        return [
            'type'    => 'text',
            'subtype' => 'calendar',
            'name'    => $this->summary ?? '',
            'stream'  => $this->content,
        ];
    }
}
