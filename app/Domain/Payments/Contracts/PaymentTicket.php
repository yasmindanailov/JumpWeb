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
     * @param  array<string, string>  $formData  payload CRUDO del proveedor (ver `gatewayFields()`)
     */
    public function __construct(
        public Payment $payment,
        public array $formData,
    ) {}

    /** Dónde hay que POSTear el formulario. */
    public function gatewayUrl(): string
    {
        return (string) ($this->formData['gatewayUrl'] ?? '');
    }

    /**
     * Los campos del formulario CON SUS NOMBRES REALES, listos para enviarse tal cual.
     *
     * `formData` no es eso, aunque su nombre lo sugiera: es el payload crudo del proveedor, con la
     * URL mezclada dentro y claves propias (`params`, `signature`) que **no** son los nombres de los
     * `<input>`. Traducir de una forma a la otra era conocimiento repartido por las plantillas, y un
     * cliente de API no tiene plantilla donde mirarlo. Aquí queda dicho una vez.
     *
     * ⚠️ El contenido va firmado: tocar un solo campo invalida la firma y la pasarela rechaza el
     * cobro (SIS0042). Se transporta, no se manipula.
     *
     * @return array<string, string>
     */
    public function gatewayFields(): array
    {
        return [
            'Ds_SignatureVersion' => (string) ($this->formData['signatureVersion'] ?? ''),
            'Ds_MerchantParameters' => (string) ($this->formData['params'] ?? ''),
            'Ds_Signature' => (string) ($this->formData['signature'] ?? ''),
        ];
    }
}
