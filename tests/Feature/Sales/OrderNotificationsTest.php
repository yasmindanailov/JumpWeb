<?php

namespace Tests\Feature\Sales;

use App\Domain\Booking\Models\Order;
use App\Domain\Identity\Models\User;
use App\Domain\Payments\Models\Payment;
use App\Domain\Platform\Services\Analytics\EmailUtm;
use App\Notifications\OrderCancelled;
use App\Notifications\OrderExpiredWithoutPayment;
use App\Notifications\OrderPaymentDeclined;
use App\Notifications\OrderRefunded;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Lang;
use Tests\TestCase;

/**
 * Audit #114 (2026-05-28) — Las 4 notifications nuevas del cierre de gaps de feedback.
 *
 * Verifica que cada notification:
 *  - Tiene asunto, saludo, intro y acción traducidos (no quedan claves i18n sin definir).
 *  - Lleva el código del pedido (`JJ-XXXX`) en el subject y/o el body (trazabilidad).
 *  - Tiene un CTA hacia `/mi-cuenta/pedidos` o `/` según corresponda.
 *
 * No verifica la lógica de DISPARO (eso está en los tests del handler y de ExpireOrders).
 * Aquí blindamos el contenido del email — si alguien borra una key i18n, el test lo capta.
 */
class OrderNotificationsTest extends TestCase
{
    use RefreshDatabase;

    private function makeOrder(string $code = 'JJ-AUDIT114'): Order
    {
        $user = User::factory()->create();

        return Order::create([
            'user_id' => $user->id,
            'code' => $code,
            'status' => Order::STATUS_PENDING,
            'subtotal' => 2500,
            'total' => 2500,
            'currency' => 'EUR',
        ]);
    }

    // ⚠️ Lo de la CONFIRMACIÓN (si habla del formulario de invitados, la fecha del cobro) se fue con la R2b: su correo es el
    // diseño y sus casos viven en `Mail\ReservationMailTest` —los pasos de una fiesta, solo en una fiesta—. La fecha del
    // cobro ya no es una línea: el diseño no la lleva, y cuando al pedido le ha pasado algo la dice el libro, con su fecha.

    public function test_order_payment_declined_renders_subject_intro_reason_and_cta(): void
    {
        $order = $this->makeOrder('JJ-DECL01');

        $mail = (new OrderPaymentDeclined($order, '0101'))->toMail($order->user);

        $this->assertStringContainsString('JJ-DECL01', $mail->subject);
        $this->assertSame(__('emails.order_declined.action'), $mail->actionText);
        // Con la UTM del correo pegada (T1c de la analítica, `EmailUtm`): el destino no cambia.
        $this->assertSame(EmailUtm::tag(route('account.orders'), 'order_payment_declined'), $mail->actionUrl);

        // ⚠️ El motivo (0101 → card_expired) ya no es una línea del cuerpo: desde `#503` vive en su
        // propio AVISO, porque es lo que el cliente busca al abrir este correo y como frase suelta
        // en medio se leía como una más. Se asevera sobre el HTML RENDERIZADO —lo que la persona
        // LEE— para que el caso no se vuelva a romper si el texto cambia de contenedor.
        $this->assertStringContainsString(
            __('tickets.payment_failed.reasons.card_expired'), $mail->render()
        );
    }

    public function test_order_payment_declined_uses_default_reason_when_code_unknown(): void
    {
        $order = $this->makeOrder('JJ-DECL02');

        $html = (new OrderPaymentDeclined($order, '9999'))->toMail($order->user)->render();

        $this->assertStringContainsString(__('tickets.payment_failed.reasons.default'), $html);
    }

    public function test_order_expired_without_payment_renders_correctly(): void
    {
        $order = $this->makeOrder('JJ-EXP01');

        $mail = (new OrderExpiredWithoutPayment($order))->toMail($order->user)->toArray();

        $this->assertStringContainsString('JJ-EXP01', $mail['subject']);
        $this->assertSame(__('emails.order_expired_without_payment.action'), $mail['actionText']);
        $this->assertSame(EmailUtm::tag(route('home'), 'order_expired_without_payment'), $mail['actionUrl']);
        $body = implode("\n", $mail['introLines'] ?? []);
        $this->assertStringContainsString(__('emails.order_expired_without_payment.no_charge'), $body);
    }

    public function test_order_cancelled_renders_without_refund_promise(): void
    {
        // Cambio de modelo en sub-fase 7.2b ampliada (#139): el email de cancelación
        // ya NO promete reembolso. Cancelar y reembolsar son operaciones independientes;
        // si procede devolución llega aparte vía `OrderRefunded`. Por eso el texto pierde
        // las líneas `amount` y `refund_note` y gana la `next_steps` condicional.
        $order = $this->makeOrder('JJ-CAN01');

        $mail = (new OrderCancelled($order))->toMail($order->user)->toArray();

        $this->assertStringContainsString('JJ-CAN01', $mail['subject']);
        $this->assertSame(EmailUtm::tag(route('account.orders'), 'order_cancelled'), $mail['actionUrl']);

        $body = implode("\n", $mail['introLines'] ?? []);
        // Importe del pedido fuera (era ruidoso si no había devolución asociada).
        $this->assertStringNotContainsString('25,00', $body);
        // Mensaje condicional sobre la devolución (no la promete).
        $this->assertStringContainsString(__('emails.order_cancelled.next_steps'), $body);
    }

    public function test_order_refunded_uses_payment_amount_when_provided(): void
    {
        $order = $this->makeOrder('JJ-REF01');
        $payment = Payment::create([
            'payable_type' => (new Order)->getMorphClass(),
            'payable_id' => $order->id,
            'provider' => 'redsys',
            'amount' => 1750, // distinto del total para verificar que se usa este
            'currency' => 'EUR',
            'status' => Payment::STATUS_REFUNDED,
        ]);

        $mail = (new OrderRefunded($order, $payment))->toMail($order->user)->toArray();
        $body = implode("\n", $mail['introLines'] ?? []);
        $this->assertStringContainsString('17,50', $body);
    }

    public function test_order_refunded_falls_back_to_order_total_without_payment(): void
    {
        // Defensa: si llamamos OrderRefunded sin Payment (no debería en flujo normal,
        // pero algún panel admin podría hacerlo), usamos el total del Order como fallback.
        $order = $this->makeOrder('JJ-REF02');

        $mail = (new OrderRefunded($order))->toMail($order->user)->toArray();
        $body = implode("\n", $mail['introLines'] ?? []);
        $this->assertStringContainsString('25,00', $body);
    }

    /**
     * T5 (§25.5): las dos voces del canal del reembolso existen en los TRES idiomas del cliente —
     * `Lang::has(..., fallback: false)`, la lección de `#134` §23.6: una clave que falte en EN/FR
     * no sale en crudo, sale un cliente leyendo castellano en su email de dinero.
     */
    public function test_the_refund_channel_lines_exist_in_every_client_locale(): void
    {
        foreach (['order_refunded.when_manual', 'order_item_refunded.when_manual'] as $key) {
            foreach (['es', 'en', 'fr'] as $locale) {
                $this->assertTrue(
                    Lang::has('emails.'.$key, $locale, false),
                    "emails.{$key} falta en «{$locale}»",
                );
            }
        }
    }
}
