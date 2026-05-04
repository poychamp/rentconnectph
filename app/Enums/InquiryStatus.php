<?php

namespace App\Enums;

use Spatie\Enum\Enum;

/**
 * @method static self new()
 * @method static self handedOff()
 * @method static self dead()
 */
class InquiryStatus extends Enum
{
    protected static function values(): array
    {
        return [
            'new'        => 'new',
            'handedOff'  => 'handed_off',
            'dead'       => 'dead',
        ];
    }

    protected static function labels(): array
    {
        return [
            'new'        => 'New',
            'handedOff'  => 'Handed Off',
            'dead'       => 'Dead',
        ];
    }
}
