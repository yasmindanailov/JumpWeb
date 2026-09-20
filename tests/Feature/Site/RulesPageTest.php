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
 * **`/normas`: la CONDUCTA del producto** (`DECISIONES #533`; partida por lo que afirma en F5 · T2b, `#655`).
 *
 * ❗❗ **Aquí no se lee el HTML** (`#649`): el contrato con la landing de una instancia son los DATOS que
 * recibe la vista —`board`, `scale`, `waiverEnabled`—, y sobre ellos se afirma. Hasta la mudanza estos
 * casos leían el marcado de la página de PlayJump, que ya no está en el producto; lo que ese marcado
 * garantizaba está en la doc de la instancia (`paginas/normas.md`), y el anfitrión mínimo tiene su guarda
 * propia (`AnfitrionNormasTest`).
 *
 * Las cuatro cosas que se rompen EN SILENCIO y que esto sostiene: ninguna norma se pierde (sin momento, o
 * con uno que el producto ya no declara, va en `ungrouped`); el orden de los grupos es el de la constante,
 * no el del panel; la fecha es la de la norma tocada más recientemente, nunca la de hoy; y la escala de
 * altura sale del dato, con cada franja donde el dato la pone.
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

    /** @return array<string, mixed> Lo que el controlador le pasa a la vista: el sujeto de esta guarda. */
    private function datos(): array
    {
        return $this->get('/normas')->assertOk()->original->getData();
    }

    /** @return list<string> */
    private function nombres(iterable $rules): array
    {
        return collect($rules)->map(fn (VenueRule $r): string => (string) $r->tr('name'))->values()->all();
    }

    /** @return list<array{float, float}> Cada franja como [arriba, alto], en tanto por ciento. */
    private function franjas(array $scale): array
    {
        return array_map(fn (array $b): array => [(float) $b['top'], (float) $b['height']], $scale['bands']);
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  Que ninguna norma se pierda
    // ─────────────────────────────────────────────────────────────────────────────────

    /**
     * ❗❗❗ **UNA NORMA SIN MOMENTO SE PUBLICA IGUAL**: va en `ungrouped`, no desaparece. El campo es
     * opcional en el panel a propósito, y sin este caso una norma creada con prisa se perdería sin fallar.
     */
    public function test_a_rule_without_a_moment_is_still_published(): void
    {
        VenueRule::create([
            'name' => ['es' => 'NormaSinMomentoZZ'],
            'description' => ['es' => 'Se publica igual.'],
            'position' => 99,
            'is_active' => true,
        ]);

        $board = $this->datos()['board'];

        $this->assertContains('NormaSinMomentoZZ', $this->nombres($board['ungrouped']), 'una norma sin momento desapareció del tablero');
        $this->assertNotContains(
            'NormaSinMomentoZZ',
            $this->nombres(collect($board['groups'])->flatMap(fn (array $g) => $g['rules'])),
            'una norma sin momento se coló en un grupo',
        );
    }

    /**
     * Y lo mismo con un momento que el producto **ya no declara**: una fila vieja, o un valor metido por
     * la puerta de atrás, no puede hacer desaparecer la norma.
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

        $board = $this->datos()['board'];

        $this->assertContains('NormaMomentoRaroZZ', $this->nombres($board['ungrouped']));
        $this->assertNotContains('el_que_ya_no_esta', array_column($board['groups'], 'moment'), 'un momento desconocido abre un grupo');
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  El orden y la fecha
    // ─────────────────────────────────────────────────────────────────────────────────

    /**
     * ❗❗ **EL ORDEN DE LOS GRUPOS ES EL DE `VenueRule::MOMENTS`, NO EL DEL PANEL.** El caso mueve al
     * primer puesto una norma de «dentro»: si los grupos salieran del orden de los datos, «Dentro» abriría
     * el tablero. Lo que el visitante necesita leer primero es lo que decide si entra.
     */
    public function test_the_groups_follow_the_declared_order_not_the_panel_one(): void
    {
        VenueRule::where('moment', VenueRule::MOMENTS[2])->orderBy('position')->first()?->update(['position' => 0]);

        $momentos = array_column($this->datos()['board']['groups'], 'moment');

        $this->assertSame(VenueRule::MOMENTS, $momentos, 'el orden de los momentos lo está decidiendo el panel');
    }

    /**
     * ⚠️⚠️ **LA FECHA ES LA DE LA NORMA, NO LA DE HOY.** Escribir el mes actual diría que las normas se
     * revisaron este mes aunque lleven un año sin tocarse. Cómo se ESCRIBE la fecha ya no es cosa de la
     * vista: `LocalDate` (`#656`).
     */
    public function test_the_updated_date_comes_from_the_rules_not_from_today(): void
    {
        Carbon::setTestNow('2026-09-12');
        VenueRule::query()->update(['updated_at' => Carbon::parse('2026-03-04')]);

        $this->assertSame('2026-03-04', $this->datos()['board']['updatedAt']?->toDateString());
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  La escala de altura y la chapa del descargo
    // ─────────────────────────────────────────────────────────────────────────────────

    /**
     * **LA ESCALA SALE DEL DATO, Y SIN DATO NO HAY ESCALA.** Una franja por tramo, cortada en cada
     * frontera que el dato declara (`#589`).
     *
     * ⚠️⚠️ **El caso siembra su propio sujeto**: el seeder no siembra ninguna altura, así que sin esto
     * compararía cero franjas contra cero zonas y pasaría en verde sin vigilar nada. ▶ Y las dos
     * direcciones a propósito: «a partir de» ocupa de la frontera hacia ARRIBA y «hasta» de la frontera
     * hacia ABAJO. La misma cifra, lo contrario —lo dijo el arnés: con «hasta» dibujado como «a partir
     * de», Kids iba del suelo al techo, o sea «sin límite de altura», y nada fallaba—.
     */
    public function test_the_height_scale_is_data_and_without_it_there_is_none(): void
    {
        $zonas = Zone::where('is_active', true)->where('show_in_landing', true)->orderBy('position')->get();
        $zonas[0]?->forceFill(['height_min_cm' => 130])->save();
        $zonas[1]?->forceFill(['height_max_cm' => 130])->save();

        $conAltura = Zone::where('is_active', true)->where('show_in_landing', true)
            ->where(fn ($q) => $q->whereNotNull('height_min_cm')->orWhereNotNull('height_max_cm'))
            ->count();
        $this->assertGreaterThan(0, $conAltura, 'el caso nace sin sujeto: ninguna zona declara altura');

        $scale = $this->datos()['scale'];
        $this->assertNotNull($scale, 'con zonas con altura no hay escala');
        $this->assertCount($conAltura, $scale['bands'], 'las franjas no salen de las zonas con altura');

        // Sobre la escala de 190, el umbral de 130 cae al 31,58 %.
        $corte = round((1 - 130 / ZoneCards::ESCALA_CM) * 100, 2);
        $franjas = $this->franjas($scale);
        $this->assertContains([0.0, $corte], $franjas, 'la zona con «a partir de» no ocupa la franja ALTA de la escala');
        $this->assertContains([$corte, round(100 - $corte, 2)], $franjas, 'la zona con «hasta» no ocupa la franja BAJA: su franja dice que no tiene límite');

        // LA REGLA (`#589`): una marca por cota —techo, la frontera y suelo—, y la frontera en fuerte.
        $this->assertCount(3, $scale['ticks'], 'la regla no lleva una marca por cota');
        $this->assertCount(1, array_filter($scale['ticks'], fn (array $t): bool => $t['strong']), 'la frontera de zona no va marcada');

        // CONTROL: sin ninguna regla de altura, no hay escala.
        Zone::query()->update(['height_min_cm' => null, 'height_max_cm' => null]);
        $this->assertNull($this->datos()['scale'], 'se compone una escala vacía');
    }

    /**
     * ❗❗ **LAS FRANJAS NO SE PISAN** (`#589`, `[DECIDIDO owner]`). Con «hasta 1,50» y «desde 1,30» —los
     * datos del parque, donde manda la edad— una franja por zona dibujaba una ENCIMA de la otra. La escala
     * se corta en cada frontera y el tramo compartido es su propia franja, que dice las dos.
     */
    public function test_overlapping_zones_get_a_shared_band_and_no_band_covers_another(): void
    {
        $zonas = Zone::where('is_active', true)->where('show_in_landing', true)->orderBy('position')->get();
        $this->assertGreaterThanOrEqual(2, $zonas->count(), 'el caso nace sin sujeto: hacen falta dos zonas publicadas');

        Zone::query()->update(['height_min_cm' => null, 'height_max_cm' => null]);
        $zonas[0]->forceFill(['height_min_cm' => 130])->save();
        $zonas[1]->forceFill(['height_max_cm' => 150])->save();

        $scale = $this->datos()['scale'];
        $escala = ZoneCards::ESCALA_CM;

        $this->assertCount(3, $scale['bands'], 'el solape no se ha cortado en tres franjas');
        $compartidas = array_values(array_filter($scale['bands'], fn (array $b): bool => $b['overlap']));
        $this->assertCount(1, $compartidas, 'el tramo compartido no es su propia franja');
        $this->assertSame(
            [round((1 - 150 / $escala) * 100, 2), round(20 / $escala * 100, 2)],
            [(float) $compartidas[0]['top'], (float) $compartidas[0]['height']],
            'la franja compartida no ocupa exactamente de 1,30 a 1,50',
        );
        $this->assertSame($zonas[0]->tr('name').' o '.$zonas[1]->tr('name').' · según la edad', $compartidas[0]['label']);

        // Contiguas y sin solape: cada franja empieza donde acaba la de encima.
        $franjas = $this->franjas($scale);
        for ($i = 1; $i < count($franjas); $i++) {
            $this->assertEqualsWithDelta($franjas[$i - 1][0] + $franjas[$i - 1][1], $franjas[$i][0], 0.02, 'dos franjas se pisan o dejan un hueco');
        }

        $fuertes = array_values(array_filter($scale['ticks'], fn (array $t): bool => $t['strong']));
        $this->assertCount(2, $fuertes, 'las dos fronteras no van marcadas en la regla');
        $this->assertContains('1,50', array_column($fuertes, 'label'));
    }

    /**
     * **La chapa del descargo sigue al MISMO ajuste que el pie** (`#216`): si la instalación no usa la
     * exención, el pie retira su enlace y la vista recibe `waiverEnabled = false`.
     */
    public function test_the_waiver_plate_follows_the_same_switch_as_the_footer(): void
    {
        $this->assertTrue($this->datos()['waiverEnabled']);

        Setting::query()->updateOrCreate(['key' => 'waiver.mode'], ['value' => WaiverSettings::MODE_OFF]);
        Setting::flushMemo();

        $this->assertFalse($this->datos()['waiverEnabled']);
    }
}
