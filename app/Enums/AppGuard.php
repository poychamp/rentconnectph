<?php

namespace App\Enums;

use Spatie\Enum\Enum;

/**
 * @method static self admin()
 */
class AppGuard extends Enum
{
    protected static function labels(): array
    {
        return ['admin' => 'Admin'];
    }
}
