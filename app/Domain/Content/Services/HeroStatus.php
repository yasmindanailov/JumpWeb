<?php

namespace App\Domain\Content\Services;

use App\Domain\Booking\Contracts\OperatingCalendar;
use App\Domain\Booking\Contracts\OperatingWindow;
use App\Domain\Platform\Services\DisplayTime;
use Carbon\CarbonInterface;

/**
 * Estado de apertura del parque para el CHIP del hero de la landing (decisión clienta 2026-06-14):
 * «{Día} · Abierto ahora» cuando realmente está abierto, o «{Día} · Abrimos en Xh» (o «mañana / el
 * {día} a las HH:MM») cuando está fuera de horario.
 *
 * Data-driven sobre la MISMA fuente que las reservas ({@see OperatingCalendar}: special_dates > seasons >
 * opening_hours) y en la zona horaria del parque ({@see DisplayTime}). Devuelve `null` cuando NO hay
 * un horario concreto que anunciar, para no mostrar un «abierto» engañoso si el parque aún no ha
 * configurado sus horas. Asume ventanas dentro del mismo día (el parque no abre cruzando medianoche).
 */
class HeroStatus
{
    public function __construct(private readonly OperatingCalendar $schedule) {}

    /**
     * ⚠️ **`closes_at` NO se deduce de `ScheduleDisplay::weeklyRows()`** y por eso viaja aquí
     * (`specs/idioma-visual-heredado.md` §3.quinquies.5): el `is_today` de aquella fila se apaga
     * cuando hoy lo gobierna una TEMPORADA o una FECHA ESPECIAL, así que quien la usara para decir
     * «hasta las 21:30» acertaría casi siempre y fallaría los días raros — que son justo los días
     * en que el visitante necesita el dato. Este servicio ya resuelve la ventana efectiva con la
     * MISMA fuente que las reservas; hasta ahora la calculaba y la tiraba.
     *
     * Es un campo AÑADIDO: `null` salvo cuando el parque está abierto ahora mismo, de modo que
     * ningún consumidor existente cambia de conducta (el chip del menú lee `day` y `status`).
     *
     * ❗❗❗ **LOS CUATRO ESTADOS SON UN CAMPO NUEVO Y `status` NO SE TOCA** (`#487`, sección 07).
     * El sistema del canvas lo escribe como regla dura: *«Un dato con hora tiene **cuatro** estados,
     * no tres: "hoy no abre" y "hoy ya ha cerrado" son hechos distintos, y decirlos igual deja el
     * titular contradiciendo a la tabla, que sigue enseñando las horas de hoy»*. Este servicio solo
     * distinguía DOS —abierto y «abrimos en…»—, así que un jueves ya cerrado y un lunes de cierre
     * decían lo mismo.
     *
     * ▶ **Entran `face`, `title` y `line` SIN tocar `status`**, y eso es deliberado: `status` lo leen
     * el chip del hero y el del menú, que son piezas del ARMAZÓN con su propio artboard. Cambiarlo
     * desde una tanda de sección movería el hero, que es justo lo que `#479` evitó con `/precios`.
     * ⚠️ La consecuencia, declarada: en la misma página el chip dice «Abrimos en 5 h» y la sección
     * dice «Abre hoy · Abre a las 16:30 y cierra a las 21:30». No se contradicen —uno es la cuenta
     * atrás y el otro el hecho del día— pero son dos redacciones del mismo estado.
     *
     * @return array{open_now: bool, day: string, status: string, closes_at: string|null,
     *               face: string, title: string, line: string|null}|null
     */
    public function current(): ?array
    {
        $now = now(DisplayTime::timezone());
        $day = (string) __('landing.info.weekdays.'.$now->dayOfWeek);
        $today = $this->schedule->windowFor($now);

        // Abierto AHORA: hoy abierto, con ventana concreta que cubre la hora actual.
        if ($today->isOpen && $this->withinWindow($now, $today->opensAt, $today->closesAt)) {
            $cierra = substr((string) $today->closesAt, 0, 5);

            return [
                'open_now' => true,
                'day' => $day,
                'status' => (string) __('landing.hero.status_open'),
                // Misma convención que `ScheduleDisplay` ('21:30:00' → '21:30').
                'closes_at' => $cierra,
                'face' => 'open',
                'title' => (string) __('landing.info.state.open'),
                'line' => (string) __('landing.info.state.open_line', ['time' => $cierra]),
            ];
        }

        // Cerrado ahora → próxima apertura (hoy más tarde, o el próximo día con horario).
        $opensAt = $this->nextOpening($now);
        if ($opensAt === null) {
            return null; // sin horario configurado → no mostramos chip (evita un «abierto» falso)
        }

        return [
            'open_now' => false,
            'day' => $day,
            'status' => $this->opensLabel($now, $opensAt),
            'closes_at' => null,
            ...$this->closedFace($now, $today, $opensAt),
        ];
    }

    /**
     * Las tres caras que NO son «abierto ahora», con su titular y su línea.
     *
     * ⚠️⚠️ **`later` y `closed_now` se distinguen por la HORA, no por el día**: los dos caen en un día
     * que abre. Lo que los separa es si la ventana de hoy está por venir o ya pasó — y es justo la
     * distinción que la regla dura pide, porque con la ventana pasada **la fila de hoy deja de estar
     * vigente** y el resaltado de la tabla tiene que apagarse.
     *
     * @return array{face: string, title: string, line: string|null}
     */
    private function closedFace(CarbonInterface $now, OperatingWindow $today, CarbonInterface $opensAt): array
    {
        $siguiente = $this->nextOpeningLine($now, $opensAt);

        // Hoy ABRE, y todavía no ha abierto: la ventana de hoy es la que hay que anunciar.
        if ($today->isOpen && $today->opensAt !== null && $now->lessThan($now->copy()->setTimeFromTimeString($today->opensAt))) {
            return [
                'face' => 'later',
                'title' => (string) __('landing.info.state.later'),
                'line' => (string) __('landing.info.state.later_line', [
                    'opens' => substr((string) $today->opensAt, 0, 5),
                    'closes' => substr((string) $today->closesAt, 0, 5),
                ]),
            ];
        }

        // Hoy abría y la ventana ya pasó.
        if ($today->isOpen) {
            return ['face' => 'closed_now', 'title' => (string) __('landing.info.state.closed_now'), 'line' => $siguiente];
        }

        // Hoy no abre.
        return ['face' => 'closed_today', 'title' => (string) __('landing.info.state.closed_today'), 'line' => $siguiente];
    }

    /**
     * «Mañana abre a las 11:30» / «El viernes abre a las 11:30».
     *
     * ⚠️ **No se escribe «mañana» a secas**: la próxima apertura puede caer en tres días, y una
     * frase quemada contra un dato variable miente — la misma regla por la que el titular no dice
     * «por la tarde».
     */
    private function nextOpeningLine(CarbonInterface $now, CarbonInterface $opensAt): string
    {
        $time = $opensAt->format('H:i');

        if ($opensAt->isSameDay($now->copy()->addDay())) {
            return (string) __('landing.info.state.next_tomorrow', ['time' => $time]);
        }

        return (string) __('landing.info.state.next_day', [
            'day' => mb_strtolower((string) __('landing.info.weekdays.'.$opensAt->dayOfWeek)),
            'time' => $time,
        ]);
    }

    /** ¿`$now` cae dentro de la ventana [open, close) del día? Requiere una ventana concreta. */
    private function withinWindow(CarbonInterface $now, ?string $open, ?string $close): bool
    {
        if ($open === null || $close === null) {
            return false; // sin ventana concreta → no afirmamos «abierto ahora»
        }

        $start = $now->copy()->setTimeFromTimeString($open);
        $end = $now->copy()->setTimeFromTimeString($close);

        return $end->greaterThan($start) && $now->greaterThanOrEqualTo($start) && $now->lessThan($end);
    }

    /** Instante de la próxima apertura desde `$now`, o null si no hay horario en los próximos 7 días. */
    private function nextOpening(CarbonInterface $now): ?CarbonInterface
    {
        // Hoy, si el parque abre MÁS TARDE.
        $today = $this->schedule->windowFor($now);
        if ($today->isOpen && $today->opensAt !== null) {
            $openAt = $now->copy()->setTimeFromTimeString($today->opensAt);
            if ($now->lessThan($openAt)) {
                return $openAt;
            }
        }

        // Próximos 7 días: el primer día abierto con hora de apertura concreta.
        for ($i = 1; $i <= 7; $i++) {
            $day = $now->copy()->addDays($i)->startOfDay();
            $eff = $this->schedule->windowFor($day);
            if ($eff->isOpen && $eff->opensAt !== null) {
                return $day->setTimeFromTimeString($eff->opensAt);
            }
        }

        return null;
    }

    /** Texto de «abrimos…»: cuenta atrás el mismo día; día + hora si es futuro. */
    private function opensLabel(CarbonInterface $now, CarbonInterface $opensAt): string
    {
        if ($opensAt->isSameDay($now)) {
            $minutes = (int) round(abs($now->diffInMinutes($opensAt)));
            $duration = $minutes < 60
                ? $minutes.' min'
                : ((int) round($minutes / 60)).' h';

            return (string) __('landing.hero.status_opens_in', ['duration' => $duration]);
        }

        $time = $opensAt->format('H:i');

        if ($opensAt->isSameDay($now->copy()->addDay())) {
            return (string) __('landing.hero.status_opens_tomorrow', ['time' => $time]);
        }

        return (string) __('landing.hero.status_opens_day', [
            'day' => mb_strtolower((string) __('landing.info.weekdays.'.$opensAt->dayOfWeek)),
            'time' => $time,
        ]);
    }
}
