<?php

namespace App\Enums;

use Spatie\Enum\Enum;

/**
 * @method static self new()
 * @method static self dead()
 */
class InquiryStatus extends Enum
{
    protected static function values(): array
    {
        return [
            'new'  => 'new',
            'dead' => 'dead',
        ];
    }

    protected static function labels(): array
    {
        return [
            'new'  => 'New',
            'dead' => 'Dead',
        ];
    }
}
