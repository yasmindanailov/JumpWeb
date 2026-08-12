<?php

namespace App\Support;

use App\Domain\Payments\Contracts\RefundGateway;
use App\Domain\Payments\Contracts\RefundResult;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentRefund;
use App\Models\Setting;
use App\Support\Redsys\Vendor\Signature;
use App\Support\Redsys\Vendor\Utils;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Único punto de la aplicación que conoce la criptografía de Redsys.
 *
 * Implementa HMAC_SHA512_V2 (con derivación AES-128-CBC) tal como define la librería
 * oficial PHP v2.0 publicada por Redsys; ver `app/Support/Redsys/Vendor/Signature.php`
 * y la decisión #104 (`docs/DECISIONES.md`).
 *
 * Coexiste con la librería antigua v1.0 (SHA-256 + 3DES) que ya NO usamos.
 */
class Redsys implements RefundGateway
{
    public const SIGNATURE_VERSION = 'HMAC_SHA512_V2';

    public const URL_TEST = 'https://sis-t.redsys.es:25443/sis/realizarPago';

    public const URL_LIVE = 'https://sis.redsys.es/sis/realizarPago';

    /**
     * Endpoint REST server-to-server (sub-fase 7.2b extendida, #142). Usado para
     * operaciones operativas que NO pasan por el navegador del cliente:
     * devolución (TransactionType=3), consulta de operación, anulación.
     *
     * Verificado contra la doc oficial Redsys:
     * https://pagosonline.redsys.es/desarrolladores-inicio/documentacion-operativa/devolver-o-anular-un-pago/
     *
     * El endpoint sandbox es el mismo dominio que el iframe pero ruta
     * `/sis/rest/trataPeticionREST`. Producción análogo en `sis.redsys.es`.
     */
    public const REST_URL_TEST = 'https://sis-t.redsys.es:25443/sis/rest/trataPeticionREST';

    public const REST_URL_LIVE = 'https://sis.redsys.es/sis/rest/trataPeticionREST';

    /**
     * Timeout para la REST call de devolución. Redsys suele responder bajo 2-3s;
     * 10s deja margen para latencias puntuales sin colgar el panel demasiado
     * tiempo. Si se excede, se trata como transport error y el operador debe
     * verificar manualmente en el portal antes de reintentar (decisión #142).
     */
    public const REST_TIMEOUT_SECONDS = 10;

    /**
     * Portal admin del comercio Redsys (back-office / "Módulo de Administración"). Sin
     * deep-link a transacciones específicas: el operativo loguea con sus credenciales y
     * busca por `Ds_Merchant_Order` (= `payments.gateway_order`).
     *
     * Verificado contra `docs/PLAN-REDSYS.md` §11 (sandbox) y panel de descargas oficial
     * Redsys (producción). La URL de producción debe re-verificarse con el banco en Fase 9
     * (algunas entidades white-labelean este portal; ver `docs/PLAN-REDSYS.md` §14.bis).
     */
    public const ADMIN_URL_TEST = 'https://sis-t.redsys.es:25443/admincanales-web/index.jsp';

    public const ADMIN_URL_LIVE = 'https://canales.redsys.es/admincanales-web/index.jsp';

    /**
     * Clave secreta del SANDBOX público de Redsys (manual oficial v2.0). Es pública a propósito:
     * sirve de fallback en desarrollo cuando `REDSYS_SECRET_KEY` está vacía. En producción NUNCA
     * debe ser la clave efectiva — la guarda del panel (`Settings::save`) impide pasar a `live`
     * mientras la clave efectiva sea ésta o no mida 32 chars (auditoría Fase 1 · runbook §5).
     */
    public const SANDBOX_SECRET_KEY = 'sq7HjrUOBfKmC576ILgskD5srU870gJ7';

    /** Longitud (en caracteres) de una clave de comercio Redsys válida (base64, manual §Anexo). */
    public const SECRET_KEY_LENGTH = 32;

    /**
     * Codifica el array de parámetros como JSON → Base64URL safe (`Ds_MerchantParameters`).
     *
     * Usa `json_encode` SIN flags para reproducir bit-a-bit la salida de la librería oficial
     * v2.0 (cuyo `ejemploGeneraPet.php` también la llama sin flags). Esto implica que las
     * URLs salen con barras escapadas (`http:\/\/...`); es JSON válido y Redsys lo decodifica
     * sin problema. El requisito firme es que firmemos exactamente los bytes enviados.
     */
    public function createMerchantParameters(array $data): string
    {
        $json = json_encode($data);

        if ($json === false) {
            throw new RuntimeException('Redsys: cannot encode merchant parameters as JSON.');
        }

        return Utils::base64_url_encode_safe($json);
    }

    /**
     * Firma la IDA: HMAC-SHA512 sobre `$params` con la clave derivada del número de pedido.
     */
    public function createMerchantSignature(string $secretKey, string $params, string $order): string
    {
        return Signature::createMerchantSignature($secretKey, $params, $order);
    }

    /**
     * Firma esperada para la VUELTA/NOTIFICACIÓN: extrae el `Ds_Order` del propio payload
     * y aplica la misma derivación. NO confía en datos externos (la firma se calcula sobre
     * los bytes recibidos, tal cual).
     */
    public function createMerchantSignatureNotif(string $secretKey, string $params): string
    {
        $data = $this->decodeMerchantParameters($params);
        $order = $this->extractOrder($data);

        return Signature::createMerchantSignature($secretKey, $params, $order);
    }

    /**
     * Decodifica `Ds_MerchantParameters` recibidos (Base64URL safe → JSON → array).
     * Lanza si el payload no es decodificable o no es JSON.
     */
    public function decodeMerchantParameters(string $params): array
    {
        $json = Utils::base64_url_decode_safe($params);

        if ($json === false) {
            throw new RuntimeException('Redsys: `Ds_MerchantParameters` is not valid Base64URL.');
        }

        $data = json_decode($json, true);

        if (! is_array($data)) {
            throw new RuntimeException('Redsys: `Ds_MerchantParameters` is not a JSON object.');
        }

        return $data;
    }

    /**
     * Verifica la firma de una respuesta (vuelta/notificación). Devuelve `true` si la firma
     * recibida coincide con la calculada; `false` en caso contrario (timing-safe).
     */
    public function verifySignature(string $secretKey, string $params, string $signatureReceived): bool
    {
        $expected = $this->createMerchantSignatureNotif($secretKey, $params);

        return hash_equals($expected, $signatureReceived);
    }

    /**
     * URL de redirección según el entorno configurado en `settings`.
     */
    public function gatewayUrl(): string
    {
        return $this->isLive() ? self::URL_LIVE : self::URL_TEST;
    }

    /**
     * URL REST server-to-server según entorno. Sub-fase 7.2b extendida (#142).
     */
    public function restUrl(): string
    {
        return $this->isLive() ? self::REST_URL_LIVE : self::REST_URL_TEST;
    }

    /**
     * URL del portal admin del comercio Redsys según entorno (sub-fase 7.2a). El operativo
     * abre esta URL en pestaña nueva desde el detalle del pedido y busca por
     * `Ds_Merchant_Order` (= `payments.gateway_order`). NO hay deep-link a transacciones
     * concretas — Redsys no lo soporta.
     */
    public function adminPanelUrl(): string
    {
        return $this->isLive() ? self::ADMIN_URL_LIVE : self::ADMIN_URL_TEST;
    }

    /**
     * `true` si Redsys está configurado para producción; `false` para sandbox.
     */
    public function isLive(): bool
    {
        return Setting::value('redsys_environment', 'test') === 'live';
    }

    /**
     * Snapshot inmutable de la configuración (settings) para construir el formulario de pago.
     * Lee de `settings` (data-driven); valores por defecto = sandbox público de Redsys.
     *
     * Hardening #113 (A2): la **clave secreta** (`secret_key`) puede sobrescribirse desde
     * `.env` vía `REDSYS_SECRET_KEY`. En Fase 9 con la clave real del banco, vivirá en `.env`
     * (fuera del repo, en el vault de Enhance) — NUNCA en BD donde un dump la expone en
     * claro. Hoy en sandbox la clave es pública (verificada en el ejemplo oficial Redsys
     * v2.0); seguir leyéndola de `settings` como fallback no añade riesgo.
     *
     * Auditoría Fase 1 (H3): se lee vía `config('services.redsys.secret_key')` (definido en
     * `config/services.php` como `env('REDSYS_SECRET_KEY')`), NO con `env()` directo. Con la
     * config CACHEADA (`php artisan config:cache`, paso del runbook) `env()` en runtime devuelve
     * `null` → el cobro caería a la clave de sandbox = apagón de cobros en producción. La capa
     * `config()` SÍ se hornea en la caché y sobrevive.
     */
    public function config(): array
    {
        $secretFromEnv = config('services.redsys.secret_key');
        $secret = is_string($secretFromEnv) && $secretFromEnv !== ''
            ? $secretFromEnv
            : (string) Setting::value('redsys_secret_key', self::SANDBOX_SECRET_KEY);

        return [
            'environment' => Setting::value('redsys_environment', 'test'),
            'merchant_code' => (string) Setting::value('redsys_merchant_code', '999008881'),
            'terminal' => (string) Setting::value('redsys_terminal', '001'),
            'secret_key' => $secret,
            // Hardening #113 (M2): defensivo. Si el setting está corrupto fallback a '978'.
            'currency' => PaymentSettings::redsysCurrency(),
            'merchant_name' => (string) Setting::value('redsys_merchant_name', (string) (Setting::value('business.name') ?: config('app.name'))),
        ];
    }

    /**
     * Mapea idioma de la app (`es|en|fr`) al código Redsys (`DS_MERCHANT_CONSUMERLANGUAGE`).
     * Verificado contra Anexo 1 del manual: 001=ES, 002=EN, 004=FR (003=catalán).
     */
    public function consumerLanguageCode(?string $locale): string
    {
        return match ($locale) {
            'es' => '001',
            'en' => '002',
            'fr' => '004',
            default => '0',
        };
    }

    /**
     * Genera el siguiente `Ds_Merchant_Order` (atómico, 12 chars, 4 primeros numéricos).
     *
     * Estrategia: contador propio en `settings.redsys_next_gateway_order`, incrementado
     * dentro de una transacción con `lockForUpdate`. Robustez:
     *   - Sobrevive a `migrate:fresh` mientras los seeders se ejecuten (el contador parte de
     *     un valor configurable; aquí arranca en `100000` para tener siempre ≥4 dígitos).
     *   - Concurrencia: dos peticiones simultáneas obtienen valores distintos.
     *   - Nunca reutiliza valores (Redsys responde `0913` "pedido repetido" si lo hiciera).
     *
     * Devuelve el valor como string zero-padded a 10 caracteres: `0000123456`. 10 chars
     * deja 2 chars de margen antes del tope de 12 (manual Redsys §5, `SIS0075`/`SIS0077`).
     */
    public function nextGatewayOrder(): string
    {
        return DB::transaction(function (): string {
            $row = DB::table('settings')
                ->where('key', 'redsys_next_gateway_order')
                ->lockForUpdate()
                ->first();

            $counter = $row !== null ? (int) $row->value : 100000;

            // SUELO ANTI-COLISIÓN (#169, edge case empírico 2026-06-02). El
            // contador por sí solo NO es fiable como fuente de unicidad: si se
            // reinicia por debajo de un `gateway_order` ya emitido (p. ej. un
            // `db:seed` que reescribía el setting — bug corregido en el seeder)
            // o si el incremento se revierte porque la transacción del caller
            // (creación del Payment) hace rollback, el contador se queda
            // "atascado" devolviendo siempre el mismo valor → duplicate key en
            // `payments.gateway_order` → todo intento de pago falla en bucle.
            //
            // Defensa: el siguiente valor nunca puede ser ≤ el máximo ya
            // emitido. `gateway_order` es SIEMPRE string de 10 chars
            // zero-padded, así que su MAX lexicográfico == MAX numérico
            // (cross-DB, sin CAST). Esto autorrepara cualquier desincronización
            // del contador y sobrevive al rollback del caller (lee de la tabla
            // `payments`, fuente de verdad durable, no solo del setting).
            $maxIssued = (int) (DB::table('payments')->max('gateway_order') ?? 0);
            $next = max($counter, $maxIssued + 1);

            DB::table('settings')->updateOrInsert(
                ['key' => 'redsys_next_gateway_order'],
                [
                    'value' => (string) ($next + 1),
                    'group' => 'payment',
                    'updated_at' => now(),
                    'created_at' => $row->created_at ?? now(),
                ],
            );

            return str_pad((string) $next, 10, '0', STR_PAD_LEFT);
        });
    }

    /**
     * Construye el payload + firma de IDA para un pedido y un pago. Todos los importes y
     * referencias salen del modelo (servidor): NO se acepta ningún input del cliente aquí.
     *
     * Devuelve un array listo para renderizar el formulario auto-POST:
     *   ['gatewayUrl', 'signatureVersion', 'params', 'signature'].
     */
    public function buildPaymentFormData(Order $order, Payment $payment, ?string $locale = null): array
    {
        if ($payment->gateway_order === null || $payment->gateway_order === '') {
            throw new RuntimeException('Redsys: payment has no `gateway_order`; reserve one first with `nextGatewayOrder()`.');
        }

        // Backstop de importe (auditoría Fase 1, M1): Redsys rechaza un cobro de 0 con un SIS error
        // genérico y dejaría el aforo retenido sin pista clara. Fallamos rápido y con mensaje propio
        // (el llamador lo registra en el audit log como `payment_init_failed`). Defensa en
        // profundidad sobre las guardas de señal=0 del catálogo/`depositCents`.
        if ((int) $payment->amount <= 0) {
            throw new RuntimeException('Redsys: refusing to open the gateway with a zero/negative amount ('.$payment->amount.').');
        }

        $cfg = $this->config();

        // Hardening #113 (M3): el manual Redsys §3 advierte de evitar caracteres especiales
        // en los campos de texto del formulario. Hoy el código del pedido es ASCII (`JJ-XXXX`)
        // y los i18n no llevan caracteres exóticos, pero futuras ediciones del template o el
        // nombre del comercio editado en panel (Fase 7) podrían introducirlos. `Str::ascii()`
        // translitera caracteres latinos comunes (á → a, ñ → n) y descarta el resto. Aplicamos
        // ANTES de limitar la longitud para que el truncado no parta un código UTF-8 multi-byte.
        $description = trim(__('tickets.redsys_product_description', [
            'code' => $order->code,
            // Marca de la instalación (data-driven, Fase 1): nunca una marca quemada.
            'name' => (string) (Setting::value('business.name') ?: config('app.name')),
        ]));
        $description = Str::limit(Str::ascii($description), 122, '...'); // 125 chars máx (manual Anexo 1).

        $merchantName = Str::limit(Str::ascii($cfg['merchant_name']), 57, '...');

        $data = [
            'DS_MERCHANT_AMOUNT' => (string) $payment->amount,         // céntimos (manual §3). FUENTE ÚNICA = Payment.amount (= señal/depósito #225) → blinda el canario amount_mismatch.
            'DS_MERCHANT_ORDER' => (string) $payment->gateway_order,
            'DS_MERCHANT_MERCHANTCODE' => $cfg['merchant_code'],
            'DS_MERCHANT_CURRENCY' => $cfg['currency'],
            'DS_MERCHANT_TRANSACTIONTYPE' => '0',                       // 0 = autorización.
            'DS_MERCHANT_TERMINAL' => $cfg['terminal'],
            'DS_MERCHANT_MERCHANTNAME' => $merchantName,
            'DS_MERCHANT_PRODUCTDESCRIPTION' => $description,
            // `MerchantData` vuelve íntegro en la respuesta (manual Anexo 1, máx 1024 chars).
            // Llevamos el código legible de NUESTRO pedido (`JJ-XXXX`) — defensa adicional para
            // reconciliar la respuesta (la reconciliación primaria es `gateway_order → Payment`).
            'DS_MERCHANT_MERCHANTDATA' => $order->code,
            'DS_MERCHANT_CONSUMERLANGUAGE' => $this->consumerLanguageCode($locale ?? app()->getLocale()),
            'DS_MERCHANT_URLOK' => route('payments.redsys.return.ok'),
            'DS_MERCHANT_URLKO' => route('payments.redsys.return.ko'),
            // Notificación on-line: solo si está configurada (5.5d, requiere URL pública).
            // Vacío = Redsys no notifica server-to-server; la fuente de verdad es la vuelta firmada.
            'DS_MERCHANT_MERCHANTURL' => (string) Setting::value('redsys_merchant_url', ''),
        ];

        $params = $this->createMerchantParameters($data);
        $signature = $this->createMerchantSignature($cfg['secret_key'], $params, $payment->gateway_order);

        return [
            'gatewayUrl' => $this->gatewayUrl(),
            'signatureVersion' => self::SIGNATURE_VERSION,
            'params' => $params,
            'signature' => $signature,
        ];
    }

    /**
     * Ejecuta una devolución REST sobre el `Payment` original (TransactionType=3).
     * Sub-fase 7.2b extendida (#142, decisión validada empíricamente contra la doc
     * oficial Redsys "Devolver o anular un pago").
     *
     * Reusa la cripto HMAC_SHA512_V2 + base64-url del flujo iframe (#104) — la
     * única diferencia es el endpoint (REST vs realizarPago) y la `TransactionType`
     * (3 en lugar de 0). `DS_MERCHANT_ORDER` es el mismo del Payment original
     * (idempotencia: el banco enlaza la devolución con su autorización por este campo).
     *
     * **NUNCA lanza excepciones**: cualquier fallo (red, timeout, JSON inválido,
     * firma rota en la respuesta, código distinto a 0900) se normaliza en un
     * `RefundResult` con `success=false` y `failureReason` categorizado.
     * El orquestador (Order::executeFullRefund) decide qué hacer con el resultado
     * sin try/catch.
     *
     * @param  int  $amountCents  Importe a devolver en céntimos. Debe ser ≤ amount cobrado.
     *                            Redsys admite varias devoluciones parciales acumulativas
     *                            sobre el mismo Payment hasta agotar el importe original.
     */
    public function executeRefund(Payment $original, int $amountCents): RefundResult
    {
        if ($original->gateway_order === null || $original->gateway_order === '') {
            return RefundResult::malformedResponse(
                'Payment has no gateway_order; cannot identify the original authorization to refund.',
            );
        }
        if ($amountCents <= 0) {
            return RefundResult::malformedResponse(
                'Refund amount must be positive (got '.$amountCents.').',
            );
        }

        $cfg = $this->config();

        $data = [
            'DS_MERCHANT_AMOUNT' => (string) $amountCents,
            'DS_MERCHANT_ORDER' => (string) $original->gateway_order,
            'DS_MERCHANT_MERCHANTCODE' => $cfg['merchant_code'],
            'DS_MERCHANT_CURRENCY' => $cfg['currency'],
            // Operación 3 = devolución (manual Redsys §refund). NO usar 9 (anulación):
            // anulación solo es válida el mismo día sin que el cobro se haya consolidado.
            // Para nuestra operativa (refunds horas/días después) devolución es lo correcto.
            'DS_MERCHANT_TRANSACTIONTYPE' => '3',
            'DS_MERCHANT_TERMINAL' => $cfg['terminal'],
        ];

        $params = $this->createMerchantParameters($data);
        $signature = $this->createMerchantSignature(
            $cfg['secret_key'],
            $params,
            (string) $original->gateway_order,
        );

        $body = [
            'Ds_SignatureVersion' => self::SIGNATURE_VERSION,
            'Ds_MerchantParameters' => $params,
            'Ds_Signature' => $signature,
        ];

        try {
            $response = Http::timeout(self::REST_TIMEOUT_SECONDS)
                ->acceptJson()
                ->asJson()
                ->post($this->restUrl(), $body);
        } catch (Throwable $e) {
            Log::warning('redsys.refund.transport_error', [
                'gateway_order' => $original->gateway_order,
                'amount_cents' => $amountCents,
                'error' => $e->getMessage(),
                'exception' => $e::class,
            ]);

            return RefundResult::transportError(
                'Network error contacting Redsys: '.$e->getMessage(),
            );
        }

        // Cualquier 5xx o respuesta sin status 200 → transporte. No interpretamos
        // el cuerpo como definitivo (Redsys puede devolver HTML de error).
        if ($response->serverError() || $response->status() !== 200) {
            Log::warning('redsys.refund.bad_http_status', [
                'gateway_order' => $original->gateway_order,
                'status' => $response->status(),
                'body_preview' => Str::limit($response->body(), 300, '…'),
            ]);

            return RefundResult::transportError(
                'Redsys returned HTTP '.$response->status().' — verify in portal before retrying.',
                ['http_status' => $response->status(), 'body' => Str::limit($response->body(), 1000, '…')],
            );
        }

        $payload = $response->json();

        // Redsys puede responder un error "bare" `{"errorCode":"SISxxxx"}` (HTTP 200, SIN
        // `Ds_MerchantParameters`): p. ej. `SIS0054` (no existe la operación a devolver), `SIS0042`
        // (error de firma), `SIS0058` (importe excede), etc. Es una DENEGACIÓN con un código
        // ACCIONABLE, no una respuesta malformada: la surfaceamos como `gatewayDenied` con el código
        // para que el operador (y el audit log `orders.refund_failed`, recomendación C) vea qué pasó,
        // en vez de un genérico «missing Ds_MerchantParameters». Verificado empíricamente contra el
        // sandbox (recomendación A): una devolución sobre una operación inexistente devuelve SIS0054.
        if (is_array($payload) && isset($payload['errorCode']) && ! isset($payload['Ds_MerchantParameters'])) {
            $errorCode = (string) $payload['errorCode'];
            Log::warning('redsys.refund.error_code', [
                'gateway_order' => $original->gateway_order,
                'error_code' => $errorCode,
            ]);

            return RefundResult::gatewayDenied(
                $errorCode,
                $payload,
                'Redsys rechazó la devolución con código '.$errorCode.'.',
            );
        }

        if (! is_array($payload) || ! isset($payload['Ds_MerchantParameters'])) {
            Log::warning('redsys.refund.malformed_response', [
                'gateway_order' => $original->gateway_order,
                'body_preview' => Str::limit($response->body(), 300, '…'),
            ]);

            return RefundResult::malformedResponse(
                'Redsys response missing Ds_MerchantParameters.',
                is_array($payload) ? $payload : ['body' => Str::limit($response->body(), 1000, '…')],
            );
        }

        try {
            $responseData = $this->decodeMerchantParameters((string) $payload['Ds_MerchantParameters']);
        } catch (Throwable $e) {
            return RefundResult::malformedResponse(
                'Cannot decode Ds_MerchantParameters: '.$e->getMessage(),
                $payload,
            );
        }

        // Verificación de firma de la respuesta OBLIGATORIA (auditoría Fase 1, endurecimiento del
        // camino del dinero). Si Redsys responde SIN firma o con firma inválida → malformed: NO
        // aceptamos un resultado de devolución a ciegas. Antes el `!== ''` cortocircuitaba la
        // verificación cuando faltaba la firma —contradiciendo este comentario— y se daba por buena
        // una respuesta sin autenticar. La firma es la prueba de autenticidad (defensa MITM /
        // respuestas adulteradas; improbable sobre TLS sano, pero el manual lo exige).
        $signatureReceived = (string) ($payload['Ds_Signature'] ?? '');
        if ($signatureReceived === '' || ! $this->verifySignature(
            $cfg['secret_key'],
            (string) $payload['Ds_MerchantParameters'],
            $signatureReceived,
        )) {
            Log::warning('redsys.refund.invalid_signature', [
                'gateway_order' => $original->gateway_order,
                'missing_signature' => $signatureReceived === '',
            ]);

            return RefundResult::malformedResponse(
                'Redsys response signature missing or mismatched — verify in portal before retrying.',
                $payload,
            );
        }

        $dsResponse = isset($responseData['Ds_Response'])
            ? (string) $responseData['Ds_Response']
            : null;

        if ($dsResponse === PaymentRefund::REDSYS_REFUND_SUCCESS_CODE) {
            return RefundResult::succeeded($dsResponse, $responseData);
        }

        // Cualquier otro código = denegado por la pasarela. La traducción a texto
        // humano queda fuera de aquí (responsabilidad de la UI vía
        // `RedsysResponseCode::reasonText()`); aquí solo registramos el código.
        return RefundResult::gatewayDenied(
            $dsResponse ?? 'unknown',
            $responseData,
            'Redsys denied the refund (Ds_Response='.($dsResponse ?? 'missing').').',
        );
    }

    private function extractOrder(array $data): string
    {
        foreach (['Ds_Order', 'DS_ORDER', 'DS_Order'] as $key) {
            if (isset($data[$key]) && $data[$key] !== '') {
                return (string) $data[$key];
            }
        }

        throw new RuntimeException('Redsys: response payload does not contain `Ds_Order`.');
    }
}
