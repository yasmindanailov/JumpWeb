<?php

namespace Tests\Feature\Mail;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Models\Setting;
use App\Notifications\OrderExpiredWithoutPayment;
use App\Notifications\OrderPaymentDeclined;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use Tests\TestCase;

/**
 * **EL 5 Y EL 6, COMO SU DISEÑO** (la R2e de `specs/correos-rediseno.md` §4.3): «El pago no ha salido» —el día y la hora en el
 * asunto, hasta qué hora sigue GUARDADA (en la zona del parque), los botones, el motivo y la ayuda por WhatsApp solo si la
 * hay— y «Tu hora se ha liberado» —por dónde escribir si pagó: el WhatsApp, si no el correo, si no el teléfono—.
 *
 * ⚠️ El reloj, ANCLADO (`TESTING.md` §2): el sábado 3 de octubre de 2026 a las 10:00 del parque (08:00 UTC).
 */
class PaymentMailsTest extends TestCase
{
    use RefreshDatabase;

    private const AHORA = '2026-10-03 08:00:00';

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse(self::AHORA, 'UTC'));
        App::setLocale('es');
        $this->ajustes(['contact.whatsapp' => '+34 600 123 456', 'contact.email' => 'hola@parque.test', 'contact.phone' => '600 123 456']);
    }

    public function test_the_declined_mail_says_when_until_when_it_is_held_and_why(): void
    {
        $pedido = $this->pedido(expira: '2026-10-03 08:15:00'); // las 10:15 del parque

        $correo = $this->rechazo($pedido);

        $this->assertSame('El pago no ha salido · sábado 17 de octubre a las 17:00', $correo->subject);
        $this->assertSame(['El pago no ha salido', ''], [$correo->viewData['hero']['titulo'], $correo->viewData['hero']['chapa']]);
        $this->assertSame(['texto', 'boton', 'motivo', 'linea'], array_column($correo->viewData['cuerpo'], 'tipo'));
        $this->assertSame(['Tu banco no ha autorizado el cobro y no se ha cargado nada. Tu hora sigue guardada hasta las **10:15**.'], $this->bloque($correo, 'texto')['lineas'], 'la hora, la del PARQUE');
        $this->assertSame('https://wa.me/34600123456', $correo->viewData['enlaces']['whatsapp']);

        // Pasada la retención, no se inventa una hora.
        $this->travelTo(Carbon::parse('2026-10-03 08:20:00', 'UTC'));
        $this->assertSame([__('emails.order_declined.body_sin_hora')], $this->bloque($this->rechazo($pedido), 'texto')['lineas']);
    }

    public function test_without_whatsapp_the_declined_mail_does_not_offer_to_write(): void
    {
        $this->ajustes(['contact.whatsapp' => '']);

        $correo = $this->rechazo($this->pedido());

        $this->assertNull($this->bloque($correo, 'linea'), 'la frase nombra WhatsApp: sin él, no sale');
        // CONTROL: el motivo sigue.
        $this->assertNotNull($this->bloque($correo, 'motivo'));
    }

    public function test_the_expired_mail_says_which_time_was_released_and_where_to_write(): void
    {
        $pedido = $this->pedido();

        $correo = $this->caducada($pedido);
        $this->assertSame('Tu hora se ha liberado · sábado 17 de octubre a las 17:00', $correo->subject);
        $this->assertSame('Tu hora se ha liberado', $correo->viewData['hero']['titulo']);
        $this->assertSame('https://wa.me/34600123456', $correo->viewData['enlaces']['contacto']);
        $this->assertSame([__('emails.order_expired_without_payment.contact', ['code' => $pedido->code])], $this->bloque($correo, 'linea')['lineas']);

        // Sin WhatsApp, el correo; sin correo, el teléfono; sin nada, sin la frase.
        $this->ajustes(['contact.whatsapp' => '']);
        $this->assertSame('mailto:hola@parque.test', $this->caducada($pedido)->viewData['enlaces']['contacto']);
        $this->ajustes(['contact.email' => '']);
        $this->assertSame('tel:600123456', $this->caducada($pedido)->viewData['enlaces']['contacto']);
        $this->ajustes(['contact.phone' => '']);
        $this->assertNull($this->bloque($this->caducada($pedido), 'linea'));
    }

    public function test_with_several_reservations_both_say_the_number_and_not_one_time(): void
    {
        $pedido = $this->pedido(otraFranja: true);

        $this->assertSame('El pago no ha salido · nº '.$pedido->code, $this->rechazo($pedido)->subject);
        $caducada = $this->caducada($pedido);
        $this->assertSame('Tu reserva ha caducado · nº '.$pedido->code, $caducada->subject);
        $this->assertSame('Tu reserva ha caducado', $caducada->viewData['hero']['titulo']);
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

    private function pedido(?string $expira = null, bool $otraFranja = false): Order
    {
        $zona = Zone::firstOrCreate(['slug' => 'jump'], ['name' => ['es' => 'Jump'], 'position' => 1, 'is_active' => true]);
        $tipo = TicketType::create([
            'zone_id' => $zona->id, 'type' => TicketType::TYPE_ENTRY, 'name' => ['es' => 'Entrada 1 hora'],
            'duration_min' => 60, 'seats_per_unit' => 1, 'is_sellable' => true, 'is_active' => true, 'position' => 1,
        ]);
        $franja = static fn (string $dia): Slot => Slot::query()->firstOrCreate(
            ['zone_id' => $zona->id, 'date' => $dia, 'start_time' => '17:00:00'],
            ['end_time' => '18:00:00', 'capacity' => 50, 'online_capacity' => 50],
        );
        $pedido = Order::create([
            'user_id' => User::factory()->create(['locale' => 'es'])->id, 'code' => 'R-PAGO'.random_int(10, 99),
            'status' => Order::STATUS_PENDING, 'subtotal' => 1600, 'total' => 1600, 'currency' => 'EUR',
            'expires_at' => $expira !== null ? Carbon::parse($expira, 'UTC') : null,
        ]);
        $pedido->items()->create(['ticket_type_id' => $tipo->id, 'slot_id' => $franja('2026-10-17')->id, 'quantity' => 2, 'unit_price' => 800, 'seats' => 2]);
        if ($otraFranja) {
            $pedido->items()->create(['ticket_type_id' => $tipo->id, 'slot_id' => $franja('2026-10-18')->id, 'quantity' => 1, 'unit_price' => 800, 'seats' => 1]);
        }

        return $pedido->fresh();
    }

    private function rechazo(Order $pedido): MailMessage
    {
        return (new OrderPaymentDeclined($pedido->fresh(), '0190'))->toMail($pedido->user);
    }

    private function caducada(Order $pedido): MailMessage
    {
        return (new OrderExpiredWithoutPayment($pedido->fresh()))->toMail($pedido->user);
    }

    /** @return array<string, mixed>|null */
    private function bloque(MailMessage $correo, string $tipo): ?array
    {
        return collect($correo->viewData['cuerpo'])->firstWhere('tipo', $tipo);
    }
}
