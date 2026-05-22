<?php

declare(strict_types=1);

/**
 * Copyright 2002-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * The TNEF rendering is based on code by:
 *   Graham Norbury <gnorbury@bondcar.com>
 * Original design by:
 *   Thomas Boll <tb@boll.ch>, Mark Simpson <damned@world.std.com>
 *
 * @category  Horde
 * @copyright 2002-2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Compress
 */

namespace Horde\Compress\Tnef;

use Horde\Compress\Base;
use Horde\Compress\Exception;
use Psr\Log\LoggerInterface;
use Horde_Date;
use Horde_Date_Exception;
use Horde_Mapi;
use Horde_Mapi_Exception;

/**
 * Decodes MS-TNEF (Transport Neutral Encapsulation Format) data.
 *
 * @category  Horde
 * @copyright 2002-2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Compress
 */
class TnefDecoder extends Base
{
    use BufferReaderTrait;

    public const PSETID_MEETING            = '{6ED8DA90-450B-101B-98DA-00AA003F1305}';
    public const PSETID_APPOINTMENT        = '{00062002-0000-0000-C000-000000000046}';
    public const PSETID_COMMON             = '{00062008-0000-0000-C000-000000000046}';
    public const PSETID_PUBLIC_STRINGS     = '{00020329-0000-0000-C000-000000000046}';
    public const PSETID_NOTE               = '{0006200E-0000-0000-C000-000000000046}';
    public const PSETID_TASK               = '{00062003-0000-0000-C000-000000000046}';
    public const PSETID_MAPI               = '{00020328-0000-0000-C000-000000000046}';

    public const SIGNATURE                 = 0x223e9f78;
    public const LVL_MESSAGE               = 0x01;
    public const LVL_ATTACHMENT            = 0x02;

    public const AOWNER                    = 0x60000;
    public const ASENTFOR                  = 0x60001;
    public const AORIGINALMCLASS           = 0x70006;
    public const ASUBJECT                  = 0x18004;
    public const ADATESENT                 = 0x38005;
    public const ADATERECEIVED             = 0x38006;
    public const AFROM                     = 0x08000;
    public const ASTATUS                   = 0x68007;
    public const AMCLASS                   = 0x78008;
    public const AMESSAGEID                = 0x18009;
    public const APARENTID                 = 0x1800a;
    public const ACONVERSATIONID           = 0x1800b;
    public const ABODY                     = 0x2800c;
    public const APRIORITY                 = 0x4800d;
    public const ATTACHDATA                = 0x6800f;
    public const AFILENAME                 = 0x18010;
    public const ATTACHMETAFILE            = 0x68011;
    public const ATTACHCREATEDATE          = 0x38012;
    public const ADATEMODIFIED             = 0x38020;
    public const ARENDDATA                 = 0x69002;
    public const AMAPIPROPS                = 0x69003;
    public const ARECIPIENTTABLE           = 0x69004;
    public const AMAPIATTRS                = 0x69005;
    public const OEMCODEPAGE               = 0x69007;
    public const AVERSION                  = 0x89006;
    public const ID_REQUEST_RESP           = 0x40009;
    public const ID_FROM                   = 0x8000;
    public const AIDOWNER                  = 0x50008;
    public const ID_DATE_START             = 0x30006;
    public const ID_DATE_END               = 0x30007;

    public const MAPI_TYPE_UNSPECIFIED     = 0x0000;
    public const MAPI_NULL                 = 0x0001;
    public const MAPI_SHORT                = 0x0002;
    public const MAPI_INT                  = 0x0003;
    public const MAPI_FLOAT                = 0x0004;
    public const MAPI_DOUBLE               = 0x0005;
    public const MAPI_CURRENCY             = 0x0006;
    public const MAPI_APPTIME              = 0x0007;
    public const MAPI_ERROR                = 0x000a;
    public const MAPI_BOOLEAN              = 0x000b;
    public const MAPI_OBJECT               = 0x000d;
    public const MAPI_INT8BYTE             = 0x0014;
    public const MAPI_STRING               = 0x001e;
    public const MAPI_UNICODE_STRING       = 0x001f;
    public const MAPI_SYSTIME              = 0x0040;
    public const MAPI_CLSID                = 0x0048;
    public const MAPI_BINARY               = 0x0102;

    public const MAPI_MEETING_INITIAL      = 0x00000001;
    public const MAPI_MEETING_FULL_UPDATE  = 0x100010000;
    public const MAPI_MEETING_INFO         = 0x00020000;

    public const MAPI_MESSAGE_CLASS        = 0x001A;
    public const MAPI_TAG_SUBJECT_PREFIX   = 0x003D;
    public const MAPI_CONVERSATION_TOPIC   = 0x0070;
    public const MAPI_SENT_REP_NAME        = 0x0042;
    public const MAPI_SENT_REP_EMAIL_ADDR  = 0x0065;
    public const MAPI_DISPLAY_TO           = 0x0e04;
    public const MAPI_SENT_REP_SMTP_ADDR   = 0x5d02;
    public const MAPI_IN_REPLY_TO_ID       = 0x1042;

    public const MAPI_CREATION_TIME        = 0x3007;
    public const MAPI_MODIFICATION_TIME    = 0x3008;
    public const MAPI_ATTACH_DATA          = 0x3701;
    public const MAPI_ATTACH_EXTENSION     = 0x3703;
    public const MAPI_ATTACH_LONG_FILENAME = 0x3707;
    public const MAPI_ATTACH_MIME_TAG      = 0x370E;
    public const MAPI_ORIGINAL_CREATORID   = 0x3FF9;
    public const MAPI_LAST_MODIFIER_NAME   = 0x3FFA;
    public const MAPI_CODEPAGE             = 0x3FFD;
    public const MAPI_SENDER_SMTP          = 0x5D01;

    public const MAPI_START_DATE                   = 0x0060;
    public const MAPI_END_DATE                     = 0x0061;
    public const MAPI_APPOINTMENT_SEQUENCE         = 0x8201;
    public const MAPI_BUSY_STATUS                  = 0x8205;
    public const MAPI_MEETING_REQUEST_TYPE         = 0x0026;
    public const MAPI_RESPONSE_REQUESTED           = 0x0063;
    public const MAPI_APPOINTMENT_LOCATION         = 0x8208;
    public const MAPI_APPOINTMENT_URL              = 0x8209;
    public const MAPI_APPOINTMENT_START_WHOLE      = 0x820D;
    public const MAPI_APPOINTMENT_END_WHOLE        = 0x820E;
    public const MAPI_APPOINTMENT_DURATION         = 0x8213;
    public const MAPI_APPOINTMENT_SUBTYPE          = 0x8215;
    public const MAPI_APPOINTMENT_RECUR            = 0x8216;
    public const MAPI_APPOINTMENT_STATE_FLAGS      = 0x8217;
    public const MAPI_RESPONSE_STATUS              = 0x8218;
    public const MAPI_RECURRING                    = 0x8223;
    public const MAPI_RECURRENCE_TYPE              = 0x8231;
    public const MAPI_ALL_ATTENDEES                = 0x8238;
    public const MAPI_TO_ATTENDEES                 = 0x823B;

    public const MAPI_TIMEZONE_STRUCT              = 0x8233;
    public const MAPI_TIMEZONE_DESCRIPTION         = 0x8234;
    public const MAPI_START_CLIP_START             = 0x8235;
    public const MAPI_START_CLIP_END               = 0x8236;
    public const MAPI_CONFERENCING_TYPE            = 0x8241;
    public const MAPI_ORGANIZER_ALIAS              = 0x8243;
    public const MAPI_APPOINTMENT_COUNTER_PROPOSAL = 0x8257;
    public const MAPI_TIMEZONE_START               = 0x825E;
    public const MAPI_TIMEZONE_END                 = 0x825F;
    public const MAPI_TIMEZONE_DEFINITION_RECUR    = 0x8260;
    public const MAPI_REMINDER_DELTA               = 0x8501;
    public const MAPI_SIGNAL_TIME                  = 0x8502;
    public const MAPI_REMINDER_SIGNAL_TIME         = 0x8560;
    public const MAPI_ENTRY_UID                    = 0x0003;
    public const MAPI_ENTRY_CLEANID                = 0x0023;
    public const MAPI_MEETING_TYPE                 = 0x0026;

    public const MSG_EDITOR_FORMAT                 = 0x5909;
    public const MSG_EDITOR_FORMAT_UNKNOWN         = 0;
    public const MSG_EDITOR_FORMAT_PLAIN           = 1;
    public const MSG_EDITOR_FORMAT_HTML            = 2;
    public const MSG_EDITOR_FORMAT_RTF             = 3;

    public const MAPI_NAMED_TYPE_ID                = 0x00;
    public const MAPI_NAMED_TYPE_STRING            = 0x01;
    public const MAPI_NAMED_TYPE_NONE              = 0xff;

    public const MAPI_MV_FLAG                      = 0x1000;

    public const IPM_MEETING_REQUEST               = 'IPM.Microsoft Schedule.MtgReq';
    public const IPM_MEETING_RESPONSE_POS          = 'IPM.Microsoft Schedule.MtgRespP';
    public const IPM_MEETING_RESPONSE_NEG          = 'IPM.Microsoft Schedule.MtgRespN';
    public const IPM_MEETING_RESPONSE_TENT         = 'IPM.Microsoft Schedule.MtgRespA';
    public const IPM_MEETING_REQUEST_CANCELLED     = 'IPM.Microsoft Schedule.MtgCncl';

    public const MAPI_MEETING_RESPONSE_POS         = 'IPM.Schedule.Meeting.Resp.Pos';
    public const MAPI_MEETING_RESPONSE_NEG         = 'IPM.Schedule.Meeting.Resp.Neg';
    public const MAPI_MEETING_RESPONSE_TENT        = 'IPM.Schedule.Meeting.Resp.Tent';

    public const IPM_TASK_REQUEST                  = 'IPM.TaskRequest';
    public const IPM_TASK_GUID                     = 0x8519;

    public const MAPI_TAG_BODY                     = 0x1000;
    public const MAPI_NATIVE_BODY                  = 0x1016;
    public const MAPI_TAG_HTML                     = 0x1013;
    public const MAPI_TAG_RTF_COMPRESSED           = 0x1009;

    public const RECUR_DAILY                       = 0x200A;
    public const RECUR_WEEKLY                      = 0x200B;
    public const RECUR_MONTHLY                     = 0x200C;
    public const RECUR_YEARLY                      = 0x200D;

    public const PATTERN_DAY                       = 0x0000;
    public const PATTERN_WEEK                      = 0x0001;
    public const PATTERN_MONTH                     = 0x0002;
    public const PATTERN_MONTH_END                 = 0x0004;
    public const PATTERN_MONTH_NTH                 = 0x0003;

    public const RECUR_END_DATE                    = 0x00002021;
    public const RECUR_END_N                       = 0x00002022;

    /** @var array<TnefObjectInterface> */
    protected array $files = [];

    /** @var array<self> */
    protected array $attachments = [];

    protected ?MessageData $msgInfo = null;

    protected ?TnefObjectInterface $currentObject = null;

    public function canCompress(): bool
    {
        return false;
    }

    public function canDecompress(): bool
    {
        return true;
    }

    public function decompress(string $data, array $params = []): array
    {
        $out = [];

        if ($this->readInt($data, 32) === self::SIGNATURE) {
            $this->logger->debug(sprintf(
                'TNEF: Signature: 0x%08X Key: 0x%04X',
                self::SIGNATURE,
                $this->readInt($data, 16)
            ));

            // Version
            $this->readInt($data, 8);
            $this->readInt($data, 32);
            $this->readBytes($data, $this->readInt($data, 32) ?? 0);
            $this->readInt($data, 16);

            // Codepage
            $this->readInt($data, 8);
            $this->readInt($data, 32);
            $this->readBytes($data, $this->readInt($data, 32) ?? 0);
            $this->readInt($data, 16);

            $this->msgInfo = new MessageData($this->logger);
            while (strlen($data) > 0) {
                switch ($this->readInt($data, 8)) {
                    case self::LVL_MESSAGE:
                        $this->logger->debug('DECODING LVL_MESSAGE property.');
                        $this->decodeMessageProperty($data);
                        break;

                    case self::LVL_ATTACHMENT:
                        $this->logger->debug('DECODING LVL_ATTACHMENT property.');
                        $this->decodeAttachment($data);
                        break;
                }
            }
        }

        foreach ($this->files as $object) {
            $out[] = $object->toArray();
        }

        return $out;
    }

    /** @return array<TnefObjectInterface> */
    public function getFiles(): array
    {
        return $this->files;
    }

    /** @return array<self> */
    public function getAttachments(): array
    {
        return $this->attachments;
    }

    public function getMsgInfo(): ?MessageData
    {
        return $this->msgInfo;
    }

    public function setCurrentObject(TnefObjectInterface $object): void
    {
        $this->currentObject = $object;
    }

    protected function extractMapiAttributes(string $data): void
    {
        $number = $this->readInt($data, 32);
        $this->logger->debug(sprintf('TNEF: Extracting %d MAPI attributes.', $number));

        while ((strlen($data) > 0) && $number--) {
            $haveMval = false;
            $numMval = 1;
            $value = null;
            $attrType = $this->readInt($data, 16);
            $attrName = $this->readInt($data, 16);
            $namespace = null;

            if (($attrType & self::MAPI_MV_FLAG) !== 0) {
                $haveMval = true;
                $attrType = $attrType & ~self::MAPI_MV_FLAG;
                $this->logger->debug(sprintf(
                    'TNEF: Multivalue attribute of type: 0x%04X',
                    $attrType
                ));
            }

            if (($attrName >= 0x8000) && ($attrName < 0xFFFE)) {
                $namespace = $this->toNamespaceGUID($this->readBytes($data, 16) ?? '');

                $namedType = $this->readInt($data, 32);
                switch ($namedType) {
                    case self::MAPI_NAMED_TYPE_ID:
                        $pid = $attrName;
                        $attrName = $this->readInt($data, 32);
                        $this->logger->debug(sprintf(
                            'TNEF: pid: 0x%X type: 0x%X Named Id: %s 0x%04X',
                            $pid,
                            $attrType,
                            $namespace,
                            $attrName
                        ));
                        break;

                    case self::MAPI_NAMED_TYPE_STRING:
                        $attrName = 0x9999;
                        $idLen = $this->readInt($data, 32);
                        $dataLen = $idLen + ((4 - ($idLen % 4)) % 4);
                        $name = substr($this->readBytes($data, $dataLen) ?? '', 0, $idLen);
                        $name = trim(mb_convert_encoding($name, 'UTF-8', 'UTF-16LE'));
                        $this->logger->debug(sprintf('TNEF: Named String Id: %s', $name));
                        break;

                    case self::MAPI_NAMED_TYPE_NONE:
                        continue 2;

                    default:
                        $this->logger->notice(sprintf(
                            'TNEF: Unknown NAMED type: pid: 0x%X type: 0x%X Named TYPE: %s 0x%04X',
                            $pid ?? 0,
                            $attrType,
                            $namespace,
                            $namedType
                        ));
                        continue 2;
                }
            }

            if ($haveMval) {
                $numMval = $this->readInt($data, 32);
                $this->logger->debug(sprintf('TNEF: Number of multivalues: %s', $numMval));
            }

            switch ($attrType) {
                case self::MAPI_NULL:
                case self::MAPI_TYPE_UNSPECIFIED:
                    break;

                case self::MAPI_SHORT:
                    $value = $this->readInt($data, 16);
                    $this->readInt($data, 16);
                    break;

                case self::MAPI_INT:
                case self::MAPI_BOOLEAN:
                    for ($i = 0; $i < $numMval; $i++) {
                        $value = $this->readInt($data, 32);
                    }
                    break;

                case self::MAPI_FLOAT:
                case self::MAPI_ERROR:
                    $value = $this->readBytes($data, 4);
                    break;

                case self::MAPI_DOUBLE:
                case self::MAPI_APPTIME:
                case self::MAPI_CURRENCY:
                case self::MAPI_INT8BYTE:
                case self::MAPI_SYSTIME:
                    $value = $this->readBytes($data, 8);
                    break;

                case self::MAPI_CLSID:
                    $this->logger->debug('TNEF: CLSID??');
                    $this->readBytes($data, 16);
                    break;

                case self::MAPI_STRING:
                case self::MAPI_UNICODE_STRING:
                case self::MAPI_BINARY:
                case self::MAPI_OBJECT:
                    $numVals = ($haveMval) ? $numMval : $this->readInt($data, 32);
                    for ($i = 0; $i < $numVals; $i++) {
                        $length = $this->readInt($data, 32);
                        $dataLen = $length + ((4 - ($length % 4)) % 4);
                        $value = substr($this->readBytes($data, $dataLen) ?? '', 0, $length);
                    }

                    if ($attrType === self::MAPI_UNICODE_STRING) {
                        $value = mb_convert_encoding($value, 'UTF-8', 'UTF-16LE');
                    }

                    if ($attrType === self::MAPI_STRING || $attrType === self::MAPI_UNICODE_STRING) {
                        $value = substr($value, 0, -1);
                    }
                    break;

                default:
                    throw new Exception(sprintf(
                        'TNEF: Unknown attribute type, "0x%X"',
                        $attrType
                    ));
            }

            $this->logger->debug(sprintf('TNEF: Attribute: 0x%X Type: 0x%X', $attrName, $attrType));

            switch ($attrName) {
                case self::MAPI_TAG_RTF_COMPRESSED:
                    $this->logger->debug('TNEF: Found compressed RTF text.');
                    $rtf = new Rtf($this->logger, $value);
                    $this->files[] = $rtf;
                    if ($this->currentObject) {
                        try {
                            $this->currentObject->setMapiAttribute($attrType, $attrName, $rtf->toPlain());
                        } catch (Exception $e) {
                            $this->logger->error(sprintf('TNEF: Unable to set attribute: %s', $e->getMessage()));
                        }
                    }
                    break;

                case self::MAPI_ATTACH_DATA:
                    $this->logger->debug('TNEF: Found nested MAPI object. Parsing.');
                    $nestedData = $value;
                    $this->readBytes($nestedData, 16);
                    $att = new self($this->logger);
                    $att->setCurrentObject($this->currentObject);
                    $att->decompress($nestedData);
                    $this->attachments[] = $att;
                    $this->logger->debug('TNEF: Completed nested attachment parsing.');
                    break;

                default:
                    try {
                        $this->msgInfo->setMapiAttribute($attrType, $attrName, $value);
                        if ($this->currentObject) {
                            $this->currentObject->setMapiAttribute($attrType, $attrName, $value, $namespace);
                        }
                    } catch (Exception $e) {
                        $this->logger->error(sprintf('TNEF: Unable to set attribute: %s', $e->getMessage()));
                    }
            }
        }
    }

    protected function decodeAttachment(string &$data): void
    {
        $attribute = $this->readInt($data, 32);
        $size = $this->readInt($data, 32);
        $value = $this->readBytes($data, $size) ?? '';
        $this->readInt($data, 16);

        switch ($attribute) {
            case self::ARENDDATA:
                $this->logger->debug('Creating new attachment.');
                if (!$this->currentObject instanceof VTodo) {
                    $this->currentObject = new File($this->logger);
                    $this->files[] = $this->currentObject;
                }
                break;

            case self::AFILENAME:
                $value = preg_replace('/.*[\/](.*)$/', '\1', $value);
                $value = str_replace("\0", '', $value);
                $this->currentObject?->setTnefAttribute($attribute, $value, $size);
                break;

            case self::ATTACHDATA:
                $this->currentObject?->setTnefAttribute($attribute, $value, $size);
                break;

            case self::AMAPIATTRS:
                $this->extractMapiAttributes($value);
                break;

            default:
                $this->currentObject?->setTnefAttribute($attribute, $value, $size);
        }
    }

    protected function decodeMessageProperty(string &$data): void
    {
        $attribute = $this->readInt($data, 32);
        $this->logger->debug(sprintf('TNEF: Message property 0x%X found.', $attribute));
        $value = false;

        switch ($attribute) {
            case self::AMCLASS:
                $messageClass = trim($this->decodeAttribute($data));
                $this->logger->debug(sprintf('TNEF: Message class: %s', $messageClass));
                switch ($messageClass) {
                    case self::IPM_MEETING_REQUEST:
                        $this->currentObject = new Icalendar($this->logger);
                        $this->currentObject->setMethod('REQUEST', $messageClass);
                        $this->files[] = $this->currentObject;
                        break;
                    case self::IPM_MEETING_RESPONSE_TENT:
                    case self::IPM_MEETING_RESPONSE_NEG:
                    case self::IPM_MEETING_RESPONSE_POS:
                        $this->currentObject = new Icalendar($this->logger);
                        $this->currentObject->setMethod('REPLY', $messageClass);
                        $this->files[] = $this->currentObject;
                        break;
                    case self::IPM_MEETING_REQUEST_CANCELLED:
                        $this->currentObject = new Icalendar($this->logger);
                        $this->currentObject->setMethod('CANCEL', $messageClass);
                        $this->files[] = $this->currentObject;
                        break;
                    case self::IPM_TASK_REQUEST:
                        $this->currentObject = new VTodo($this->logger, null, ['parent' => $this]);
                        $this->files[] = $this->currentObject;
                        break;
                    default:
                        $this->logger->debug(sprintf('Unknown message class: %s', $messageClass));
                }
                break;

            case self::AMAPIPROPS:
                $this->logger->debug('TNEF: Extracting encapsulated message properties (idMsgProps)');
                $properties = $this->decodeAttribute($data);
                $this->extractMapiAttributes($properties);
                break;

            case self::APRIORITY:
            case self::AOWNER:
            case self::ARECIPIENTTABLE:
            case self::ABODY:
            case self::ASTATUS:
            case self::ACONVERSATIONID:
            case self::APARENTID:
            case self::AMESSAGEID:
            case self::ASUBJECT:
            case self::AORIGINALMCLASS:
                $value = $this->decodeAttribute($data);
                break;

            case self::ADATERECEIVED:
            case self::ADATESENT:
            case self::ADATEMODIFIED:
            case self::ID_DATE_END:
                try {
                    $value = new Horde_Date(
                        Horde_Mapi::filetimeToUnixtime($this->decodeAttribute($data)),
                        'UTC'
                    );
                } catch (Horde_Mapi_Exception $e) {
                    throw new Exception($e->getMessage(), $e->getCode(), $e);
                } catch (Horde_Date_Exception $e) {
                    $this->logger->error(sprintf('TNEF: Unable to parse date string - %s', $e->getMessage()));
                }
                break;

            case self::AFROM:
            case self::ASENTFOR:
                $msgObj = $this->decodeAttribute($data);
                $displayName = $this->readBytes($msgObj, $this->readInt($msgObj, 16) ?? 0);
                $email = $this->readBytes($msgObj, $this->readInt($msgObj, 16) ?? 0);
                $value = $email;
                break;

            default:
                $size = $this->readInt($data, 32);
                $value = $this->readBytes($data, $size ?? 0);
                $this->readInt($data, 16);
        }

        if ($value && $this->currentObject) {
            $this->currentObject->setTnefAttribute(
                $attribute,
                $value,
                empty($size) ? strlen((string) $value) : $size
            );
        }
    }

    protected function decodeAttribute(string &$data): string
    {
        $size = $this->readInt($data, 32) ?? 0;
        $value = $this->readBytes($data, $size) ?? '';
        $this->readInt($data, 16);

        return $value;
    }

    protected function toNamespaceGUID(string $value): string
    {
        $guid = unpack('VV/v2v/n4n', $value);

        return sprintf(
            '{%08X-%04X-%04X-%04X-%04X%04X%04X}',
            $guid['V'],
            $guid['v1'],
            $guid['v2'],
            $guid['n1'],
            $guid['n2'],
            $guid['n3'],
            $guid['n4']
        );
    }
}
