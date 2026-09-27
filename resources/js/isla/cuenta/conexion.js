/**
 * **SIN CONEXIÓN EN MI CUENTA, sin estado** (T5f de `docs/specs/isla-y-landing-nueva.md` §4.13; el diseño,
 * `paginas/mi-cuenta/cuenta.jsx`: su `enLinea` y su `intentar`, y la regla «Sin conexión, se avisa arriba y lo que guarda
 * deja reintentar»).
 *
 * ⚠️ `navigator.onLine === false` es la única señal FIABLE de que no hay red; `true` no garantiza que la haya (una wifi
 * sin salida). Ese caso lo dice después el cliente de la API (`offline` en su resultado) con el «inténtalo más tarde» de
 * cada formulario del motor: aquí solo se evita INTENTAR lo que se sabe que no va a salir.
 */

/** ¿Se sabe que no hay red? */
export const sinRed = (nav = globalThis.navigator) => nav?.onLine === false;

/**
 * Envuelve lo que GUARDA: sin red, no lo intenta y deja el fallo con su reintento —que vuelve a pasar por aquí, así que
 * reintentar sin red vuelve a decirlo—; con red, retira el fallo de antes y lo hace.
 *
 * @param {(...args: any[]) => any} hace
 * @param {{fallar: (reintento: () => any) => void, limpiar: () => void, nav?: {onLine?: boolean}}} deps
 * @returns {(...args: any[]) => any}
 */
export function intentar(hace, { fallar, limpiar, nav }) {
    const vez = (...args) => {
        if (sinRed(nav ?? globalThis.navigator)) {
            fallar(() => vez(...args));

            return undefined;
        }
        limpiar();

        return hace(...args);
    };

    return vez;
}
