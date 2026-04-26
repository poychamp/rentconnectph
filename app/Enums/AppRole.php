<?php

namespace App\Enums;

use Spatie\Enum\Enum;

/**
 * @method static self superAdmin()
 */
class AppRole extends Enum
{
    protected static function values(): array
    {
        return ['superAdmin' => 'super-admin'];
    }

    protected static function labels(): array
    {
        return ['super-admin' => 'Super Admin'];
    }
}
