<?php

namespace App\Domain\Booking\Services;

use App\Domain\Booking\Models\WristbandColor;
use App\Domain\Platform\Models\Setting;

/**
 * **LA RUEDA DE LAS PULSERAS: qué color lleva cada hora** (`docs/specs/puerta-nueva.md` §4.4, la P2; D11).
 *
 * El mockup reparte las pulseras por la HORA de inicio de la reserva, no por la zona: seis colores, cada media hora, y
 * vuelta a empezar (17:00 naranja, 17:30 lila… 21:30 rosa). Aquí no hay ni un color: los colores «en la rueda» son del
 * parque ({@see WristbandColor}, en su orden), y la hora del PRIMERO y el paso son dos ajustes de la Puerta.
 *
 * ▶ **Periódica hacia los dos lados**: la hora solo ANCLA qué color va con qué hora; una reserva antes de ella (un
 * parque que abre antes) sigue la rueda hacia atrás. Y una hora a mitad de paso toma la del paso en curso (17:15 → 17:00).
 * Sin hora del primero, o sin colores en la rueda, no hay rueda: solo cuentan los colores fijos de los productos.
 */
final class WristbandWheel
{
    /** La hora del PRIMER color de la rueda, `H:i`. Vacía = sin rueda. */
    public const KEY_START = 'puerta.wristband_wheel_start';

    /** Cada cuántos minutos cambia de color. */
    public const KEY_STEP = 'puerta.wristband_wheel_step_minutes';

    public const STEP_DEFAULT = 30;

    public const STEP_MIN = 5;

    public const STEP_MAX = 240;

    /**
     * @param  int|null  $startMinutes  la hora del primero en minutos desde medianoche; `null` = sin rueda
     * @param  list<WristbandColor>  $colors  los de la rueda, en su orden
     */
    public function __construct(
        private readonly ?int $startMinutes,
        private readonly int $step,
        private readonly array $colors,
    ) {}

    /** La rueda del panel: dos ajustes y UNA consulta (los colores en la rueda), para toda una ficha. */
    public static function fromSettings(): self
    {
        $start = self::minutesOf((string) Setting::value(self::KEY_START, ''));
        $colors = $start === null ? [] : WristbandColor::query()->where('in_wheel', true)->orderBy('position')->orderBy('id')->get()->all();

        return new self($start, self::step(), array_values($colors));
    }

    /** El paso del panel, acotado; vacío o fuera de rango, el de por defecto. */
    public static function step(): int
    {
        $n = filter_var(Setting::value(self::KEY_STEP, (string) self::STEP_DEFAULT), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => self::STEP_MIN, 'max_range' => self::STEP_MAX],
        ]);

        return $n === false ? self::STEP_DEFAULT : $n;
    }

    /** El color de una reserva que empieza a `$startTime` (`H:i` o `H:i:s`); `null` sin rueda o sin hora. */
    public function colorFor(?string $startTime): ?WristbandColor
    {
        $time = $startTime === null ? null : self::minutesOf($startTime);
        if ($this->startMinutes === null || $this->colors === [] || $time === null) {
            return null;
        }

        $count = count($this->colors);
        $turns = (int) floor(($time - $this->startMinutes) / $this->step);

        return $this->colors[(($turns % $count) + $count) % $count];
    }

    /** `H:i` (o `H:i:s`) en minutos desde medianoche; `null` si no es una hora. */
    public static function minutesOf(string $time): ?int
    {
        if (preg_match('/^(\d{1,2}):(\d{2})(?::\d{2})?$/', trim($time), $m) !== 1) {
            return null;
        }
        $hours = (int) $m[1];
        $minutes = (int) $m[2];

        return $hours < 24 && $minutes < 60 ? $hours * 60 + $minutes : null;
    }
}
