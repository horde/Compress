<?php

declare(strict_types=1);

/**
 * Copyright 2003-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category  Horde
 * @copyright 2003-2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Compress
 */

namespace Horde\Compress\Driver;

use Horde\Compress\Base;
use Horde\Compress\Exception;
use Horde\Compress\Translation;

/**
 * DBX (Outlook Express) file reader.
 *
 * @category  Horde
 * @copyright 2003-2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Compress
 */
class Dbx extends Base
{
    private const FLAG_ARRAY = [
        0x1  => 'MsgFlags',
        0x2  => 'Sent',
        0x4  => 'position',
        0x7  => 'MessageID',
        0x8  => 'Subject',
        0x9  => 'From_reply',
        0xA  => 'References',
        0xB  => 'Newsgroup',
        0xD  => 'From',
        0xE  => 'Reply_To',
        0x12 => 'Received',
        0x13 => 'Receipt',
        0x1A => 'Account',
        0x1B => 'AccountID',
        0x80 => 'Msg',
        0x81 => 'MsgFlags',
        0x84 => 'position',
        0x91 => 'size',
    ];

    /** @var list<array{info: array, content: string}> */
    private array $mails = [];

    /** @var array<int, true> */
    private array $processed = [];

    public function canDecompress(): bool
    {
        return true;
    }

    public function decompress(string $data, array $params = []): array
    {
        $this->mails = [];
        $this->processed = [];

        $position = 0xC4;
        $headerInfo = unpack('Lposition/LDataLength/nHeaderLength/nFlagCount', substr($data, $position, 12));
        $position += 12;

        if ($headerInfo['position'] > 0) {
            $position = 0x30;
            $buf = unpack('Lposition', substr($data, $position, 4));
            $position = $buf['position'];
            $this->readIndex($data, $position);
        }

        return $this->mails;
    }

    private function readNullString(string $buf, int $pos): string
    {
        $len = strpos(substr($buf, $pos), chr(0));
        return $len ? substr($buf, $pos, $len) : '';
    }

    private function readMessage(string $data, int $position): string
    {
        $msg = '';

        if ($position > 0) {
            $indexItemsCount = unpack('S', substr($data, 0xC4, 4))[1];
            if ($indexItemsCount > 0) {
                while ($position < strlen($data)) {
                    $s = substr($data, $position, 528);
                    if (strlen($s) === 0) {
                        break;
                    }
                    $msgItem = unpack('LFilePos/LUnknown/LItemSize/LNextItem/Z512Content', $s);
                    if ($msgItem['FilePos'] != $position) {
                        throw new Exception(Translation::t("Invalid file format"));
                    }
                    $position += 528;
                    $msg .= substr($msgItem['Content'], 0, $msgItem['ItemSize']);
                    $position = $msgItem['NextItem'];
                    if ($position === 0) {
                        break;
                    }
                }
            }
        }

        return $msg;
    }

    private function readMessageInfo(string $data, int $position): array
    {
        $messageInfo = [];
        $msgHeader = unpack('Lposition/LDataLength/SHeaderLength/SFlagCount', substr($data, $position, 12));
        if ($msgHeader['position'] != $position) {
            throw new Exception(Translation::t("Invalid file format"));
        }
        $position += 12;
        $messageInfo['HeaderPosition'] = $msgHeader['position'];
        $flags = $msgHeader['FlagCount'] & 0xFF;
        $dataSize = $msgHeader['DataLength'] - $flags * 4;
        $size = 4 * $flags;
        $flagsBuffer = substr($data, $position, $size);
        $position += $size;
        $size = $dataSize;
        $dataBuffer = substr($data, $position, $size);
        $messageInfo = [];

        for ($i = 0; $i < $flags; ++$i) {
            $pos = 0;
            $f = unpack('L', substr($flagsBuffer, $i * 4, 4))[1];

            $mask = $f & 0xFF;
            switch ($mask) {
                case 0x1:
                    $pos += ($f >> 8);
                    $messageInfo['MsgFlags'] = unpack('C', substr($dataBuffer, $pos++, 1))[1];
                    $messageInfo['MsgFlags'] += unpack('C', substr($dataBuffer, $pos++, 1))[1] * 256;
                    $messageInfo['MsgFlags'] += unpack('C', substr($dataBuffer, $pos, 1))[1] * 65536;
                    break;

                case 0x2:
                case 0x4:
                    $pos += unpack('L', substr($flagsBuffer, $i * 4, 4))[1] >> 8;
                    $messageInfo[self::FLAG_ARRAY[$mask]] = unpack('L', substr($dataBuffer, $pos, 4))[1];
                    break;

                case 0x7:
                case 0x8:
                case 0x9:
                case 0xA:
                case 0xB:
                case 0xD:
                case 0xE:
                case 0x13:
                case 0x1A:
                    $pos += unpack('L', substr($flagsBuffer, $i * 4, 4))[1] >> 8;
                    $messageInfo[self::FLAG_ARRAY[$mask]] = $this->readNullString($dataBuffer, $pos);
                    break;

                case 0x12:
                    $pos += unpack('L', substr($flagsBuffer, $i * 4, 4))[1] >> 8;
                    $messageInfo['Received'] = unpack('L', substr($dataBuffer, $pos, 4))[1];
                    break;

                case 0x1B:
                    $pos += unpack('L', substr($flagsBuffer, $i * 4, 4))[1] >> 8;
                    $messageInfo['AccountID'] = intval($this->readNullString($dataBuffer, $pos));
                    break;

                case 0x80:
                case 0x81:
                case 0x84:
                case 0x91:
                    $messageInfo[self::FLAG_ARRAY[$mask]] = unpack('L', substr($flagsBuffer, $i * 4, 4))[1] >> 8;
                    break;
            }
        }

        return $messageInfo;
    }

    private function readIndex(string $data, int $position): void
    {
        $indexHeader = unpack('LFilePos/LUnknown1/LPrevIndex/LNextIndex/LCount/LUnknown', substr($data, $position, 24));
        if ($indexHeader['FilePos'] != $position) {
            throw new Exception(Translation::t("Invalid file format"));
        }

        $this->processed[$position] = true;

        if ($indexHeader['NextIndex'] > 0 && empty($this->processed[$indexHeader['NextIndex']])) {
            $this->readIndex($data, $indexHeader['NextIndex']);
        }
        if ($indexHeader['PrevIndex'] > 0 && empty($this->processed[$indexHeader['PrevIndex']])) {
            $this->readIndex($data, $indexHeader['PrevIndex']);
        }

        $position += 24;
        $icount = $indexHeader['Count'] >> 8;

        if ($icount > 0) {
            $buf = substr($data, $position, 12 * $icount);
            for ($i = 0; $i < $icount; $i++) {
                $hdrBuf = substr($buf, $i * 12, 12);
                $indexItem = unpack('LHeaderPos/LChildIndex/LUnknown', $hdrBuf);
                if ($indexItem['HeaderPos'] > 0) {
                    $info = $this->readMessageInfo($data, $indexItem['HeaderPos']);
                    $content = $this->readMessage($data, $info['position']);
                    $this->mails[] = ['info' => $info, 'content' => $content];
                }
                if ($indexItem['ChildIndex'] > 0 && empty($this->processed[$indexItem['ChildIndex']])) {
                    $this->readIndex($data, $indexItem['ChildIndex']);
                }
            }
        }
    }
}
