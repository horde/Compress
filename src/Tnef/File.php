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

use Horde\Mime\Magic;
use Psr\Log\LoggerInterface;

/**
 * Represents a file attachment within a TNEF message.
 *
 * @category  Horde
 * @copyright 2002-2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Compress
 */
class File extends TnefObject
{
    public ?string $name = null;
    public ?string $content = null;
    public ?string $metafile = null;
    public ?Date $created = null;
    public ?int $size = null;
    public ?string $type = null;
    public ?string $subtype = null;

    public function __construct(?LoggerInterface $logger = null)
    {
        parent::__construct($logger);
    }

    public function toArray(): array
    {
        return [
            'type' => $this->type ?? '',
            'subtype' => $this->subtype ?? '',
            'name' => $this->name ?? '',
            'size' => $this->size ?? 0,
            'stream' => $this->content ?? '',
        ];
    }

    public function setTnefAttribute(int $attribute, mixed $value, int $size): void
    {
        $this->logger->debug(sprintf(
            'TNEF: File::setTnefAttribute(0x%X, <value>, %d)',
            $attribute,
            $size
        ));

        switch ($attribute) {
            case TnefDecoder::AFILENAME:
                $this->name = preg_replace('/.*[\/](.*)$/', '\1', $value);
                break;

            case TnefDecoder::ATTACHDATA:
                $this->content = $value;
                $this->size = $size;
                break;

            case TnefDecoder::ATTACHMETAFILE:
                $this->metafile = $value;
                break;

            case TnefDecoder::ATTACHCREATEDATE:
                $this->created = new Date($value);
                break;
        }
    }

    public function setMapiAttribute(int $type, int $name, mixed $value): void
    {
        switch ($name) {
            case TnefDecoder::MAPI_ATTACH_LONG_FILENAME:
                $this->name = preg_replace('/.*[\/](.*)$/', '\1', $value);
                break;

            case TnefDecoder::MAPI_ATTACH_MIME_TAG:
                $mimeType = str_replace("\0", '', preg_replace('/^(.*)\/.*/', '\1', $value));
                $mimeSubtype = str_replace("\0", '', preg_replace('/.*\/(.*)$/', '\1', $value));
                $this->type = $mimeType;
                $this->subtype = $mimeSubtype;
                break;

            case TnefDecoder::MAPI_ATTACH_EXTENSION:
                $value = Magic::extToMime(str_replace("\0", '', $value));
                $mimeType = preg_replace('/^(.*)\/.*/', '\1', $value);
                $mimeSubtype = preg_replace('/.*\/(.*)$/', '\1', $value);
                $this->type = $mimeType;
                $this->subtype = $mimeSubtype;
                break;
        }
    }
}
