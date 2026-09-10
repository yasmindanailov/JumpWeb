<?php

namespace Tests\Feature\Mail;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Booking\Services\EmailBookBlock;
use App\Domain\Identity\Models\User;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Models\PaymentRefund;
use App\Notifications\MixedPartySurchargeChanged;
use App\Notifications\OrderConfirmation;
use App\Notifications\OrderItemModified;
use App\Notifications\OrderItemRefunded;
use App\Notifications\OrderRefunded;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\App;
use Tests\TestCase;

/**
 * **Guarda P de la T3·3** (`specs/desglose-libro.md` §6.3.4, D-T3·5): los CINCO correos de dinero
 * llevan el bloque del LIBRO, compuesto AL ENVIAR — al reenviar un correo tras una edición, dice el
 * saldo NUEVO— y `OrderItemModified` no lleva ningún importe suelto (D-T3·21).
 *
 * Mutaciones: el bloque lee `Order.total` en vez del libro · `forOrder()` devuelve `''` · el correo
 * de modificación recupera un parámetro de céntimos.
 */
class EmailBookBlockTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;

    private Order $order;

    private OrderItem $entry;

    protected function setUp(): void
    {
        parent::setUp();
        App::setLocale('es');

        RateType::create(['key' => RateType::KEY_NORMAL, 'label' => ['es' => 'Normal'], 'weekdays' => null, 'priority' => 0]);
        $zone = Zone::create(['slug' => 'jump', 'name' => ['es' => 'Jump'], 'position' => 1, 'is_active' => true]);
        $type = TicketType::create([
            'name' => ['es' => 'Entrada 1h'], 'type' => TicketType::TYPE_ENTRY, 'zone_id' => $zone->id,
            'duration_min' => 60, 'seats_per_unit' => 1, 'is_sellable' => true, 'is_active' => true, 'position' => 1,
        ]);
        $slot = Slot::create([
            'zone_id' => $zone->id, 'date' => now()->addDays(5)->toDateString(),
            'start_time' => '10:00:00', 'end_time' => '11:00:00', 'capacity' => 50, 'online_capacity' => 50,
        ]);

        $this->customer = User::factory()->create();
        // Un pedido que CUADRA: 2 × 10,00 facturados y cobrados por web.
        $this->order = Order::create([
            'user_id' => $this->customer->id, 'code' => 'R-MAIL001', 'status' => Order::STATUS_PAID,
            'subtotal' => 2000, 'tax' => 0, 'total' => 2000, 'currency' => 'EUR', 'paid_at' => now()->subDay(),
        ]);
        $this->entry = $this->order->items()->create(['ticket_type_id' => $type->id, 'slot_id' => $slot->id, 'quantity' => 2, 'unit_price' => 1000, 'seats' => 2]);
        Payment::create([
            'payable_type' => $this->order->getMorphClass(), 'payable_id' => $this->order->id,
            'amount' => 2000, 'currency' => 'EUR', 'provider' => 'redsys',
            'status' => Payment::STATUS_PAID, 'paid_at' => now()->subDay(), 'gateway_order' => '0000880001',
        ]);
        $this->order = $this->order->fresh();
    }

    public function test_every_money_email_carries_the_book(): void
    {
        $payment = $this->order->payments->first();
        PaymentRefund::create([
            'payment_id' => $payment->id, 'order_item_id' => $this->entry->id, 'amount_cents' => 500, 'currency' => 'EUR',
            'status' => PaymentRefund::STATUS_SUCCEEDED, 'mode' => PaymentRefund::MODE_REST,
            'gateway_order' => $payment->gateway_order, 'gateway_response_code' => PaymentRefund::REDSYS_REFUND_SUCCESS_CODE,
            'requested_by' => $this->customer->id, 'requested_at' => now(), 'processed_at' => now(),
        ]);
        $this->order->forceFill(['refund_amount_cents' => 500, 'refunded_at' => now()])->save();
        $order = $this->order->fresh();
        $item = $this->entry->fresh(['ticketType', 'slot', 'order']);

        $mails = [
            'confirmación' => new OrderConfirmation($order),
            'modificación' => new OrderItemModified($order, $item, ['quantity_change' => ['old' => 2, 'new' => 2]]),
            'devolución por línea' => new OrderItemRefunded($order, $item, 500),
            'devolución del pedido' => new OrderRefunded($order),
            'suplemento mixto' => new MixedPartySurchargeChanged($item, 0, 700),
        ];

        foreach ($mails as $name => $notification) {
            $body = $this->body($notification);

            $this->assertStringContainsString('data-book', $body, "el correo de $name no lleva el bloque del libro");
            $this->assertStringContainsString(__('tickets.journal.email_title'), $body, $name);
            $this->assertStringContainsString(__('tickets.journal.booking'), $body, $name);
            $this->assertStringContainsString('+20,00 €', $body, "$name · el nacimiento con su signo");
            $this->assertStringContainsString(__('tickets.journal.refund_card'), $body, "$name · la devolución es una línea");
            $this->assertStringContainsString('−5,00 €', $body, $name);
            $this->assertStringContainsString('data-book-balance="pay_at_park"', $body, "$name · devolver sin bajar el valor deja saldo a pagar");
        }
    }

    /**
     * **Al ENVIAR, no al gestionar**: los correos se REENVÍAN desde el panel, y para entonces el pedido
     * puede haber cambiado. Mutación: componer el bloque desde `Order.total` deja los dos iguales.
     */
    public function test_a_resend_after_an_edit_says_the_new_balance(): void
    {
        $before = $this->body(new OrderConfirmation($this->order));
        $this->assertStringContainsString('data-book-balance="settled"', $before);
        $this->assertStringNotContainsString(__('tickets.journal.balance_pay_at_park'), $before);

        // El operador sube la entrada de 2 a 3 desde el panel…
        $this->entry->forceFill(['quantity' => 3, 'seats' => 3])->save();
        $this->order->recordEdit($this->entry->fresh(), 1000, $this->customer, 'item_edit', ['changes' => ['quantity_change' => ['old' => 2, 'new' => 3]]]);

        // …y reenvía la confirmación: dice el libro de HOY.
        $after = $this->body(new OrderConfirmation($this->order->fresh()));
        $this->assertStringContainsString('data-book-movement="edit"', $after);
        $this->assertStringContainsString('+10,00 €', $after);
        // ⚠️ Acotado a la FILA del Total: la tarjeta de producto del mismo correo imprime «30,00 €»
        // (3 × 10,00), y con la aserción sobre el cuerpo entero la mutación «el bloque imprime lo
        // Pagado como Total» pasaba en VERDE. Acota al elemento antes de creerte un test verde.
        $this->assertStringContainsString('30,00 €', $this->bookRow($after, 'total'), 'el Total nuevo, en su fila');
        $this->assertStringContainsString('20,00 €', $this->bookRow($after, 'paid'), 'lo pagado no cambió');
        $this->assertStringContainsString('data-book-balance="pay_at_park"', $after);
        $this->assertStringContainsString(__('tickets.journal.balance_pay_at_park'), $after);
    }

    /** D-T3·21: el correo de modificación no lleva NINGÚN importe suelto — solo qué cambió y el libro. */
    public function test_the_modification_email_carries_no_loose_amount(): void
    {
        $params = array_map(
            fn (\ReflectionParameter $p): string => $p->getName(),
            (new \ReflectionClass(OrderItemModified::class))->getConstructor()->getParameters(),
        );

        $this->assertSame(['order', 'item', 'changes'], $params, 'un céntimo suelto en el constructor es una segunda composición del mismo dinero');
    }

    /** D-T3·20: sin pedido no hay bloque, y el correo no revienta. */
    public function test_the_block_is_defensive(): void
    {
        $this->assertSame('', (string) EmailBookBlock::forOrder(null));
        $this->assertStringContainsString('data-book', (string) EmailBookBlock::forOrder($this->order));
    }

    /** La fila `<tr data-book-{row}>…</tr>` del bloque, o cadena vacía si no está. */
    private function bookRow(string $html, string $row): string
    {
        return preg_match('/<tr data-book-'.$row.'>.*?<\/tr>/s', $html, $m) === 1 ? $m[0] : '';
    }

    /**
     * El cuerpo ENTERO del correo — las líneas de antes del aviso y las de después.
     *
     * ⚠️ Miraba sólo `introLines`, y en `#507` el libro del suplemento mixto pasó a CIERRE
     * (`outroLines`) para que la cifra quedara por encima de él: el caso se puso rojo con el
     * producto sano. Lo correcto no era re-apuntarlo al sitio nuevo, sino mirar **lo que recibe
     * una persona** — así este helper tampoco se quedará ciego el día que otro de los cinco
     * correos mueva su libro.
     */
    private function body(Notification $notification): string
    {
        /** @var MailMessage $mail */
        $mail = $notification->toMail($this->customer);

        return collect($mail->introLines)
            ->concat($mail->outroLines)
            ->map(fn ($l) => (string) $l)
            ->implode(' ');
    }
}
