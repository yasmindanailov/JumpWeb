<?php

namespace Tests\Feature\Admin\Orders;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Filament\Pages\CreateManualOrderPage as Cmo;
use App\Notifications\GuardianAuthorizationRequest;
use App\Notifications\GuestFormRequest;
use App\Notifications\OrderConfirmation;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * **EL DESENLACE del pedido manual** (`#466`, T4 de `specs/asistente-crear-pedido.md`).
 *
 * `[owner]`: «después de crear el pedido, una pantalla nueva: pedido creado correctamente…». Antes
 * había un *toast* y una redirección a la ficha del pedido —2.347 px de administración, con el
 * cliente delante y sin que nada dijera qué hacer ahora—.
 *
 * ❗❗❗ **Lo que esta guarda protege no es la pantalla: es que no MIENTA.** El mostrador la lee en voz
 * alta, y su afirmación más peligrosa es «se le ha enviado». **Hay clientes SIN correo** —el alta de
 * mostrador solo pide teléfono (`#263`)— y con ellos `ManualOrderFulfiller` **no envía nada**: ni la
 * confirmación, ni el post-form, ni el justificante. Una pantalla que lo diera por enviado mandaría
 * al operador a casa creyendo que el cliente tiene su enlace.
 *
 * ▶ Por eso el caso central **compara lo que la pantalla dice con lo que se ha NOTIFICADO de verdad**
 * (`Notification::fake()`), en vez de comprobar que un texto aparece: si el fulfiller cambia a quién
 * le manda qué, la pantalla se pone roja aquí en vez de empezar a mentir.
 */
class CreateManualOrderDoneTest extends TestCase
{
    use RefreshDatabase;

    private Zone $zona;

    private TicketType $entrada;

    private TicketType $pack;

    private string $dia;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-16 09:00:00');
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        RateType::firstOrCreate(['key' => RateType::KEY_NORMAL], ['label' => ['es' => 'Normal'], 'weekdays' => null, 'priority' => 0]);
        $tarifa = RateType::where('key', RateType::KEY_NORMAL)->value('id');

        $this->dia = '2026-09-18';
        $this->zona = Zone::create(['slug' => 'jump', 'name' => ['es' => 'JUMP'], 'position' => 1]);
        $this->entrada = TicketType::create([
            'name' => ['es' => 'Jump · 1 hora'], 'zone_id' => $this->zona->id, 'duration_min' => 60,
            'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 1, 'position' => 1,
        ]);
        $this->entrada->prices()->create(['rate_type_id' => $tarifa, 'amount_cents' => 1190]);

        // ⚠️⚠️ **El PACK con post-form y con justificante es el SUJETO de la guarda central.** Sin él,
        // «formularios enviados» y «justificantes enviados» valen 0 en los dos lados de la
        // comparación y el caso pasaría con la pantalla informando siempre CERO. *Una igualdad entre
        // dos ceros no vigila nada.*
        $this->pack = TicketType::create([
            'name' => ['es' => 'Cumpleaños Jump'], 'type' => TicketType::TYPE_PACK,
            'zone_id' => $this->zona->id, 'duration_min' => 60,
            'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 1,
            'min_qty' => 2, 'max_qty' => 20, 'position' => 2,
            'guest_fields' => [['key' => 'nombre', 'label' => ['es' => 'Nombre'], 'type' => 'text', 'required' => true]],
            'guardian_authorization' => TicketType::GUARDIAN_OPTIONAL,
        ]);
        $this->pack->prices()->create(['rate_type_id' => $tarifa, 'amount_cents' => 5000]);

        Slot::create([
            'zone_id' => $this->zona->id, 'date' => $this->dia,
            'start_time' => '10:00:00', 'end_time' => '11:00:00',
            'capacity' => 40, 'online_capacity' => 40,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ─── Lo que la pantalla afirma sobre el correo ────────────────────────────────────────────

    /**
     * ❗❗❗ **La pantalla dice exactamente lo que se ha enviado.** No se comprueba un texto: se compara
     * con las notificaciones REALES, que es lo único que no puede desincronizarse.
     */
    public function test_what_the_screen_says_was_sent_is_what_was_really_sent(): void
    {
        Notification::fake();
        $cliente = $this->cliente('con-correo@jumpweb.test');

        $componente = $this->crear($cliente, cantidad: 2, producto: $this->pack, justificante: true);
        $resumen = $componente->instance()->doneSummary();

        // CONTROL del propio caso: si el sujeto no disparara los tres correos, comparar ceros con
        // ceros daría verde con la pantalla informando cualquier cosa.
        Notification::assertSentTo($cliente, OrderConfirmation::class);
        $this->assertSame(1, $this->enviadas($cliente, GuestFormRequest::class), 'el sujeto no pidió el formulario de invitados');
        $this->assertSame(1, $this->enviadas($cliente, GuardianAuthorizationRequest::class), 'el sujeto no pidió el justificante');
        $this->assertTrue($resumen['sent']['confirmation'], 'la pantalla no cuenta la confirmación que SÍ se envió');
        $this->assertSame(
            $this->enviadas($cliente, GuestFormRequest::class), $resumen['sent']['guest_form'],
            'la pantalla y el correo no cuentan lo mismo del formulario de invitados',
        );
        $this->assertSame(
            $this->enviadas($cliente, GuardianAuthorizationRequest::class), $resumen['sent']['guardian'],
            'la pantalla y el correo no cuentan lo mismo del justificante',
        );

        $componente->assertSee(__('admin.orders.create_manual.done_mail_title'));
        $componente->assertSee('con-correo@jumpweb.test');
    }

    /**
     * ❗❗❗ **El caso que motiva la tanda**: un cliente de agenda —solo teléfono— no recibe NADA, y la
     * pantalla lo dice con todas las letras en vez de callarse.
     */
    public function test_a_customer_without_email_is_told_that_nothing_was_sent(): void
    {
        Notification::fake();
        $cliente = $this->cliente(null);

        // ⚠️⚠️ **Compra EXACTAMENTE lo mismo que el caso con correo** —el pack con post-form y con
        // justificante—, y ahí está el sujeto: sin él las tres cifras valen 0 con y sin la regla, y
        // la mutación «cuenta los correos sin mirar si hay email» pasaba en VERDE. *Lo que distingue
        // los dos casos tiene que ser SOLO el correo.*
        $componente = $this->crear($cliente, cantidad: 2, producto: $this->pack, justificante: true);
        $resumen = $componente->instance()->doneSummary();

        Notification::assertNothingSentTo($cliente);
        $this->assertNotSame([], $resumen['links'], 'CONTROL: este pedido SÍ tiene enlaces que entregar a mano.');

        $this->assertFalse($resumen['sent']['confirmation']);
        $this->assertSame(0, $resumen['sent']['guest_form']);
        $this->assertSame(0, $resumen['sent']['guardian']);

        $componente->assertSee(__('admin.orders.create_manual.done_no_mail_title'));
        $componente->assertSee(__('admin.orders.create_manual.done_no_mail_body'));
        $componente->assertDontSee(__('admin.orders.create_manual.done_mail_title'));
    }

    // ─── Lo que impide cobrar dos veces ───────────────────────────────────────────────────────

    /**
     * ⚠️⚠️ **Un segundo `create()` no crea un segundo pedido.**
     *
     * Antes lo garantizaba la redirección: al terminar, la página dejaba de existir. Sin ella el
     * estado sigue vivo en el navegador, así que un doble clic —o un `wire:click` repetido a mano—
     * volvería a cobrar. Lo que lo impide es que el carrito se VACÍA al crear, y por eso este caso
     * llama a `create()` otra vez en vez de confiar en que el botón ya no esté.
     */
    public function test_calling_create_again_does_not_charge_twice(): void
    {
        Notification::fake();
        $cliente = $this->cliente('doble@jumpweb.test');

        $componente = $this->crear($cliente);
        $this->assertSame(1, Order::where('user_id', $cliente->id)->count());

        $componente->call('create');

        $this->assertSame(
            1, Order::where('user_id', $cliente->id)->count(),
            'Un segundo «cobrar» ha creado OTRO pedido: el carrito no se vació al terminar.',
        );
    }

    /**
     * ⚠️ **Desde el desenlace no se vuelve al asistente.** Un «atrás» llevaría a un formulario con el
     * carrito vacío y un pedido ya cobrado detrás: ni el estado de antes ni el de después. La única
     * salida es empezar otro pedido.
     */
    public function test_the_wizard_does_not_navigate_while_the_outcome_is_on_screen(): void
    {
        Notification::fake();
        $componente = $this->crear($this->cliente('nav@jumpweb.test'));

        foreach ([['back', []], ['next', []], ['goToStep', [Cmo::STEP_PRODUCT]]] as [$metodo, $args]) {
            $componente->call($metodo, ...$args)->assertSet('step', Cmo::STEP_DONE, "«{$metodo}» sacó del desenlace");
        }
    }

    /**
     * «Crear otro pedido» limpia TODO, **incluido el cliente**: en un mostrador el siguiente pedido es
     * de otra persona, y dejar al anterior puesto es la forma más fácil de cobrarle a quien no era.
     */
    public function test_starting_another_order_clears_everything_including_the_customer(): void
    {
        Notification::fake();
        $componente = $this->crear($this->cliente('otro@jumpweb.test'));

        $componente->call('startAnotherOrder')
            ->assertSet('step', Cmo::STEP_CUSTOMER)
            ->assertSet('createdOrderId', null)
            ->assertSet('cart', [])
            ->assertSet('dependentsSkipped', 0)
            ->assertSet('data.customer_id', null);

        $this->assertNull($componente->instance()->doneSummary(), 'el desenlace sobrevivió a su propia salida');
    }

    // ─── Lo que la pantalla enseña del pedido ─────────────────────────────────────────────────

    /**
     * El CÓDIGO es el dato de la pantalla —se dicta por teléfono y se busca en el buscador— y el
     * dinero lo pinta el LIBRO, no una cuenta propia (`#311`: un solo pintor).
     */
    public function test_the_screen_shows_the_code_and_the_book_of_the_order(): void
    {
        Notification::fake();
        $cliente = $this->cliente('libro@jumpweb.test');

        $componente = $this->crear($cliente, cantidad: 3);
        $pedido = Order::where('user_id', $cliente->id)->sole();
        $resumen = $componente->instance()->doneSummary();

        $this->assertSame($pedido->code, $resumen['code']);

        // ⚠️⚠️ **Acotado al ELEMENTO, y no a la página**: la URL de la ficha (`/admin/orders/R-XXXX`)
        // lleva el código dentro, así que un `assertSee` global pasaba **con el hueco del código
        // vacío** — lo dijo la mutación. Es la lección de `#295`/`#303`, otra vez.
        $this->assertMatchesRegularExpression(
            '/data-done-code[^>]*>\s*'.preg_quote($pedido->code, '/').'\s*</',
            $componente->html(),
            'El desenlace ha dejado de enseñar el código del pedido, que es lo que se dicta en voz alta.',
        );

        // El libro del PEDIDO, sin recomponerlo aquí: 3 × 11,90 €.
        $this->assertSame(3570, $resumen['book']->totalCents);
        $this->assertSame(3570, $resumen['book']->paidCents);

        // Y lo reservado, para leerlo en voz alta.
        $this->assertSame(
            [['label' => 'Jump · 1 hora', 'when' => '18/09/2026 10:00', 'qty' => 3]],
            $resumen['reservations'],
        );
    }

    // ─── Andamio ──────────────────────────────────────────────────────────────────────────────

    private function cliente(?string $email): User
    {
        $u = User::factory()->create(['email' => $email, 'name' => 'Cliente Mostrador']);
        $u->roles()->sync([Role::where('name', 'customer')->value('id')]);

        return $u;
    }

    private function staff(): User
    {
        $u = User::factory()->create();
        $u->roles()->sync([Role::where('name', 'admin')->value('id')]);

        return $u;
    }

    /** Monta una línea y cobra, que es el camino por el que se llega al desenlace. */
    private function crear(User $cliente, int $cantidad = 1, ?TicketType $producto = null, bool $justificante = false): Testable
    {
        return Livewire::actingAs($this->staff())
            ->test(Cmo::class)
            ->set('data.customer_id', $cliente->id)
            ->set('data.payment_method', 'cash')
            ->call('pickProduct', ($producto ?? $this->entrada)->id)
            ->set('data.sel_date', $this->dia)
            ->set('data.sel_time', '10:00:00')
            ->set('data.sel_qty', $cantidad)
            ->set('data.sel_guardian_authorization', $justificante)
            ->call('addLineToCart')
            ->call('create')
            ->assertSet('step', Cmo::STEP_DONE);
    }

    /** Cuántas notificaciones de esa clase ha recibido REALMENTE el cliente. */
    private function enviadas(User $cliente, string $clase): int
    {
        return Notification::sent($cliente, $clase)->count();
    }
}
