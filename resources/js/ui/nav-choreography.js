/**
 * **La coreografía del armazón: cuándo se retira y cuándo vuelve.**
 *
 * `docs/specs/armazon-y-menu.md` §4.4 (armazón · tanda 2c·2). Vive aquí y no dentro del
 * componente de Alpine por una razón práctica: **es lógica pura y así se puede probar**.
 * `app.js` registra componentes sobre el DOM y no lo cubre ningún test; una regla con tres
 * casos de borde metida ahí es una regla que nadie puede ejercitar.
 *
 * ⚠️ **No decide «visible/oculto» en cada evento de scroll: decide si hay INTENCIÓN.** Por
 * debajo del umbral, el temblor de un trackpad o el rebote elástico de iOS harían parpadear el
 * armazón sin que nadie haya pedido nada. Por eso devuelve `null` —«no me consta»— y el
 * llamante conserva el estado y su referencia anteriores.
 */

/** Píxeles de movimiento que hacen falta para creerse una intención. */
export const MOVEMENT_THRESHOLD = 8;

/** Por encima de esta altura el armazón no se retira nunca: es la primera pantalla. */
export const TOP_ZONE = 120;

/**
 * ¿Debe retirarse el armazón?
 *
 * @param {{y: number, previous: number, locked: boolean}} state
 *   `y` posición actual · `previous` la de la última decisión · `locked` hay un overlay abierto.
 * @returns {boolean|null} `true` retirar · `false` mostrar · `null` no hay intención, no tocar nada.
 */
export function shouldHideNav({ y, previous, locked = false }) {
    if (Math.abs(y - previous) < MOVEMENT_THRESHOLD) {
        return null;
    }

    // ⚠️ Con un overlay abierto NUNCA se retira, y no es cosmética: la hamburguesa es la forma
    // de cerrar el menú. Si se fuera con el scroll del propio menú, el visitante se quedaría
    // dentro sin salida visible — Escape seguiría funcionando, pero eso no es una salida que
    // nadie vea.
    if (locked) {
        return false;
    }

    return y - previous > 0 && y > TOP_ZONE;
}
