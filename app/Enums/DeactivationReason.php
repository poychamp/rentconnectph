<?php

namespace App\Enums;

use Spatie\Enum\Enum;

/**
 * @method static self rentedOut()
 * @method static self rentedOutClosed()
 * @method static self unavailable()
 * @method static self others()
 */
class DeactivationReason extends Enum
{
    protected static function values(): array
    {
        return [
            'rentedOut'       => 'rented_out',
            'rentedOutClosed' => 'rented_out_closed',
            'unavailable'     => 'unavailable',
            'others'          => 'others',
        ];
    }

    protected static function labels(): array
    {
        return [
            'rentedOut'       => 'Rented Out',                // off-platform: owner rented it, we didn't close
            'rentedOutClosed' => 'Rented Out (Closed)',       // closed deal — platform attributed, ROI tracked
            'unavailable'     => 'Unavailable',
            'others'          => 'Others',
        ];
    }
}
