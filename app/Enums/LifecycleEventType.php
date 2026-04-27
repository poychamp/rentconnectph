<?php

namespace App\Enums;

use Spatie\Enum\Enum;

/**
 * @method static self deactivated()
 */
class LifecycleEventType extends Enum
{
    protected static function labels(): array
    {
        return [
            'deactivated' => 'Deactivated',
        ];
    }
}
