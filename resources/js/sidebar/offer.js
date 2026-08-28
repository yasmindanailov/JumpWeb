/**
 * Lo que la OFERTA del servidor dice del paso 3: qué hora está elegida, cuántos se pueden pedir y a
 * qué precio va el día (Fase 4 · paso 4.7·2b·2·B, `DECISIONES #69`).
 *
 * **Por qué existe.** Estas derivaciones vivían sueltas dentro de `Sidebar.vue`, sin alcance para
 * `node --test` (`CE-6`) y sin que el diff de árbol las ejecutara —a Vue se le pasaban ya resueltas
 * desde el view-model de Livewire—. Son cuatro líneas cada una, y precisamente por eso nadie las
 * miraba: el paso 3 es el más denso del embudo y su selector de cantidad depende entero de ellas.
 *
 * ⚠️ **Aquí no se decide NADA de aforo** (`CE-4`). Qué horas hay, cuántas plazas quedan y cuántos
 * invitados admite una fiesta lo dice `SlotOffer` y llega por
 * `POST availability/{producto}/times`. Lo de aquí es leer esa respuesta.
 */

/**
 * La fila de la hora elegida dentro de la oferta, o `null` si esa hora ya no se ofrece.
 *
 * ⚠️ **El `null` no es defensivo, es un estado real**: entre que el cliente ve las horas y elige una,
 * otra persona puede haber llenado la franja. La siguiente petición traerá la lista sin ella.
 */
export function timeAt(offeredTimes, time) {
    const list = Array.isArray(offeredTimes) ? offeredTimes : [];

    return list.find((t) => t?.time === time) ?? null;
}

/**
 * El techo del selector de cantidad.
 *
 * ⚠️ **Es `max_quantity`, NO `available`, y no son el mismo número**: en un pack `available` son las
 * plazas libres de la franja y `max_quantity` cuántos invitados admite ESA fiesta, topado por el aforo
 * de invitados. Acotar con el primero dejaría pedir invitados que el checkout rechazaría (§10.nonies 46).
 *
 * Sin hora elegida —o con una que ya no se ofrece— el techo es 0, que es lo que deja el selector
 * inerte en vez de dejar pedir a ciegas.
 */
export function maxQuantityFor(offeredTimes, time) {
    return timeAt(offeredTimes, time)?.max_quantity ?? 0;
}

/**
 * El suelo del selector: el mínimo contratable.
 *
 * Un pack tiene mínimo de invitados (`min_quantity`, que publica la ficha del producto); una entrada
 * es siempre 1. Un `min_quantity` ausente cae a 1 — el mismo respaldo que aplica el servidor.
 */
export function minQuantityFor(product) {
    return product?.type === 'pack' ? (product?.min_quantity ?? 1) : 1;
}

/**
 * La cantidad con la que arranca el paso 3 al elegir hora.
 *
 * ⚠️ **Parece que diverge del servidor y NO diverge. Está medido, y conviene no «arreglarlo».**
 * `Purchase::selectTime()` escribe `qty = maxQty() >= min ? min : 0` y aquí se escribe
 * `min(minimo, techo)`. Con `0 < techo < minimo` darían cosas distintas (0 frente al techo)… pero ese
 * caso **no es alcanzable**: la oferta RETIRA la hora entera cuando el mínimo no cabe. Comprobado el
 * 2026-08-15 con un pack de mínimo 8 y aforo de invitados casi agotado:
 * · quedan 5 → los DOS motores ofrecen `[]`, así que no hay hora que elegir;
 * · quedan 8 → los dos ofrecen la hora con `max_quantity = 8`, y las dos fórmulas dan 8.
 * O sea: para toda hora ofrecida se cumple `techo ≥ mínimo`, y ahí las dos expresiones coinciden.
 * Cambiar una de las dos «para que se parezcan» sin reproducir eso sería tocar conducta a ciegas.
 */
export function initialQuantity(product, offeredTimes, time) {
    return Math.min(minQuantityFor(product), maxQuantityFor(offeredTimes, time));
}

/**
 * El precio del DÍA elegido, tal y como lo publica la oferta de días.
 *
 * ⚠️ **No se deriva del «desde» del catálogo**: aquel es el mínimo de todas las tarifas del producto y
 * este es el del día concreto, que puede ser el especial de un fin de semana. Publicarlos como si
 * fueran el mismo número es cómo se anuncia un precio que luego no se cobra.
 *
 * `null` es legítimo: «este producto no tiene tarifa ese día».
 */
export function dayPriceCents(offeredDates, date) {
    const list = Array.isArray(offeredDates) ? offeredDates : [];

    return list.find((d) => d?.date === date)?.price_cents ?? null;
}

/**
 * ¿Esta hora se anuncia como **casi llena**? (`DECISIONES #239`)
 *
 * ⚠️ **Espejo exacto de `AvailabilitySettings::isLow()`**, y por eso el umbral llega del servidor con
 * su operador escrito en el contrato (`available <= low_availability_max`, `GET /config`). Reinventar
 * aquí la comparación —o el número— es como divergen las dos superficies del mismo aviso.
 *
 * ⚠️ **`0` significa «no avisar», no «avisar cuando no queden plazas»**, y por eso se comprueba el
 * umbral ANTES de comparar: si el operador escribió cero pidió silencio. Sin sesión de configuración
 * —`/config` caído— el umbral se queda en 0 y ninguna hora se anuncia: inventar escasez que no se ha
 * podido leer es peor que no decir nada.
 *
 * ⚠️ **Lee `available`, NO `max_quantity`**: `available` son las plazas que le quedan a la FRANJA, que
 * es lo que «casi llena» significa. `max_quantity` es cuántas admite esta compra —en un pack son
 * números distintos (60 frente a 20, medido)— y anunciar con él diría «casi llena» de una franja
 * vacía en cuanto la fiesta llegara a su tope.
 */
export function isAlmostFull(offeredTime, lowMax) {
    const available = offeredTime?.available;

    return Number.isInteger(lowMax) && lowMax > 0
        && Number.isInteger(available) && available <= lowMax;
}
