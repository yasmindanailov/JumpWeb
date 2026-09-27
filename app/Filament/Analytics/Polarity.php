<?php

namespace App\Filament\Analytics;

/**
 * **Si subir es bueno, malo o ninguna de las dos** (T0b de `analitica-para-decidir.md` §4.2, `#755`). Cada cifra del
 * cuadro la DECLARA —no hay valor por defecto—, porque sin ella el color del cambio miente: una devolución que sube
 * es mala, una encuesta «no preguntada» que sube también, y las búsquedas de la puerta no son ni buenas ni malas.
 */
enum Polarity: string
{
    case UpIsGood = 'up';
    case DownIsGood = 'down';
    case Neutral = 'neutral';
}
