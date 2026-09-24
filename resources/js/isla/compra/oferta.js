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
 * donde cabe una. Una fila que falla se queda sin días —se pinta apagada— en vez de tumbar la pantalla.
 *
 * @param {{api: {get: Function}, ids: number[]}} deps
 * @returns {Promise<Record<number, Array<{date: string, price_cents: number, rate_key: string}>>>}
 */
export async function cargarDiasDeFilas({ api, ids }) {
    const unicos = [...new Set(Array.isArray(ids) ? ids : [])];
    const respuestas = await Promise.all(unicos.map((id) => api.get(`/availability/${id}/dates`)));

    return Object.fromEntries(unicos.map((id, i) => [id, respuestas[i].ok ? (respuestas[i].data?.data ?? []) : []]));
}

/**
 * El complemento POR CANTIDAD de una entrada (los calcetines, en PlayJump): opcional, que se suma por unidades, ni
 * por invitado ni de un grupo de elección. Se reconoce por su FORMA y no por su nombre (`CE-4`): otra instalación
 * lo llamará de otra manera. Con varios, el primero; sin ninguno, `null`, y la pantalla no pregunta.
 *
 * @param {{addons?: Array<object>}|null} producto  la ficha (`GET /catalog/products/{id}`)
 * @returns {{id: number, price_cents: number, max_quantity: number|null}|null}
 */
export function calcetinDe(producto) {
    const a = (producto?.addons ?? []).find((x) => x.allow_extra === true && ! x.per_guest && ! x.choice_group
        && ! x.mandatory && ! x.included && ! x.requires_addon_id);

    return a ? { id: a.id, price_cents: a.price_cents, max_quantity: a.max_quantity ?? null } : null;
}

/** El borrador de la pantalla 0 en blanco: sin zona, una persona y sin calcetines (`startCuando` del diseño). */
export const borradorVacio = () => ({ modo: 'nuevo', zona: null, elegirZona: false, dia: null, hora: null, fila: null, n: 1, cal: 0, otra: null });

/**
 * **La intención de la landing → el borrador con el que abre la pantalla 0** (`intent.js` del cajón, en isla).
 *
 *  · `{ type: 'product', id }` de una ENTRADA: su zona, con esa fila ya elegida;
 *  · `{ type: 'zone', slug }`: esa zona, con su primera fila;
 *  · sin intención, o con una que la isla aún no sabe abrir (los packs de cumpleaños, T3e·5) o que no casa con el
 *    catálogo: «Para hoy», eligiendo zona. Nunca inventa una fila: sale del listado del servidor.
 *
 * @param {{type?: string, id?: number, slug?: string}|null} intencion
 * @param {Array<object>} productos  el catálogo tal cual
 */
export function borradorDeIntencion(intencion, productos) {
    const entradas = (Array.isArray(productos) ? productos : []).filter((p) => p?.type === 'entry');
    const producto = intencion?.type === 'product' ? entradas.find((p) => p.id === intencion.id) : null;
    const zona = producto?.zone?.slug ?? (intencion?.type === 'zone' ? intencion.slug : null);
    const fila = producto ?? entradas.find((p) => p.zone?.slug === zona) ?? null;

    return fila ? { ...borradorVacio(), zona: fila.zone.slug, fila: fila.id } : { ...borradorVacio(), elegirZona: true };
}

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
