<?php

namespace App\Enums;

use Spatie\Enum\Enum;

/**
 * @method static self adminAccess()
 * @method static self listingsManage()
 * @method static self listingsFieldWork()
 * @method static self usersManage()
 * @method static self systemAdmin()
 */
class AppPermission extends Enum
{
    protected static function values(): array
    {
        return [
            'adminAccess'       => 'admin.access',
            'listingsManage'    => 'listings.manage',
            'listingsFieldWork' => 'listings.field-work',
            'usersManage'       => 'users.manage',
            'systemAdmin'       => 'system.admin',
        ];
    }

    protected static function labels(): array
    {
        return [
            'adminAccess'       => 'Admin Access',
            'listingsManage'    => 'Manage Listings',
            'listingsFieldWork' => 'Field Work',
            'usersManage'       => 'Manage Users',
            'systemAdmin'       => 'System Admin',
        ];
    }
}
