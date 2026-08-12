<?php

namespace Tests\Feature\Sales;

use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Livewire\Tickets\Purchase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Sidebar v2 (mockup «Sidebar Catalogo v2»): tres mejoras de UX del sidebar de compra:
 *   (1) «Mi cuenta» se minimiza al entrar en el flujo (modo del panel derivado de `$step`),
 *   (2) stepper detallado de 3 fases (Producto · Día · Hora) en lugar de las 3 barras mudas,
 *   (3) footer sticky y dinámico cuyo CTA cambia según el contexto.
 *
 * El estado de negocio sigue siendo server-authoritative (Livewire); este test cubre los VM que
 * lo proyectan (`sidebarMode`, `bookingProgress`, `footer`) y el markup que los consume. La
 * animación/colapso (puro CSS/Alpine) se valida visualmente (DoD).
 */
class SidebarV2Test extends TestCase
{
    use RefreshDatabase;

    private Zone $zone;

    private TicketType $entry;

    private TicketType $pack;

    private TicketType $depEntry;

    private string $date;

    protected function setUp(): void
    {
        parent::setUp();
        App::setLocale('es');
        RateType::create(['key' => RateType::KEY_NORMAL, 'label' => ['es' => 'Normal'], 'weekdays' => null, 'priority' => 0]);
        $normal = (int) RateType::where('key', 'normal')->value('id');

        $this->zone = Zone::create(['slug' => 'jump', 'name' => ['es' => 'JUMP'], 'is_active' => true, 'show_in_landing' => true, 'position' => 1]);
        $this->date = Carbon::today()->addDays(2)->toDateString();
        // Varias franjas contiguas: el pack dura 120 min y necesita cobertura de toda la fiesta
        // (entra a las 10:00 → 10:00–12:00). Con una sola franja la disponibilidad sería 0.
        foreach ([['10:00:00', '11:00:00'], ['11:00:00', '12:00:00'], ['12:00:00', '13:00:00']] as [$start, $end]) {
            Slot::create([
                'zone_id' => $this->zone->id, 'date' => $this->date,
                'start_time' => $start, 'end_time' => $end, 'capacity' => 50, 'online_capacity' => 50,
            ]);
        }

        $this->entry = TicketType::create([
            'name' => ['es' => 'Salto 60 min'], 'type' => TicketType::TYPE_ENTRY, 'zone_id' => $this->zone->id,
            'duration_min' => 60, 'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 1, 'position' => 1,
        ]);
        $this->entry->prices()->create(['rate_type_id' => $normal, 'amount_cents' => 1390]);

        // Pack: la 3.ª fase del stepper debe ser «Datos» (no «Extras»).
        $this->pack = TicketType::create([
            'name' => ['es' => 'Pack cumpleaños'], 'type' => TicketType::TYPE_PACK, 'zone_id' => $this->zone->id,
            'duration_min' => 120, 'min_qty' => 8, 'max_qty' => 20, 'is_sellable' => true, 'is_active' => true,
            'seats_per_unit' => 1, 'position' => 2,
        ]);
        $this->pack->prices()->create(['rate_type_id' => $normal, 'amount_cents' => 1500]);

        // Entrada con señal fija 30 € sobre 180 €: el footer muestra el badge corto de señal.
        $this->depEntry = TicketType::create([
            'name' => ['es' => 'Con señal'], 'type' => TicketType::TYPE_ENTRY, 'zone_id' => $this->zone->id,
            'duration_min' => 60, 'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 1, 'position' => 3,
            'deposit_type' => TicketType::DEPOSIT_FIXED, 'deposit_value' => 3000,
        ]);
        $this->depEntry->prices()->create(['rate_type_id' => $normal, 'amount_cents' => 18000]);
    }

    /** El modo del sidebar se deriva del paso y gobierna la clase del panel (minimiza la cuenta). */
    public function test_sidebar_mode_is_derived_from_the_step(): void
    {
        $c = Livewire::test(Purchase::class);
        $this->assertSame('catalog', $c->instance()->sidebarMode());        // paso 1

        $c->call('selectType', $this->entry->id);
        $this->assertSame('booking', $c->instance()->sidebarMode());        // paso 2

        $c->call('selectDate', $this->date)->call('goToTime');
        $this->assertSame('booking', $c->instance()->sidebarMode());        // paso 3

        $c->set('step', 4);
        $this->assertSame('cart', $c->instance()->sidebarMode());           // carrito

        $c->set('step', 8);
        $this->assertSame('cart', $c->instance()->sidebarMode());           // pago

        $c->set('step', 6);
        $this->assertSame('result', $c->instance()->sidebarMode());         // confirmación
    }

    /** El mapa paso→modo (fuente única que consume Alpine) se expone al blade. */
    public function test_step_mode_map_is_exposed_to_the_view_for_the_alpine_bridge(): void
    {
        $map = Livewire::test(Purchase::class)->instance()->stepModeMap();
        $this->assertSame('catalog', $map[1]);
        $this->assertSame('booking', $map[2]);
        $this->assertSame('cart', $map[4]);
        // El blade pasa el mapa a Alpine (`x-effect` sobre `$wire.step`).
        Livewire::test(Purchase::class)->assertSee('stepMode', false);
    }

    /**
     * El puente expone también el paso de identificación (5) y fija `$store.purchase.identifying`
     * cuando el flujo lo alcanza → el bloque de cuenta bloquea su «Iniciar sesión» (login redundante).
     */
    public function test_identify_step_is_wired_to_the_alpine_identifying_signal(): void
    {
        Livewire::test(Purchase::class)
            ->assertSee('identifyStep', false)                                 // expuesto al blade
            ->assertSee('$store.purchase.identifying = ($wire.step === identifyStep)', false); // x-effect
    }

    /** Stepper detallado: 3 fases (Fecha·Hora·Extras) que avanzan, incluso DENTRO del paso 3. */
    public function test_booking_progress_phases_and_states(): void
    {
        $c = Livewire::test(Purchase::class);
        $this->assertNull($c->instance()->bookingProgress());               // catálogo → sin stepper

        $c->call('selectType', $this->entry->id);                           // paso 2 → fase Fecha
        $p = $c->instance()->bookingProgress();
        $this->assertSame(1, $p['active']);
        $this->assertSame(3, $p['total']);
        $this->assertSame(__('tickets.phase_date'), $p['steps'][0]['label']);
        $this->assertSame('current', $p['steps'][0]['state']);              // Fecha
        $this->assertSame('todo', $p['steps'][1]['state']);                 // Hora
        $this->assertSame(__('tickets.phase_extras'), $p['steps'][2]['label']); // entrada → «Extras»
        $this->assertSame('todo', $p['steps'][2]['state']);

        $c->call('selectDate', $this->date)->call('goToTime');                                // paso 3 sin hora → fase Hora
        $p = $c->instance()->bookingProgress();
        $this->assertSame(2, $p['active']);
        $this->assertSame('done', $p['steps'][0]['state']);                 // Fecha hecha
        $this->assertSame('current', $p['steps'][1]['state']);              // Hora actual
        $this->assertSame('todo', $p['steps'][2]['state']);                 // Extras pendiente

        $c->call('selectTime', '10:00:00');                                 // paso 3 con hora → fase Extras
        $p = $c->instance()->bookingProgress();
        $this->assertSame(3, $p['active']);
        $this->assertSame('done', $p['steps'][1]['state']);                 // Hora hecha
        $this->assertSame('current', $p['steps'][2]['state']);              // Extras actual

        $c->set('step', 4);
        $this->assertNull($c->instance()->bookingProgress());               // carrito → sin stepper
    }

    /** El paso de fecha NO auto-avanza: elegir día habilita «Continuar» en el footer (sin auto-salto). */
    public function test_date_step_requires_explicit_continue(): void
    {
        $c = Livewire::test(Purchase::class)->call('selectType', $this->entry->id);
        $this->assertSame(2, $c->get('step'));

        // Footer del paso de fecha: MISMO footer (Total + CTA + IVA); el total es «—» (sin precio aún)
        // y «Continuar» está deshabilitado hasta elegir día.
        $f = $c->instance()->footer();
        $this->assertSame('goToTime', $f['action']);
        $this->assertSame(__('tickets.continue'), $f['cta']);
        $this->assertSame(__('tickets.total'), $f['label']);
        $this->assertSame('—', $f['amount']);                   // placeholder, no hay precio todavía
        $this->assertSame(__('tickets.iva_note'), $f['note']);
        $this->assertTrue($f['disabled']);

        // Elegir día NO avanza solo; habilita el «Continuar» y MARCA el día en el calendario.
        $c->call('selectDate', $this->date);
        $this->assertSame(2, $c->get('step'));                  // sigue en fecha (no auto-avanza)
        $this->assertFalse($c->instance()->footer()['disabled']);
        $this->assertSame('—', $c->instance()->footer()['amount']); // el total sigue «—» en el paso de fecha
        $c->assertSee('is-selected', false);                     // el día elegido queda marcado

        // «Continuar» lleva al paso de hora.
        $c->call('goToTime');
        $this->assertSame(3, $c->get('step'));
    }

    /** La 3.ª fase del stepper es coherente con el TIPO: «Extras» (entrada) vs «Datos» (pack). */
    public function test_third_phase_label_depends_on_product_type(): void
    {
        $entry = Livewire::test(Purchase::class)->call('selectType', $this->entry->id)->instance()->bookingProgress();
        $this->assertSame(__('tickets.phase_extras'), $entry['steps'][2]['label']);

        $pack = Livewire::test(Purchase::class)->call('selectType', $this->pack->id)->instance()->bookingProgress();
        $this->assertSame(__('tickets.phase_details'), $pack['steps'][2]['label']);
    }

    /** La línea de contexto se construye sin separadores huérfanos cuando faltan datos. */
    public function test_booking_progress_context_has_no_orphan_separators(): void
    {
        $c = Livewire::test(Purchase::class)->call('selectType', $this->entry->id); // paso 2: solo producto
        $context = $c->instance()->bookingProgress()['context'];
        $this->assertSame('Salto 60 min', $context);                        // sin « · » colgando
        $this->assertStringNotContainsString(' ·  · ', $context);

        $c->call('selectDate', $this->date)->call('goToTime')->call('selectTime', '10:00:00'); // paso 3: producto · día · hora
        $context = $c->instance()->bookingProgress()['context'];
        $this->assertStringContainsString('Salto 60 min', $context);
        $this->assertStringContainsString('10:00', $context);
        $this->assertStringNotContainsString(' ·  · ', $context);
    }

    /** Footer dinámico: el CTA y la acción cambian según el contexto. */
    public function test_footer_is_contextual_per_step(): void
    {
        $c = Livewire::test(Purchase::class);
        $this->assertNull($c->instance()->footer());                        // catálogo, cesta vacía → sin barra

        // Catálogo con cesta → barra-carrito (tipo «cart») con «Ir al carrito» y el nº de artículos.
        $c->set('cart', [['ticket_type_id' => $this->entry->id, 'date' => $this->date, 'time' => '10:00:00', 'qty' => 1]])
            ->set('step', 1);
        $f = $c->instance()->footer();
        $this->assertSame('cart', $f['type']);
        $this->assertSame('goToCart', $f['action']);
        $this->assertSame(__('tickets.go_to_cart'), $f['cta']);
        $this->assertSame(1, $f['count']);

        // Carrito → barra (tipo «bar») con «Ir a pagar» (checkout); desglose vía ⓘ (popover).
        $c->set('step', 4);
        $f = $c->instance()->footer();
        $this->assertSame('bar', $f['type']);
        $this->assertSame('checkout', $f['action']);
        $this->assertSame(__('tickets.go_to_pay'), $f['cta']);
        $this->assertSame('popover', $f['splitMode']);

        // Pago → «Pagar y confirmar» (confirmReservation); desglose en banda sticky propia.
        $c->set('step', 8);
        $this->assertSame('confirmReservation', $c->instance()->footer()['action']);
        $this->assertSame('band', $c->instance()->footer()['splitMode']);

        // Login embebido y pantallas terminales → sin barra.
        $c->set('step', 5);
        $this->assertNull($c->instance()->footer());
        $c->set('step', 6);
        $this->assertNull($c->instance()->footer());
    }

    /** El CTA «Añadir al carrito» se inactiva SOLO hasta elegir hora (estructural). El resto se valida
        al pulsar, NO con un botón muerto: una vez hay hora, el botón está activo. */
    public function test_add_to_cart_is_disabled_only_until_a_time_is_chosen(): void
    {
        $c = Livewire::test(Purchase::class)
            ->call('selectType', $this->entry->id)
            ->call('selectDate', $this->date)->call('goToTime');

        // Sin hora: footer presente, total «—» y CTA inactivo (aún no hay nada que añadir).
        $f = $c->instance()->footer();
        $this->assertSame('addToCart', $f['action']);
        $this->assertSame('—', $f['amount']);
        $this->assertTrue($f['disabled']);

        $c->call('selectTime', '10:00:00');                                 // fija qty al mínimo y el total
        $f = $c->instance()->footer();
        $this->assertNotSame('—', $f['amount']);
        $this->assertFalse($f['disabled']);                                 // con hora → ACTIVO

        // Bajar la cantidad a 0 NO inactiva el botón (se valida al pulsar, con feedback claro).
        $c->set('qty', 0);
        $this->assertFalse($c->instance()->footer()['disabled']);
    }

    /** Validar-al-pulsar (#UX): con un campo obligatorio vacío, el botón está ACTIVO; al pulsar
        «Añadir» se da feedback ESPECÍFICO (error por campo + resumen que lo nombra) y NO se añade.
        Al rellenarlo y reintentar, se añade al carrito. */
    public function test_pack_validates_required_fields_on_click_with_specific_feedback(): void
    {
        App::setLocale('es');
        // Pack con un campo de evento obligatorio en la fase de reserva (stage por defecto = booking).
        $this->pack->update(['event_fields' => [
            ['key' => 'festejado', 'type' => 'text', 'required' => true, 'label' => ['es' => 'Nombre del festejado']],
        ]]);

        $c = Livewire::test(Purchase::class)
            ->call('selectType', $this->pack->id)
            ->call('selectDate', $this->date)->call('goToTime')
            ->call('selectTime', '10:00:00');                               // qty = min del pack

        // Con el campo vacío, el botón sigue ACTIVO (no es un botón muerto).
        $this->assertFalse($c->instance()->footer()['disabled']);

        // Pulsar «Añadir» con el campo vacío → feedback específico, y NO se añade al carrito.
        $c->call('addToCart')
            ->assertHasErrors('eventData.festejado')                        // resalta el campo concreto
            ->assertHasErrors('selection')                                  // resumen
            ->assertSee('Nombre del festejado')                             // el resumen NOMBRA el campo
            ->assertSet('step', 3);                                         // sigue en el paso (no añadió)
        $this->assertSame(0, count($c->get('cart')));

        // Rellenar y reintentar → se añade y pasa al carrito.
        $c->set('eventData.festejado', 'Leo')
            ->call('addToCart')
            ->assertHasNoErrors()
            ->assertSet('step', 4);
        $this->assertSame(1, count($c->get('cart')));
    }

    /** El footer ancla en el TOTAL y, con señal, desglosa «Pagas ahora / En el parque» + nota de IVA. */
    public function test_footer_anchors_on_total_and_breaks_down_the_deposit(): void
    {
        // Paso 3 (producto único con señal): Total 180 €, desglose con «(señal)» (aclara el cobro menor).
        $c = Livewire::test(Purchase::class)
            ->call('selectType', $this->depEntry->id)
            ->call('selectDate', $this->date)->call('goToTime')
            ->call('selectTime', '10:00:00')
            ->set('qty', 1);
        $f = $c->instance()->footer();
        $this->assertSame(__('tickets.total'), $f['label']);
        $this->assertSame('180,00 €', $f['amount']);                       // ancla en el TOTAL
        $this->assertSame('popover', $f['splitMode']);                     // desglose vía ⓘ
        $this->assertSame(__('tickets.footer_pay_now_deposit'), $f['split']['nowLabel']);
        $this->assertSame('30,00 €', $f['split']['now']);                  // pagas ahora (señal)
        $this->assertSame('150,00 €', $f['split']['park']);                // en el parque
        $this->assertSame(__('tickets.iva_note'), $f['note']);

        // Producto sin señal: sin desglose, solo «Total» + nota de IVA.
        $plain = Livewire::test(Purchase::class)
            ->call('selectType', $this->entry->id)
            ->call('selectDate', $this->date)->call('goToTime')
            ->call('selectTime', '10:00:00');
        $this->assertNull($plain->instance()->footer()['split']);
        $this->assertSame(__('tickets.iva_note'), $plain->instance()->footer()['note']);

        // Carrito con señal: el Total sigue siendo el ancla y el desglose «Pagas ahora» es NEUTRO
        // (sin «(señal)»; en cestas mixtas no es solo señal — #225).
        $depCart = Livewire::test(Purchase::class)
            ->set('cart', [['ticket_type_id' => $this->depEntry->id, 'date' => $this->date, 'time' => '10:00:00', 'qty' => 1]])
            ->set('step', 4);
        $f = $depCart->instance()->footer();
        $this->assertSame(__('tickets.total'), $f['label']);
        $this->assertSame('180,00 €', $f['amount']);
        $this->assertSame(__('tickets.footer_pay_now'), $f['split']['nowLabel']);   // neutro
        $this->assertSame('30,00 €', $f['split']['now']);
        $this->assertSame('150,00 €', $f['split']['park']);
    }

    /** El markup: stepper y footer se pintan; las 3 barras mudas y el enlace de carrito superior se retiran. */
    public function test_markup_renders_stepper_and_footer_and_drops_legacy_controls(): void
    {
        Livewire::test(Purchase::class)
            ->call('selectType', $this->entry->id)                          // paso 2 → modo booking
            ->assertSee('bk-progress', false)                               // stepper detallado
            ->assertSee('bk-seg', false)
            ->assertSee(__('tickets.phase_date'))                           // Fecha
            ->assertSee(__('tickets.phase_time'))                           // Hora
            ->assertSee(__('tickets.phase_extras'))                         // Extras (entrada)
            ->assertSee(__('tickets.step_count', ['n' => 1, 'total' => 3])) // «Paso 1 de 3»
            ->assertDontSee('wiz__steps', false)                            // las 3 barras mudas, fuera
            ->assertDontSee('cart-link', false);                            // enlace de carrito superior, fuera
    }

    /** El footer sticky aparece fuera del scroll con su CTA y la nota de pago cuando hay barra. */
    public function test_footer_markup_appears_with_its_cta(): void
    {
        Livewire::test(Purchase::class)
            ->call('selectType', $this->entry->id)
            ->call('selectDate', $this->date)->call('goToTime')
            ->call('selectTime', '10:00:00')
            ->assertSee('purchase__scroll', false)                          // zona scrollable
            ->assertSee('bk-foot', false)                                   // footer fuera del scroll
            ->assertSee('bk-foot__row', false)                              // fila total + CTA
            ->assertSee(__('tickets.add_to_cart'))
            ->assertSee(__('tickets.iva_note'));                            // nota de pago en el footer
    }

    /** El footer del catálogo es el botón-carrito del mockup (badge + info + «Ir al carrito»). */
    public function test_catalog_footer_renders_the_mockup_cart_button(): void
    {
        Livewire::test(Purchase::class)
            ->set('cart', [['ticket_type_id' => $this->entry->id, 'date' => $this->date, 'time' => '10:00:00', 'qty' => 1]])
            ->set('step', 1)
            ->assertSee('cartbar', false)                                   // botón-carrito del mockup
            ->assertSee('cartbar__count', false)                            // badge de cantidad
            ->assertSee(__('tickets.go_to_cart'));
    }

    /** Carrito con señal: el desglose va en un popover (ⓘ), no en líneas que ensanchen el footer. */
    public function test_cart_footer_keeps_clean_height_with_a_deposit_popover(): void
    {
        Livewire::test(Purchase::class)
            ->set('cart', [['ticket_type_id' => $this->depEntry->id, 'date' => $this->date, 'time' => '10:00:00', 'qty' => 1]])
            ->set('step', 4)
            ->assertSee('bk-foot__info', false)        // disparador ⓘ del desglose
            ->assertSee('bk-foot__pop', false)         // popover (oculto hasta abrir, vía Alpine)
            ->assertSee('180,00 €')                    // Total (ancla, en la fila)
            ->assertDontSee('bk-paybreakdown', false); // la banda es solo del paso de pago
    }

    /** Paso de pago: desglose en banda sticky propia (siempre visible) + «volver al carrito». */
    public function test_pay_step_shows_breakdown_band_and_back_to_cart(): void
    {
        $c = Livewire::test(Purchase::class)
            ->set('cart', [['ticket_type_id' => $this->depEntry->id, 'date' => $this->date, 'time' => '10:00:00', 'qty' => 1]])
            ->set('step', 8);

        $this->assertSame('band', $c->instance()->footer()['splitMode']);

        $c->assertSee('bk-paybreakdown', false)        // banda de desglose, encima del footer
            ->assertSee(__('tickets.footer_pay_now'))   // pagas ahora (neutro)
            ->assertSee(__('tickets.pay_at_park'))      // en el parque
            ->assertDontSee('bk-foot__info', false)     // en pago no se usa el popover
            ->assertSee(__('tickets.back_to_cart'));     // CTA para volver al carrito
    }

    /** La cuenta del sidebar incluye el tag sutil (visible solo al minimizarse, vía CSS). */
    public function test_account_context_includes_the_minimised_tag(): void
    {
        // Invitado: la página de entradas renderiza el layout con el bloque de cuenta y su tag, y el
        // panel enlaza su clase de modo a `$store.purchase.mode` (puente del sidebar v2).
        $this->get(route('entradas'))
            ->assertOk()
            ->assertSee(__('account.sidecart.tag'))
            ->assertSee("'is-' + \$store.purchase.mode", false);
    }
}
