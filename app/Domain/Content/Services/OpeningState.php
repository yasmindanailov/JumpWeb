<?php

namespace App\Domain\Content\Services;

use App\Domain\Booking\Contracts\OperatingCalendar;
use App\Domain\Platform\Services\DisplayTime;
use Carbon\CarbonInterface;

/**
 * **¿ESTÁ ABIERTO AHORA?, como HECHO y sin una sola palabra traducida** (F5 · T2 del menú,
 * `docs/specs/instancia-y-landing-fuera.md` §4.1).
 *
 * Esto vivía dentro de {@see HeroStatus}, mezclado con los rótulos del chip del hero —«Abrimos en 2h», «Hoy ya
 * hemos cerrado»—. Para la landing del producto valía; para la API pública no, por dos motivos:
 *
 *  1. Una landing de instancia escribe SUS palabras. Servirle «Abrimos en 2h» sería devolverle presentación
 *     al producto, que es justo lo que F5 saca de él.
 *  2. Y si el chip y la API calcularan por su cuenta, **podrían contradecirse** el día raro: una fecha
 *     especial, una temporada, un cierre a media tarde. El hecho se calcula UNA vez y cada transporte lo
 *     viste (mismo patrón que `SidebarBoot` en F4 · T1: un modelo de lectura, dos transportes).
 *
 * ⚠️ La ventana efectiva sale de {@see OperatingCalendar} —`special_dates` > `seasons` > `opening_hours`—,
 * que es **la misma fuente que las reservas**: un horario que la web anuncie y el embudo no respete es peor
 * que no anunciarlo.
 *
 * ⚠️ Sin horario configurado, `openNow` es `false` y `opensAt` es `null`: no se afirma «abierto» por defecto.
 * Asume ventanas dentro del mismo día (el parque no abre cruzando medianoche), igual que antes.
 */
class OpeningState
{
    public function __construct(private readonly OperatingCalendar $schedule) {}

    public function current(?CarbonInterface $now = null): OpeningNow
    {
        $now ??= now(DisplayTime::timezone());
        $hoy = $this->schedule->windowFor($now);

        if ($hoy->isOpen && $this->dentroDeLaVentana($now, $hoy->opensAt, $hoy->closesAt)) {
            return new OpeningNow(true, $this->hhmm($hoy->closesAt), null);
        }

        return new OpeningNow(false, null, $this->proximaApertura($now));
    }

    private function dentroDeLaVentana(CarbonInterface $now, ?string $abre, ?string $cierra): bool
    {
        if ($abre === null || $cierra === null) {
            return false;
        }

        $inicio = $now->copy()->setTimeFromTimeString($abre);
        $fin = $now->copy()->setTimeFromTimeString($cierra);

        return $fin->greaterThan($inicio) && $now->greaterThanOrEqualTo($inicio) && $now->lessThan($fin);
    }

    /**
     * La próxima apertura: hoy si aún no ha abierto, o el primer día de los siete siguientes con hora.
     *
     * ⚠️ Siete días y no «el siguiente laborable»: un parque puede cerrar una semana entera por obras con
     * fechas especiales, y entonces «mañana» sería mentira.
     */
    private function proximaApertura(CarbonInterface $now): ?CarbonInterface
    {
        $hoy = $this->schedule->windowFor($now);

        if ($hoy->isOpen && $hoy->opensAt !== null) {
            $abre = $now->copy()->setTimeFromTimeString($hoy->opensAt);

            if ($now->lessThan($abre)) {
                return $abre;
            }
        }

        for ($i = 1; $i <= 7; $i++) {
            $dia = $now->copy()->addDays($i)->startOfDay();
            $ventana = $this->schedule->windowFor($dia);

            if ($ventana->isOpen && $ventana->opensAt !== null) {
                return $dia->setTimeFromTimeString($ventana->opensAt);
            }
        }

        return null;
    }

    /** '21:30:00' → '21:30', la convención de todo el sistema. */
    private function hhmm(?string $hora): ?string
    {
        return $hora === null ? null : substr($hora, 0, 5);
    }
}
