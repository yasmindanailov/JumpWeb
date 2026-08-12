<?php

namespace App\Domain\Payments\Contracts;

/**
 * Resultado inmutable de `RefundGateway::executeRefund()` (sub-fase 7.2b extendida,
 * #142). Refleja exactamente lo que el orquestador necesita para decidir si actualizar
 * la Order o registrar un fallo: ninguna excepción escala desde el gateway — cualquier
 * error de transporte se normaliza aquí con `success=false` y `failureReason`
 * categorizado.
 *
 * Esto permite que el orquestador (Order::executeFullRefund) sea un flujo
 * lineal sin try/catch, y que los tests cubran cada caso con un fake HTTP.
 *
 * Contrato de Payments (Fase 2, paso 1): los cinco campos son EXACTAMENTE los que
 * consume Booking (`Order::executeFullRefund`/`executePartialRefund`) — ni uno más.
 * `failureReason` viaja como string porque se persiste literal en
 * `payment_refunds.failure_reason`; convertirlo a enum es una decisión aparte.
 */
final readonly class RefundResult
{
    public function __construct(
        public bool $success,
        public ?string $dsResponse,
        public ?array $rawResponse,
        public ?string $failureReason,
        public ?string $message,
    ) {}

    public static function succeeded(string $dsResponse, array $rawResponse): self
    {
        return new self(
            success: true,
            dsResponse: $dsResponse,
            rawResponse: $rawResponse,
            failureReason: null,
            message: null,
        );
    }

    /**
     * Redsys respondió pero con un código distinto a `0900` (devolución denegada
     * por el banco: importe excede, autorización original no encontrada, etc.).
     * Recuperable solo cambiando los parámetros — reintentar idéntico no ayuda.
     */
    public static function gatewayDenied(string $dsResponse, array $rawResponse, string $message): self
    {
        return new self(
            success: false,
            dsResponse: $dsResponse,
            rawResponse: $rawResponse,
            failureReason: 'gateway_denied',
            message: $message,
        );
    }

    /**
     * No hubo respuesta utilizable de Redsys (timeout, conexión rechazada, DNS,
     * 5xx, etc.). El operador debe verificar manualmente en el portal Redsys
     * antes de reintentar — Redsys PUEDE haber procesado la devolución aunque
     * nuestro cliente no la haya recibido.
     */
    public static function transportError(string $message, ?array $rawResponse = null): self
    {
        return new self(
            success: false,
            dsResponse: null,
            rawResponse: $rawResponse,
            failureReason: 'transport_error_check_portal',
            message: $message,
        );
    }

    /**
     * Estructura de la respuesta sin la pinta esperada (no JSON, sin
     * `Ds_MerchantParameters`, etc.). Conservamos lo recibido para audit;
     * tratamiento idéntico a transport (operador debe verificar en portal).
     */
    public static function malformedResponse(string $message, ?array $rawResponse = null): self
    {
        return new self(
            success: false,
            dsResponse: null,
            rawResponse: $rawResponse,
            failureReason: 'unknown',
            message: $message,
        );
    }
}
