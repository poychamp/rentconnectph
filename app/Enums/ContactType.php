<?php

namespace App\Enums;

use Spatie\Enum\Enum;

/**
 * @method static self owner()
 * @method static self authorizedRep()
 * @method static self broker()
 * @method static self caretaker()
 * @method static self other()
 */
class ContactType extends Enum
{
    protected static function values(): array
    {
        return [
            'owner'         => 'owner',
            'authorizedRep' => 'authorized_rep',
            'broker'        => 'broker',
            'caretaker'     => 'caretaker',
            'other'         => 'other',
        ];
    }

    protected static function labels(): array
    {
        return [
            'owner'         => 'Owner',
            'authorizedRep' => 'Authorized Rep',
            'broker'        => 'Broker',
            'caretaker'     => 'Caretaker',
            'other'         => 'Other',
        ];
    }
}
