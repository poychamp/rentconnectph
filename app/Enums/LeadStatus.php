<?php

namespace App\Enums;

use Spatie\Enum\Enum;

/**
 * @method static self pending()
 * @method static self sent()
 * @method static self finalized()
 * @method static self lost()
 */
class LeadStatus extends Enum
{
    protected static function values(): array
    {
        return [
            'pending'   => 'pending',
            'sent'      => 'sent',
            'finalized' => 'finalized',
            'lost'      => 'lost',
        ];
    }

    protected static function labels(): array
    {
        return [
            'pending'   => 'Pending',
            'sent'      => 'Sent',
            'finalized' => 'Finalized',
            'lost'      => 'Lost',
        ];
    }
}
