<?php

namespace App\Enums;

use Spatie\Enum\Enum;

/**
 * @method static self olx()
 * @method static self rentPh()
 * @method static self lamudi()
 * @method static self facebookGroup()
 * @method static self facebookMarketplace()
 * @method static self fieldDiscovery()
 * @method static self referral()
 */
class SourceSite extends Enum
{
    protected static function values(): array
    {
        return [
            'olx'                 => 'olx',
            'rentPh'              => 'rent_ph',
            'lamudi'              => 'lamudi',
            'facebookGroup'       => 'facebook_group',
            'facebookMarketplace' => 'facebook_marketplace',
            'fieldDiscovery'      => 'field_discovery',
            'referral'            => 'referral',
        ];
    }

    protected static function labels(): array
    {
        return [
            'olx'                 => 'OLX',
            'rentPh'              => 'Rent.ph',
            'lamudi'              => 'Lamudi',
            'facebookGroup'       => 'Facebook Group',
            'facebookMarketplace' => 'Facebook Marketplace',
            'fieldDiscovery'      => 'Field Discovery',
            'referral'            => 'Referral',
        ];
    }
}
