<?php

namespace App\Domain\Platform\Services\Analytics;

use App\Domain\Platform\Services\DisplayTime;
use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * **Lo que un hecho de la fiesta calcula desde escalares** (`docs/specs/analitica-fiesta.md` §4.2).
 *
 * Vive en `Platform` y no ve ninguna reserva: recibe la FECHA de la fiesta y devuelve un número. Quien la
 * conoce (la capa de entrega, `Http\Concerns\RecordsPartyFacts`) es quien se la pasa.
 *
 * ⚠️ Los días se cuentan en el día del PARQUE, no en el del servidor: entre las dos medianoches (UTC y Madrid)
 * no es el mismo día, y una invitación abierta a las 00:30 de Madrid la víspera de la fiesta tiene que decir
 * «1», no «2». Por eso el reloj es `DisplayTime::today()` y no `now()` a secas.
 */
final class PartyFacts
{
    /**
     * Días que faltan para la fiesta, en días del parque: `0` el mismo día, negativo si ya pasó, `null` sin fecha.
     */
    public static function daysBefore(DateTimeInterface|string|null $partyDate): ?int
    {
        if ($partyDate === null || $partyDate === '') {
            return null;
        }

        $timezone = DisplayTime::timezone();
        $day = $partyDate instanceof DateTimeInterface ? $partyDate->format('Y-m-d') : (string) $partyDate;
        $party = CarbonImmutable::parse($day, $timezone)->startOfDay();
        $today = CarbonImmutable::instance(DisplayTime::today())->setTimezone($timezone)->startOfDay();

        return (int) $today->diffInDays($party, false);
    }
}
