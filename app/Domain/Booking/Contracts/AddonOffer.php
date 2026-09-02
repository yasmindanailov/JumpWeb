<?php

namespace App\Domain\Booking\Contracts;

/**
 * Los complementos de un producto RESUELTOS contra la selección del cliente (Fase 4 · paso 4.0b·5,
 * `docs/specs/sidebar-spa.md` §4.4.1, hueco 5).
 *
 * **Qué le falta a la API sin esto.** `GET catalog/products/{id}` publica la CONFIGURACIÓN de cada
 * enganche —`is_included`, `is_mandatory`, `per_guest`, `choice_group`, `requires_addon_id`…— y con
 * ella un cliente no puede pintar la pantalla: tendría que aplicar por su cuenta la partición en
 * grupos excluyentes, los tres textos de nota, la etiqueta, las unidades gratis, los topes, la poda
 * **en cadena** de las dependencias y la regla de que **un complemento de pago sin tarifa ese día no
 * se ofrece**. Reimplementar eso en el cliente es exactamente lo que `CE-4` prohíbe.
 *
 * **No inventa reglas: las pide.** Todo sale de `Booking\Services\AddonResolver`, que es la misma
 * autoridad que aplica `OrderCreator` al crear el pedido y la compra web al pintar el paso 3. Se
 * cita en prosa, y no con `{@see}`, porque una anotación resoluble haría que un contrato importara
 * un servicio.
 *
 * ⚠️ **No depende de la fecha de la línea, y eso está MEDIDO** (spec §4.4.0, punto 2): los cuatro
 * llamantes de producción —la compra web, el alta manual del panel, `OrderCreator` y la
 * tarificación— pasan **hoy**, no el día reservado. Aceptar una fecha aquí habría inventado una
 * divergencia que no existe: lo que decide es el PRODUCTO, el estado de la selección y la CANTIDAD.
 *
 * **Qué NO hace:**
 *  - **no tarifica la línea**: eso es {@see CartPricing}, y el endpoint que publica esto lo compone
 *    con él para que un clic no cueste dos peticiones — pero el cálculo del dinero sigue teniendo un
 *    solo dueño (`PAY-12` exige una sola fuente de CÁLCULO, no una sola URL);
 *  - **no reserva nada**.
 *
 * ⚠️ **«No comprueba aforo: un complemento no consume plazas» dejó de ser verdad con la HORA EXTRA**
 * (`specs/hora-extra.md` §4.5, `#410`): un complemento que OCUPA la franja siguiente a su línea sí
 * consume, y ofrecerlo a una hora donde no aterriza es ofrecer lo que el checkout rechaza — el primo
 * de `AFORO-02`. Por eso `resolve()` acepta la FECHA y la HORA de la línea: con ellas delante, un
 * ocupante que no aterriza (sin franja siguiente, cerrada o llena) **no se ofrece** —la misma regla
 * que el complemento de pago sin tarifa— y el que aterriza justo sale con su `max`/`canIncrease`
 * capados por las plazas reales y por «no se quedan más de los que entran». Sin fecha/hora, la
 * oferta no puede saberlo y pasa sin decorar: la autoridad sigue siendo `OrderCreator` bajo lock —
 * recalcular evita el rechazo, no lo sustituye.
 *
 * Implementación actual: `App\Domain\Booking\Services\AddonOfferReader` (bind en
 * `BookingServiceProvider`).
 */
interface AddonOffer
{
    /**
     * Los complementos del producto, resueltos contra la selección enviada.
     *
     * ⚠️ **Un grupo excluyente que no venga en `choices` se resuelve con su ELEGIDO POR DEFECTO** —el
     * miembro incluido, o el primero por orden—, que es lo que hace la compra web al elegir el
     * producto. Así, una primera llamada con la selección vacía devuelve ya el estado inicial
     * correcto en vez de una pantalla con todos los grupos sin elegir: esa configuración no existe
     * —un grupo siempre tiene uno activo— y su total engañaría. Es también la razón de que no haga
     * falta un segundo método para «dame los valores por defecto».
     *
     * Devuelve `null` si ese id no está en el catálogo —porque no existe, porque no se vende o
     * porque su zona no opera—. Las tres razones dan la misma respuesta a propósito, igual que en
     * `ProductCatalog::product()`: distinguirlas le contaría a un desconocido qué hay en la BD.
     *
     * Un producto **sin complementos** no es `null`: es una oferta vacía, que es una respuesta
     * legítima y distinta de «ese producto no existe».
     *
     * @param  int  $productId  el producto BASE (una entrada o un pack, nunca un complemento)
     * @param  int  $quantity  cantidad de la línea — los invitados de un pack. Decide la cantidad de
     *                         los complementos por-invitado, así que cambiarla cambia el importe
     * @param  array<int, int>  $quantities  complemento → cantidad pedida, para los de cantidad libre
     * @param  array<string, int>  $choices  grupo excluyente → complemento elegido. **Va aparte de
     *                                       las cantidades a propósito**: dentro de un grupo lo que
     *                                       selecciona es ser el elegido, no tener cantidad, y con
     *                                       una lista plana dos miembros marcados serían ambiguos
     * @param  string|null  $date  fecha de la línea (`Y-m-d`) — SOLO para la hora extra (ver arriba)
     * @param  string|null  $time  hora de la línea (`H:i:s` o `H:i`) — ídem
     */
    public function resolve(int $productId, int $quantity, array $quantities = [], array $choices = [], ?string $date = null, ?string $time = null): ?ResolvedAddons;
}
