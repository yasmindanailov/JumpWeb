/**
 * La CESTA del cajón (Fase 4 · paso 4.3·2).
 *
 * Módulo plano, sin Vue y **sin tocar el almacén del navegador**: la persistencia en `localStorage`
 * llega en 4.3·3 y se le pasará el almacén por parámetro. Aquí no puede haber ni un `localStorage`
 * global, y no es purismo: en el Node del contenedor —el que corre `npm run test:js` y el
 * renderizador SSR del gate— `typeof localStorage === 'undefined'` (medido), así que un módulo que lo
 * leyera del global no se podría probar.
 *
 * ⚠️ **Aquí no se decide nada de negocio** (`CE-4`). Si una línea cabe, con cuántas unidades entra y
 * con cuál se funde lo dice `POST /cart/validate-line`; qué vale la cesta lo dice
 * `POST /orders/quote`. Este módulo aplica esas respuestas a una lista.
 *
 * **La línea se guarda con el vocabulario de la API** (`product_id`, `quantity`) y no con el del
 * dominio (`ticket_type_id`, `qty`): la cesta viaja a tres endpoints con esa forma —disponibilidad,
 * validación y presupuesto—, y guardarla de otra manera obligaría a traducir en cada viaje.
 */

/**
 * Una línea de la cesta, en la forma que aceptan `availability/{id}/times`, `cart/validate-line`,
 * `orders/quote` y `POST orders`.
 *
 * ⚠️ **La hora se guarda canónica (`HH:MM:SS`)**, no como se pinta. El dominio compara franjas por
 * CADENA, así que una hora sin segundos no casa con ninguna y falla EN SILENCIO: presupuesto sin
 * líneas en vez de error.
 *
 * @typedef {{product_id: number, date: string, time: string, quantity: number, event_data: object, addons: Array<{product_id: number, quantity: number}>}} CartLine
 */

/**
 * Añade una línea, o la funde con la que el servidor haya señalado.
 *
 * ⚠️ **La fusión NO se decide aquí**: `merges_with_index` viene en el veredicto y solo ocurre entre
 * entradas del mismo producto, día y hora **sin complementos** —un pack es siempre su propio bloque,
 * porque fundirlo mezclaría dos fiestas y cambiaría señal y ocupación—. Un cliente que la reinventara
 * crearía una línea duplicada donde el servidor habría sumado cantidades.
 *
 * ⚠️ Y la cantidad que entra es la **efectiva** del veredicto, no la que se pidió: el servidor recorta
 * al cupo en silencio desde siempre (anti-manipulación), y pintar la pedida enseña una reserva que no
 * se tiene.
 *
 * @param {CartLine[]} cart
 * @param {CartLine} line
 * @param {{quantity: number, merges_with_index: number|null}} verdict
 * @returns {CartLine[]} una cesta NUEVA
 */
export function addLine(cart, line, verdict) {
    const quantity = verdict.quantity;
    const target = verdict.merges_with_index;

    if (target !== null && target !== undefined && cart[target] !== undefined) {
        return cart.map((existing, index) => (
            index === target ? { ...existing, quantity: existing.quantity + quantity } : existing
        ));
    }

    return [...cart, { ...line, quantity }];
}

/**
 * Quita la línea que ocupa esa posición.
 *
 * ⚠️ **El índice es el de la CESTA, no el ordinal de lo pintado.** El presupuesto salta las líneas
 * cuyo producto dejó de venderse y conserva el índice original, así que la segunda línea que se ve
 * puede ser la número 3. Quitar por ordinal borra otra reserva.
 *
 * @param {CartLine[]} cart
 * @param {number} index
 * @returns {CartLine[]}
 */
export function removeLine(cart, index) {
    return cart.filter((_, position) => position !== index);
}

/**
 * La cesta en la forma que viaja a la API.
 *
 * `event_data` viaja —el servidor lo necesita para crear el pedido y lo sanea él— pero **no vuelve
 * nunca** en ninguna respuesta: son datos personales de un menor y los endpoints son públicos.
 *
 * @param {CartLine[]} cart
 * @returns {Array<object>}
 */
export function toApiItems(cart) {
    return cart.map((line) => ({
        product_id: line.product_id,
        date: line.date,
        time: line.time,
        quantity: line.quantity,
        ...(Object.keys(line.event_data ?? {}).length > 0 ? { event_data: line.event_data } : {}),
        ...(line.addons?.length > 0 ? { addons: line.addons } : {}),
    }));
}

/**
 * Las filas que el carrito PINTA, emparejando cada línea tarificada con las respuestas del pack que
 * el cliente tiene en memoria.
 *
 * ⚠️ **El emparejamiento es por `index`, no por posición en el array.** Una línea cuyo producto ya no
 * se vende **no se tarifica** y desaparece de la respuesta; el hueco en la secuencia de `index` es la
 * única señal de que existió. Recorrer las dos listas en paralelo pinta los precios de una línea
 * sobre otra.
 *
 * Las respuestas del evento se emparejan con su etiqueta aquí porque el presupuesto **no las
 * devuelve** (RGPD: son datos de un menor y el endpoint es público) y las etiquetas están en
 * `GET catalog/products/{id}`. El emparejado es el mismo que hace `TicketType::eventAnswers()`: orden
 * del ESQUEMA y fuera las vacías.
 *
 * @param {Array<object>} quoteLines  `lines` de `POST /orders/quote`
 * @param {CartLine[]} cart
 * @param {Record<number, Array<{key: string, label: string}>>} fieldsByProduct
 * @returns {Array<object>}
 */
export function cartRows(quoteLines, cart, fieldsByProduct = {}) {
    return quoteLines.map((line) => ({
        ...line,
        event: eventAnswers(fieldsByProduct[line.product_id] ?? [], cart[line.index]?.event_data ?? {}),
    }));
}

/**
 * Respuestas del evento emparejadas con su etiqueta, en el orden del esquema y sin las vacías.
 *
 * Espejo de `TicketType::eventAnswers()`, que **no sanea**: sanear es del servidor y ocurre al crear
 * el pedido. Lo que este emparejado sí respeta es su criterio de «vacío», que es el que decide qué
 * fila se pinta.
 *
 * @param {Array<{key: string, label: string}>} fields
 * @param {Record<string, unknown>} answers
 * @returns {Array<{key: string, label: string, value: string}>}
 */
export function eventAnswers(fields, answers) {
    return fields
        .map((field) => ({ key: field.key, label: field.label, value: answers?.[field.key] }))
        .filter((row) => row.value !== null && row.value !== undefined && typeof row.value !== 'object' && String(row.value) !== '')
        .map((row) => ({ ...row, value: String(row.value) }));
}
