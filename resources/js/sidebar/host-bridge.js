/**
 * **El anfitrión del cajón, visto desde DENTRO del motor** (F4 · T2, `docs/specs/cajon-empaquetable.md` §4.2).
 *
 * El motor publica hacia fuera tres señales —el modo del panel, si se está identificando y si la sesión cambió
 * sin recargar— y hasta el 2026-09-18 las escribía nombrando a Alpine (`window.Alpine.store('purchase')`). Eso
 * ataba el motor a un framework que una página ajena no tiene por qué cargar. Ahora pregunta por el anfitrión:
 * `window.JumpWeb.cajon`, que es el controlador sin framework — y, cuando Alpine está, su proxy reactivo, de modo
 * que la carcasa de la landing del producto sigue reaccionando exactamente igual.
 *
 * ⚠️ Devuelve `null` sin lanzar cuando no hay anfitrión (un montaje raro, un test): las señales hacia fuera son
 * un extra, y que falten no puede tumbar el cajón.
 *
 * @param {Window} win  por parámetro (`CE-6`): es lo que deja probar a quien lo usa con `node --test`.
 * @returns {{setMode: Function, identifying: boolean, authChanged: boolean}|null}
 */
export function cajonHost(win = window) {
    return win?.JumpWeb?.cajon ?? null;
}
