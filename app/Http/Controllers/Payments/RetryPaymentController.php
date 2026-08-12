<?php

namespace App\Http\Controllers\Payments;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Support\AuditLogger;
use App\Support\MaintenanceSettings;
use App\Support\PaymentSettings;
use App\Support\Redsys;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Reintento de pago Redsys desde la página "Mis pedidos" (audit edge cases 2026-05-28).
 *
 * Cierra el hueco descubierto en validación: el email `OrderPaymentDeclined` enviaba al
 * cliente al área privada (`/mi-cuenta/pedidos`), pero la vista no ofrecía un botón para
 * reintentar el pago. El cliente quedaba "informado pero sin acción". Este controller +
 * el botón en `account/orders.blade.php` cierran el loop.
 *
 * Es el espejo en HTTP del método `Purchase::retryPayment()` del sidebar (mismo audit):
 *  1. Auth + verified (las rutas privadas ya lo exigen).
 *  2. Filtro por `user_id` del Order recibido → defensa IDOR (sin esto, un atacante con
 *     el `code` de otro user podría disparar un nuevo Payment para esa Order).
 *  3. Order debe estar `pending` y NO expirada (la plaza retenida; si caducó el aforo
 *     pudo cederse lazy a otro cliente — un nuevo Payment podría llevar a sobreventa).
 *  4. Nuevo Payment con NUEVO `gateway_order` (Redsys §5: gateway_order único de por
 *     vida, error 0913 si se reusa).
 *  5. Extensión de `expires_at` (nueva ventana completa de retención).
 *  6. Renderiza vista intermedia con auto-POST a la pasarela.
 *
 * NO usa Livewire — el controller HTTP es más simple (no necesita state ni reactividad)
 * y la vista intermedia es estática. CSRF activo por defecto (POST estándar autenticado).
 */
class RetryPaymentController extends Controller
{
    public function __invoke(Request $request, string $code, Redsys $redsys): View|RedirectResponse
    {
        $user = $request->user();
        assert($user !== null); // auth middleware lo garantiza

        // Reservas en pausa (#218): no se reinicia un cobro nuevo desde «Mis pedidos». El banner
        // global + el flash invitan a llamar; un pago YA iniciado finaliza por la callback de Redsys
        // (no pasa por aquí). El pedido manual del panel tampoco se ve afectado.
        if (MaintenanceSettings::reservationsPaused()) {
            return redirect()
                ->route('account.orders')
                ->with('status', 'order-retry-paused');
        }

        // Check + extensión de `expires_at` ATÓMICOS (auditoría Fase 1, L2): un UPDATE condicionado a
        // pending + no-vencida que, en la MISMA sentencia, fija la nueva ventana de retención. Defensa
        // IDOR (acotado a `$user->orders()`). Si el hold cruzó entre el render de «Mis pedidos» y este
        // POST, el WHERE `expires_at > now` no casa → 0 filas → no reabrimos el cobro (antes el check y
        // la extensión eran pasos separados: la extensión resucitaba un hold ya vencido SIN recontar
        // aforo, esquivando la detección C1 de sobreventa).
        $extended = $user->orders()
            ->where('code', $code)
            ->where('status', Order::STATUS_PENDING)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->update(['expires_at' => now()->addMinutes(PaymentSettings::holdMinutes())]);

        if ($extended === 0) {
            return redirect()
                ->route('account.orders')
                ->with('status', 'order-retry-unavailable');
        }

        /** @var Order $order */
        $order = $user->orders()->where('code', $code)->firstOrFail();

        // Crear nuevo Payment con nuevo gateway_order + firmar payload + render vista.
        try {
            $payment = DB::transaction(function () use ($order, $redsys): Payment {
                $gatewayOrder = $redsys->nextGatewayOrder();

                // Descarta los intentos `pending` previos (auditoría Fase 1, complemento C1): el
                // reintento crea un cobro nuevo y el anterior deja de ser el intento activo. Se
                // marca `superseded` (NO `failed`): si ese intento viejo se autorizase tarde, el
                // handler lo capturará como cobro real y su guarda de incidencia evitará emitir
                // tickets duplicados (la Order ya estará PAID por este reintento).
                Payment::where('payable_type', Order::class)
                    ->where('payable_id', $order->id)
                    ->where('status', Payment::STATUS_PENDING)
                    ->update(['status' => Payment::STATUS_SUPERSEDED]);

                return Payment::create([
                    'payable_type' => Order::class,
                    'payable_id' => $order->id,
                    'provider' => 'redsys',
                    'amount' => $order->onlineDueCents(), // #225: importe ONLINE (señal/depósito), no el total
                    'currency' => $order->currency,
                    'status' => Payment::STATUS_PENDING,
                    'gateway_order' => $gatewayOrder,
                ]);
            });

            $locale = $user->locale ?? app()->getLocale();
            $formData = $redsys->buildPaymentFormData($order, $payment, $locale);
        } catch (Throwable $e) {
            Log::error('redsys.retry_from_orders_failed', [
                'order_id' => $order->id,
                'order_code' => $order->code,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'exception' => $e::class,
            ]);

            // Feedback ADMIN (#169): rastro visible en el panel (historial del pedido).
            AuditLogger::log('orders.payment_init_failed', $order, [
                'order_code' => $order->code,
                'source' => 'retry_account',
                'exception' => $e::class,
                'error' => $e->getMessage(),
            ]);

            return redirect()
                ->route('account.orders')
                ->with('status', 'order-retry-failed');
        }

        // (La ventana `expires_at` ya se extendió arriba, atómicamente con el check — L2.)

        // Vista intermedia con auto-POST a la pasarela. La tarjeta NO toca este server.
        return view('payments.retry-redirect', [
            'redsysFormData' => $formData,
            'orderCode' => $order->code,
        ]);
    }
}
