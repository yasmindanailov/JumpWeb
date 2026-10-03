<?php

namespace Tests\Feature\Sales;

use App\Domain\Booking\Models\Order;
use App\Domain\Identity\Models\User;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Services\MarcasDePago;
use App\Domain\Platform\Models\Setting;
use App\Domain\Platform\Services\Analytics\EmailUtm;
use App\Notifications\OrderCancelled;
use App\Notifications\OrderExpiredWithoutPayment;
use App\Notifications\OrderPaymentDeclined;
use App\Notifications\OrderRefunded;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Messages\MailMessage;
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

        $mail = (new OrderPaymentDeclined($order, '0101', conBizum: false))->toMail($order->user);

        // Sin una reserva con franja, el asunto dice el número (la R2e: con una, el día y la hora).
        $this->assertStringContainsString('JJ-DECL01', $mail->subject);
        // El botón, en el cuerpo en orden (la R2e), con la UTM del correo pegada (T1c de la analítica): el destino no cambia.
        $boton = collect($mail->viewData['cuerpo'])->firstWhere('tipo', 'boton');
        $this->assertSame(__('emails.order_declined.action'), $boton['texto']);
        $this->assertSame(EmailUtm::tag(route('account.orders'), 'order_payment_declined'), $boton['url']);

        // ⚠️ El motivo (0101 → card_expired), en su bloque MONO, aparte (el diseño): es lo que el cliente busca al abrir este
        // correo y lo dicta igual al llamar al banco. Sobre el HTML RENDERIZADO —lo que la persona LEE—.
        $this->assertSame(['tipo' => 'motivo', 'etiqueta' => __('emails.order_declined.reason_label'), 'texto' => __('tickets.payment_failed.reasons.card_expired')], collect($mail->viewData['cuerpo'])->firstWhere('tipo', 'motivo'));
        $this->assertStringContainsString(__('tickets.payment_failed.reasons.card_expired'), $mail->render());
    }

    /** `#915` (c): «Pagar con Bizum» SOLO si el parque cobra con Bizum (`payment.marks`); los dos, al mismo reintento. */
    public function test_the_declined_mail_offers_bizum_only_where_the_park_has_it(): void
    {
        $order = $this->makeOrder('JJ-DECL03');
        $botones = static fn (MailMessage $m): array => array_column(collect($m->viewData['cuerpo'])->whereIn('tipo', ['boton', 'botones'])->all(), 'tipo');

        $sin = (new OrderPaymentDeclined($order, '0190'))->toMail($order->user);
        $this->assertSame(['boton'], $botones($sin), 'sin Bizum en el panel, un botón');

        Setting::query()->updateOrCreate(['key' => MarcasDePago::KEY], ['value' => 'visa,bizum']);
        Setting::flushMemo();
        $con = (new OrderPaymentDeclined($order->fresh(), '0190'))->toMail($order->user);
        $dos = collect($con->viewData['cuerpo'])->firstWhere('tipo', 'botones');
        $this->assertSame([__('emails.order_declined.action_bizum'), __('emails.order_declined.action_card')], [$dos['principal']['texto'], $dos['secundario']['texto']]);
        $this->assertSame($dos['principal']['url'], $dos['secundario']['url'], 'el banco ofrece los dos: el mismo reintento');
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

        $mail = (new OrderExpiredWithoutPayment($order))->toMail($order->user);

        $this->assertStringContainsString('JJ-EXP01', $mail->subject);
        $boton = collect($mail->viewData['cuerpo'])->firstWhere('tipo', 'boton');
        $this->assertSame(__('emails.order_expired_without_payment.action'), $boton['texto']);
        $this->assertSame(EmailUtm::tag(route('home'), 'order_expired_without_payment'), $boton['url']);
        $this->assertSame([__('emails.order_expired_without_payment.body')], collect($mail->viewData['cuerpo'])->firstWhere('tipo', 'texto')['lineas'], 'lo que pasó y que no se cobró nada');
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
