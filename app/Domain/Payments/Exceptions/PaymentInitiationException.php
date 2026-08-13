<?php

namespace App\Domain\Payments\Exceptions;

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
 */
class PaymentInitiationException extends RuntimeException {}
