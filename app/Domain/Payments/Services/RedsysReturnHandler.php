<?php

namespace App\Domain\Payments\Services;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Services\TicketIssuer;
use App\Domain\Payments\Models\Payment;
use App\Domain\Platform\Models\AuditLog;
use App\Domain\Platform\Services\AuditLogger;
use App\Mail\PaymentIncidentMail;
use App\Notifications\GuestFormRequest;
use App\Notifications\OrderConfirmation;
use App\Notifications\OrderPaymentDeclined;
use App\Notifications\OrderProcessedAfterExpiration;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Capa 5.5c (vuelta firmada) + 5.5d (notificación on-line) — procesador único.
 *
 * Es el ÚNICO punto del sistema autorizado a transicionar `Payment.status` a `paid` y
 * `Order.status` a `paid`. Vale para AMBAS vías de confirmación (manual §7, #62):
 *   1. Vuelta del navegador (POST cross-site a UrlOK/UrlKO; valida 100% en local).
 *   2. Notificación servidor-a-servidor (POST de Redsys a `Ds_Merchant_MerchantURL`;
 *      fuente de verdad en producción — 5.5d).
 *
 * Seguridad ÓPTIMA aplicada (cf. docs/PLAN-REDSYS.md §6/§8 y docs/SEGURIDAD.md regla 5–6):
 *  - Firma `Ds_Signature` verificada PRIMERO con `hash_equals` (timing-safe) antes de
 *    consultar la BD por el pedido. Sin firma válida → no hay lookup, no hay trazas.
 *  - **Sin sesión**: ni `auth()` ni `session()` se tocan (los POST son cross-site /
 *    server-to-server). El pedido se identifica por `Ds_Order ↔ Payment.gateway_order`
 *    (UNIQUE en BD, decisión #104).
 *  - **`lockForUpdate` sobre Payment** para serializar vuelta + notificación + reintentos.
 *  - **Idempotencia**: si Payment ya está `paid`, no re-emitimos tickets ni email.
 *  - **Defense in depth**: el `Ds_Amount` recibido debe coincidir con `Payment.amount`
 *    aunque la firma haya validado (canario contra desviaciones de protocolo/config).
 *  - **Transición atómica** Order pending → paid + emisión de tickets dentro de UNA
 *    transacción. El email se envía FUERA (un fallo SMTP no debe deshacer un pago real).
 *  - **Logging rico** con `order_id`, `gateway_order`, `ds_response`, outcome — **sin**
 *    `Ds_Signature` ni la clave del comercio.
 */
class RedsysReturnHandler
{
    /**
     * Mapeo ISO-4217 alfa-3 → numérico (manual Redsys §3). Solo las divisas que el comercio
     * vende — para una nueva (USD, GBP, etc.) basta añadir la entrada. Si `Payment.currency`
     * no está aquí, NO se valida la divisa de la respuesta (la firma sigue protegiendo;
     * preferimos un mapping incompleto a un rechazo de pago legítimo por mapping faltante).
     */
    private const ISO_4217_NUMERIC = [
        'EUR' => '978',
    ];

    /**
     * Allowlist de campos guardables en `Payment.raw_response` (audit #113 M1, RGPD
     * principio de minimización + PCI DSS: aunque PAN truncado NO es dato sensible,
     * conviene no guardarlo si no añade valor operativo). Solo campos útiles para
     * soporte/auditoría:
     *  - `Ds_Response`: código de autorización Redsys (0000-0099 = OK).
     *  - `Ds_AuthorisationCode`: código del banco para conciliar.
     *  - `Ds_TransactionType`: tipo (0 autorización, otros).
     *  - `Ds_Amount`, `Ds_Currency`: importe ya en `Payment.amount/currency`, lo guardamos
     *    como redundancia de auditoría (verificar después que ambos coinciden).
     *  - `Ds_Date`, `Ds_Hour`: timestamp del banco (útil para reconciliar con extracto).
     *  - `Ds_Card_Brand`, `Ds_Card_Country`: marca y país (no PAN), útil para soporte.
     *  - `Ds_Order`: gateway_order (redundante con `Payment.gateway_order`, redundancia OK).
     *  - `Ds_MerchantData`: nuestro `orders.code` que volvió íntegro.
     *
     * Lo que NO guardamos:
     *  - `Ds_Card_Number` (PAN truncado): innecesario para soporte; minimización RGPD.
     *  - `Ds_ConsumerLanguage`, `Ds_TerminalVisa`, `Ds_TransactionDate`, etc.: telemetría
     *    de Redsys sin valor para nosotros.
     *  - Cualquier campo nuevo que Redsys añada (manual §4.5: "Redsys puede añadir campos
     *    sin previo aviso"): por seguridad NO se guarda nada que no esté en allowlist
     *    explícita.
     */
    private const RAW_RESPONSE_ALLOWLIST = [
        'Ds_Response',
        'Ds_AuthorisationCode',
        'Ds_TransactionType',
        'Ds_Amount',
        'Ds_Currency',
        'Ds_Date',
        'Ds_Hour',
        'Ds_Card_Brand',
        'Ds_Card_Country',
        'Ds_Order',
        'Ds_MerchantData',
    ];

    public function __construct(private Redsys $redsys) {}

    /**
     * Procesa los 3 campos canónicos del POST de Redsys.
     *
     * @param  array{Ds_SignatureVersion?: string, Ds_MerchantParameters?: string, Ds_Signature?: string}  $payload
     * @param  string  $source  canal de entrada para auditoría ('browser_return' o 'notification').
     *                          5.5d (#107): distinguir en logs ayuda a un operador en producción a
     *                          diferenciar una firma inválida desde el navegador del cliente (raro,
     *                          probable bug propio) de una desde la notificación on-line (posible
     *                          atacante o mal-configuración del terminal).
     * @return array{outcome: RedsysReturnOutcome, payment: ?Payment, order: ?Order, ds_response: ?string}
     */
    public function process(array $payload, string $source = 'unknown'): array
    {
        // 1. Validación de campos obligatorios. Cualquier ausencia → rechazo silencioso.
        $version = $payload['Ds_SignatureVersion'] ?? null;
        $params = $payload['Ds_MerchantParameters'] ?? null;
        $signature = $payload['Ds_Signature'] ?? null;

        if (! is_string($version) || ! is_string($params) || ! is_string($signature) || $params === '' || $signature === '') {
            Log::warning('redsys.return.malformed', ['source' => $source, 'has_version' => (bool) $version, 'has_params' => (bool) $params, 'has_signature' => (bool) $signature]);

            return $this->result(RedsysReturnOutcome::MalformedPayload);
        }

        // 2. Decodificar params para extraer `Ds_Order`. NO confiamos en él aún —
        //    sólo lo usamos para localizar la clave del comercio y verificar la firma.
        try {
            $data = $this->redsys->decodeMerchantParameters($params);
        } catch (Throwable $e) {
            Log::warning('redsys.return.decode_failed', ['source' => $source, 'error' => $e->getMessage()]);

            return $this->result(RedsysReturnOutcome::MalformedPayload);
        }

        // 3. Verificar firma con la clave del entorno actual (timing-safe, vía hash_equals
        //    en `Redsys::verifySignature`). Sin firma válida → NO consultamos BD.
        $secretKey = $this->redsys->config()['secret_key'];
        if (! $this->redsys->verifySignature($secretKey, $params, $signature)) {
            Log::warning('redsys.return.invalid_signature', [
                'source' => $source,
                'gateway_order' => $data['Ds_Order'] ?? null,
                'ds_response' => $data['Ds_Response'] ?? null,
            ]);

            return $this->result(RedsysReturnOutcome::InvalidSignature);
        }

        // 4. Localizar Payment por gateway_order. NO usamos `Ds_MerchantData` para esto
        //    (ese campo es un dato de comercio retornado tal cual; el lookup primario va
        //    por la UNIQUE constraint de `payments.gateway_order`).
        $gatewayOrder = $data['Ds_Order'] ?? null;
        if (! is_string($gatewayOrder) || $gatewayOrder === '') {
            return $this->result(RedsysReturnOutcome::MalformedPayload);
        }

        $payment = Payment::where('gateway_order', $gatewayOrder)->first();
        if ($payment === null) {
            Log::warning('redsys.return.unknown_order', ['source' => $source, 'gateway_order' => $gatewayOrder]);

            return $this->result(RedsysReturnOutcome::UnknownOrder);
        }

        // 5. Sección crítica: transición atómica con `lockForUpdate` para serializar
        //    vuelta + notificación + reintentos. Solo aquí se transiciona a `paid`.
        $dsResponse = isset($data['Ds_Response']) ? (string) $data['Ds_Response'] : null;
        $dsAmount = isset($data['Ds_Amount']) ? (string) $data['Ds_Amount'] : null;
        $dsCurrency = isset($data['Ds_Currency']) ? (string) $data['Ds_Currency'] : null;
        $authCode = $data['Ds_AuthorisationCode'] ?? null;

        $emitConfirmation = false;
        $emitOverbookedIncident = false; // #113 C1
        $emitDeclined = false;           // #114 G1
        $resultOutcome = null;
        $orderFor = null;
        // Visibilidad de incidencias (recomendación C, 2026-06-15): metadatos SIN PII del cobro
        // problemático, capturados dentro de la transacción y consumidos fuera (audit_log + aviso).
        $incident = null;

        try {
            DB::transaction(function () use (&$resultOutcome, &$emitConfirmation, &$emitOverbookedIncident, &$emitDeclined, &$orderFor, &$incident, $payment, $data, $dsResponse, $dsAmount, $dsCurrency, $authCode, $source): void {
                // Re-fetch dentro de la transacción con lock para evitar carreras (vuelta
                // del navegador + notificación llegando simultáneamente).
                $locked = Payment::where('id', $payment->id)->lockForUpdate()->first();
                if ($locked === null) {
                    $resultOutcome = RedsysReturnOutcome::UnknownOrder;

                    return;
                }

                // Idempotencia: si ya está `paid`, NO reprocesamos. El cliente verá éxito,
                // pero no enviamos tickets ni email duplicados (replay defense).
                if ($locked->status === Payment::STATUS_PAID) {
                    $resultOutcome = RedsysReturnOutcome::IdempotentPaid;
                    $orderFor = $locked->payable;

                    return;
                }

                // Idempotencia simétrica para FAILED (#114, audit edge cases): un segundo
                // evento Denied sobre Payment ya marcado failed (típico: notif llega tras
                // la vuelta del navegador, ambas Denied) NO debe re-enviar el email de
                // denegación. Devolvemos `Denied` para el outcome HTTP pero sin tocar BD
                // ni emails. Sin esto, el cliente podría recibir DOS emails idénticos.
                if ($locked->status === Payment::STATUS_FAILED) {
                    $resultOutcome = RedsysReturnOutcome::Denied;
                    $orderFor = $locked->payable;

                    return;
                }

                // Defensa en profundidad: si la firma valida, el `Ds_Amount` DEBERÍA coincidir
                // con lo que firmamos en la ida. Si no, algo extraño está pasando — descartamos.
                if ($dsAmount !== null && $dsAmount !== (string) $locked->amount) {
                    Log::error('redsys.return.amount_mismatch', [
                        'source' => $source,
                        'payment_id' => $locked->id,
                        'expected' => $locked->amount,
                        'received' => $dsAmount,
                    ]);
                    $resultOutcome = RedsysReturnOutcome::AmountMismatch;

                    return;
                }

                // Defense in depth simétrica (#113, A3): `Ds_Currency` (ISO-4217 numérico, p. ej.
                // '978' = EUR) debe coincidir con `Payment.currency` (ISO alfa-3, p. ej. 'EUR').
                // Mapeamos solo las divisas que vendemos; si Payment.currency es algo no esperado,
                // no comparamos para no rechazar pagos legítimos por un mapping incompleto.
                if ($dsCurrency !== null) {
                    $expectedNumeric = self::ISO_4217_NUMERIC[strtoupper((string) $locked->currency)] ?? null;
                    if ($expectedNumeric !== null && $dsCurrency !== $expectedNumeric) {
                        Log::error('redsys.return.currency_mismatch', [
                            'source' => $source,
                            'payment_id' => $locked->id,
                            'expected' => $expectedNumeric,
                            'received' => $dsCurrency,
                            'payment_currency' => $locked->currency,
                        ]);
                        $resultOutcome = RedsysReturnOutcome::CurrencyMismatch;

                        return;
                    }
                }

                $order = Order::where('id', $locked->payable_id)->lockForUpdate()->firstOrFail();
                $isAuthorized = $this->isAuthorizedResponse($dsResponse);

                if ($isAuthorized) {
                    $now = now();

                    // El cobro entrante SIEMPRE se registra como `paid`: es real e irreversible en
                    // el banco, sea cual sea el estado de la Order. Lo que NO es incondicional es
                    // emitir tickets / transicionar la Order (ver `$canFulfil` abajo).
                    $locked->forceFill([
                        'status' => Payment::STATUS_PAID,
                        'transaction_id' => is_string($authCode) ? $authCode : null,
                        'auth_code' => is_string($authCode) ? $authCode : null,
                        'raw_response' => self::filterRawResponse($data),
                        'paid_at' => $now,
                    ])->save();

                    // Una Order es CUMPLIBLE (transición a paid + emisión de tickets) SOLO si está
                    // estrictamente VIVA: `pending` y con el hold aún no vencido. Cualquier otro
                    // estado es una INCIDENCIA. Esto GENERALIZA el fix #4/C1 (auditoría Fase 1), que
                    // solo cubría EXPIRED y dejaba sin red: (a) una Order ya PAID por OTRO Payment
                    // (un 2.º pago autorizado re-emitía tickets y capturaba el cargo → doble cobro +
                    // tickets duplicados, critical) y (b) una Order CANCELLED/REFUNDED (la resucitaba
                    // a PAID → sobreventa de una plaza ya liberada, high). En todos esos casos el
                    // banco capturó el dinero pero NO debemos emitir tickets ni reescribir el pedido.
                    $canFulfil = $order->status === Order::STATUS_PENDING && ! $order->isExpiredInPractice();

                    if ($canFulfil) {
                        $order->forceFill([
                            'status' => Order::STATUS_PAID,
                            'paid_at' => $now,
                            'expires_at' => null, // ya está pagada; sin caducidad.
                        ])->save();

                        // Un Ticket por plaza, sólo para items con slot (addons sin slot —
                        // calcetines, taquillas — no emiten ticket). Lógica compartida con el pedido
                        // manual de back-office (7.3). `TicketIssuer` es IDEMPOTENTE (no-op si la
                        // Order ya tiene tickets): cinturón extra contra la doble emisión.
                        (new TicketIssuer)->issue($order);

                        // Pay-first (decisión clienta 2026-06-14): completar el pago demuestra que es
                        // una persona (un bot no paga con tarjeta real). Si el titular se registró en
                        // la compra y aún no había verificado su email, lo verificamos AUTOMÁTICAMENTE
                        // — así entra a «mi cuenta»/«mis reservas» sin el paso del correo. Idempotente.
                        $this->autoVerifyBuyer($order);

                        $resultOutcome = RedsysReturnOutcome::Authorized;
                        $emitConfirmation = true;
                        $orderFor = $order;
                    } else {
                        // INCIDENCIA: cobro capturado sobre una Order NO cumplible. Dos sub-casos,
                        // tratados distinto para no corromper el estado del pedido:
                        $lateButReal = $order->status === Order::STATUS_EXPIRED
                            || $order->isExpiredInPractice();

                        if ($lateButReal) {
                            // (a) Reserva REAL pero TARDÍA (EXPIRED o pending-expired): reflejamos el
                            //     cobro pasando la Order a paid (comportamiento C1 original) PERO sin
                            //     tickets (la plaza pudo cederse) + email «procesado tras caducar».
                            // El estado ORIGINAL (expired / pending-caducado) es el dato diagnóstico de
                            // la incidencia: lo capturamos ANTES del forceFill, que lo machacaría a paid.
                            $originalStatus = $order->status;
                            $order->forceFill([
                                'status' => Order::STATUS_PAID,
                                'paid_at' => $now,
                                'expires_at' => null,
                            ])->save();

                            Log::error('redsys.return.overbooked_alert', [
                                'source' => $source,
                                'order_id' => $order->id,
                                'order_code' => $order->code,
                                'gateway_order' => $locked->gateway_order,
                                'message' => 'Authorized notification arrived AFTER Order was expired. '
                                    .'Payment captured in bank, but seat may have been ceded to another customer. '
                                    .'Operator must contact the customer to reschedule or refund.',
                            ]);

                            $emitOverbookedIncident = true;
                            $incident = [
                                'kind' => 'overbooked',
                                'action' => AuditLog::ACTION_OVERBOOKED_CAPTURE,
                                'order_id' => $order->id,
                                'order_code' => $order->code,
                                'order_status' => $originalStatus,
                                'payment_id' => $locked->id,
                                'gateway_order' => $locked->gateway_order,
                                'source' => $source,
                            ];
                        } else {
                            // (b) Order muerta por OTRA razón (CANCELLED, REFUNDED, o ya PAID por otro
                            //     Payment): NO tocamos su status (una cancelada sigue cancelada; una ya
                            //     pagada conserva su paid_at y sus tickets). El dinero se capturó SIN
                            //     red → el operador debe DEVOLVERLO. No mandamos email al cliente (ya
                            //     tiene su pedido o lo canceló); el operador actúa por el log ALERT.
                            Log::error('redsys.return.duplicate_or_dead_capture', [
                                'source' => $source,
                                'order_id' => $order->id,
                                'order_code' => $order->code,
                                'order_status' => $order->status,
                                'payment_id' => $locked->id,
                                'gateway_order' => $locked->gateway_order,
                                'message' => 'Authorized notification arrived for a non-fulfillable Order '
                                    .'(already paid by another payment, cancelled, or refunded). Payment '
                                    .'captured in bank with NO matching fulfilment. Operator must refund this '
                                    .'duplicate/orphan charge.',
                            ]);

                            $incident = [
                                'kind' => 'duplicate',
                                'action' => AuditLog::ACTION_DUPLICATE_CAPTURE,
                                'order_id' => $order->id,
                                'order_code' => $order->code,
                                'order_status' => $order->status,
                                'payment_id' => $locked->id,
                                'gateway_order' => $locked->gateway_order,
                                'source' => $source,
                            ];
                        }

                        // Outcome común de incidencia: el cliente que vuelve por el navegador ve la
                        // pantalla «procesado tras caducar» y la notificación responde 200 (no
                        // queremos que Redsys reintente: el cobro SÍ se registró por nuestra parte).
                        $resultOutcome = RedsysReturnOutcome::AuthorizedAfterExpiration;
                        $orderFor = $order;
                    }
                } else {
                    // Pago denegado: marcamos Payment failed; Order sigue pending y
                    // caducará por `orders:expire` cuando cruce expires_at (#105). El
                    // aforo se libera entonces. No tocamos `expires_at` aquí.
                    $locked->forceFill([
                        'status' => Payment::STATUS_FAILED,
                        'raw_response' => self::filterRawResponse($data),
                    ])->save();

                    $resultOutcome = RedsysReturnOutcome::Denied;
                    $emitDeclined = true; // #114 G1: primer evento Denied → email al cliente
                    $orderFor = $order;
                }
            });
        } catch (Throwable $e) {
            Log::error('redsys.return.transaction_failed', [
                'source' => $source,
                'gateway_order' => $gatewayOrder,
                'error' => $e->getMessage(),
                'exception' => $e::class,
            ]);

            return $this->result(RedsysReturnOutcome::UnknownOrder, $payment);
        }

        // Email FUERA de la transacción: un fallo SMTP no debe deshacer un pago ya capturado.
        if ($emitConfirmation && $orderFor !== null && $orderFor->user) {
            try {
                $orderFor->user->notify(new OrderConfirmation($orderFor));
            } catch (Throwable $e) {
                Log::warning('redsys.return.confirmation_mail_failed', [
                    'source' => $source,
                    'order_id' => $orderFor->id,
                    'error' => $e->getMessage(),
                ]);
            }

            // Post-form de datos por invitado (#217): si la reserva incluye un pack que lo pide,
            // email aparte con el enlace firmado para rellenarlo. Aislado en su propio try/catch.
            try {
                $orderFor->loadMissing('items.ticketType');
                if ($orderFor->needsGuestForm()) {
                    // Individualizado POR RESERVA (#217): un email por cada pack que pide el post-form
                    // y aún está pendiente (un pedido con dos cumpleaños → dos emails).
                    foreach ($orderFor->guestFormItems() as $reservation) {
                        if ($reservation->needsGuestForm()) {
                            $orderFor->user->notify(new GuestFormRequest($reservation));
                        }
                    }
                }
            } catch (Throwable $e) {
                Log::warning('redsys.return.guest_form_mail_failed', [
                    'source' => $source,
                    'order_id' => $orderFor->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // #113 C1: email distinto cuando la notificación llegó tras caducar la Order. El
        // cliente sabe que el cobro ocurrió + que será contactado; sin "reserva confirmada"
        // (no la tiene) y sin tickets (no se emitieron).
        if ($emitOverbookedIncident && $orderFor !== null && $orderFor->user) {
            try {
                $orderFor->user->notify(new OrderProcessedAfterExpiration($orderFor));
            } catch (Throwable $e) {
                Log::warning('redsys.return.overbooked_mail_failed', [
                    'source' => $source,
                    'order_id' => $orderFor->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // #114 G1: email al cliente cuando el banco deniega el pago. Se envía SOLO en la
        // primera transición pending → failed (gracias a la idempotencia simétrica para
        // STATUS_FAILED arriba). El motivo `Ds_Response` se traduce a un texto al cliente
        // vía `RedsysResponseCode` (audit #114).
        if ($emitDeclined && $orderFor !== null && $orderFor->user) {
            try {
                $orderFor->user->notify(new OrderPaymentDeclined($orderFor, $dsResponse));
            } catch (Throwable $e) {
                Log::warning('redsys.return.declined_mail_failed', [
                    'source' => $source,
                    'order_id' => $orderFor->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // VISIBILIDAD DE INCIDENCIAS DE DINERO (recomendación C, 2026-06-15). Los dos peores casos
        // —cobro duplicado/huérfano sobre una Order no cumplible, y cobro autorizado tras caducar—
        // antes SOLO iban a `Log::error` (invisible en producción: nadie lee `laravel.log`). Ahora,
        // además del log, se registran en `audit_logs` (rastro DURADERO → aparecen en la página
        // «Incidencias» del panel) y se avisa por email al operador (best-effort). FUERA de la
        // transacción y tolerante a fallos: ni la auditoría ni el email pueden deshacer un cobro ya
        // capturado en el banco. `AuditLogger` es defensivo (try/catch interno).
        //
        // `logSystem` (no `log`): es un evento del SISTEMA disparado por el callback/retorno de
        // Redsys, no una acción de un usuario; NO debe grabar el `user_id`/IP/UA de la petición en
        // curso (la del banco, o la del navegador del cliente que vuelve) — no es el actor relevante
        // y es PII innecesaria (minimización RGPD). El payload va sin datos personales (códigos/ids).
        // Los `Log::error` previos de amount_mismatch/currency_mismatch NO se replican aquí a
        // propósito: ahí la Order NO se transiciona a paid (no hay cobro capturado por nuestra
        // parte), así que no es una incidencia accionable de devolución.
        if ($incident !== null && $orderFor !== null) {
            AuditLogger::logSystem($incident['action'], $orderFor, [
                'kind' => $incident['kind'],
                'order_code' => $incident['order_code'],
                'order_status' => $incident['order_status'],
                'gateway_order' => $incident['gateway_order'],
                'payment_id' => $incident['payment_id'],
                'source' => $incident['source'],
            ]);

            $alertEmail = IncidentSettings::alertEmail();
            if ($alertEmail !== null) {
                try {
                    Mail::to($alertEmail)->send(new PaymentIncidentMail($incident));
                } catch (Throwable $e) {
                    Log::warning('redsys.return.incident_alert_failed', [
                        'source' => $source,
                        'order_id' => $orderFor->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        Log::info('redsys.return.processed', [
            'source' => $source,
            'gateway_order' => $gatewayOrder,
            'outcome' => $resultOutcome?->value,
            'ds_response' => $dsResponse,
        ]);

        return $this->result($resultOutcome ?? RedsysReturnOutcome::UnknownOrder, $payment, $orderFor, $dsResponse);
    }

    /**
     * `Ds_Response` autorizado = numérico ∈ [0, 99] (manual §4: 0000–0099). Cualquier otro
     * valor (incluyendo no numéricos) → denegado. Tolerante a `null` y a representaciones
     * con padding (`"0000"`, `"42"`).
     */
    private function isAuthorizedResponse(?string $dsResponse): bool
    {
        if (! is_string($dsResponse) || ! ctype_digit($dsResponse)) {
            return false;
        }

        $code = (int) $dsResponse;

        return $code >= 0 && $code <= 99;
    }

    /**
     * Reduce el payload de Redsys a los campos de la allowlist (audit #113 M1).
     * No guardar lo innecesario es RGPD por defecto: si Redsys cambia el shape o añade
     * campos sensibles (PAN, IBAN…), nuestra app NO los persistirá.
     */
    private static function filterRawResponse(array $data): array
    {
        return array_intersect_key($data, array_flip(self::RAW_RESPONSE_ALLOWLIST));
    }

    /**
     * @return array{outcome: RedsysReturnOutcome, payment: ?Payment, order: ?Order, ds_response: ?string}
     */
    private function result(RedsysReturnOutcome $outcome, ?Payment $payment = null, ?Order $order = null, ?string $dsResponse = null): array
    {
        return [
            'outcome' => $outcome,
            'payment' => $payment,
            'order' => $order,
            'ds_response' => $dsResponse,
        ];
    }

    /**
     * Pay-first (decisión clienta 2026-06-14): un pago real de Redsys demuestra que el titular es una
     * persona, así que verificamos su email automáticamente si aún no lo estaba (típicamente quien se
     * registró DENTRO de la compra). Idempotente (no-op si ya estaba verificado o no hay titular).
     */
    private function autoVerifyBuyer(Order $order): void
    {
        $buyer = $order->user;
        if ($buyer === null || $buyer->hasVerifiedEmail()) {
            return;
        }

        $buyer->markEmailAsVerified();
        event(new Verified($buyer));
        Log::info('auth.email_verified', ['user_id' => $buyer->id, 'via' => 'payment']);
    }
}
