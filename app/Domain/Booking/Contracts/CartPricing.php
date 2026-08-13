<?php

namespace App\Domain\Booking\Contracts;

/**
 * La TARIFICACIÓN de una cesta (Fase 3 · paso 4a, `docs/specs/api-v1.md` §4.6.4).
 *
 * Responde a una sola pregunta —**¿cuánto suma esta cesta y cuánto se cobra online?**— y reúne la
 * aritmética que hasta ahora vivía dentro de `Livewire\Tickets\Purchase`, es decir, en una clase de
 * interfaz: el precio del día de cada línea, los complementos resueltos con su config de pivote, la
 * señal por línea (#225) y el resto a cobrar en el parque.
 *
 * **Por qué es un contrato y no un método más del componente**: sin él, `POST orders/quote` habría
 * tenido que sumar por su cuenta, y esa es exactamente la segunda fuente de verdad que §4.6.4 del
 * spec manda evitar. El cliente que no puede preguntar el precio acaba reimplementando
 * `RateResolver` + `AddonResolver` + `depositCents()` — y en cuanto una de las dos copias se toca,
 * lo que se MUESTRA deja de ser lo que se COBRA.
 *
 * **Qué NO hace, y es deliberado:**
 *  - **no bloquea aforo ni comprueba disponibilidad**: un presupuesto no retiene una plaza. Que la
 *    franja siga teniendo sitio lo decide `OrderCreator` bajo lock (`AFORO-01`), y decirlo aquí
 *    sería una promesa que caduca en el instante en que se emite;
 *  - **no admite ni rechaza la reserva**: eso es {@see ReservationAdmission};
 *  - **no valida los datos del evento** de un pack (los obligatorios los exige el checkout).
 *
 * Recibe la cesta en la forma canónica de `Booking\Services\Cart::sanitize()` —la misma que consume
 * `OrderCreator`— para que no existan dos ideas de «qué es una línea de cesta». Se cita en prosa, y
 * no con `{@see}`, porque una anotación resoluble haría que un contrato importara un servicio.
 *
 * Implementación actual: `App\Domain\Booking\Services\CartPricer` (bind en `BookingServiceProvider`)
 * — se nombra en prosa y no con `{@see}` para que una interfaz no importe a su implementación.
 */
interface CartPricing
{
    /**
     * Tarifica la cesta: qué suma cada línea, cuánto suma el total y cuánto se cobra online.
     *
     * Las líneas cuyo producto ya no se vende (retirado de la venta online o con la zona
     * desactivada) **se descartan**, igual que hace hoy el carrito de la web: cobrar o anunciar un
     * producto que el checkout va a rechazar es peor que no listarlo. Por eso cada línea del
     * resultado lleva su `index` de origen — es lo que permite al llamante saber cuál cayó.
     *
     * @param  array<mixed>  $cart  cesta en bruto; se sanea aquí con `Cart::sanitize()`
     */
    public function quote(array $cart): CartQuote;
}
