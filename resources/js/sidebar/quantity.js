import { clampQuantity } from './offer.js';

/**
 * **La CANTIDAD del paso 3, y el precio que se enseña con ella** (`#327`,
 * `docs/specs/precio-por-tramo.md`).
 *
 * Módulo PLANO, sin Vue y sin red (`CE-6`): aquí vive el acotado y de dónde sale el precio unitario;
 * el `.vue` pinta y los stores guardan.
 *
 * ## Por qué existe
 *
 * La cantidad dejó de ser «pulsar `+` unas cuantas veces». Con el precio por tramo (`#324`) un
 * producto puede exigir **30 unidades como mínimo y admitir 100**, y con solo `+`/`−` eso son treinta
 * pulsaciones antes de poder comprar y cien para llenar el grupo. El campo pasa a escribirse, y en
 * cuanto se escribe aparecen los casos que un `+` nunca produce: vacío, letras, decimales, 500.
 *
 * ## ⚠️⚠️ El precio: se PINTA el del servidor, no se calcula
 *
 * Hasta `#327` el paso enseñaba el precio del CALENDARIO, que no conoce la cantidad. Con los tramos
 * ese número pasó a ser el más barato del día («desde 12 €»), así que un grupo de 30 leía 12 € y se
 * le cobraban 15: **lo mostrado dejaba de ser lo cobrado**, que es justo el defecto que los tramos
 * existen para evitar.
 *
 * ▶ La respuesta correcta **ya venía en el payload**: `POST catalog/products/{id}/addons` —que el
 * cajón llama en CADA cambio de cantidad— tarifica la línea con `CartPricing`, la MISMA
 * implementación que cobra el checkout, y publica `line.unit_price_cents`. Ni petición nueva ni
 * resolver el tramo aquí (`PAY-12`, `CE-4`): eso sería una segunda implementación de una regla de
 * dinero en JavaScript.
 */

/**
 * El precio por unidad a ENSEÑAR mientras se elige la cantidad.
 *
 * El respaldo al precio del día se conserva para el instante en que aún no hay línea tarificada
 * —antes de elegir hora—, que es cuando el precio del día es la única respuesta honesta que existe.
 *
 * @param  {?{unit_price_cents?: number}} line       `line` del endpoint de complementos
 * @param  {?number} dayPriceCents                   el precio del día, de la oferta de fechas
 * @return {?number}
 */
export function unitPriceToShow(line, dayPriceCents) {
    return line?.unit_price_cents ?? dayPriceCents ?? null;
}

/**
 * La cantidad que el cajón debe adoptar tras un cambio, venga de `+`/`−` o del campo escrito.
 *
 * ⚠️ **Los dos caminos comparten acotado a propósito.** Tener la regla dos veces es cómo un camino
 * acaba admitiendo lo que el otro rechaza: `+` no puede pasar del techo y teclear tampoco.
 *
 * Devuelve `null` cuando no hay nada que hacer —el valor no cambia—, y así el llamante sabe que
 * puede ahorrarse la consulta de complementos.
 *
 * @param  {{raw?: unknown, delta?: number}} change  lo tecleado, o el paso de `+`/`−`
 * @param  {{floor:number, ceiling:number, current:number}} range
 * @return {?number} la cantidad nueva, o `null` si no cambia
 */
export function nextQuantity(change, range) {
    const target = 'delta' in change ? range.current + change.delta : clampQuantity(change.raw, range);

    // Un `+`/`−` fuera de rango NO se pega al extremo: el botón ya sale deshabilitado en ese borde,
    // así que si llega es un estado que no debería existir y lo correcto es no moverse. Lo tecleado
    // sí se pega, porque ahí el cliente expresó una intención («todos los que quepan»).
    if ('delta' in change && (target < range.floor || target > range.ceiling)) return null;

    return target === range.current ? null : target;
}
