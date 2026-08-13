<?php

namespace App\Domain\Payments\Contracts;

use App\Domain\Payments\Models\Payment;

/**
 * Lo que hace falta para mandar al cliente a la pasarela (Fase 3 · paso 2): el intento de cobro
 * recién abierto y el formulario ya FIRMADO que el navegador auto-POSTea.
 *
 * `formData` sale firmado del servidor y ninguna superficie lo toca: cualquier manipulación
 * invalida la firma y Redsys rechaza el pago (SIS0042). Se transporta como array porque es
 * literalmente el conjunto de campos `<input>` que exige la pasarela.
 *
 * El `Payment` viaja al lado porque quien abre el cobro suele querer registrarlo o mostrar su
 * referencia; el que no lo necesite, lo ignora.
 */
final readonly class PaymentTicket
{
    /**
     * @param  array<string, string>  $formData  campos del formulario auto-POST, ya firmados
     */
    public function __construct(
        public Payment $payment,
        public array $formData,
    ) {}
}
