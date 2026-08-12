<?php

namespace App\Support;

use App\Domain\Platform\Services\DisplayTime;
use Carbon\CarbonInterface;

/**
 * Estado de apertura del parque para el CHIP del hero de la landing (decisión clienta 2026-06-14):
 * «{Día} · Abierto ahora» cuando realmente está abierto, o «{Día} · Abrimos en Xh» (o «mañana / el
 * {día} a las HH:MM») cuando está fuera de horario.
 *
 * Data-driven sobre la MISMA fuente que las reservas ({@see ParkSchedule}: special_dates > seasons >
 * opening_hours) y en la zona horaria del parque ({@see DisplayTime}). Devuelve `null` cuando NO hay
 * un horario concreto que anunciar, para no mostrar un «abierto» engañoso si el parque aún no ha
 * configurado sus horas. Asume ventanas dentro del mismo día (el parque no abre cruzando medianoche).
 */
class HeroStatus
{
    public function __construct(private readonly ParkSchedule $schedule) {}

    /**
     * @return array{open_now: bool, day: string, status: string}|null
     */
    public function current(): ?array
    {
        $now = now(DisplayTime::timezone());
        $day = (string) __('landing.info.weekdays.'.$now->dayOfWeek);
        $today = $this->schedule->effectiveFor($now);

        // Abierto AHORA: hoy abierto, con ventana concreta que cubre la hora actual.
        if ($today['is_open'] && $this->withinWindow($now, $today['open'], $today['close'])) {
            return ['open_now' => true, 'day' => $day, 'status' => (string) __('landing.hero.status_open')];
        }

        // Cerrado ahora → próxima apertura (hoy más tarde, o el próximo día con horario).
        $opensAt = $this->nextOpening($now);
        if ($opensAt === null) {
            return null; // sin horario configurado → no mostramos chip (evita un «abierto» falso)
        }

        return ['open_now' => false, 'day' => $day, 'status' => $this->opensLabel($now, $opensAt)];
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
        $today = $this->schedule->effectiveFor($now);
        if ($today['is_open'] && $today['open'] !== null) {
            $openAt = $now->copy()->setTimeFromTimeString($today['open']);
            if ($now->lessThan($openAt)) {
                return $openAt;
            }
        }

        // Próximos 7 días: el primer día abierto con hora de apertura concreta.
        for ($i = 1; $i <= 7; $i++) {
            $day = $now->copy()->addDays($i)->startOfDay();
            $eff = $this->schedule->effectiveFor($day);
            if ($eff['is_open'] && $eff['open'] !== null) {
                return $day->setTimeFromTimeString($eff['open']);
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
