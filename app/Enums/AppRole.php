<?php

namespace App\Enums;

use Spatie\Enum\Enum;

/**
 * @method static self superAdmin()
 * @method static self field()
 */
class AppRole extends Enum
{
    protected static function values(): array
    {
        return [
            'superAdmin' => 'super-admin',
            'field'      => 'field',
        ];
    }

    protected static function labels(): array
    {
        return [
            'superAdmin' => 'Super Admin',
            'field'      => 'Field Officer',
        ];
    }
}
