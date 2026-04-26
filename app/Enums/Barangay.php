<?php

namespace App\Enums;

use Spatie\Enum\Enum;

/**
 * @method static self carmen()
 * @method static self kauswagan()
 * @method static self lapasan()
 * @method static self macasandig()
 * @method static self nazareth()
 * @method static self puebloDeOro()
 * @method static self indahag()
 * @method static self uptown()
 * @method static self other()
 */
class Barangay extends Enum
{
    protected static function values(): array
    {
        return [
            'carmen' => 'carmen',
            'kauswagan' => 'kauswagan',
            'lapasan' => 'lapasan',
            'macasandig' => 'macasandig',
            'nazareth' => 'nazareth',
            'puebloDeOro' => 'pueblo_de_oro',
            'indahag' => 'indahag',
            'uptown' => 'uptown',
            'other' => 'other',
        ];
    }

    protected static function labels(): array
    {
        return [
            'carmen' => 'Carmen',
            'kauswagan' => 'Kauswagan',
            'lapasan' => 'Lapasan',
            'macasandig' => 'Macasandig',
            'nazareth' => 'Nazareth',
            'puebloDeOro' => 'Pueblo de Oro',
            'indahag' => 'Indahag',
            'uptown' => 'Uptown',
            'other' => 'Other',
        ];
    }
}
