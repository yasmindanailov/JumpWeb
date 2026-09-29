/**
 * **LA DEMANDA SIN HUECO EN LA ISLA** (`DECISIONES #758`; `docs/specs/isla-y-landing-nueva.md` §4.26): lo que el cajón
 * mide al abrir un producto (`sidebar/missing.js`), con SUS funciones y su regla —cada mes sin un solo día desde el en
 * curso sale como `availability_missing {product, month}`, una vez por producto y mes en la visita, y solo si la oferta
 * LLEGÓ—.
 *
 * ⚠️ **Un reportero por PÁGINA, no por app**: la calculadora de la página y la compra son dos apps de Vue, y «Reservar y
 * pagar» pasa de una a otra con el mismo producto. Con uno cada una, esa visita contaría dos veces: el cuadro cuenta
 * FILAS (`OccupancyReport::missing()`). Las entradas de la isla son un mismo build y Rollup no duplica un módulo entre
 * trozos, así que este módulo —y con él su reportero— existe una sola vez en la página.
 * ⚠️ **Se informa al MIRAR un producto, no al cargar sus días**: las calculadoras piden los de todas sus filas al
 * acercarse su pieza, y eso es pasar por la página. Llama aquí quien sabe que el cliente lo mira.
 */
import { createMissingReporter } from '../../sidebar/missing.js';

let dePagina = null;

/**
 * La fila que el cliente MIRA, si su oferta llegó (`llegaron`, de `oferta.js::cargarDiasDeFilas`). El tracker es
 * `JumpWeb.track`, que publica el cajón; sin él no pasa nada.
 *
 * @param {{id: number|string|null|undefined, dias?: Array<{date: string}>, llegaron?: Array<number|string>}} fila
 * @param {Function} [reportar]  un reportero de `createMissingReporter`; por defecto, el de la página
 * @returns {string[]}  los meses que emitió
 */
export function informarDemanda({ id, dias, llegaron }, reportar) {
    if (id == null || ! (llegaron ?? []).some((x) => String(x) === String(id))) return [];
    const r = reportar ?? (dePagina ??= createMissingReporter((name, props) => globalThis.window?.JumpWeb?.track?.(name, props)));

    return r(id, dias ?? []);
}
