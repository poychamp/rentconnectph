<?php

namespace App\Enums;

use Spatie\Enum\Enum;

/**
 * @method static self deactivated()
 * @method static self reactivated()
 * @method static self rejected()
 * @method static self reopened()
 * @method static self created()
 * @method static self updated()
 */
class LifecycleEventType extends Enum
{
    protected static function labels(): array
    {
        return [
            'deactivated' => 'Deactivated',
            'reactivated' => 'Reactivated',
            'rejected'    => 'Rejected',
            'reopened'    => 'Reopened',
            'created'     => 'Created',
            'updated'     => 'Updated',
        ];
    }
}
