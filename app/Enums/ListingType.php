<?php

namespace App\Enums;

use Spatie\Enum\Enum;

/**
 * @method static self apartment()
 * @method static self studio()
 * @method static self house()
 * @method static self condo()
 * @method static self bedspacer()
 */
class ListingType extends Enum
{
    protected static function labels(): array
    {
        return [
            'apartment' => 'Apartment',
            'studio'    => 'Studio',
            'house'     => 'House',
            'condo'     => 'Condo',
            'bedspacer' => 'Bedspacer',
        ];
    }
}
