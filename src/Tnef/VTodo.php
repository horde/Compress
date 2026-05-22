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
use Horde_Icalendar;
use Horde_Mail_Rfc822_List;
use Horde_Mapi;
use Horde_Mapi_Exception;
use Horde_Text_Filter;
use InvalidArgumentException;

/**
 * Object to parse and represent vTODO data encapsulated in a TNEF file.
 *
 * @author    Michael J Rubinsky <mrubinsk@horde.org>
 * @category  Horde
 * @copyright 2002-2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Compress
 */
class VTodo extends TnefObject
{
    public const MAPI_TASK_STATUS          = 0x8101;
    public const MAPI_TASK_PERCENTCOMPLETE = 0x8102;

    // These are in user's local timezone and MUST have a time component of
    // 12:00 midnight.
    public const MAPI_TASK_STARTDATE       = 0x8104;
    public const MAPI_TASK_DUEDATE         = 0x8105;

    // The following is used as a placeholder value when a task has no start
    // or due date. Some clients set the property empty, some set it to this
    // value.
    public const MAPI_TASK_NODUEDATE       = 0x5AE980E0;

    // The following properties are UTC equivalent values of MAPI_TASK_STARTDATE
    // and MAPI_TASK_DUEDATE
    public const MAPI_TASK_COMMONEND       = 0x8517;
    public const MAPI_TASK_COMMONSTART     = 0x8516;

    public const MAPI_TASK_ACCEPTED        = 0x8108;
    public const MAPI_TASK_DATECOMPLETED   = 0x810F;
    public const MAPI_TASK_STATE           = 0x8113;
    public const MAPI_TASK_ASSIGNERS       = 0x8117;

    // If non-zero, updates are requested.
    public const MAPI_TASK_UPDATES         = 0x811B;
    public const MAPI_TASK_OWNER           = 0x811F;
    public const MAPI_TASK_ASSIGNER        = 0x8121;
    public const MAPI_TASK_LASTUSER        = 0x8122;
    public const MAPI_TASK_OWNERSHIP       = 0x8129;

    /**
     * MAPI_TASK_STATUS constants
     */
    public const STATUS_NOT_STARTED        = 0x00000000;
    public const STATUS_IN_PROGRESS        = 0x00000001;
    public const STATUS_COMPLETE           = 0x00000002;
    public const STATUS_WAIT               = 0x00000003;
    public const STATUS_DEFERRED           = 0x00000004;

    /**
     * MAPI_TASK_STATE constants
     */
    public const STATE_TASK_NOT_FOUND     = 0x00000000;
    public const STATE_NOT_ASSIGNED       = 0x00000001;
    public const STATE_ASSIGNEE_COPY      = 0x00000002;
    public const STATE_ASSIGNERS_COPY     = 0x00000003;
    public const STATE_ASSIGNERS_REJECTED = 0x00000004;

    /**
     * MAPI_TASK_OWNERSHIP
     */
    public const OWNERSHIP_NONE           = 0x00000000;
    public const OWNERSHIP_ASSIGNERS_COPY = 0x00000001;
    public const OWNERSHIP_ASSIGNEES_COPY = 0x00000002;

    /**
     * MAPI_MESSAGE_CLASS
     */
    public const CLASS_REQUEST            = 'IPM.TaskRequest';
    public const CLASS_ACCEPT             = 'IPM.TaskRequest.Accept';
    public const CLASS_DECLINE            = 'IPM.TaskRequest.Decline';
    public const CLASS_UPDATE             = 'IPM.TaskRequest.Update';

    public const TASK_STATUS_ACTION       = 'NEEDS-ACTION';
    public const TASK_STATUS_IN_PROGRESS  = 'IN-PROGRESS';
    public const TASK_STATUS_COMPLETED    = 'COMPLETED';

    /**
     * Due date (timestamp).
     */
    private ?int $due = null;

    /**
     * UID
     */
    private ?string $guid = null;

    /**
     * Message editor format.
     */
    private ?int $msgformat = null;

    /**
     * Percentage of task that is completed.
     */
    private ?int $percentComplete = null;

    /**
     * Plain body.
     */
    private ?string $bodyPlain = null;

    /**
     * HTML body.
     */
    private ?string $bodyHtml = null;

    /**
     * Compressed RTF body.
     */
    private ?string $rtfCompressed = null;

    /**
     * If true, assignee is requested to send updates.
     */
    private bool $updates = false;

    /**
     * The MAPI_TASK_STATE value. Used to help determine METHOD.
     */
    private ?int $state = null;

    /**
     * The MAPI_TASK_OWNERSHIP value.
     */
    private ?int $ownership = null;

    /**
     * The METHOD to use in the generated vTodo component. Default
     * to REQUEST since TNEF files are generally not used for PUBLISH.
     */
    private string $method = 'REQUEST';

    /**
     * The MAPI_MESSAGE_CLASS.
     */
    private ?string $messageClass = null;

    /**
     * The current owner of the task. Note, this is the CURRENT owner,
     * so for the initial REQUEST, this will be empty. MS doesn't consider
     * the task creator the owner in this context.
     */
    private ?string $owner = null;

    /**
     * Last user to modify the request.
     */
    private ?string $lastUser = null;

    /**
     * Organizer (task creator).
     */
    private ?string $organizer = null;

    /**
     * Start time of task (timestamp).
     */
    private ?int $start = null;

    /**
     * Status of task.
     */
    private ?string $status = null;

    /**
     * The MIME type of this object's content.
     */
    public string $type = 'text/calendar';

    /**
     * Timestamp when task was completed.
     */
    private ?int $completed = null;

    /**
     * Accessor.
     */
    public function __get(string $property): mixed
    {
        if ($property === 'content') {
            return $this->tovTodo();
        }

        throw new InvalidArgumentException('Invalid property access.');
    }

    /**
     * Allow this object to set any TNEF attributes it needs to know about,
     * ignore any it doesn't care about.
     *
     * @param int   $attribute The attribute descriptor.
     * @param mixed $value     The value from the MAPI stream.
     * @param int   $size      The byte length of the data, as reported by
     *                         the MAPI data.
     */
    public function setTnefAttribute(int $attribute, mixed $value, int $size): void
    {
        switch ($attribute) {
            case TnefDecoder::ABODY:
                $this->bodyPlain = trim($value);
                break;
        }
    }

    /**
     * Allow this object to set any MAPI attributes it needs to know about,
     * ignore any it doesn't care about.
     *
     * @param int         $type  The attribute type descriptor.
     * @param int         $name  The attribute name descriptor.
     * @param mixed       $value The attribute value.
     * @param string|null $ns    The attribute namespace.
     *
     * @throws Exception
     */
    public function setMapiAttribute(int $type, int $name, mixed $value, ?string $ns = null): void
    {
        if ($ns === TnefDecoder::PSETID_COMMON) {
            switch ($name) {
                case TnefDecoder::IPM_TASK_GUID:
                    // Almost positive this is wrong :(
                    $this->guid = Horde_Mapi::getUidFromGoid(bin2hex($value));
                    break;
                case TnefDecoder::MSG_EDITOR_FORMAT:
                    // Map this?
                    $this->msgformat = $value;
                    break;
                case TnefDecoder::MAPI_TAG_BODY:
                    // plaintext. Most likely set via the attBody TNEF attribute,
                    // and not by the MAPI property.
                    if (empty($this->bodyPlain)) {
                        $this->bodyPlain = $value;
                    }
                    break;
                case TnefDecoder::MAPI_TAG_HTML:
                    // html
                    $this->bodyHtml = $value;
                    break;
                case self::MAPI_TASK_COMMONSTART:
                    try {
                        $date = new Horde_Date(Horde_Mapi::filetimeToUnixtime($value));
                    } catch (Horde_Date_Exception $e) {
                        throw new Exception($e->getMessage(), $e->getCode(), $e);
                    }
                    $this->start = $date->timestamp();
                    break;
                case self::MAPI_TASK_COMMONEND:
                    try {
                        $date = new Horde_Date(Horde_Mapi::filetimeToUnixtime($value));
                    } catch (Horde_Mapi_Exception $e) {
                        throw new Exception($e->getMessage(), $e->getCode(), $e);
                    } catch (Horde_Date_Exception $e) {
                        throw new Exception($e->getMessage(), $e->getCode(), $e);
                    }
                    $this->due = $date->timestamp();
                    break;
            }
        } elseif ($ns === TnefDecoder::PSETID_TASK) {
            switch ($name) {
                case self::MAPI_TASK_OWNER:
                    // This is the OWNER, not to be confused with the ORGANIZER.
                    // I.e., this is the person the task has been assigned to.
                    // The ORGANIZER is the person who created the task and has
                    // assigned it. I.e., the person that any task updates are
                    // sent back to by the owner.
                    $this->owner = str_replace(['(', ')'], ['<', '>'], $value);
                    break;
                case self::MAPI_TASK_DUEDATE:
                    // Favor COMMONEND
                    if (empty($this->due)) {
                        $this->due = Horde_Mapi::filetimeToUnixtime($value);
                    }
                    break;
                case self::MAPI_TASK_STARTDATE:
                    if (empty($this->start)) {
                        $this->start = Horde_Mapi::filetimeToUnixtime($value);
                    }
                    break;
                case self::MAPI_TASK_DATECOMPLETED:
                    $this->completed = Horde_Mapi::filetimeToUnixtime($value);
                    break;
                case self::MAPI_TASK_PERCENTCOMPLETE:
                    $unpacked = unpack('d', $value);
                    $this->percentComplete = (int) ($unpacked[1] * 100);
                    break;
                case self::MAPI_TASK_STATUS:
                    switch ($value) {
                        case self::STATUS_NOT_STARTED:
                        case self::STATUS_WAIT:
                        case self::STATUS_DEFERRED:
                            $this->percentComplete = 0;
                            $this->status = self::TASK_STATUS_ACTION;
                            break;
                        case self::STATUS_IN_PROGRESS:
                            $this->status = self::TASK_STATUS_IN_PROGRESS;
                            break;
                        case self::STATUS_COMPLETE:
                            $this->status = self::TASK_STATUS_COMPLETED;
                            $this->percentComplete = 1;
                            break;
                    }
                    break;
                case self::MAPI_TASK_UPDATES:
                    if (!empty($value)) {
                        $this->updates = true;
                    }
                    break;
                case self::MAPI_TASK_OWNERSHIP:
                    $this->ownership = $value;
                    break;
                case self::MAPI_TASK_STATE:
                    $this->state = $value;
                    break;
            }
        } else {
            // pidTag?
            switch ($name) {
                case TnefDecoder::MAPI_SENT_REP_EMAIL_ADDR:
                    $this->organizer = $value;
                    break;
                case TnefDecoder::MAPI_LAST_MODIFIER_NAME:
                    $this->lastUser = $value;
                    break;
            }
        }
    }

    /**
     * Output the data for this object in an array.
     *
     * @return array{type: string, subtype: string, name: string, stream: string}
     */
    public function toArray(): array
    {
        return $this->tovTodo();
    }

    /**
     * Build and return the vTodo iCalendar representation.
     *
     * @return array{type: string, subtype: string, name: string, stream: string}
     */
    private function tovTodo(): array
    {
        $iCal = new Horde_Icalendar();
        $iCal->setAttribute('METHOD', $this->method);
        $vtodo = Horde_Icalendar::newComponent('vtodo', $iCal);

        $vtodo->setAttribute('UID', $this->guid);

        // For REQUESTS, we MUST have the ORGANIZER and an ATTENDEE.
        if ($this->state === self::STATE_ASSIGNERS_COPY || $this->ownership === self::OWNERSHIP_ASSIGNERS_COPY) {
            // When sending a REQUEST the lastUser to edit it should be the
            // ORGANIZER. I can't find any of the other properties that work
            // consistently.
            $vtodo->setAttribute('ORGANIZER', 'mailto: ' . $this->lastUser);
            $list = new Horde_Mail_Rfc822_List($this->owner);
            foreach ($list as $email) {
                $vtodo->setAttribute('ATTENDEE', $email, ['ROLE' => 'REQ-PARTICIPANT']);
            }
        }
        if ($this->due) {
            $vtodo->setAttribute('DUE', $this->due);
        }
        if ($this->start) {
            $vtodo->setAttribute('DTSTART', $this->start);
        }
        if ($this->completed) {
            $vtodo->setAttribute('COMPLETED', $this->completed);
        }

        if (isset($this->percentComplete)) {
            $vtodo->setAttribute('PERCENT-COMPLETE', $this->percentComplete);
        }

        // Summary is stored in the message data.
        $msg = $this->options['parent']->getMsgInfo();
        if ($msg->subject) {
            $vtodo->setAttribute('SUMMARY', $msg->subject);
        }

        // Figure out the body.
        if ($this->bodyPlain) {
            $vtodo->setAttribute('DESCRIPTION', $this->bodyPlain);
        } elseif ($this->bodyHtml) {
            $vtodo->setAttribute('DESCRIPTION', Horde_Text_Filter::filter($this->bodyHtml, 'html2text'));
        }

        $iCal->addComponent($vtodo);

        return [
            'type'    => 'text',
            'subtype' => 'calendar',
            'name'    => $msg->subject ? $msg->subject . '.vtodo' : 'Untitled.vtodo',
            'stream'  => $iCal->exportvCalendar(),
        ];
    }
}
