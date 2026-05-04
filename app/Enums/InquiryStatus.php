<?php

namespace App\Enums;

use Spatie\Enum\Enum;

/**
 * @method static self new()
 * @method static self handedOff()
 * @method static self rejected()
 */
class InquiryStatus extends Enum
{
    protected static function values(): array
    {
        return [
            'new'        => 'new',
            'handedOff'  => 'handed_off',
            'rejected'   => 'rejected',
        ];
    }

    protected static function labels(): array
    {
        return [
            'new'        => 'New',
            'handedOff'  => 'Handed Off',
            'rejected'   => 'Rejected',
        ];
    }
}
