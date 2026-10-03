<?php

namespace Tests\Feature\Mail;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderAdjustment;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Identity\Models\Dependent;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\WaiverSettings;
use App\Domain\Payments\Models\Payment;
use App\Domain\Platform\Models\Setting;
use App\Domain\Platform\Services\DisplayTime;
use App\Notifications\OrderConfirmation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use Tests\TestCase;

/**
 * **LA RESERVA HECHA, EL DISEÑO** (la R2b de `specs/correos-rediseno.md` §4.3; el 1, el 1b y el 2): sus tres caras, el
 * resguardo con el dinero del LIBRO de la reserva, quién firma (`#875`), lo comprado y lo del producto en «Antes de venir», el
 * plazo de cambio y los pasos de una fiesta con su formulario firmado. Cada promesa, con su control.
 *
 * ⚠️ El reloj, ANCLADO (`TESTING.md` §2): el sábado 3 de octubre de 2026 a las 10:00 en el parque; la visita, el sábado 17 a
 * las 17:00 —el plazo de un día vence el viernes 16 a la misma hora—.
 */
class ReservationMailTest extends TestCase
{
    use RefreshDatabase;

    private const AHORA = '2026-10-03 08:00:00';

    private const NB = "\u{00A0}";

    private Zone $zona;

    private Slot $franja;

    private User $cliente;

    /** El número del pedido siguiente («R-1», «R-2»…): por prueba, no por proceso. */
    private int $pedidos = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse(self::AHORA, 'UTC'));
        App::setLocale('es');
        $this->ajustes([
            'contact.phone' => '600 123 456', 'contact.whatsapp' => '+34 600 123 456', 'contact.email' => 'hola@parque.test',
            'address.maps_url' => 'https://maps.example.test/parque', WaiverSettings::KEY_MODE => WaiverSettings::MODE_INTERNAL,
        ]);
        $this->zona = Zone::create(['slug' => 'jump', 'name' => ['es' => 'Jump'], 'position' => 1, 'is_active' => true]);
        $this->franja = $this->franja('2026-10-17');
        $this->cliente = User::factory()->create(['locale' => 'es']);
    }

    public function test_each_face_has_its_header_and_subject(): void
    {
        $entradas = $this->correo($this->pedido([[$this->entrada(), 2, 800]]));
        $this->assertSame('Reservado', $entradas->viewData['hero']['chapa']);
        $this->assertSame('¡Nos vemos el sábado 17!', $entradas->viewData['hero']['titulo']);
        $this->assertSame('Reservado: sábado 17 de octubre a las 17:00 · Entrada 1 hora', $entradas->subject);

        $grupo = $this->correo($this->pedido([[$this->grupo(), 20, 1500]], resto: 20000));
        $this->assertSame('¡Os esperamos el sábado 17!', $grupo->viewData['hero']['titulo']);

        $fiesta = $this->correo($this->pedido([[$this->fiesta(), 8, 1500]], datos: ['celebrant' => 'Vera']));
        $this->assertSame('', $fiesta->viewData['hero']['chapa'], 'la fiesta no lleva chapa, como el diseño');
        $this->assertSame('¡Fiesta reservada!', $fiesta->viewData['hero']['titulo']);
        $this->assertSame('Fiesta reservada: sábado 17 de octubre a las 17:00 · el cumple de Vera', $fiesta->subject);
        // CONTROL: sin el nombre de quien cumple, el producto.
        $sinNombre = $this->correo($this->pedido([[$this->fiesta(), 8, 1500]], datos: ['celebrant' => '']));
        $this->assertSame('Fiesta reservada: sábado 17 de octubre a las 17:00 · Cumpleaños Kids', $sinNombre->subject);

        // Varias reservas en DOS días: ni un día en el titular ni uno en el asunto (`#506`).
        $pedido = $this->pedido([[$this->entrada(), 1, 800], [$this->entrada(), 1, 800, $this->franja('2026-10-18')]]);
        $varias = $this->correo($pedido);
        $this->assertSame('¡Reservado!', $varias->viewData['hero']['titulo']);
        $this->assertSame('Reservado: 2 reservas · nº '.$pedido->code, $varias->subject);
        $this->assertCount(2, $this->bloques($varias, 'resguardo'), 'cada reserva, su resguardo');
    }

    public function test_the_slip_says_when_what_and_the_money_of_the_reservation_book(): void
    {
        $resguardo = $this->bloques($this->correo($this->pedido([[$this->entrada(), 2, 800]])), 'resguardo')[0];

        $this->assertSame(['dow' => 'sáb', 'n' => '17', 'month' => 'oct'], $resguardo['dia']);
        $this->assertSame('17:00', $resguardo['hora']);
        $this->assertSame('Entrada 1 hora · '.trans_choice('tickets.entries_count', 2, ['count' => 2]), $resguardo['que']);
        $this->assertSame('16'.self::NB.'€ pagados', $resguardo['precio'], 'pagada entera: lo pagado, del libro');
        $this->assertSame([], $resguardo['dinero']);
        $this->assertSame('Nº R-1', $resguardo['codigo']);

        // Con señal: el precio por persona y las dos filas, la del día en negrita (`shows_deposit_note`).
        $grupo = $this->bloques($this->correo($this->pedido([[$this->grupo(), 20, 1500]], resto: 20000)), 'resguardo')[0];
        $this->assertSame('15'.self::NB.'€ por persona', $grupo['precio']);
        $this->assertSame([['Señal pagada', '100'.self::NB.'€', false], ['El día de la visita', '200'.self::NB.'€', true]], $grupo['dinero']);

        // Un libro que NO cuadra (cobrado de menos) no afirma ningún importe: solo su frase, la de Mi cuenta («en revisión»).
        $raro = $this->bloques($this->correo($this->pedido([[$this->entrada(), 2, 800]], cobrado: 900)), 'resguardo')[0];
        $this->assertSame([], $raro['dinero']);
        $this->assertNotNull($raro['precio'], 'dice que está en revisión, no calla');
        $this->assertStringNotContainsString('€', (string) $raro['precio']);
    }

    public function test_the_slip_links_open_the_map_and_a_signed_calendar(): void
    {
        $enlaces = $this->bloques($this->correo($this->pedido([[$this->entrada(), 2, 800]])), 'resguardo')[0]['enlaces'];

        $this->assertSame(['Cómo llegar', 'https://maps.example.test/parque', 'map-pin'], $enlaces[0]);
        [$texto, $url, $icono] = $enlaces[1];
        $this->assertSame(['Añadir al calendario', 'calendar-plus'], [$texto, $icono]);
        $this->get($url)->assertOk()->assertHeader('Content-Type', 'text/calendar; charset=utf-8');
        // CONTROL: sin su firma, no se abre.
        $this->get(preg_replace('/signature=[0-9a-f]+/', 'signature=00', $url))->assertForbidden();

        // Sin mapa en el panel, sin «Cómo llegar» (sin dato, sin enlace).
        $this->ajustes(['address.maps_url' => '']);
        $sinMapa = $this->bloques($this->correo($this->pedido([[$this->entrada(), 2, 800]])), 'resguardo')[0]['enlaces'];
        $this->assertSame(['Añadir al calendario'], array_column($sinMapa, 0));
    }

    /** Quién firma (`#875`): en unas ENTRADAS si la instalación firma dentro; la tarea de los menores, si aún no tiene ninguno. */
    public function test_who_signs_follows_the_installation_and_the_holder(): void
    {
        $lineas = fn (): array => array_column($this->lista($this->correo($this->pedido([[$this->entrada(), 2, 800]]))), 'texto');
        $menores = (string) __('emails.reserva.minors');
        $adultos = (string) __('emails.reserva.adults');

        $this->assertSame([$menores, $adultos], array_slice($lineas(), 0, 2));
        $tarea = $this->lista($this->correo($this->pedido([[$this->entrada(), 2, 800]])))[0];
        $this->assertSame(['user-round-plus', true], [$tarea['icono'], $tarea['tarea']]);

        // Con un menor a su cargo, sin la tarea; uno DESVINCULADO no cuenta.
        $menor = Dependent::create(['user_id' => $this->cliente->id, 'name' => 'Vera', 'relationship' => 'mother', 'born_on' => DisplayTime::today()->subYears(7)->toDateString()]);
        $this->assertNotContains($menores, $lineas());
        $this->assertContains($adultos, $lineas());
        $menor->forceFill(['removed_at' => now()])->save();
        $this->assertContains($menores, $lineas(), 'un menor desvinculado no es uno a su cargo');

        // Sin firma en la web, nada de quién firma.
        $this->ajustes([WaiverSettings::KEY_MODE => WaiverSettings::MODE_EXTERNAL]);
        $this->assertSame([], array_values(array_intersect([$menores, $adultos], $lineas())));

        // Y en un grupo, tampoco (la regla de «¡Reservado!»: solo entradas).
        $this->ajustes([WaiverSettings::KEY_MODE => WaiverSettings::MODE_INTERNAL]);
        $grupo = array_column($this->lista($this->correo($this->pedido([[$this->grupo(), 20, 1500]], resto: 20000))), 'texto');
        $this->assertSame([], array_values(array_intersect([$menores, $adultos], $grupo)));
    }

    public function test_before_visit_says_what_was_bought_what_the_product_adds_and_the_time(): void
    {
        $entrada = $this->entrada(['before_visit' => ['es' => ['Parking gratis para todos.']]]);
        $calcetines = $this->complemento('Calcetines', ['icon' => 'socks', 'reservation_note' => ['es' => 'Tenéis :n pares de calcetines; os los damos en la puerta.']]);
        $tarta = $this->complemento('Tarta');
        $pedido = $this->pedido([[$entrada, 2, 800]], complementos: [[$calcetines, 2], [$tarta, 1]]);

        $lineas = array_slice($this->lista($this->correo($pedido)), 2); // tras quién firma
        $this->assertSame([
            ['texto' => 'Tenéis 2 pares de calcetines; os los damos en la puerta.', 'icono' => 'footprints', 'tarea' => false],
            ['texto' => (string) __('isla.mi_cuenta.proxima.complemento', ['nombre' => 'Tarta', 'cantidad' => trans_choice('tickets.units_count', 1, ['count' => 1])]), 'icono' => 'package', 'tarea' => false],
            ['texto' => 'Parking gratis para todos.', 'icono' => 'info', 'tarea' => false],
            ['texto' => 'Tu tiempo empieza a las 17:00: llegad unos minutos antes.', 'icono' => 'clock', 'tarea' => false],
        ], $lineas);

        // Un grupo, su hora a su manera; con VARIAS reservas, ninguna hora (cada resguardo dice la suya).
        $grupo = $this->lista($this->correo($this->pedido([[$this->grupo(), 20, 1500]], resto: 20000)));
        $this->assertSame('Llegad unos minutos antes de las 17:00.', end($grupo)['texto']);
        $this->ajustes([WaiverSettings::KEY_MODE => WaiverSettings::MODE_EXTERNAL]);
        $varias = $this->correo($this->pedido([[$this->entrada(), 1, 800], [$this->entrada(), 1, 800, $this->franja('2026-10-18')]]));
        $this->assertSame([], $this->bloques($varias, 'lista'), 'sin nada que decir, sin «Antes de venir»');
    }

    public function test_changes_follow_the_deadline_the_deposit_and_the_phone_of_the_park(): void
    {
        $this->assertSame(
            'Puedes cambiar o cancelar hasta el viernes 16 a las 17:00: [escríbenos por WhatsApp](whatsapp) o llámanos al [600 123 456](tel).',
            $this->seccion($this->correo($this->pedido([[$this->entrada(), 2, 800]]))),
        );
        // La señal se devuelve si el producto lo promete Y la reserva nació con ella.
        $this->assertStringContainsString('y te devolvemos la señal', $this->seccion($this->correo($this->pedido([[$this->grupo(), 20, 1500]], resto: 20000))));
        $this->assertStringNotContainsString('señal', $this->seccion($this->correo($this->pedido([[$this->grupo(), 20, 1500]]))), 'CONTROL: pagada entera, no hay señal que devolver');
        // Varias reservas: sin fecha.
        $varias = $this->correo($this->pedido([[$this->entrada(), 1, 800], [$this->entrada(), 1, 800, $this->franja('2026-10-18')]]));
        $this->assertSame((string) __('emails.reserva.changes_open', ['phone' => '600 123 456']), $this->seccion($varias));

        // El enlace de WhatsApp lleva el mensaje de cambio ya escrito (el de Mi cuenta).
        $pedido = $this->pedido([[$this->entrada(), 2, 800]]);
        $correo = $this->correo($pedido);
        $this->assertSame('https://wa.me/34600123456?text='.rawurlencode("Hola, quiero cambiar o cancelar mi reserva {$pedido->code} del sábado 17 a las 17:00."), $correo->viewData['enlaces']['whatsapp']);
        $this->assertSame('tel:600123456', $correo->viewData['enlaces']['tel']);

        // Pasado el plazo, que ya no se puede.
        $this->travelTo(Carbon::parse('2026-10-16 16:00:00', 'UTC'));
        $this->assertSame((string) __('emails.reserva.changes_late', ['phone' => '600 123 456']), $this->seccion($this->correo($this->pedido([[$this->entrada(), 2, 800]]))));

        // Sin teléfono en el panel, sin sección.
        $this->ajustes(['contact.phone' => '']);
        $this->assertSame([], $this->bloques($this->correo($this->pedido([[$this->entrada(), 2, 800]])), 'seccion'));
    }

    public function test_a_party_carries_its_steps_with_the_signed_form_and_its_qr_steps_aside(): void
    {
        $fiesta = $this->correo($this->pedido([[$this->fiesta(), 8, 1500]], datos: ['celebrant' => 'Vera']));

        $pasos = $this->bloques($fiesta, 'pasos')[0];
        $this->assertSame('Ahora, dos cosas', $pasos['titulo']);
        // Hasta el plazo de la lista (`GuestCountPolicy`, un día antes por defecto): el viernes 16.
        $this->assertSame('Rellena el formulario de invitados, hasta el viernes 16: quién viene, edades y alergias.', $pasos['pasos'][0]['texto']);
        // El formulario se abre SIN sesión, con su firma (y la UTM del correo detrás).
        $this->get($pasos['pasos'][0]['url'])->assertOk();
        $this->assertStringContainsString('#gf-invite', $pasos['pasos'][1]['url']);
        $this->assertTrue($this->bloques($fiesta, 'qr')[0]['secundario'], 'el trabajo de la fiesta son los pasos');
        $this->assertArrayNotHasKey('responde', $fiesta->viewData, 'el 2 no lleva «Responde a este correo»');
        $this->assertSame([], $this->bloques($fiesta, 'lista'), 'ni «Antes de venir»');

        // Sin invitación digital, un paso.
        $sinInvitacion = $this->bloques($this->correo($this->pedido([[$this->fiesta(['guest_invitation' => false]), 8, 1500]])), 'pasos')[0];
        $this->assertSame(['Ahora, una cosa', 1], [$sinInvitacion['titulo'], count($sinInvitacion['pasos'])]);

        // CONTROL: unas entradas, sin pasos, con su QR principal y con respuesta al parque.
        $entradas = $this->correo($this->pedido([[$this->entrada(), 2, 800]]));
        $this->assertSame([], $this->bloques($entradas, 'pasos'));
        $this->assertFalse($this->bloques($entradas, 'qr')[0]['secundario']);
        $this->assertSame((string) __('emails.reserva.replies_label'), $entradas->viewData['responde']);
        $this->assertSame('hola@parque.test', $entradas->replyTo[0][0], 'responder llega al parque');
    }

    // ── El caso ───────────────────────────────────────────────────────────────────────────────────────────────────────────

    /** @param  array<string, string>  $valores */
    private function ajustes(array $valores): void
    {
        foreach ($valores as $clave => $valor) {
            Setting::query()->updateOrCreate(['key' => $clave], ['value' => $valor]);
        }
        Setting::flushMemo();
    }

    private function franja(string $dia): Slot
    {
        return Slot::create(['zone_id' => $this->zona->id, 'date' => $dia, 'start_time' => '17:00:00', 'end_time' => '18:00:00', 'capacity' => 100, 'online_capacity' => 100]);
    }

    /** @param  array<string, mixed>  $mas */
    private function entrada(array $mas = []): TicketType
    {
        // `array_replace` y no `+`: la unión conserva la clave de la IZQUIERDA y lo pedido no llegaría.
        return TicketType::create(array_replace([
            'zone_id' => $this->zona->id, 'type' => TicketType::TYPE_ENTRY, 'name' => ['es' => 'Entrada 1 hora'],
            'duration_min' => 60, 'seats_per_unit' => 1, 'is_sellable' => true, 'is_active' => true, 'position' => 1,
            'cancellation_cutoff_hours' => 24,
        ], $mas));
    }

    private function grupo(): TicketType
    {
        return TicketType::create([
            'zone_id' => $this->zona->id, 'type' => TicketType::TYPE_PACK, 'name' => ['es' => 'Excursión 2 horas'],
            'duration_min' => 120, 'seats_per_unit' => 1, 'min_qty' => 10, 'max_qty' => 80, 'is_sellable' => true, 'is_active' => true,
            'position' => 2, 'cancellation_cutoff_hours' => 72, 'deposit_refundable_in_time' => true,
        ]);
    }

    /** @param  array<string, mixed>  $mas */
    private function fiesta(array $mas = []): TicketType
    {
        return TicketType::create(array_replace([
            'zone_id' => $this->zona->id, 'type' => TicketType::TYPE_PACK, 'name' => ['es' => 'Cumpleaños Kids'],
            'duration_min' => 120, 'seats_per_unit' => 1, 'min_qty' => 1, 'max_qty' => 20, 'is_sellable' => true, 'is_active' => true,
            'position' => 3, 'guest_invitation' => true, 'guest_fields' => TicketType::DEFAULT_GUEST_FIELDS,
            'event_fields' => [['key' => 'celebrant', 'type' => 'text', 'required' => true, 'stage' => TicketType::EVENT_STAGE_BOOKING, 'label' => ['es' => 'Homenajeado']]],
        ], $mas));
    }

    /** @param  array<string, mixed>  $mas */
    private function complemento(string $nombre, array $mas = []): TicketType
    {
        return TicketType::create(array_replace(['type' => TicketType::TYPE_ADDON, 'name' => ['es' => $nombre], 'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 1, 'position' => 9], $mas));
    }

    /**
     * Un pedido PAGADO cuyo libro cuadra: sus líneas (producto, cantidad, precio y, si no es la de siempre, su franja), el
     * resto al parque si hay señal, sus complementos (de la primera) y el cobro —lo debido, salvo que se diga otro—.
     *
     * @param  list<array{0: TicketType, 1: int, 2: int, 3?: Slot}>  $lineas
     * @param  list<array{0: TicketType, 1: int}>  $complementos
     * @param  array<string, mixed>  $datos
     */
    private function pedido(array $lineas, int $resto = 0, array $complementos = [], array $datos = [], ?int $cobrado = null): Order
    {
        $total = array_sum(array_map(static fn (array $l): int => $l[1] * $l[2], $lineas));
        $pedido = Order::create([
            'user_id' => $this->cliente->id, 'code' => 'R-'.(++$this->pedidos), 'status' => Order::STATUS_PAID,
            'subtotal' => $total, 'tax' => 0, 'total' => $total, 'currency' => 'EUR', 'paid_at' => now(),
        ]);
        $primera = null;
        foreach ($lineas as $l) {
            $linea = $pedido->items()->create([
                'ticket_type_id' => $l[0]->id, 'slot_id' => ($l[3] ?? $this->franja)->id, 'quantity' => $l[1], 'unit_price' => $l[2], 'seats' => $l[1],
                'event_data' => $datos === [] ? null : $datos,
            ]);
            $primera ??= $linea;
        }
        foreach ($complementos as [$tipo, $cantidad]) {
            $primera->children()->create(['order_id' => $pedido->id, 'ticket_type_id' => $tipo->id, 'quantity' => $cantidad, 'seats' => 0, 'unit_price' => 0]);
        }
        if ($resto > 0) {
            $pedido->adjustments()->create([
                'order_item_id' => $primera->id, 'type' => OrderAdjustment::TYPE_DEPOSIT_SPLIT, 'amount_cents' => $resto,
                'currency' => 'EUR', 'reason' => 'deposit_split', 'applied_by' => $this->cliente->id,
            ]);
        }
        Payment::create([
            'payable_type' => $pedido->getMorphClass(), 'payable_id' => $pedido->id, 'provider' => 'redsys', 'amount' => $cobrado ?? $total - $resto,
            'currency' => 'EUR', 'status' => Payment::STATUS_PAID, 'paid_at' => now(), 'gateway_order' => '17'.$pedido->id.'0001',
        ]);

        return $pedido->fresh();
    }

    private function correo(Order $pedido): MailMessage
    {
        return (new OrderConfirmation($pedido))->toMail($this->cliente);
    }

    /** @return list<array<string, mixed>> */
    private function bloques(MailMessage $correo, string $tipo): array
    {
        return array_values(array_filter($correo->viewData['cuerpo'] ?? [], static fn (array $b): bool => $b['tipo'] === $tipo));
    }

    /** @return list<array{texto: string, icono: ?string, tarea: bool}> */
    private function lista(MailMessage $correo): array
    {
        return $this->bloques($correo, 'lista')[0]['lineas'] ?? [];
    }

    private function seccion(MailMessage $correo): string
    {
        return (string) ($this->bloques($correo, 'seccion')[0]['texto'] ?? '');
    }
}
