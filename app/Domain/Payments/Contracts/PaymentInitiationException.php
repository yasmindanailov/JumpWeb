<?php

namespace App\Domain\Payments\Contracts;

use RuntimeException;

/**
 * No se pudo abrir el cobro: reservar el `gateway_order`, crear el `Payment` o firmar el payload
 * falló (Fase 3 · paso 2).
 *
 * **Es un fallo del SISTEMA, no del cliente**, y por eso es una excepción y no un veredicto como
 * los de `Booking\Contracts\AdmissionDecision`: no hay nada que el cliente pueda corregir, y quien
 * llama solo tiene que decidir cómo se disculpa. El diagnóstico ya está registrado —log con traza y
 * `audit_logs` con `orders.payment_init_failed`, visible en el historial del pedido del panel (#169)—
 * antes de que la excepción salga de `PaymentInitiator`, precisamente para que nadie tenga que
 * acordarse de hacerlo al capturarla.
 *
 * El mensaje NO se le muestra al cliente: lleva detalle técnico. Cada superficie pone el suyo.
 *
 * **Por qué vive en `Contracts` y no en un `Exceptions/` propio** (cierre de Fase 3): la lanza el
 * puerto `Booking\Contracts\PaymentInitiation`, así que es parte de su contrato, no un detalle
 * interno de la implementación. Y hay una razón EJECUTABLE además de la conceptual: quien tiene que
 * capturarla es `Booking\Services\CheckoutOrchestrator` —es el disparador de la compensación—, y el
 * grafo de `ModuleBoundariesTest` solo deja a Booking alcanzar `Payments\Contracts`. Desde
 * `Payments\Exceptions` el `catch` era una flecha prohibida, y las alternativas eran peores:
 * capturar `Throwable` compensaría fallos que no son de la pasarela, y añadir una entrada a `SEAM`
 * viola la regla de que las baselines solo encogen. Precedente en la misma carpeta:
 * {@see PaymentTicket}, que tampoco es una interfaz.
 */
class PaymentInitiationException extends RuntimeException {}
