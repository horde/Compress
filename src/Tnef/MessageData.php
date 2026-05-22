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
 * Holds properties of the TNEF message as a whole.
 *
 * @category  Horde
 * @copyright 2002-2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Compress
 */
class MessageData extends TnefObject
{
    public ?string $subject = null;
    public ?Date $dateSent = null;
    public ?string $from = null;
    public ?string $fromName = null;

    public function __construct(?LoggerInterface $logger = null)
    {
        parent::__construct($logger);
    }

    public function setTnefAttribute(int $attribute, mixed $value, int $size): void
    {
        switch ($attribute) {
            case TnefDecoder::ASUBJECT:
                $this->subject = trim($value);
                break;

            case TnefDecoder::ADATERECEIVED:
                if (!$this->dateSent) {
                    $this->dateSent = new Date($value);
                }
                break;

            case TnefDecoder::ADATESENT:
                $this->dateSent = new Date($value);
                break;
        }
    }

    public function setMapiAttribute(int $type, int $name, mixed $value): void
    {
        switch ($name) {
            case TnefDecoder::MAPI_CONVERSATION_TOPIC:
                $this->subject = $value;
                break;

            case TnefDecoder::MAPI_SENT_REP_EMAIL_ADDR:
                $this->from = $value;
                break;

            case TnefDecoder::MAPI_SENT_REP_NAME:
                $this->fromName = $value;
                break;
        }
    }
}
