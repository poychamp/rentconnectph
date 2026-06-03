<?php

namespace App\Enums;

use Spatie\Enum\Enum;

/**
 * @method static self notCalled()
 * @method static self calledYes()
 * @method static self noAnswer()
 */
class PrequalStatus extends Enum
{
    protected static function values(): array
    {
        return [
            'notCalled' => 'not_called',
            'calledYes' => 'called_yes',
            'noAnswer'  => 'no_answer',
        ];
    }

    protected static function labels(): array
    {
        return [
            'notCalled' => 'Not called',
            'calledYes' => 'Called',
            'noAnswer'  => 'No answer',
        ];
    }
}
