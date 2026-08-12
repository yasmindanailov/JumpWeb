<?php

namespace App\Http\Controllers\Payments;

use App\Domain\Booking\Models\Order;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Services\RedsysReturnHandler;
use App\Domain\Payments\Services\RedsysReturnOutcome;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Throwable;

/**
 * Recepción de pagos Redsys (capas 5.5c/5.5d, decisiones #104/#106).
 *
 * Tres entradas, una sola lógica de transición (`App\Domain\Payments\Services\RedsysReturnHandler`):
 *   - `return.ok` / `return.ko` — vuelta del NAVEGADOR del cliente. El método (GET o
 *     POST) y la presencia de datos firmados depende de la **configuración del terminal**
 *     en el portal admin Redsys (#106, verificado empíricamente 2026-05-26):
 *       · Terminal con "incluir datos en redirección" activo: GET con query params O
 *         auto-POST → procesamos vía `processSignedReturn`.
 *       · Terminal sin esa opción: bare GET sin datos → fallback `handleDataLessReturn`
 *         que muestra al cliente "verificando pago" sin afirmar éxito hasta que llegue
 *         la notificación on-line (5.5d).
 *     La cookie de sesión SÍ viaja en GET top-level navigation (SameSite=Lax), pero NO
 *     la usamos para autorizar el pago — solo para asociar al cliente al pedido reciente.
 *   - `notification` — POST server-to-server (5.5d, requiere `MerchantURL` configurada
 *     en el portal admin Redsys + URL pública en producción). Estricto POST.
 *
 * Las 3 rutas están EXCLUIDAS de CSRF en `bootstrap/app.php` (la firma `Ds_Signature` es
 * la prueba de autenticidad, no la cookie).
 */
class RedsysReturnController extends Controller
{
    /** Cuánto vive el token de retorno en cache antes de caducar. 5 min cubre la latencia
     *  redirect+render y mantiene la ventana de uso lo más estrecha posible. */
    private const RETURN_TOKEN_TTL_MINUTES = 5;

    /** Ventana hacia atrás para asociar una vuelta "sin datos" al pago reciente del usuario.
     *  Más ≥ `sales.hold_minutes` (15 min, #105) más generoso por si el pago tardó. */
    private const RECENT_PAYMENT_LOOKBACK_MINUTES = 30;

    public function __construct(private RedsysReturnHandler $handler) {}

    /** UrlOK — vuelta del navegador con `Ds_Response` esperado 0000–0099 (autorizado). */
    public function returnOk(Request $request): HttpResponse|RedirectResponse
    {
        return $this->dispatchBrowserReturn($request);
    }

    /** UrlKO — vuelta del navegador tras denegación o cancelación. */
    public function returnKo(Request $request): HttpResponse|RedirectResponse
    {
        return $this->dispatchBrowserReturn($request);
    }

    /**
     * Notificación server-to-server (5.5d, manual §2.2 — "fuente de verdad en producción").
     *
     * Contrato de respuesta (Redsys, manual §7 + verificado empíricamente con sandbox):
     *   - SIEMPRE HTTP 200 OK, pase lo que pase. Cualquier 4xx/5xx provoca reintentos
     *     exponenciales de Redsys, generando ruido en logs y, peor, ciclos de re-entrega
     *     que pueden solaparse con la vuelta del navegador del cliente. Preferimos absorber
     *     el error aquí (queda en logs como WARNING/ERROR para auditoría) que dejarlo
     *     escapar como excepción HTTP.
     *   - Body vacío. Redsys no parsea nada del body de respuesta.
     *
     * Defensa en profundidad: try/catch último recurso. El handler ya captura `Throwable`
     * dentro de su propia transacción, pero una excepción muy temprana (antes de entrar
     * al método) o en una dependencia inesperada del framework escaparía como 500 sin
     * este wrapper. Coste: nulo en el flujo normal; valor: garantizar 200 ante TODO.
     */
    public function notification(Request $request): Response
    {
        try {
            $this->handler->process($request->all(), 'notification');
        } catch (Throwable $e) {
            // Último recurso. El handler ya filtra todos los caminos previstos; esto cubre
            // solo lo imprevisible (BD caída a mitad, contenedor con OOM, etc.).
            Log::error('redsys.notification.unhandled_exception', [
                'error' => $e->getMessage(),
                'exception' => $e::class,
            ]);
        }

        return response('', 200);
    }

    private function dispatchBrowserReturn(Request $request): HttpResponse|RedirectResponse
    {
        if ($this->hasRedsysPayload($request)) {
            return $this->processSignedReturn($request);
        }

        return $this->handleDataLessReturn($request);
    }

    /** ¿Vienen los 3 campos canónicos en la petición? (en query string o body) */
    private function hasRedsysPayload(Request $request): bool
    {
        return is_string($request->input('Ds_SignatureVersion'))
            && is_string($request->input('Ds_MerchantParameters'))
            && is_string($request->input('Ds_Signature'));
    }

    private function processSignedReturn(Request $request): HttpResponse|RedirectResponse
    {
        $result = $this->handler->process($request->all(), 'browser_return');
        $outcome = $result['outcome'];
        assert($outcome instanceof RedsysReturnOutcome);

        // Rechazo silencioso desde el servidor (firma inválida, pedido desconocido, importe
        // distinto, payload malformado). NO redirigimos a la home con un token — eso sería
        // un canal para que un atacante mande al usuario al éxito sin haber pagado. 400
        // genérico + auditoría (ya hecho en el handler).
        if ($outcome->isServerReject()) {
            return response(__('tickets.payment_rejected_generic'), 400)
                ->header('Cache-Control', 'no-store');
        }

        $payment = $result['payment'];
        $order = $result['order'];
        if ($payment === null || $order === null) {
            return response(__('tickets.payment_rejected_generic'), 400)->header('Cache-Control', 'no-store');
        }

        return $this->redirectWithToken($order->user_id, $order->code, $outcome);
    }

    /**
     * Caso #106: la vuelta del navegador llega SIN los `Ds_*` firmados (terminal Redsys
     * no incluye datos inline en la redirección, o algún edge case). NO podemos confirmar
     * el pago aquí — la notificación on-line es la fuente de verdad en este escenario.
     *
     * Comportamiento seguro:
     *   - Localizamos el Payment reciente del USUARIO LOGUEADO (la cookie de sesión SÍ
     *     viajó porque GET top-level navigation permite SameSite=Lax).
     *   - Si la notificación ya lo marcó `paid` (carrera con la vuelta), redirigimos con
     *     token de éxito → sidebar abre en paso 6.
     *   - Si sigue `pending`, redirigimos al home con `purchase.verifying_code` → sidebar
     *     abre en paso 11 "verificando tu pago, te avisaremos por email".
     *   - Sin usuario / sin Payment reciente → home limpia (no leakeamos información).
     *
     * Importante: NUNCA marcamos `paid` por la mera llegada a UrlOK sin firma — sería un
     * vector de fraude (atacante navega a UrlOK manualmente tras cancelar el pago).
     */
    private function handleDataLessReturn(Request $request): RedirectResponse
    {
        Log::info('redsys.return.no_payload', [
            'method' => $request->method(),
            'user_id' => $request->user()?->id,
        ]);

        $user = $request->user();
        if ($user === null) {
            return redirect()->route('home');
        }

        $latest = Payment::where('payable_type', (new Order)->getMorphClass())
            ->where('provider', 'redsys')
            ->whereIn('status', [Payment::STATUS_PENDING, Payment::STATUS_PAID])
            ->whereHas('payable', fn ($q) => $q->where('user_id', $user->id))
            ->where('created_at', '>=', now()->subMinutes(self::RECENT_PAYMENT_LOOKBACK_MINUTES))
            ->orderByDesc('id')
            ->first();

        if ($latest === null) {
            return redirect()->route('home');
        }

        $order = $latest->payable;
        if ($order === null) {
            return redirect()->route('home');
        }

        if ($latest->status === Payment::STATUS_PAID) {
            // La notificación llegó antes que el cliente. Token de éxito idempotente.
            return $this->redirectWithToken($order->user_id, $order->code, RedsysReturnOutcome::IdempotentPaid);
        }

        // Aún `pending`: mostrar "verificando pago". El sidebar abrirá en paso 11.
        session(['purchase.verifying_code' => $order->code]);

        return redirect()->route('home', status: 303);
    }

    /** Genera el token one-shot y redirige al home (303). */
    private function redirectWithToken(int $userId, string $orderCode, RedsysReturnOutcome $outcome): RedirectResponse
    {
        $token = Str::random(40);
        Cache::put(self::cacheKey($token), [
            'user_id' => $userId,
            'order_code' => $orderCode,
            'outcome' => $outcome->value,
        ], now()->addMinutes(self::RETURN_TOKEN_TTL_MINUTES));

        return redirect()->route('home', ['redsys' => $token], status: 303);
    }

    public static function cacheKey(string $token): string
    {
        return 'redsys.return:'.$token;
    }
}
