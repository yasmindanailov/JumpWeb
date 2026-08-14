<?php

namespace App\Domain\Booking\Contracts;

/**
 * Si una línea puede entrar en una cesta, y en qué queda (Fase 4 · paso 4.0b·6,
 * `docs/specs/sidebar-spa.md` §4.4.2, `DECISIONES #38(f)`).
 *
 * **Por qué existe.** Hoy esa decisión la toma `Livewire\Tickets\Purchase::addToCart()`, es decir el
 * servidor, en cada clic de «añadir a la cesta». Con la cesta de la SPA en `localStorage` **no queda
 * ninguna ida y vuelta al añadir**, así que sin este contrato la regla se transcribiría a JavaScript
 * — una segunda implementación de una regla de servidor, que es deuda por definición.
 *
 * ⚠️ **Y de las caras de descubrir.** El saneo de un campo `number` aplica
 * `preg_replace('/\D+/', '')`: la EDAD contestada «cinco» el servidor la ve **vacía** y cualquier
 * validación ingenua en el cliente la ve contestada. Con la regla transcrita, el cliente lo
 * descubriría cinco pasos después, ya identificado, con un 422 que ni siquiera nombra los campos.
 * No lo encuentra ninguna revisión de código: solo un cliente enfadado.
 *
 * **Qué comprueba, en el mismo orden que la compra web** —para que la web pueda consumirlo sin
 * cambiar qué aviso enseña primero—:
 *  1. el producto se puede elegir y la franja se ofrece de verdad;
 *  2. la cantidad llega al mínimo, y se recorta al cupo si se pasa;
 *  3. la cesta no está en su tope de líneas (`PAY-12`);
 *  4. los campos obligatorios del pack están respondidos **según el saneo del servidor**.
 *
 * Y además responde a lo que ninguna validación contesta: **con qué cantidad entraría** y **si se
 * funde con una línea que ya estaba**.
 *
 * **Qué NO hace, y es deliberado:**
 *  - **no retiene nada ni promete nada**: lo que dice es cierto en el instante en que se dice, y el
 *    juez final sigue siendo `OrderCreator` bajo lock (`AFORO-01`), que revalida todo. Este contrato
 *    evita el viaje en balde, no sustituye al checkout;
 *  - **no tarifica**: eso es {@see CartPricing}, y el precio de la cesta se pide cuando se pinta;
 *  - **no devuelve las respuestas del evento saneadas**. Son datos personales de un menor y el
 *    cliente acaba de mandarlas: `orders/quote` ya fija la regla de que se aceptan y **no se
 *    reenvían** (`RGPD` §3). Lo que sí vuelve es QUÉ campo falta, que es lo que hace falta para
 *    corregirlo;
 *  - **no admite al titular**: eso es {@see ReservationAdmission}, y consume ficha.
 *
 * La cesta llega en la forma canónica de `Booking\Services\Cart::sanitize()` —la misma que consumen
 * `OrderCreator`, la tarificación y la disponibilidad—; se cita en prosa, y no con `{@see}`, porque
 * una anotación resoluble haría que un contrato importara un servicio.
 *
 * Implementación actual: `App\Domain\Booking\Services\CartLineValidator` (bind en
 * `BookingServiceProvider`) — se nombra en prosa y no con `{@see}` para que una interfaz no importe
 * a su implementación.
 */
interface CartLineValidation
{
    /**
     * Veredicto de la línea candidata frente a la cesta que el cliente ya tiene.
     *
     * ⚠️ **La línea candidata NO va dentro de `$cart`.** La cesta es lo que ya está retenido y sirve
     * para descontar cupo; meter la candidata la haría competir consigo misma y devolvería un cupo
     * menor del real. Es el mismo reparto que hace la compra web, donde la selección en curso
     * todavía no se ha guardado.
     *
     * @param  array<mixed>  $cart  cesta en bruto del cliente; se sanea aquí
     * @param  array<mixed>  $line  línea candidata, en la misma forma que una línea de la cesta
     */
    public function validate(array $cart, array $line): CartLineVerdict;
}
