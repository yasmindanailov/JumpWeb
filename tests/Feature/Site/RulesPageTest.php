<?php

namespace Tests\Feature\Site;

use App\Domain\Booking\Models\Zone;
use App\Domain\Booking\Services\ZoneCards;
use App\Domain\Content\Models\VenueRule;
use App\Domain\Identity\Services\WaiverSettings;
use App\Domain\Platform\Models\Setting;
use Database\Seeders\LandingContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * **LA PÁGINA `/normas`, REHECHA DESDE SU ARTBOARD** (`DECISIONES #533`, Fase 3 · T3b).
 * Artboard `Normas PJP` 1a/1b.
 *
 * ▶ Lo que se vigila **no es el aspecto** —eso lo mira el owner—: son las cosas que se rompen **en
 * silencio**, con la página cargando y la suite en verde. Las cuatro que más:
 *
 *  1. **Ninguna norma se pierde.** Una sin momento —o con uno que el producto ya no declara— se
 *     publica igual, al final. El modo de fallo no es un error: es una norma que el parque escribe
 *     en el panel, da por publicada, y que no sale en ninguna parte.
 *  2. **El orden de los grupos es el de la constante, no el de los datos.** Si lo mandara el orden
 *     del panel, reordenar una norma cambiaría el sentido de la página sin que nada fallara.
 *  3. **El porqué se pinta solo si está**, y su ausencia no deja hueco ni frase inventada.
 *  4. **La fecha sale de la norma tocada más recientemente**, nunca de hoy: escribir el mes actual
 *     afirmaría una revisión que nadie ha hecho.
 */
class RulesPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(LandingContentSeeder::class);
        $this->withSession(['locale' => 'es']);
        app()->setLocale('es');
    }

    private function html(): string
    {
        return (string) $this->get('/normas')->assertOk()->getContent();
    }

    /** Los rótulos de grupo en ORDEN DE DOCUMENTO, que es lo que lee quien recorre la página. */
    private function groupLabels(string $html): array
    {
        preg_match_all('#class="rules-group__label">([^<]*)<#', $html, $m);

        return array_map(trim(...), $m[1]);
    }

    /** El texto aplanado de cada tarjeta de norma, en orden. */
    private function cards(string $html): array
    {
        preg_match_all('#<li class="rule-card">(.*?)</li>#s', $html, $m);

        return array_map(
            fn (string $c): string => trim(preg_replace('/\s+/u', ' ', strip_tags($c))),
            $m[1],
        );
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  Guarda de la guarda
    // ─────────────────────────────────────────────────────────────────────────────────

    public function test_the_probe_frames_a_real_page(): void
    {
        $html = $this->html();

        $this->assertStringContainsString(__('site.rules_headline'), $html);
        $this->assertGreaterThanOrEqual(3, count($this->groupLabels($html)), 'la página no está agrupada por momento');
        $this->assertGreaterThanOrEqual(5, count($this->cards($html)), 'hay sospechosamente pocas normas');
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  Que ninguna norma se pierda
    // ─────────────────────────────────────────────────────────────────────────────────

    /**
     * ❗❗❗ **UNA NORMA SIN MOMENTO SE PUBLICA IGUAL.**
     *
     * El campo es opcional en el panel a propósito, así que este caso es el que sostiene esa
     * decisión: sin él, el día que alguien haga obligatorio el agrupado, una norma creada con prisa
     * desaparecería de la web **sin fallar y sin avisar**.
     */
    public function test_a_rule_without_a_moment_is_still_published(): void
    {
        VenueRule::create([
            'name' => ['es' => 'NormaSinMomentoZZ'],
            'description' => ['es' => 'Se publica igual.'],
            'position' => 99,
            'is_active' => true,
        ]);

        $html = $this->html();

        $this->assertStringContainsString('NormaSinMomentoZZ', $html, 'una norma sin momento desapareció de la página');
        $this->assertStringContainsString(__('site.rules_other'), $html);
    }

    /**
     * Y lo mismo con un momento que el producto **ya no declara**: una fila vieja, o un valor metido
     * por la puerta de atrás, no puede hacer desaparecer la norma.
     */
    public function test_a_rule_with_an_unknown_moment_is_still_published(): void
    {
        VenueRule::create([
            'name' => ['es' => 'NormaMomentoRaroZZ'],
            'description' => ['es' => 'Con un momento que ya no existe.'],
            'moment' => 'el_que_ya_no_esta',
            'position' => 98,
            'is_active' => true,
        ]);

        $this->assertStringContainsString('NormaMomentoRaroZZ', $this->html());
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  El orden, el porqué y la fecha
    // ─────────────────────────────────────────────────────────────────────────────────

    /**
     * ❗❗ **EL ORDEN DE LOS GRUPOS ES EL DE `VenueRule::MOMENTS`, NO EL DEL PANEL.**
     *
     * El caso mueve al PRIMER puesto una norma de «dentro»: si los grupos salieran del orden de los
     * datos, «Dentro» abriría la página. Lo que el visitante necesita leer primero es lo que decide
     * si entra.
     */
    public function test_the_groups_follow_the_declared_order_not_the_panel_one(): void
    {
        VenueRule::where('moment', VenueRule::MOMENTS[2])->orderBy('position')->first()?->update(['position' => 0]);

        $labels = $this->groupLabels($this->html());

        $this->assertSame([
            __('site.rules_moment.before.label'),
            __('site.rules_moment.gate.label'),
            __('site.rules_moment.inside.label'),
        ], $labels, 'el orden de los momentos lo está decidiendo el panel');
    }

    /**
     * **El PORQUÉ se pinta solo si lo tiene** — y su ausencia no deja ni hueco ni frase inventada.
     * ⚠️ El control es la otra mitad: con motivo, se publica.
     */
    public function test_the_reason_is_painted_only_when_there_is_one(): void
    {
        $conMotivo = VenueRule::whereNotNull('reason')->orderBy('position')->firstOrFail();
        $sinMotivo = VenueRule::whereNull('reason')->orderBy('position')->firstOrFail();

        $html = $this->html();
        $cards = $this->cards($html);

        $laQueTiene = collect($cards)->first(fn (string $c): bool => str_contains($c, (string) $conMotivo->tr('name')));
        $laQueNo = collect($cards)->first(fn (string $c): bool => str_contains($c, (string) $sinMotivo->tr('name')));

        $this->assertNotNull($laQueTiene);
        $this->assertNotNull($laQueNo);
        $this->assertStringContainsString((string) $conMotivo->tr('reason'), $laQueTiene);
        $this->assertSame(
            substr_count($html, 'rule-card__why'),
            VenueRule::where('is_active', true)->whereNotNull('reason')->count(),
            'se pinta un porqué por cada norma que lo tiene, ni uno más',
        );
    }

    /**
     * ⚠️⚠️ **LA FECHA ES LA DE LA NORMA, NO LA DE HOY.** Escribir el mes actual diría que las normas
     * se revisaron este mes aunque lleven un año sin tocarse — y es justo el dato por el que alguien
     * mira esta línea.
     */
    public function test_the_updated_line_comes_from_the_rules_not_from_today(): void
    {
        Carbon::setTestNow('2026-09-12');
        VenueRule::query()->update(['updated_at' => Carbon::parse('2026-03-04')]);

        $html = $this->html();

        $this->assertStringContainsString(__('site.rules_updated', ['fecha' => 'marzo de 2026']), $html);
        $this->assertStringNotContainsString(__('site.rules_updated', ['fecha' => 'septiembre de 2026']), $html);
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  La escala de altura y la chapa del descargo
    // ─────────────────────────────────────────────────────────────────────────────────

    /**
     * **LA ESCALA SALE DEL DATO, Y SIN DATO NO SE PINTA.**
     *
     * ⚠️ Una franja por TRAMO de la escala, cortada en cada frontera que el dato declara (`#589`): sin
     * solape, una por zona con altura. El artboard dibuja además «de 1 a 1,30 con tutor», que **no
     * existe como dato**: es texto de acceso del panel, y va debajo de la escala.
     */
    public function test_the_height_scale_is_data_and_without_it_there_is_no_card(): void
    {
        /*
         * ⚠️⚠️ **EL CASO SIEMBRA SU PROPIO SUJETO, y la primera versión no lo hacía.** Medido: el
         * seeder **no siembra ninguna altura** —cero apariciones de `height_min_cm` en
         * `database/seeders/`—, así que en la base de test todas las zonas llegan sin regla. Sin
         * esto, el caso comparaba **cero bandas contra cero zonas** y pasaba en verde sin vigilar
         * nada; lo cazó la aserción de sujeto, no el resultado. Es la misma forma de sembrar que usa
         * la guarda hermana de la portada (`ZoneCardsSectionTest`).
         * ▶ Y las dos direcciones a propósito: «a partir de» y «hasta» son las dos columnas, y la
         * misma cifra significa lo contrario en cada una.
         */
        $zonas = Zone::where('is_active', true)->where('show_in_landing', true)->orderBy('position')->get();
        $zonas[0]?->forceFill(['height_min_cm' => 130])->save();
        $zonas[1]?->forceFill(['height_max_cm' => 130])->save();

        $html = $this->html();
        $conAltura = Zone::where('is_active', true)->where('show_in_landing', true)
            ->where(fn ($q) => $q->whereNotNull('height_min_cm')->orWhereNotNull('height_max_cm'))
            ->count();

        $this->assertGreaterThan(0, $conAltura, 'el caso nace sin sujeto: ninguna zona declara altura');
        // ⚠️ Con el espacio o la comilla detrás: `rules-axis__bands` es el CONTENEDOR y contaba como una más.
        $this->assertSame($conAltura, preg_match_all('#class="rules-axis__band[ "]#', $html), 'las bandas no salen de las zonas con altura');

        /*
         * ❗❗❗ **Y DÓNDE EMPIEZA CADA BANDA, QUE ES LO QUE LA HACE SIGNIFICAR ALGO.** Contar bandas
         * no basta y lo dijo el arnés: con «hasta» dibujado como «a partir de», la de Kids iba del
         * suelo al techo —o sea, «Kids no tiene límite de altura»— y el caso pasaba en verde.
         * ▶ Sobre la escala de 190, el umbral de 130 cae al **31,58 %**: «a partir de» ocupa de ahí
         * hacia ARRIBA (top 0) y «hasta» de ahí hacia ABAJO. La misma cifra, lo contrario.
         */
        $corte = round((1 - 130 / ZoneCards::ESCALA_CM) * 100, 2);

        $this->assertStringContainsString('style="top: 0%; height: '.$corte.'%;', $html,
            'la zona con «a partir de» no ocupa la franja ALTA de la escala');
        $this->assertStringContainsString('style="top: '.$corte.'%; height: '.round(100 - $corte, 2).'%;', $html,
            'la zona con «hasta» no ocupa la franja BAJA: su banda dice que no tiene límite');

        // LA REGLA (`#589`): una marca por cota —techo, la frontera y suelo—, y la frontera en fuerte.
        $this->assertSame(3, substr_count($html, 'class="rules-axis__tick'), 'la regla no lleva una marca por cota');
        $this->assertSame(1, substr_count($html, 'rules-axis__tick--strong'), 'la frontera de zona no va marcada');

        // CONTROL: sin ninguna regla de altura, la tarjeta entera desaparece.
        Zone::query()->update(['height_min_cm' => null, 'height_max_cm' => null]);
        $this->assertStringNotContainsString('rules-axis', $this->html(), 'se pinta un eje vacío');
    }

    /**
     * ❗❗ **LAS FRANJAS NO SE PISAN** (`#589`, `[DECIDIDO owner]`). Con «hasta 1,50» y «desde 1,30» —los
     * datos del parque, donde manda la edad— una banda por zona dibujaba una ENCIMA de la otra. La
     * escala se corta en cada frontera y el tramo compartido es su propia franja, que dice las dos.
     */
    public function test_overlapping_zones_get_a_shared_band_and_no_band_covers_another(): void
    {
        $zonas = Zone::where('is_active', true)->where('show_in_landing', true)->orderBy('position')->get();
        $this->assertGreaterThanOrEqual(2, $zonas->count(), 'el caso nace sin sujeto: hacen falta dos zonas publicadas');

        Zone::query()->update(['height_min_cm' => null, 'height_max_cm' => null]);
        $zonas[0]->forceFill(['height_min_cm' => 130])->save();
        $zonas[1]->forceFill(['height_max_cm' => 150])->save();

        $html = $this->html();
        $escala = ZoneCards::ESCALA_CM;

        $this->assertSame(3, preg_match_all('#class="rules-axis__band[ "]#', $html), 'el solape no se ha cortado en tres franjas');
        $this->assertSame(1, substr_count($html, 'rules-axis__band--overlap'), 'el tramo compartido no es su propia franja');
        $this->assertStringContainsString(
            'style="top: '.round((1 - 150 / $escala) * 100, 2).'%; height: '.round(20 / $escala * 100, 2).'%;',
            $html,
            'la franja compartida no ocupa exactamente de 1,30 a 1,50',
        );
        $this->assertStringContainsString(e($zonas[0]->tr('name').' o '.$zonas[1]->tr('name').' · según la edad'), $html);

        // Contiguas y sin solape: cada franja empieza donde acaba la de encima.
        preg_match_all('#rules-axis__band[^"]*"\s+style="top: ([\d.]+)%; height: ([\d.]+)%;#', $html, $m, PREG_SET_ORDER);
        $this->assertCount(3, $m, 'la sonda no enmarca las franjas');
        for ($i = 1; $i < count($m); $i++) {
            $this->assertEqualsWithDelta((float) $m[$i - 1][1] + (float) $m[$i - 1][2], (float) $m[$i][1], 0.02, 'dos franjas se pisan o dejan un hueco');
        }

        $this->assertSame(2, substr_count($html, 'rules-axis__tick--strong'), 'las dos fronteras no van marcadas en la regla');
        $this->assertStringContainsString('>1,50</span>', $html);
    }

    /**
     * **La chapa del descargo sigue al MISMO ajuste que el pie** (`#216`): si la instalación no usa
     * la exención, el pie retira su enlace y la página no ofrece una chapa que lleva a una página
     * que no se enseña.
     */
    public function test_the_waiver_plate_follows_the_same_switch_as_the_footer(): void
    {
        $this->assertStringContainsString('rules-waiver', $this->html());

        Setting::query()->updateOrCreate(
            ['key' => 'waiver.mode'],
            ['value' => WaiverSettings::MODE_OFF],
        );
        Setting::flushMemo();

        $this->assertStringNotContainsString('rules-waiver', $this->html());
    }

    /**
     * **Lo que se retiró no vuelve**: el pliego de tarjetas con el icono «!» y el bloque
     * «Información», que no era una norma sino «pregunta al staff» — hoy es una línea al pie, fuera
     * de la lista.
     */
    public function test_the_retired_pieces_leave_no_trace(): void
    {
        $html = $this->html();

        $this->assertStringNotContainsString('rules-grid', $html);
        $this->assertStringNotContainsString('rule__icon', $html);
        $this->assertStringContainsString(__('site.rules_staff'), $html);
    }
}
