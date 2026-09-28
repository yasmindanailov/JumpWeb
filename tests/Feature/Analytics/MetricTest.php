<?php

namespace Tests\Feature\Analytics;

use App\Domain\Platform\Enums\Comparison;
use App\Filament\Analytics\Metric;
use App\Filament\Analytics\Polarity;
use Tests\TestCase;

/**
 * **La anatomía de una cifra** (T0b de `specs/analitica-para-decidir.md` §4.2, `#755`): sin base no hay porcentaje, una
 * tasa se compara en puntos, el color solo si el cambio es claro al 95 %, la polaridad se declara. Medido el 27-09 en el
 * cuadro de entonces: «+900 %» (de 4 a 40), «Conversión 7,4 % · −20 %», el rojo y el verde sobre ruido.
 *
 * Los rótulos se escriben A MANO (`#734`). Los intervalos de Wilson se comprobaron a mano (25 de 338: del 5,06 % al
 * 10,69 %); la fórmula tiene su propio test en `ExperimentsReportTest`.
 */
class MetricTest extends TestCase
{
    private function how(): string
    {
        return 'Definición de prueba.';
    }

    // ─── Recuentos ──────────────────────────────────────────────────────────────────────────────

    public function test_a_small_base_gives_no_percent_and_no_colour(): void
    {
        $r = Metric::count('t.small', 'Contestadas', 40, 4, Polarity::UpIsGood, $this->how())->reading(Comparison::Previous);

        $this->assertSame('+36 frente al periodo anterior', $r['line'], 'ni «+900 %»');
        $this->assertSame('gray', $r['color']);
        $this->assertSame(['Pocos datos para comparar (menos de 20 casos antes)'], $r['notes']);
    }

    public function test_a_clear_change_is_coloured_by_its_polarity(): void
    {
        $up = Metric::count('t.up', 'Visitas', 100, 50, Polarity::UpIsGood, $this->how())->reading(Comparison::Previous);
        $this->assertSame("+50 (+100\u{00A0}%) frente al periodo anterior", $up['line'], 'el % va con espacio duro');
        $this->assertSame('success', $up['color']);
        $this->assertSame([], $up['notes']);

        $bad = Metric::count('t.down', 'No preguntadas', 100, 50, Polarity::DownIsGood, $this->how())->reading(Comparison::Previous);
        $this->assertSame('danger', $bad['color'], 'subir es malo');

        $neutral = Metric::count('t.neutral', 'Búsquedas en la puerta', 100, 50, Polarity::Neutral, $this->how())->reading(Comparison::Previous);
        $this->assertSame('gray', $neutral['color'], 'ni bueno ni malo');
        $this->assertSame([], $neutral['notes'], 'el cambio es claro, solo que no tiene signo');
    }

    /** 47 contra 34 cuentas nuevas: +38 %, y sin embargo puede ser azar (z ≈ 1,44). */
    public function test_an_unclear_change_stays_grey_and_says_so(): void
    {
        $r = Metric::count('t.unclear', 'Cuentas nuevas', 47, 34, Polarity::UpIsGood, $this->how())->reading(Comparison::Previous);

        $this->assertSame("+13 (+38\u{00A0}%) frente al periodo anterior", $r['line']);
        $this->assertSame('gray', $r['color']);
        $this->assertSame(['No es un cambio claro: puede ser azar'], $r['notes']);
    }

    /**
     * La prueba compara RITMOS: 310 en un mes de 31 días contra 250 en uno de 28 (10 y 8,9 al día) no es un cambio claro;
     * con las dos ventanas como si durasen lo mismo, lo parecería (z ≈ 2,5).
     */
    public function test_the_length_of_each_window_decides_whether_a_count_changed(): void
    {
        $metric = Metric::count('t.share', 'Visitas', 310, 250, Polarity::UpIsGood, $this->how());

        $this->assertSame('gray', $metric->reading(Comparison::Previous, 31 / 59)['color']);
        $this->assertSame('success', $metric->reading(Comparison::Previous, 0.5)['color']);
    }

    /**
     * Plazas que llegan en LOTES (T3a, `#759`): 300 plazas en 30 reservas de 10 contra 200 en 20. Como recuento parecería un
     * cambio claro (z ≈ 4,5); como suma de lotes no lo es (z ≈ 1,4): son diez reservas más. Y la base son las reservas.
     */
    public function test_units_that_come_in_batches_are_tested_as_a_sum_of_their_batches(): void
    {
        $batches = Metric::units('t.units', 'Visitantes', 300, 200, 30, 20, 30 * 10 * 10, 20 * 10 * 10, Polarity::UpIsGood, $this->how())->reading(Comparison::Previous);
        $this->assertSame("+100 (+50\u{00A0}%) frente al periodo anterior", $batches['line']);
        $this->assertSame('gray', $batches['color']);
        $this->assertSame(['No es un cambio claro: puede ser azar'], $batches['notes']);

        $this->assertSame('success', Metric::count('t.units.count', 'Visitantes', 300, 200, Polarity::UpIsGood, $this->how())->reading(Comparison::Previous)['color'], 'contadas una a una, sí lo parecería');

        // Con reservas de UNA plaza, la suma de lotes es el recuento de siempre: el mismo veredicto.
        $this->assertSame('success', Metric::units('t.units.ones', 'Visitantes', 300, 200, 300, 200, 300, 200, Polarity::UpIsGood, $this->how())->reading(Comparison::Previous)['color']);

        // Pocas reservas antes (19), aunque sean muchas plazas: sin porcentaje ni color.
        $few = Metric::units('t.units.few', 'Visitantes', 400, 380, 20, 19, 20 * 400, 19 * 400, Polarity::UpIsGood, $this->how())->reading(Comparison::Previous);
        $this->assertSame('+20 frente al periodo anterior', $few['line']);
        $this->assertSame(['Pocos datos para comparar (menos de 20 casos antes)'], $few['notes']);
    }

    // ─── ¿Es normal para ti? (T3b, `#790`) ───────────────────────────────────────────────────────

    /** Normal es el rango mín–máx de su historia, con los dos bordes dentro (`[DECIDIDO owner]` 28-09). */
    public function test_normal_is_between_the_lowest_and_the_highest_of_its_history(): void
    {
        $history = [150000, 120000, 190000, 160000, 140000, 170000, 130000, 180000];

        foreach ([120000 => 'normal', 190000 => 'normal', 155000 => 'normal', 119999 => 'low', 190001 => 'high'] as $value => $state) {
            $v = Metric::money('t.band', 'Ingresos netos', $value, null, 30, null, Polarity::UpIsGood, $this->how())->withHistory($history, 'month')->verdict();
            $this->assertSame($state, $v['state'], "{$value} céntimos");
            $this->assertSame([120000, 190000, 8], [$v['low'], $v['high'], $v['n']]);
        }

        $this->assertSame(
            'Normal para ti: entre 1.200 € y 1.900 € en tus últimos 8 meses.',
            Metric::money('t.band', 'Ingresos netos', 155000, null, 30, null, Polarity::UpIsGood, $this->how())->withHistory($history, 'month')->verdictLine(),
        );
    }

    /** El tono sale de la polaridad: subir es bien, mal o ni lo uno ni lo otro; y la frase lo dice con palabra. */
    public function test_the_tone_follows_the_polarity_and_the_sentence_says_it(): void
    {
        $history = [10, 12, 14, 16, 18, 20, 22, 24, 26, 28, 30, 32];
        $at = static fn (int $value, Polarity $p): Metric => Metric::count('t.tone', 'Pedidos cobrados', $value, null, $p, 'Definición.')->withHistory($history, 'week');

        $this->assertSame(['high', 'good'], [$at(40, Polarity::UpIsGood)->verdict()['state'], $at(40, Polarity::UpIsGood)->verdict()['tone']]);
        $this->assertSame('watch', $at(5, Polarity::UpIsGood)->verdict()['tone']);
        $this->assertSame('watch', $at(40, Polarity::DownIsGood)->verdict()['tone']);
        $this->assertSame('good', $at(5, Polarity::DownIsGood)->verdict()['tone']);
        $this->assertSame('neutral', $at(40, Polarity::Neutral)->verdict()['tone']);
        $this->assertSame('neutral', $at(20, Polarity::UpIsGood)->verdict()['tone'], 'lo normal no es ni bien ni mal');

        // «últimAs semanas»: el adjetivo concuerda con la unidad (la primera versión decía «tus últimos 12 semanas»).
        $this->assertSame('Bien: la más alta de tus últimas 12 semanas (iba de 10 a 32).', $at(40, Polarity::UpIsGood)->verdictLine());
        $this->assertSame('Atención: la más baja de tus últimas 12 semanas (iba de 10 a 32).', $at(5, Polarity::UpIsGood)->verdictLine());
        $this->assertSame('Fuera de lo normal: la más alta de tus últimas 12 semanas (iba de 10 a 32).', $at(40, Polarity::Neutral)->verdictLine());
        $this->assertSame(
            'Normal para ti: entre 10 y 32 en tus últimos 12 miércoles.',
            Metric::count('t.day', 'Pedidos cobrados', 20, null, Polarity::UpIsGood, 'Definición.')->withHistory($history, 'day:3')->verdictLine(),
        );
    }

    /**
     * Una historia PLANA se dice «siempre», no «entre 0,00 € y 0,00 €» (visto el 28-09 en el navegador, un lunes a primera
     * hora); y una MEDIA con pocos casos no se juzga, como una tasa.
     */
    public function test_a_flat_history_says_always_and_a_mean_with_few_cases_is_not_judged(): void
    {
        $zeros = array_fill(0, 12, 0);
        $net = static fn (int $cents): Metric => Metric::money('t.flat', 'Ingresos netos', $cents, null, 0, null, Polarity::UpIsGood, 'Definición.')->withHistory($zeros, 'week');

        $this->assertSame('Normal para ti: siempre 0,00 € en tus últimas 12 semanas.', $net(0)->verdictLine());
        $this->assertSame('Bien: la más alta de tus últimas 12 semanas (siempre había sido 0,00 €).', $net(5000)->verdictLine());

        $mean = Metric::money('t.mean', 'Valor medio del pedido', 7000, null, 19, null, Polarity::UpIsGood, $this->how(), mean: true)->withHistory(array_fill(0, 12, 6000), 'month');
        $this->assertSame('few', $mean->verdict()['state'], '19 pedidos');
    }

    /** Sin 8 periodos no se juzga, y se dice cuántos hay; en un día, la unidad es su día de la semana. */
    public function test_without_eight_periods_it_says_how_many_there_are(): void
    {
        $m = Metric::count('t.few', 'Visitas a la web', 30, null, Polarity::UpIsGood, $this->how())->withHistory([20, 25, 30], 'day:3');

        $this->assertSame(['no_history', 'neutral', 3], [$m->verdict()['state'], $m->verdict()['tone'], $m->verdict()['n']]);
        $this->assertSame('Aún sin historia para decir si es normal (3 de 8 miércoles).', $m->verdictLine());
    }

    /** Una tasa con pocos casos no se juzga; una cifra sin historia (sin comparación) o de texto, ni se dice. */
    public function test_a_rate_with_few_cases_is_not_judged_and_without_history_nothing_is_said(): void
    {
        $rate = Metric::rate('t.rate', 'Conversión', 1, 12, null, null, Polarity::UpIsGood, $this->how())->withHistory(array_fill(0, 12, 500), 'month');
        $this->assertSame('few', $rate->verdict()['state'], '12 visitas');
        $this->assertSame('Pocos casos para decir si es normal (menos de 20).', $rate->verdictLine());

        $this->assertNull(Metric::count('t.none', 'Fichas abiertas', 5, null, Polarity::Neutral, $this->how())->verdict());
        $this->assertNull(Metric::text('t.text', 'Nota media', '4,2 / 5', $this->how())->withHistory([1, 2, 3, 4, 5, 6, 7, 8], 'month')->verdict());
    }

    public function test_zero_before_is_no_data_and_equal_is_equal(): void
    {
        $none = Metric::count('t.none', 'Fiestas', 15, 0, Polarity::UpIsGood, $this->how())->reading(Comparison::YearAgo);
        $this->assertSame('Sin datos el año pasado', $none['line'], 'ni «+15 frente al año pasado»');
        $this->assertSame('gray', $none['color']);

        $same = Metric::count('t.same', 'Fiestas', 15, 15, Polarity::UpIsGood, $this->how())->reading(Comparison::Previous);
        $this->assertSame('Igual que el periodo anterior', $same['line']);

        $noComparison = Metric::count('t.nc', 'Compradores', 9, null, Polarity::UpIsGood, $this->how())->reading(Comparison::Previous);
        $this->assertNull($noComparison['line']);
    }

    // ─── Dinero ─────────────────────────────────────────────────────────────────────────────────

    public function test_money_takes_its_base_from_its_operations_and_drops_the_cents_from_100_euros(): void
    {
        $few = Metric::money('t.money.few', 'Cobrado', 150000, 100000, 12, 10, Polarity::UpIsGood, $this->how(), squares: 1, previousSquares: 1)->reading(Comparison::Previous);
        $this->assertSame('+500 € frente al periodo anterior', $few['line'], 'diez cobros no dan un porcentaje');
        $this->assertSame('gray', $few['color']);

        $this->assertSame('1.500 €', Metric::money('t.money', 'Cobrado', 150000, 100000, 30, 25, Polarity::UpIsGood, $this->how())->displayValue());
        $this->assertSame('44,98 €', Metric::money('t.small', 'Devuelto', 4498, null, 1, null, Polarity::DownIsGood, $this->how())->displayValue());
    }

    /** Sin la suma de los cuadrados de sus importes no se sabe si el cambio de dinero es claro: gris, nunca rojo. */
    public function test_money_without_its_squares_is_never_coloured(): void
    {
        $r = Metric::money('t.money.nosq', 'Cobrado', 15000000, 10000000, 3000, 2000, Polarity::UpIsGood, $this->how())->reading(Comparison::Previous);

        $this->assertSame("+50.000 € (+50\u{00A0}%) frente al periodo anterior", $r['line']);
        $this->assertSame('gray', $r['color']);
        $this->assertSame(['No es un cambio claro: puede ser azar'], $r['notes']);
    }

    /**
     * Una SUMA de importes (Poisson compuesto): 3.000 cobros de 50 € contra 2.000 es un cambio claro (z ≈ 14); 30 contra
     * 25, con +50 % en euros, no (z ≈ 1,5: lo explica que vinieran cinco personas más).
     */
    public function test_a_sum_of_money_is_tested_with_the_squares_of_its_amounts(): void
    {
        $clear = Metric::money('t.sum', 'Cobrado', 15000000, 10000000, 3000, 2000, Polarity::UpIsGood, $this->how(), squares: 3000 * 5000 * 5000, previousSquares: 2000 * 5000 * 5000);
        $this->assertSame('success', $clear->reading(Comparison::Previous)['color']);

        // z ≈ 1,44 con la varianza de LOS DOS periodos; con la de uno solo saldría 2,04 y parecería claro.
        $noise = Metric::money('t.sum.noise', 'Cobrado', 150000, 100000, 30, 25, Polarity::UpIsGood, $this->how(), squares: 600000000, previousSquares: 600000000);
        $this->assertSame('gray', $noise->reading(Comparison::Previous)['color']);
        $this->assertSame(['No es un cambio claro: puede ser azar'], $noise->reading(Comparison::Previous)['notes']);

        // Y compara RITMOS: 310 cobros de 50 € en 31 días contra 250 en 28 no es claro (z ≈ 1,3); como si durasen lo mismo, sí (≈ 2,5).
        $months = Metric::money('t.sum.share', 'Cobrado', 1550000, 1250000, 310, 250, Polarity::UpIsGood, $this->how(), squares: 310 * 5000 * 5000, previousSquares: 250 * 5000 * 5000);
        $this->assertSame('gray', $months->reading(Comparison::Previous, 31 / 59)['color']);
        $this->assertSame('success', $months->reading(Comparison::Previous, 0.5)['color']);
    }

    /**
     * Una MEDIA (valor medio del pedido): −1 % con 52 y 45 pedidos que varían como su media no es un cambio (el 27-09 salía
     * en ROJO); de 50 € a 100 € con importes casi iguales, sí.
     */
    public function test_a_mean_of_money_is_tested_as_a_mean(): void
    {
        // Importes con una desviación del 80 % de su media: la suma de cuadrados es n · 1,64 · media².
        $minusOne = Metric::money('t.mean', 'Valor medio del pedido', 7252, 7355, 52, 45, Polarity::UpIsGood, $this->how(), squares: (int) round(52 * 1.64 * 7252 ** 2), previousSquares: (int) round(45 * 1.64 * 7355 ** 2), mean: true);
        $r = $minusOne->reading(Comparison::Previous);
        $this->assertSame("−1,03 € (−1\u{00A0}%) frente al periodo anterior", $r['line']);
        $this->assertSame('gray', $r['color'], 'ni rojo por un 1 %');

        $double = Metric::money('t.mean.clear', 'Valor medio del pedido', 10000, 5000, 40, 40, Polarity::UpIsGood, $this->how(), squares: 40 * 10000 ** 2 + 40, previousSquares: 40 * 5000 ** 2 + 40, mean: true);
        $this->assertSame('success', $double->reading(Comparison::Previous)['color']);

        // La varianza es la de los importes ALREDEDOR de su media: veinte pedidos de 100 € contra veinte de 90 €, casi
        // iguales entre sí, son un cambio claro (con la suma de cuadrados sin restar la media, z ≈ 0,3: parecería ruido).
        $close = Metric::money('t.mean.close', 'Valor medio del pedido', 10000, 9000, 20, 20, Polarity::UpIsGood, $this->how(), squares: 20 * 10000 ** 2 + 20, previousSquares: 20 * 9000 ** 2 + 20, mean: true);
        $this->assertSame('success', $close->reading(Comparison::Previous)['color']);
    }

    // ─── Tasas ──────────────────────────────────────────────────────────────────────────────────

    /** 25 compras de 338 visitas contra 20 de 250: −0,6 puntos, y los intervalos se solapan. */
    public function test_a_rate_compares_in_points_and_carries_its_interval(): void
    {
        $metric = Metric::rate('t.rate', 'Conversión', 25, 338, 20, 250, Polarity::UpIsGood, $this->how());
        $r = $metric->reading(Comparison::Previous);

        $this->assertSame("7,4\u{00A0}%", $metric->displayValue());
        $this->assertSame('−0,6 puntos frente al periodo anterior', $r['line'], 'ni «−8 %» de un porcentaje');
        $this->assertSame('gray', $r['color']);
        $this->assertSame(["Con 338 casos, entre 5,1\u{00A0}% y 10,7\u{00A0}%", 'No es un cambio claro: puede ser azar'], $r['notes']);
    }

    public function test_a_rate_whose_intervals_do_not_overlap_is_coloured(): void
    {
        $r = Metric::rate('t.rate.clear', 'Tasa por correo', 100, 200, 40, 200, Polarity::UpIsGood, $this->how())->reading(Comparison::Previous);

        $this->assertSame('+30,0 puntos frente al periodo anterior', $r['line']);
        $this->assertSame('success', $r['color']);
        $this->assertSame(["Con 200 casos, entre 43,1\u{00A0}% y 56,9\u{00A0}%"], $r['notes']);
    }

    /** Una tasa de cosas distintas (pedidos entre visitas) puede pasar de 100 %: se enseña y no revienta el panel. */
    public function test_a_rate_above_one_hundred_percent_does_not_throw(): void
    {
        $metric = Metric::rate('t.rate.over', 'Conversión', 30, 20, null, null, Polarity::UpIsGood, $this->how());

        $this->assertSame("150,0\u{00A0}%", $metric->displayValue());
        $this->assertSame(["Con 20 casos, entre 83,9\u{00A0}% y 100,0\u{00A0}%"], $metric->reading(Comparison::Previous)['notes']);
    }

    public function test_a_text_figure_is_never_compared(): void
    {
        $r = Metric::text('t.text', 'Fuera del recuento', '56 bots · 1 internas', $this->how())->reading(Comparison::Previous);

        $this->assertNull($r['line']);
        $this->assertSame('gray', $r['color']);
    }
}
