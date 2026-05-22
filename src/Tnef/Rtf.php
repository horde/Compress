<?php

declare(strict_types=1);

/**
 * Copyright 2002-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category  Horde
 * @copyright 2002-2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Compress
 */

namespace Horde\Compress\Tnef;

use Psr\Log\LoggerInterface;

/**
 * Parses RTF data encapsulated in a TNEF file.
 *
 * @category  Horde
 * @copyright 2002-2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Compress
 */
class Rtf extends TnefObject
{
    private const UNCOMPRESSED = 0x414c454d;
    private const COMPRESSED   = 0x75465a4c;

    private string $content = '';
    private int $size = 0;
    public string $type = 'application/rtf';

    public function __construct(?LoggerInterface $logger = null, ?string $data = null)
    {
        parent::__construct($logger, $data);
        $this->decode();
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function toArray(): array
    {
        return [
            'type'    => 'application',
            'subtype' => 'rtf',
            'name'    => 'Untitled.rtf',
            'stream'  => $this->content,
        ];
    }

    /**
     * Returns a plain text representation of the RTF document.
     */
    public function toPlain(): string
    {
        return $this->rtf2text($this->content);
    }

    private function decode(): void
    {
        $data = $this->data ?? '';
        $cSize = $this->readInt($data, 32);
        $this->size = $this->readInt($data, 32) ?? 0;
        $magic = $this->readInt($data, 32);
        $crc = $this->readInt($data, 32);

        $this->logger->debug(sprintf(
            'TNEF: compressed size: %s, size: %s, magic: %s, CRC: %s',
            $cSize,
            $this->size,
            $magic,
            $crc
        ));

        switch ($magic) {
            case self::COMPRESSED:
                $this->decompress($data);
                break;
            case self::UNCOMPRESSED:
                $this->content = $data;
                break;
            default:
                $this->logger->notice('TNEF: Unknown RTF compression.');
        }
    }

    private function decompress(string $data): void
    {
        $uncomp = '';
        $in = $out = $flags = $flagCount = 0;

        $preload = "{\\rtf1\\ansi\\mac\\deff0\\deftab720{\\fonttbl;}{\\f0\\fnil \\froman \\fswiss \\fmodern \\fscript \\fdecor MS Sans SerifSymbolArialTimes New RomanCourier{\\colortbl\\red0\\green0\\blue0\n\r\\par \\pard\\plain\\f0\\fs20\\b\\i\\u\\tab\\tx";
        $lengthPreload = strlen($preload);

        for ($cnt = 0; $cnt < $lengthPreload; $cnt++) {
            $uncomp .= $preload[$cnt];
            ++$out;
        }

        while ($out < ($this->size + $lengthPreload)) {
            if (($flagCount++ % 8) == 0) {
                $flags = ord($data[$in++]);
            } else {
                $flags = $flags >> 1;
            }

            if (($flags & 1) != 0) {
                $offset = ord($data[$in++]);
                $length = ord($data[$in++]);
                $offset = ($offset << 4) | ($length >> 4);
                $length = ($length & 0xF) + 2;
                $offset = ((int) ($out / 4096)) * 4096 + $offset;
                if ($offset >= $out) {
                    $offset -= 4096;
                }
                $end = $offset + $length;
                while ($offset < $end) {
                    $uncomp .= $uncomp[$offset++];
                    ++$out;
                }
            } else {
                $uncomp .= $data[$in++];
                ++$out;
            }
        }

        $this->content = substr_replace($uncomp, '', 0, $lengthPreload);
    }

    private function rtf2text(string $text): string
    {
        $document = '';
        $stack = [];
        $j = -1;

        for ($i = 0, $len = strlen($text); $i < $len; $i++) {
            $c = $text[$i];
            switch ($c) {
                case '\\':
                    $nextCharacter = $text[$i + 1];

                    if ($nextCharacter == '\\' && $this->rtfIsPlain($stack[$j] ?? [])) {
                        $document .= '\\';
                    } elseif ($nextCharacter == '~' && $this->rtfIsPlain($stack[$j] ?? [])) {
                        $document .= ' ';
                    } elseif ($nextCharacter == '_' && $this->rtfIsPlain($stack[$j] ?? [])) {
                        $document .= '-';
                    } elseif ($nextCharacter == '*') {
                        $stack[$j]['*'] = true;
                    } elseif ($nextCharacter == "'") {
                        $hex = substr($text, $i + 2, 2);
                        if ($this->rtfIsPlain($stack[$j] ?? [])) {
                            $document .= html_entity_decode('&#' . hexdec($hex) . ';');
                        }
                        $i += 2;
                    } elseif (($nextCharacter >= 'a' && $nextCharacter <= 'z')
                        || ($nextCharacter >= 'A' && $nextCharacter <= 'Z')
                    ) {
                        $word = '';
                        $param = null;
                        for ($k = $i + 1, $m = 0; $k < strlen($text); $k++, $m++) {
                            $nextCharacter = $text[$k];
                            if (($nextCharacter >= 'a' && $nextCharacter <= 'z')
                                || ($nextCharacter >= 'A' && $nextCharacter <= 'Z')
                            ) {
                                if (!empty($param)) {
                                    break;
                                }
                                $word .= $nextCharacter;
                            } elseif ($nextCharacter >= '0' && $nextCharacter <= '9') {
                                $param .= $nextCharacter;
                            } elseif ($nextCharacter == '-') {
                                if (!empty($param)) {
                                    break;
                                }
                                $param .= $nextCharacter;
                            } else {
                                break;
                            }
                        }

                        $i += $m - 1;

                        $toText = '';
                        switch (strtolower($word)) {
                            case 'u':
                                $toText .= html_entity_decode('&#x' . dechex((int) $param) . ';');
                                $ucDelta = $stack[$j]['uc'] ?? 0;
                                if ($ucDelta > 0) {
                                    $i += $ucDelta;
                                }
                                break;
                            case 'par':
                            case 'page':
                            case 'column':
                            case 'line':
                            case 'lbr':
                                $toText .= "\n";
                                break;
                            case 'emspace':
                            case 'enspace':
                            case 'qmspace':
                                $toText .= ' ';
                                break;
                            case 'tab':
                                $toText .= "\t";
                                break;
                            case 'chdate':
                                $toText .= date('m.d.Y');
                                break;
                            case 'chdpl':
                                $toText .= date('l, j F Y');
                                break;
                            case 'chdpa':
                                $toText .= date('D, j M Y');
                                break;
                            case 'chtime':
                                $toText .= date('H:i:s');
                                break;
                            case 'emdash':
                                $toText .= html_entity_decode('&mdash;');
                                break;
                            case 'endash':
                                $toText .= html_entity_decode('&ndash;');
                                break;
                            case 'bullet':
                                $toText .= html_entity_decode('&#149;');
                                break;
                            case 'lquote':
                                $toText .= html_entity_decode('&lsquo;');
                                break;
                            case 'rquote':
                                $toText .= html_entity_decode('&rsquo;');
                                break;
                            case 'ldblquote':
                                $toText .= html_entity_decode('&laquo;');
                                break;
                            case 'rdblquote':
                                $toText .= html_entity_decode('&raquo;');
                                break;
                            default:
                                $stack[$j][strtolower($word)] = empty($param) ? true : $param;
                                break;
                        }
                        if ($this->rtfIsPlain($stack[$j] ?? [])) {
                            $document .= $toText;
                        }
                    }
                    $i++;
                    break;
                case '{':
                    if (!empty($stack[$j])) {
                        array_push($stack, $stack[$j++]);
                    } else {
                        $j++;
                    }
                    break;
                case '}':
                    array_pop($stack);
                    $j--;
                    break;
                case "\0":
                case "\r":
                case "\f":
                case "\n":
                    break;
                default:
                    if (!empty($stack[$j]) && $this->rtfIsPlain($stack[$j])) {
                        $document .= $c;
                    }
                    break;
            }
        }

        return $document;
    }

    private function rtfIsPlain(array $s): bool
    {
        $notPlain = ['*', 'fonttbl', 'colortbl', 'datastore', 'themedata', 'stylesheet'];
        foreach ($notPlain as $key) {
            if (!empty($s[$key])) {
                return false;
            }
        }
        return true;
    }
}
