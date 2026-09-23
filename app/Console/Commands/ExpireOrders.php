<?php

namespace App\Console\Commands;

use App\Domain\Booking\Models\Order;
use App\Domain\Payments\Models\Payment;
use App\Domain\Platform\Services\Analytics\Recorder;
use App\Notifications\OrderExpiredWithoutPayment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Marca como caducados los pedidos `pending` cuya retención de plaza ha expirado, de
 * modo que su aforo vuelve a estar disponible (#DECIDIDO-C, #105). El cálculo de plazas
 * libres también ignora los pendientes caducados, por si el scheduler no corre en local.
 *
 * Audit #114 (2026-05-28, G2): además, si la Order caducada tenía un Payment `pending`
 * (= el cliente intentó pagar pero la notificación nunca llegó / cerró la pestaña en la
 * pasarela), enviamos `OrderExpiredWithoutPayment` para que el cliente sepa que la
 * reserva ya no es válida y, si realmente pagó, tenga un canal para reclamar.
 *
 * NO se envía email si la Order:
 *  - No tenía Payment (abandono limpio antes de pasarela; o reserva provisional #76).
 *  - Tenía Payment `failed` (ya recibió `OrderPaymentDeclined`, #114 G1).
 *  - No tiene user (anonimizado por RGPD, #53/#90).
 */
class ExpireOrders extends Command
{
    protected $signature = 'orders:expire';

    protected $description = 'Caduca los pedidos pendientes cuya retención de plaza expiró (libera aforo).';

    public function __construct(private readonly Recorder $recorder)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        // Cargamos las Orders caducables con sus Payments y user ANTES de marcarlas, para
        // poder decidir si enviar email (audit #114 G2). Hacemos la carga en un query y
        // procesamos en memoria — el scheduler corre cada 5 min y el volumen esperado es
        // bajo (decenas como mucho); chunks innecesarios para este caso.
        $expiringOrders = Order::where('status', Order::STATUS_PENDING)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->with(['payments', 'user'])
            ->get();

        $count = 0;
        $notified = 0;
        foreach ($expiringOrders as $order) {
            // UPDATE atómico CONDICIONADO (auditoría Fase 1, M2): solo caduca si la Order SIGUE
            // pending y vencida. Sin el WHERE de estado, entre la carga (arriba) y este punto —ventana
            // que el `notify()` SMTP síncrono de iteraciones previas ALARGA— el handler C1 de Redsys
            // puede haber marcado la Order paid (autorización tardía); un `forceFill->save()`
            // incondicional la pisaría a EXPIRED con el dinero ya capturado → reembolso bloqueado
            // (`refundBlockedReason()` devuelve `not_paid`). Solo contamos/notificamos si afectó 1 fila.
            $affected = Order::whereKey($order->id)
                ->where('status', Order::STATUS_PENDING)
                ->whereNotNull('expires_at')
                ->where('expires_at', '<', now())
                ->update(['status' => Order::STATUS_EXPIRED, 'updated_at' => now()]);

            if ($affected !== 1) {
                continue; // otra transición ganó la carrera (típicamente paid por C1): no la tocamos.
            }
            $order->status = Order::STATUS_EXPIRED; // refleja en memoria para el resto del bucle.
            $count++;

            // El libro de eventos (`specs/analitica.md` §4.1, `#678`): la caducidad es un UPDATE de query
            // builder (arriba, y a propósito), así que ningún observador de `Order` la ve. Se registra
            // aquí, en el mismo `if` que la cuenta. El recorder nunca lanza.
            $this->recorder->fact('order_expired', ['channel' => $order->attribution_channel], ['order_id' => (int) $order->id]);

            // ¿Notificar? Solo si HAY Payment `pending` (intento que nunca se completó).
            // El cliente NO sabe que su reserva caducó; necesita feedback (G2). Si el
            // Payment está `failed`, ya recibió `OrderPaymentDeclined` (G1) — evitar
            // doble email es UX correcta.
            $hasUnresolvedPayment = $order->payments
                ->contains(fn (Payment $p) => $p->status === Payment::STATUS_PENDING);
            if (! $hasUnresolvedPayment || ! $order->user) {
                continue;
            }

            try {
                $order->user->notify(new OrderExpiredWithoutPayment($order));
                $notified++;
            } catch (Throwable $e) {
                // SMTP fail no debe bloquear el resto del comando; queda en logs.
                Log::warning('orders.expire.notification_failed', [
                    'order_id' => $order->id,
                    'order_code' => $order->code,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Pedidos caducados: {$count}.".($notified > 0 ? " Avisos enviados: {$notified}." : ''));

        return self::SUCCESS;
    }
}
