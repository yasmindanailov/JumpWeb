<?php

namespace Tests\Feature\Analytics;

use App\Domain\Platform\Services\Analytics\Reports\Window;
use App\Filament\Analytics\Goal;
use App\Filament\Analytics\Metric;
use App\Filament\Analytics\Polarity;
use Carbon\CarbonImmutable;
use Tests\TestCase;

/**
 * **El objetivo del mes de una cifra** (T3c·2 de `specs/analitica-para-decidir.md` §4.13, `DECISIONES #759`): solo en un
 * mes de calendario; lo que se acumula se mide por el RITMO mientras el mes está en curso y por alcanzado o no cuando cierra;
 * una tasa, por encima o por debajo; los tres primeros días el ritmo no se juzga.
 *
 * Las ventanas se construyen a mano (junio de 2026 tiene 30 días: el día 16 a las 00:00 ha pasado justo la mitad) y los
 * rótulos se escriben A MANO (`#734`).
 */
class GoalTest extends TestCase
{
    private const TZ = 'Europe/Madrid';

    /** Junio de 2026 entero, como «Este mes» o «El mes pasado», cortado en `$now` si está en curso. */
    private function june(?string $now = null): Window
    {
        $window = Window::ofDays(CarbonImmutable::parse('2026-06-01', self::TZ), CarbonImmutable::parse('2026-06-30', self::TZ), self::TZ, Window::UNIT_MONTH);

        // `upTo` corta en el SEGUNDO siguiente: se le da el anterior para que el corte caiga en la hora exacta.
        return $now === null ? $window : $window->upTo(CarbonImmutable::parse($now, self::TZ)->subSecond());
    }

    private function money(int $cents): Metric
    {
        return Metric::money('money.net', 'Ingresos netos', $cents, null, 10, null, Polarity::UpIsGood, 'Definición de prueba.');
    }

    public function test_only_a_calendar_month_has_goals(): void
    {
        $this->assertTrue(Goal::applies($this->june()));
        $this->assertTrue(Goal::applies($this->june('2026-06-16 00:00:00')), 'en curso, también');

        $week = Window::ofDays(CarbonImmutable::parse('2026-06-08', self::TZ), CarbonImmutable::parse('2026-06-14', self::TZ), self::TZ, Window::UNIT_WEEK);
        $this->assertFalse(Goal::applies($week));

        // Del 1 al 30 a medida es un mes en el calendario, pero no es «Este mes»: se compara como un tramo, sin objetivo.
        $span = Window::ofDays(CarbonImmutable::parse('2026-06-01', self::TZ), CarbonImmutable::parse('2026-06-30', self::TZ), self::TZ, Window::UNIT_SPAN);
        $this->assertFalse(Goal::applies($span));
        $this->assertNull(Goal::of('money.net', 100, $span));
    }

    public function test_only_the_eight_figures_take_a_goal_and_it_is_above_zero(): void
    {
        $this->assertNotNull(Goal::of('money.net', 100, $this->june()));
        $this->assertNull(Goal::of('money.avg_order', 100, $this->june()), 'una media no tiene «objetivo del mes»');
        $this->assertNull(Goal::of('money.net', 0, $this->june()));
    }

    /** A mitad de mes: llevar el 60 % va al ritmo; el 40 %, por detrás; justo la mitad, al ritmo. */
    public function test_an_accumulating_figure_in_progress_is_judged_by_its_pace(): void
    {
        $goal = Goal::of('money.net', 2_000_000, $this->june('2026-06-16 00:00:00'));
        $this->assertNotNull($goal);

        $ahead = $goal->read($this->money(1_200_000));
        $this->assertSame(Goal::STATE_ON_PACE, $ahead['state']);
        $this->assertSame(Metric::TONE_GOOD, $ahead['tone']);
        $this->assertSame("Objetivo del mes: 20.000 €. Al ritmo: llevas el 60\u{00A0}% y ha pasado el 50\u{00A0}% del mes.", $ahead['line']);

        $behind = $goal->read($this->money(800_000));
        $this->assertSame(Goal::STATE_BEHIND, $behind['state']);
        $this->assertSame(Metric::TONE_WATCH, $behind['tone']);
        $this->assertSame("Objetivo del mes: 20.000 €. Por detrás del ritmo: llevas el 40\u{00A0}% y ha pasado el 50\u{00A0}% del mes.", $behind['line']);

        $this->assertSame(Goal::STATE_ON_PACE, $goal->state(1_000_000), 'justo al ritmo cuenta como al ritmo');
        $this->assertSame(Goal::STATE_BEHIND, $goal->state(999_999));
    }

    /** El día 3 (un 6,7 % del mes) no se juzga; el 4 a las 00:00 (un 10 %), ya sí. */
    public function test_the_first_days_do_not_judge_the_pace(): void
    {
        $early = Goal::of('money.net', 2_000_000, $this->june('2026-06-03 00:00:00'));
        $this->assertNotNull($early);
        $read = $early->read($this->money(0));
        $this->assertSame(Goal::STATE_EARLY, $read['state']);
        $this->assertSame(Metric::TONE_NEUTRAL, $read['tone']);
        $this->assertSame("Objetivo del mes: 20.000 €. Llevas el 0\u{00A0}%; aún es pronto para saber si vas al ritmo.", $read['line']);

        $judged = Goal::of('money.net', 2_000_000, $this->june('2026-06-04 00:00:00'));
        $this->assertNotNull($judged);
        $this->assertSame(Goal::STATE_BEHIND, $judged->state(0));
    }

    public function test_a_closed_month_is_reached_or_missed(): void
    {
        $goal = Goal::of('money.orders', 200, $this->june());
        $this->assertNotNull($goal);
        $orders = static fn (int $n): Metric => Metric::count('money.orders', 'Pedidos cobrados', $n, null, Polarity::UpIsGood, 'Definición de prueba.');

        $reached = $goal->read($orders(208));
        $this->assertSame(Goal::STATE_REACHED, $reached['state']);
        $this->assertSame(Metric::TONE_GOOD, $reached['tone']);
        $this->assertSame("Objetivo del mes: 200. Alcanzado (104\u{00A0}%).", $reached['line']);

        $this->assertSame(Goal::STATE_REACHED, $goal->state(200), 'llegar justo es alcanzarlo');

        $missed = $goal->read($orders(164));
        $this->assertSame(Goal::STATE_MISSED, $missed['state']);
        $this->assertSame(Metric::TONE_WATCH, $missed['tone']);
        $this->assertSame("Objetivo del mes: 200. No alcanzado (82\u{00A0}%).", $missed['line']);
    }

    /** Una tasa es un nivel: a mitad de mes, 7 % contra un 8 % está por debajo, no «al ritmo». */
    public function test_a_rate_is_above_or_below_its_level_also_in_progress(): void
    {
        $goal = Goal::of('traffic.conversion', 800, $this->june('2026-06-16 00:00:00'));
        $this->assertNotNull($goal);
        $conversion = static fn (int $orders): Metric => Metric::rate('traffic.conversion', 'Conversión', $orders, 1000, null, null, Polarity::UpIsGood, 'Definición de prueba.');

        $below = $goal->read($conversion(70));
        $this->assertSame(Goal::STATE_BELOW, $below['state'], 'el 7 % pasaría «el ritmo» del 50 % de un 8 %: una tasa no se acumula');
        $this->assertSame(Metric::TONE_WATCH, $below['tone']);
        $this->assertSame("Objetivo del mes: 8,0\u{00A0}%. Por debajo.", $below['line']);

        $above = $goal->read($conversion(80));
        $this->assertSame(Goal::STATE_ABOVE, $above['state'], 'llegar justo al nivel es estar por encima');
        $this->assertSame("Objetivo del mes: 8,0\u{00A0}%. Por encima.", $above['line']);
    }

    /** La cifra lleva su objetivo junto a su historia, en cualquier orden. */
    public function test_a_metric_keeps_its_goal_and_its_history(): void
    {
        $goal = Goal::of('money.net', 2_000_000, $this->june());
        $this->assertNotNull($goal);

        $metric = $this->money(1)->withGoal($goal)->withHistory([1, 2, 3], 'month');
        $this->assertSame($goal, $metric->goal);
        $this->assertSame([1, 2, 3], $metric->history);

        $other = $this->money(1)->withHistory([4], 'month')->withGoal($goal);
        $this->assertSame([4], $other->history);
        $this->assertSame($goal, $other->goal);
    }
}
