/**
 * **LO QUE LA PANTALLA 0 DE LA ISLA PIDE AL MOTOR** (T3e de `docs/specs/isla-y-landing-nueva.md` §4.10).
 *
 * La pantalla 0 es un FORMULARIO, no un embudo: el tiempo (el producto) se cambia después de elegir la hora, y cada
 * fila enseña su precio del día. Por eso pide los días de TODAS las filas de la zona, y no solo de la elegida como
 * hace el calendario del cajón. Las secuencias con red viven aquí, con `api` por parámetro (`CE-6`, el patrón de
 * `admission.js`), y lo que decide sin red, también: se prueba con `node --test`.
 */

/**
 * Los días que se venden de cada fila, EN PARALELO: son peticiones independientes, y en cadena sumarían esperas
 * donde cabe una. Una fila que falla se queda sin días —se pinta apagada— en vez de tumbar la pantalla, y FUERA de
 * `llegaron`: es lo que separa «no hay días» de «no se supo» (la demanda sin hueco, `demanda.js`: una red caída no es
 * un mes lleno).
 *
 * @param {{api: {get: Function}, ids: number[]}} deps
 * @returns {Promise<{dias: Record<number, Array<{date: string, price_cents: number, rate_key: string}>>, llegaron: number[]}>}
 */
export async function cargarDiasDeFilas({ api, ids }) {
    const unicos = [...new Set(Array.isArray(ids) ? ids : [])];
    const respuestas = await Promise.all(unicos.map((id) => api.get(`/availability/${id}/dates`)));

    return {
        dias: Object.fromEntries(unicos.map((id, i) => [id, respuestas[i].ok ? (respuestas[i].data?.data ?? []) : []])),
        llegaron: unicos.filter((id, i) => respuestas[i].ok),
    };
}

/**
 * El complemento POR CANTIDAD de una entrada o de una fiesta (los calcetines, en PlayJump): opcional, que se suma por
 * unidades, ni por invitado ni de un grupo de elección. Se reconoce por su FORMA y no por su nombre (`CE-4`): otra
 * instalación lo llamará de otra manera. Con varios, el primero; sin ninguno, `null`, y la pantalla no pregunta.
 * ⚠️ Con TOPE 1 no se suma nada: es un sí o un no (la hora extra, que tiene la misma forma salvo el tope) y lo pinta la
 * lista de complementos (`complementos.js`). Antes ganaba el primero por `position`: con la hora extra delante en el
 * panel, la isla preguntaba cuántos «pares» de hora extra (`#880`, medido en la ficha de la 101).
 *
 * @param {{addons?: Array<object>}|null} producto  la ficha (`GET /catalog/products/{id}`)
 * @returns {{id: number, price_cents: number, max_quantity: number|null}|null}
 */
export function calcetinDe(producto) {
    const a = (producto?.addons ?? []).find((x) => x.allow_extra === true && ! x.per_guest && ! x.choice_group
        && ! x.mandatory && ! x.included && ! x.requires_addon_id && x.max_quantity !== 1);

    return a ? { id: a.id, price_cents: a.price_cents, max_quantity: a.max_quantity ?? null } : null;
}

// El borrador de la intención con la que se abre la compra (`borradorDeIntencion` y los suyos) vive en `intencion.js`:
// solo lo usa la compra, y aquí viajaba con las calculadoras de la página (T6b·3a).

/**
 * Las FICHAS de varios productos (los packs de una fiesta: sus tramos de edad, mínimos y campos), EN PARALELO. Una que
 * falla se queda fuera en vez de tumbar la pantalla.
 *
 * @returns {Promise<Record<number, object>>}
 */
export async function cargarFichas({ api, ids }) {
    const unicos = [...new Set(Array.isArray(ids) ? ids : [])];
    const respuestas = await Promise.all(unicos.map((id) => api.get(`/catalog/products/${id}`)));

    return Object.fromEntries(unicos.flatMap((id, i) => (respuestas[i].ok ? [[id, respuestas[i].data]] : [])));
}

// Los grupos de elección (el menú) resueltos ANTES de tener hora viajan con los sueltos en UNA llamada, y solo los usa la
// compra: `complementos.js::cargarSinHora` (`#880`).

/** El primer día que se vende de una fila, o `null`. Es el día con el que la pantalla 0 nace elegida. */
export function primerDia(dias) {
    return Array.isArray(dias) && dias.length > 0 ? dias[0].date : null;
}

/**
 * La hora del motor (`HH:MM:SS`) que corresponde a la que eligió el selector (`HH:MM`), entre las ofrecidas.
 * ⚠️ El dominio compara franjas por CADENA (`cart.js`): una hora sin segundos no casa con ninguna.
 */
export function horaDelMotor(ofrecidas, corta) {
    return (Array.isArray(ofrecidas) ? ofrecidas : []).find((h) => typeof h.time === 'string' && h.time.slice(0, 5) === corta)?.time ?? null;
}

/** ¿Sigue cabiendo esa hora con esa gente? Si no, se vacía: el precio y la hora nunca cambian a escondidas. */
export function horaQueCabe(ofrecidas, hora, gente) {
    return (Array.isArray(ofrecidas) ? ofrecidas : []).some((h) => h.time === hora && h.sellable !== false && Number(h.available ?? 0) >= gente);
}
