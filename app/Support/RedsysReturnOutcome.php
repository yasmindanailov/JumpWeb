<?php

namespace App\Support;

/**
 * Resultado tipado del procesamiento de una vuelta o notificación de Redsys
 * (capa 5.5c/5.5d, decisión #104). Cada caso refleja una rama distinta que la
 * vista web (vuelta del navegador) o el cliente Redsys (notificación) deben
 * tratar de forma distinta — sin acoplar el handler a HTTP.
 *
 *   - Authorized: `Ds_Response` ∈ 0000–0099 y todo se ha aplicado (idempotente).
 *   - Denied: respuesta numérica > 0099 o fuera de rango — pago denegado.
 *   - IdempotentPaid: el Payment ya estaba `paid` antes (replay o segunda vía
 *     legítima como vuelta + notificación). Sin efectos secundarios.
 *   - InvalidSignature: la firma `Ds_Signature` no valida; rechazamos sin tocar BD.
 *   - UnknownOrder: el `Ds_Order` no corresponde a ningún `Payment.gateway_order`.
 *   - AmountMismatch: defensa en profundidad — el `Ds_Amount` recibido no coincide
 *     con `Payment.amount` (no debería pasar si la firma valida, pero sirve de
 *     canario para anomalías de protocolo o configuración).
 *   - MalformedPayload: faltan campos obligatorios (`Ds_SignatureVersion`,
 *     `Ds_MerchantParameters`, `Ds_Signature`) o no son decodificables.
 */
enum RedsysReturnOutcome: string
{
    case Authorized = 'authorized';

    case Denied = 'denied';

    case IdempotentPaid = 'idempotent_paid';

    /**
     * Audit hardening #113 (C1, 2026-05-28) — Race condition crítica resuelta.
     *
     * Escenario: notificación on-line autorizada llega DESPUÉS de que `orders:expire` haya
     * caducado la Order (porque el cobro tardó más que `sales.hold_minutes`). El cobro YA
     * está capturado en el banco; no podemos rechazarlo. Pero la plaza pudo haber sido
     * cedida lazy a otro cliente → posible sobreventa.
     *
     * Comportamiento: marcamos Payment paid (el dinero llegó) + Order paid + paid_at, PERO:
     *  - log `redsys.return.overbooked_alert` (ERROR) para que el operador lo audite.
     *  - email distinto al cliente (`OrderProcessedAfterExpiration`): "tu pago se procesó,
     *    pero la reserva caducó, te contactamos en 24h" → operativa decide reagendar/devolver.
     *  - NO emite tickets automáticos (el operador decide manualmente).
     */
    case AuthorizedAfterExpiration = 'authorized_after_expiration';

    case InvalidSignature = 'invalid_signature';

    case UnknownOrder = 'unknown_order';

    case AmountMismatch = 'amount_mismatch';

    /** Audit hardening #113 (2026-05-28, A3): defense in depth simétrica al amount.
     *  Si la firma valida pero `Ds_Currency` no coincide con `Payment.currency_code`,
     *  rechazamos. No debería ocurrir si la firma vale, pero canario contra desviaciones
     *  de protocolo/configuración (e.g., terminal Redsys reconfigurado a otra divisa). */
    case CurrencyMismatch = 'currency_mismatch';

    case MalformedPayload = 'malformed_payload';

    /** El cliente debe ver la pantalla de éxito (Order pagada, tickets emitidos). */
    public function isSuccess(): bool
    {
        return $this === self::Authorized || $this === self::IdempotentPaid;
    }

    /** Incidencia: cobro capturado pero reserva caducada (#113 C1). Email distinto. */
    public function isOverbooked(): bool
    {
        return $this === self::AuthorizedAfterExpiration;
    }

    /** El cliente debe ver la pantalla de KO (denegación del banco; puede reintentar). */
    public function isClientFailure(): bool
    {
        return $this === self::Denied;
    }

    /**
     * Outcomes que indican rechazo silencioso desde el servidor (la petición se descarta
     * y NO se muestra detalle al cliente). Para `notificación` server-to-server devolveremos
     * un HTTP 200/400 según el caso; para la vuelta del navegador, una pantalla genérica.
     */
    public function isServerReject(): bool
    {
        return $this === self::InvalidSignature
            || $this === self::UnknownOrder
            || $this === self::AmountMismatch
            || $this === self::CurrencyMismatch
            || $this === self::MalformedPayload;
    }
}
