<?php

namespace App\Domain\Payments\Contracts;

use App\Models\Payment;

/**
 * Devolución de dinero al cliente a través de la pasarela — la ÚNICA superficie de
 * Payments que consume Booking (Fase 2, paso 1: `docs/specs/modulos-dominio.md` §5.1).
 *
 * Extraído literalmente de la llamada existente `Order::executeFullRefund()` y
 * `Order::executePartialRefund()` (`app(Redsys::class)->executeRefund(...)`): mismo
 * método, mismos parámetros, mismo resultado. NO se añade el «cobro» (la ida a la
 * pasarela, `Redsys::buildPaymentFormData`) porque Booking no lo consume: sus únicos
 * llamantes son la capa de entrega (`Livewire\Tickets\Purchase` y
 * `RetryPaymentController`), que en Fase 2 se queda quieta. Inventarlo aquí sería una
 * superficie nueva, prohibida por el spec (§4).
 *
 * **Nunca lanza**: cualquier fallo (red, timeout, respuesta malformada, denegación del
 * banco) se normaliza en un `RefundResult` con `success=false`. El orquestador de
 * Booking es un flujo lineal sin try/catch y esa garantía es parte del contrato.
 *
 * Implementación actual: `App\Support\Redsys` (bind en `PaymentsServiceProvider`;
 * viaja a `App\Domain\Payments` en el paso 5). La abstracción multi-proveedor
 * (`PaymentProvider`, Stripe y otros) es de Fase 3 — este contrato es su semilla.
 *
 * `Payment` es el modelo del PROPIO módulo Payments; sigue en `App\Models` hasta el
 * paso 5 y por eso aparece en la baseline legacy de `ModuleBoundariesTest`.
 */
interface RefundGateway
{
    /**
     * Ejecuta una devolución sobre el `Payment` original.
     *
     * @param  int  $amountCents  Importe a devolver en céntimos (> 0 y ≤ importe cobrado).
     *                            Se admiten varias devoluciones parciales acumulativas
     *                            sobre el mismo Payment hasta agotar el original.
     */
    public function executeRefund(Payment $original, int $amountCents): RefundResult;
}
