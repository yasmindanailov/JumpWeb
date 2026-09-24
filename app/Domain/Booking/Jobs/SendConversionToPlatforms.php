<?php

namespace App\Domain\Booking\Jobs;

use App\Domain\Booking\Models\Order;
use App\Domain\Platform\Contracts\ConsentLedger;
use App\Domain\Platform\Services\Analytics\Conversion;
use App\Domain\Platform\Services\Analytics\ConversionSender;
use App\Domain\Platform\Services\Analytics\Pixels;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * **La compra se comunica a los anunciantes desde el servidor** (`docs/specs/analitica.md` §4.3, T3b·2): en
 * la transición del pedido a pagado (`OrderAnalyticsObserver`, nunca un evento ingerido), un job en cola
 * RELEE el consentimiento VIVO del visitante —su última decisión, por `Platform\Contracts\ConsentLedger`—
 * y, solo con `marketing`, manda la compra a Meta y a TikTok con el código del pedido como id del evento
 * (el píxel manda el mismo: la plataforma cuenta una vez), el importe PAGADO, los ids del navegador que el
 * sello guardó y el correo y el teléfono del titular HASHEADOS.
 *
 * ⚠️ Vive en Booking porque es quien ve el pedido y a su titular; lo que habla con la plataforma es Platform
 * (`ConversionSender`), que no ve a nadie. ⚠️ En cola (`ShouldQueue`, `PAY-14`): habla con terceros y no
 * puede retrasar ni tumbar un cobro. ⚠️ Sin visitante en el sello (un pedido del mostrador, o sin
 * `marketing` al comprar) no se crea ({@see forOrder()}): no hay a quién preguntarle su decisión de hoy.
 * ⚠️ Un pedido anonimizado (art. 17) manda la compra sin identificadores de persona: el titular ya no es
 * nadie, pero la venta sigue siendo una venta.
 */
class SendConversionToPlatforms implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 3;

    public function __construct(public readonly int $orderId, public readonly int $paidCents) {}

    /**
     * El job para un pedido, o `null` si no procede: sin píxel de Meta ni de TikTok configurado, o sin
     * visitante en el sello (no hay decisión viva que releer).
     */
    public static function forOrder(Order $order, int $paidCents): ?self
    {
        $pixels = Pixels::config();

        if (! isset($pixels[Pixels::META]) && ! isset($pixels[Pixels::TIKTOK])) {
            return null;
        }

        $visitorId = ((array) $order->attribution)['visitor_id'] ?? null;

        if (! is_string($visitorId) || $visitorId === '') {
            return null;
        }

        return new self((int) $order->getKey(), $paidCents);
    }

    public function handle(ConsentLedger $ledger, ConversionSender $sender): void
    {
        $order = Order::query()->with('user')->find($this->orderId);

        if ($order === null || $order->status !== Order::STATUS_PAID) {
            return;
        }

        $attribution = (array) $order->attribution;
        $visitorId = $attribution['visitor_id'] ?? null;

        if (! is_string($visitorId) || $ledger->consentedNow($visitorId, 'marketing') !== true) {
            Log::info('analytics.conversion_skipped', ['order' => (string) $order->code, 'reason' => 'no live marketing consent']);

            return;
        }

        $user = $order->user;
        $anonymous = $user === null || $user->isAnonymized();

        $sender->send(new Conversion(
            eventId: (string) $order->code,
            eventTime: (int) ($order->paid_at?->getTimestamp() ?? $order->updated_at?->getTimestamp() ?? now()->getTimestamp()),
            value: round($this->paidCents / 100, 2),
            currency: (string) ($order->currency ?: 'EUR'),
            emailHash: $anonymous ? null : Conversion::hash(Conversion::normalizeEmail($user->email)),
            phoneHash: $anonymous ? null : Conversion::hash(Conversion::normalizePhone($user->phone)),
            browserIds: array_filter((array) ($attribution['browser_ids'] ?? []), 'is_string'),
            clickId: is_string($attribution['click_ids']['ttclid'] ?? null) ? $attribution['click_ids']['ttclid'] : null,
            sourceUrl: url('/'),
        ));
    }
}
