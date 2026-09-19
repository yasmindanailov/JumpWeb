<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Booking\Contracts\OperatingCalendar;
use App\Domain\Platform\Services\DisplayTime;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * **`GET /api/v1/schedule` — el HORARIO como hechos** (F5 · T2 del menú,
 * `docs/specs/instancia-y-landing-fuera.md` §4.1).
 *
 * El segundo plato del menú, y el que más envejece si una landing lo teclea: un horario a mano se queda
 * viejo el primer festivo. Aquí sale de la MISMA fuente que las reservas —{@see OperatingCalendar}:
 * `special_dates` > `seasons` > `opening_hours`—, así que lo que la web anuncia es lo que el embudo
 * respeta. Un horario anunciado que el embudo no cumple es peor que no anunciarlo.
 *
 * ⚠️⚠️ **Sin una palabra traducida.** `ScheduleDisplay` existe para el producto y devuelve «Lunes», «Abre
 * hoy», rangos ya escritos; eso es presentación y en F5 la escribe quien diseña la landing. Aquí van números
 * y horas, y el nombre del día lo pone quien pinta.
 *
 * ⚠️⚠️ **`weekday` es 0 = DOMINGO**, la convención de Carbon y la de `Date.getDay()` en el navegador — NO la
 * ISO (1 = lunes … 7 = domingo). Publicarlo sin decirlo desplazaría la semana entera de una landing que
 * asumiera lo otro, y el error saldría un domingo. `ScheduleFactsTest` lo clava contra una fecha real.
 *
 * ⚠️ **El estado EN VIVO no está aquí**: vive en `/schedule/now` porque no se cachea igual. Éste son los
 * hechos del calendario y cambian cuando alguien toca el panel; aquél cambia dos veces al día, solo.
 */
class ScheduleFactsResource extends JsonResource
{
    public static $wrap = null;

    /** Cuántas fechas especiales se anuncian. Las que caben en un pie sin volverse un calendario. */
    private const PROXIMAS = 8;

    public function __construct(private readonly OperatingCalendar $calendario)
    {
        parent::__construct(null);
    }

    public function toArray(Request $request): array
    {
        return [
            // La zona horaria de la INSTALACIÓN, no la del servidor ni la de quien mira: sin ella, «21:30»
            // no significa nada para un cliente que calcule en UTC.
            'timezone' => DisplayTime::timezone(),
            'weekly' => array_map(fn ($dia): array => [
                'weekday' => $dia->weekday,
                'closed' => $dia->isClosed,
                'opens_at' => $this->hhmm($dia->opensAt),
                'closes_at' => $this->hhmm($dia->closesAt),
            ], $this->calendario->weeklyOpenings()),
            // Las temporadas PISAN al horario semanal en su rango (verano, Navidad). Van con sus fechas para
            // que una landing pueda decir «del 21 de junio al 21 de septiembre» sin adivinar.
            'seasons' => array_map(fn ($temporada): array => [
                'name' => $temporada->name,
                'starts_on' => $temporada->startsOn,
                'ends_on' => $temporada->endsOn,
                'opens_at' => $this->hhmm($temporada->opensAt),
                'closes_at' => $this->hhmm($temporada->closesAt),
                'current' => $temporada->isCurrent,
            ], $this->calendario->activeSeasons()),
            // Y las fechas especiales pisan a las dos. `closed: true` con horas a `null` es un cierre; con
            // horas, un día de horario distinto. `note` es lo que la instalación escribió («Fiesta Nacional»).
            'special_days' => array_map(fn ($dia): array => array_filter([
                'date' => $dia->date,
                'closed' => $dia->isClosed,
                'opens_at' => $this->hhmm($dia->opensAt),
                'closes_at' => $this->hhmm($dia->closesAt),
                'note' => $dia->note,
                'rate_label' => $dia->rateLabel,
            ], fn ($valor, string $clave): bool => $clave === 'closed' || $valor !== null, ARRAY_FILTER_USE_BOTH),
                $this->calendario->upcomingSpecialDays(self::PROXIMAS)),
        ];
    }

    /** '21:30:00' → '21:30', la convención de todo el sistema. */
    private function hhmm(?string $hora): ?string
    {
        return $hora === null ? null : substr($hora, 0, 5);
    }
}
