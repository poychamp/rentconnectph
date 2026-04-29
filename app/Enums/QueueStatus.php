<?php

namespace App\Enums;

use Spatie\Enum\Enum;

/**
 * @method static self unassigned()
 * @method static self assigned()
 * @method static self visited()
 * @method static self dead()
 */
class QueueStatus extends Enum
{
    protected static function values(): array
    {
        return [
            'unassigned' => 'unassigned',
            'assigned'   => 'assigned',
            'visited'    => 'visited',
            'dead'       => 'dead',
        ];
    }

    protected static function labels(): array
    {
        return [
            'unassigned' => 'Unassigned',
            'assigned'   => 'Assigned',
            'visited'    => 'Visited',
            'dead'       => 'Dead',
        ];
    }
}
