<?php

namespace App\Enums;

use Spatie\Enum\Enum;

/**
 * @method static self owner()
 * @method static self authorizedRep()
 * @method static self broker()
 * @method static self caretaker()
 * @method static self coOwner()
 * @method static self familyMember()
 * @method static self agent()
 * @method static self propertyManager()
 * @method static self others()
 */
class ContactType extends Enum
{
    protected static function values(): array
    {
        return [
            'owner'           => 'owner',
            'authorizedRep'   => 'authorized_rep',
            'broker'          => 'broker',
            'caretaker'       => 'caretaker',
            'coOwner'         => 'co_owner',
            'familyMember'    => 'family_member',
            'agent'           => 'agent',
            'propertyManager' => 'property_manager',
            'others'          => 'others',
        ];
    }

    protected static function labels(): array
    {
        return [
            'owner'           => 'Owner',
            'authorizedRep'   => 'Authorized Rep',
            'broker'          => 'Broker',
            'caretaker'       => 'Caretaker',
            'coOwner'         => 'Co-Owner',
            'familyMember'    => 'Family Member',
            'agent'           => 'Agent',
            'propertyManager' => 'Property Manager',
            'others'          => 'Others',
        ];
    }
}
