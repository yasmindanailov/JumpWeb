<?php

namespace Tests\Feature\Admin\Orders;

use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Filament\Pages\CreateManualOrderPage;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * **El CALENDARIO del paso «Cuándo»** (`#464`, T3 de `specs/asistente-crear-pedido.md`).
 *
 * `[owner]`: «la fecha la selecciona de un calendario grande, bien visible». Sustituye a la tira de
 * 14 días y al `DatePicker` plegado tras su CTA que traía `#241` — medido antes de tocarlo, aquel
 * calendario era un **popover de 259×248 px con celdas de 29×28**, bajo el mínimo táctil, y costaba
 * dos toques abrirlo. `[DECIDIDO owner, 2026-09-04]`: la tira se retira, manda el calendario.
 *
 * ⚠️ **Lo que se fija aquí es el MODELO DE VISTA, que lo compone el servidor.** La rejilla no
 * decide qué día se vende: lo pregunta a `SlotOffer` (`AFORO-02`) igual que la web, y con la venta
 * de MOSTRADOR puesta (`#330`). Un calendario que se inventara los días —«hoy + N»— pintaría días
 * que el checkout rechaza, que es exactamente lo que `#240` vino a cerrar.
 */
class CreateManualOrderCalendarTest extends TestCase
{
    use RefreshDatabase;

    private Zone $zona;

    private TicketType $producto;

    protected function setUp(): void
    {
        parent::setUp();
        // Lunes 14 de septiembre de 2026. La fecha está clavada porque el caso de los HUECOS depende
        // de en qué día de la semana cae el 1 (martes), y el de las flechas, de que octubre esté vacío.
        Carbon::setTestNow('2026-09-14 09:00:00');

        RateType::firstOrCreate(
            ['key' => RateType::KEY_NORMAL],
            ['label' => ['es' => 'Normal'], 'weekdays' => null, 'priority' => 0],
        );

        $this->zona = Zone::create(['slug' => 'jump', 'name' => ['es' => 'JUMP'], 'position' => 1]);
        $this->producto = TicketType::create([
            'name' => ['es' => 'Jump · 1 hora'], 'zone_id' => $this->zona->id, 'duration_min' => 60,
            'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 1, 'position' => 1,
        ]);
        $this->producto->prices()->create([
            'rate_type_id' => RateType::where('key', RateType::KEY_NORMAL)->value('id'),
            'amount_cents' => 990,
        ]);

        // ⚠️ **Octubre se queda VACÍO a propósito**: es lo que hace observable que la flecha salte al
        // mes con fechas en vez de al mes de al lado. Un fixture con franjas todos los meses daría
        // verde con las dos conductas.
        foreach (['2026-09-16', '2026-09-17', '2026-11-05'] as $dia) {
            Slot::create([
                'zone_id' => $this->zona->id, 'date' => $dia,
                'start_time' => '10:00:00', 'end_time' => '11:00:00',
                'capacity' => 20, 'online_capacity' => 20,
            ]);
        }
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * ⚠️ **Solo se encienden los días que la OFERTA admite.** Un día del mes sin franja sale apagado
     * —no escondido—, que es la misma regla que las horas: el operador ve que el día existe y que ese
     * producto no se vende ahí, en vez de encontrarse un hueco sin explicación.
     */
    public function test_the_grid_only_lights_the_days_the_offer_admits(): void
    {
        $mes = $this->calendario();

        $this->assertSame(
            ['2026-09-16', '2026-09-17'],
            $this->encendidos($mes),
            'La rejilla ha dejado de leer la oferta de `SlotOffer` y se está inventando los días.',
        );

        $this->assertFalse(
            $this->dia($mes, '2026-09-18')['offerable'],
            'Un día sin franja tiene que salir apagado, y salir: no se esconde.',
        );
    }

    /**
     * ⚠️⚠️ **Las flechas saltan al mes OFRECIBLE, no al mes de al lado.** Con octubre entero sin
     * franjas, «mes siguiente» tiene que llevar a NOVIEMBRE: si llevara a octubre, el operador
     * aterrizaría en una rejilla apagada sin saber cuántas veces más tiene que pulsar.
     *
     * Y en septiembre no hay flecha hacia atrás: un control que no lleva a ninguna parte es peor que
     * su ausencia.
     */
    public function test_the_month_arrows_jump_to_the_next_month_with_dates(): void
    {
        $mes = $this->calendario();

        $this->assertSame('2026-09', $mes['ym']);
        $this->assertNull($mes['prev'], 'No hay meses anteriores con fechas: no debe haber flecha.');
        $this->assertSame('2026-11', $mes['next'], 'La flecha ha llevado a un mes sin una sola fecha.');
    }

    /**
     * ⚠️⚠️ **El mes que se pinta lo decide el LECTOR, no quien escribe la propiedad.**
     *
     * `$calMonth` es una propiedad PÚBLICA de Livewire: el navegador puede escribirla sin pasar por
     * `goToMonth()`, así que una comprobación dentro de la acción daría sensación de defensa sin
     * defender nada. Por eso este caso **escribe la propiedad a pelo**, que es lo que puede hacer un
     * cliente manipulado, y comprueba que la rejilla no se va a un mes sin oferta ni a una cadena
     * cualquiera.
     *
     * ▶ Lo dijo el arnés de mutación: con la comprobación puesta en `goToMonth()` **la mutación que
     * la quitaba no mordía**, porque el lector ya descartaba el mes. Dos defensas para lo mismo y
     * solo una observable — la de fuera sobraba.
     */
    public function test_the_grid_ignores_a_month_without_offer_whoever_wrote_it(): void
    {
        $componente = $this->pagina();

        $componente->set('calMonth', '2026-10');
        $this->assertSame('2026-09', $componente->instance()->calendarMonth()['ym'], 'La rejilla se ha ido a un mes sin fechas.');

        $componente->set('calMonth', 'no-es-un-mes');
        $this->assertSame('2026-09', $componente->instance()->calendarMonth()['ym'], 'La rejilla ha aceptado una cadena cualquiera.');

        $componente->call('goToMonth', '2026-11');
        $this->assertSame('2026-11', $componente->instance()->calendarMonth()['ym'], 'No ha dejado ir a un mes que sí tiene fechas.');
    }

    /**
     * ⚠️⚠️ **El mes navegado se descarta en cuanto deja de tener oferta.** Es lo que evita que
     * cambiar de producto deje el calendario plantado en un mes donde el nuevo no se vende: el
     * operador vería una rejilla entera apagada y ninguna pista de por qué.
     *
     * ▶ Y por eso NO se reinicia a mano al elegir producto: si los dos venden en ese mes, quedarse es
     * lo que el operador espera.
     */
    public function test_a_browsed_month_without_offer_gives_way_to_the_offer(): void
    {
        $otro = TicketType::create([
            'name' => ['es' => 'Jump · 2 horas'], 'zone_id' => $this->zona->id, 'duration_min' => 120,
            'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 1, 'position' => 2,
        ]);
        // Este solo se vende en noviembre… y el primero, no.
        Slot::where('date', '2026-11-05')->update(['end_time' => '12:00:00']);

        $componente = $this->pagina()->call('goToMonth', '2026-11');
        $this->assertSame('2026-11', $componente->instance()->calendarMonth()['ym']);

        // Se retiran las franjas de noviembre: el mes navegado se queda sin oferta.
        Slot::where('date', '2026-11-05')->delete();

        $this->assertSame(
            '2026-09',
            $componente->call('pickProduct', $otro->id)->instance()->calendarMonth()['ym'],
            'El calendario se ha quedado en un mes donde este producto no se vende.',
        );
    }

    /**
     * **HOY y el día ELEGIDO son marcas distintas** y las dos hacen falta: en un mostrador la mayoría
     * de las ventas son para hoy, y el día elegido tiene que verse sin releer el resumen.
     */
    public function test_today_and_the_chosen_day_are_marked(): void
    {
        $componente = $this->pagina();

        $mes = $componente->instance()->calendarMonth();
        $this->assertTrue($this->dia($mes, '2026-09-14')['today'], 'Hoy ha dejado de marcarse.');
        $this->assertFalse($this->dia($mes, '2026-09-14')['selected']);

        $mes = $componente->call('pickDay', '2026-09-16')->instance()->calendarMonth();
        $this->assertTrue($this->dia($mes, '2026-09-16')['selected'], 'El día elegido ha dejado de marcarse.');
        $this->assertFalse($this->dia($mes, '2026-09-17')['selected']);
    }

    /**
     * ⚠️ **Los días de otro mes son HUECOS, no números atenuados.** Un gris que significa «no es de
     * este mes» al lado de otro gris que significa «no se vende» son dos cosas distintas con la misma
     * pinta — y en una tablet se pulsan igual de mal.
     *
     * El 1 de septiembre de 2026 cae en MARTES, así que la primera casilla (lunes) tiene que ser
     * hueco. Y la semana siempre son 7 casillas: la rejilla es de 7 columnas.
     */
    public function test_the_days_of_another_month_are_holes_and_the_week_starts_on_monday(): void
    {
        $mes = $this->calendario();

        $this->assertNull($mes['weeks'][0][0], 'El relleno de otro mes se está pintando como un día.');
        $this->assertSame(1, $mes['weeks'][0][1]['day'], 'La semana no empieza en lunes.');

        // ⚠️⚠️ **Siete rótulos DISTINTOS**, y esto cazó un defecto real: con la INICIAL del día, en
        // español «martes» y «miércoles» dan las dos `M` y las columnas del medio dejan de
        // distinguirse. Se asevera la propiedad y no las letras, porque las letras dependen del
        // idioma del panel y la propiedad no.
        $this->assertCount(7, $mes['weekdays']);
        $this->assertCount(
            7, array_unique($mes['weekdays']),
            'Dos días de la semana comparten rótulo: la rejilla ha dejado de ser legible.',
        );
        $this->assertSame('Lun', $mes['weekdays'][0], 'La cabecera no empieza en lunes.');

        foreach ($mes['weeks'] as $i => $semana) {
            $this->assertCount(7, $semana, "La semana {$i} no tiene 7 casillas.");
        }
    }

    /**
     * ⚠️⚠️ **Y que la REJILLA lo pinte, que es distinto de que el modelo de vista lo sepa.**
     *
     * Lo dijo el arnés de mutación: con las guardas mirando solo `calendarMonth()`, quitar el
     * `@disabled` del partial o la marca del día elegido **pasaba en verde**. El servidor seguiría
     * rechazando el día (`pickDay()`), así que no habría daño en los datos — el operador simplemente
     * pulsaría y no pasaría nada, que es la peor clase de defecto de una pantalla: silencioso.
     *
     * *Que el servidor sepa la respuesta no es que la pantalla la enseñe.*
     */
    public function test_the_rendered_grid_disables_what_is_not_offered_and_marks_what_is_chosen(): void
    {
        $html = $this->pagina()->call('pickDay', '2026-09-16')->html();

        $this->assertMatchesRegularExpression(
            '/wire:key="cmo-cal-2026-09-18"[^>]*\sdisabled/s',
            $html,
            "Un día que la oferta no admite se está pintando PULSABLE.\n".
            'El servidor lo rechazaría, así que el operador pulsa y no pasa nada.',
        );

        $this->assertDoesNotMatchRegularExpression(
            '/wire:key="cmo-cal-2026-09-16"[^>]*\sdisabled/s',
            $html,
            'Un día ofrecible se está pintando deshabilitado.',
        );

        $elegido = $this->botonDel($html, '2026-09-16');
        $this->assertStringContainsString('is-selected', $elegido, 'El día elegido no se distingue en la rejilla.');
        $this->assertStringContainsString('aria-current="date"', $elegido, 'El día elegido no se anuncia a un lector de pantalla.');

        $this->assertStringNotContainsString('is-selected', $this->botonDel($html, '2026-09-17'));
    }

    // ─── Andamio ──────────────────────────────────────────────────────────────────────────────

    /** La etiqueta de apertura del botón de un día concreto, para aseverar sobre ÉL y no sobre la página. */
    private function botonDel(string $html, string $ymd): string
    {
        $i = strpos($html, 'wire:key="cmo-cal-'.$ymd.'"');
        $this->assertNotFalse($i, "no se encuentra el día {$ymd} en la rejilla");

        // Hacia atrás hasta el `<button`, hacia delante hasta cerrar la etiqueta: el atributo de
        // clase va ANTES del `wire:key`, así que acotar solo hacia delante dejaría fuera la marca.
        $desde = strrpos(substr($html, 0, $i), '<button');

        return substr($html, $desde, strpos($html, '>', $i) - $desde);
    }

    private function pagina(): Testable
    {
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        $admin = User::factory()->create();
        $admin->roles()->sync([Role::where('name', 'admin')->value('id')]);

        return Livewire::actingAs($admin)
            ->test(CreateManualOrderPage::class)
            ->set('step', CreateManualOrderPage::STEP_WHEN)
            ->call('pickProduct', $this->producto->id);
    }

    private function calendario(): array
    {
        return $this->pagina()->instance()->calendarMonth();
    }

    /** @return list<string> los días del mes que se pueden pulsar */
    private function encendidos(array $mes): array
    {
        $dias = [];
        foreach ($mes['weeks'] as $semana) {
            foreach ($semana as $dia) {
                if ($dia !== null && $dia['offerable']) {
                    $dias[] = $dia['date'];
                }
            }
        }

        return $dias;
    }

    private function dia(array $mes, string $ymd): array
    {
        foreach ($mes['weeks'] as $semana) {
            foreach ($semana as $dia) {
                if ($dia !== null && $dia['date'] === $ymd) {
                    return $dia;
                }
            }
        }

        $this->fail("el día {$ymd} no está en la rejilla del mes {$mes['ym']}");
    }
}
