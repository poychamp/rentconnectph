<?php

namespace App\Enums;

use Spatie\Enum\Enum;

/**
 * @method static self deactivated()
 * @method static self reactivated()
 */
class LifecycleEventType extends Enum
{
    protected static function labels(): array
    {
        return [
            'deactivated' => 'Deactivated',
            'reactivated' => 'Reactivated',
        ];
    }
}
