<?php

namespace App\Enums;

use Spatie\Enum\Enum;

/**
 * @method static self agusan()
 * @method static self balulang()
 * @method static self bayabas()
 * @method static self bonbon()
 * @method static self bugo()
 * @method static self bulua()
 * @method static self camamanAn()
 * @method static self canitoan()
 * @method static self carmen()
 * @method static self cogon()
 * @method static self consolacion()
 * @method static self corralesExtension()
 * @method static self cugman()
 * @method static self divisoria()
 * @method static self gusa()
 * @method static self indahag()
 * @method static self iponan()
 * @method static self jrBorjaExtension()
 * @method static self kauswagan()
 * @method static self lapasan()
 * @method static self limketkai()
 * @method static self lumbia()
 * @method static self macabalan()
 * @method static self macasandig()
 * @method static self nazareth()
 * @method static self pagatpat()
 * @method static self patag()
 * @method static self puebloDeOro()
 * @method static self puerto()
 * @method static self puntod()
 * @method static self tablon()
 * @method static self taguanao()
 * @method static self uptown()
 * @method static self others()
 */
class Barangay extends Enum
{
    protected static function values(): array
    {
        return [
            'agusan' => 'agusan',
            'balulang' => 'balulang',
            'bayabas' => 'bayabas',
            'bonbon' => 'bonbon',
            'bugo' => 'bugo',
            'bulua' => 'bulua',
            'camamanAn' => 'camaman_an',
            'canitoan' => 'canitoan',
            'carmen' => 'carmen',
            'cogon' => 'cogon',
            'consolacion' => 'consolacion',
            'corralesExtension' => 'corrales_extension',
            'cugman' => 'cugman',
            'divisoria' => 'divisoria',
            'gusa' => 'gusa',
            'indahag' => 'indahag',
            'iponan' => 'iponan',
            'jrBorjaExtension' => 'jr_borja_extension',
            'kauswagan' => 'kauswagan',
            'lapasan' => 'lapasan',
            'limketkai' => 'limketkai',
            'lumbia' => 'lumbia',
            'macabalan' => 'macabalan',
            'macasandig' => 'macasandig',
            'nazareth' => 'nazareth',
            'pagatpat' => 'pagatpat',
            'patag' => 'patag',
            'puebloDeOro' => 'pueblo_de_oro',
            'puerto' => 'puerto',
            'puntod' => 'puntod',
            'tablon' => 'tablon',
            'taguanao' => 'taguanao',
            'uptown' => 'uptown',
            'others' => 'others',
        ];
    }

    protected static function labels(): array
    {
        return [
            'agusan' => 'Agusan',
            'balulang' => 'Balulang',
            'bayabas' => 'Bayabas',
            'bonbon' => 'Bonbon',
            'bugo' => 'Bugo',
            'bulua' => 'Bulua',
            'camamanAn' => 'Camaman-an',
            'canitoan' => 'Canitoan',
            'carmen' => 'Carmen',
            'cogon' => 'Cogon',
            'consolacion' => 'Consolacion',
            'corralesExtension' => 'Corrales Extension',
            'cugman' => 'Cugman',
            'divisoria' => 'Divisoria',
            'gusa' => 'Gusa',
            'indahag' => 'Indahag',
            'iponan' => 'Iponan',
            'jrBorjaExtension' => 'JR Borja Extension',
            'kauswagan' => 'Kauswagan',
            'lapasan' => 'Lapasan',
            'limketkai' => 'Limketkai',
            'lumbia' => 'Lumbia',
            'macabalan' => 'Macabalan',
            'macasandig' => 'Macasandig',
            'nazareth' => 'Nazareth',
            'pagatpat' => 'Pagatpat',
            'patag' => 'Patag',
            'puebloDeOro' => 'Pueblo de Oro',
            'puerto' => 'Puerto',
            'puntod' => 'Puntod',
            'tablon' => 'Tablon',
            'taguanao' => 'Taguanao',
            'uptown' => 'Uptown',
            'others' => 'Others',
        ];
    }
}
