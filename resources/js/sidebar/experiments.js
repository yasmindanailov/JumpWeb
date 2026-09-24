/**
 * **Los experimentos en el motor** (`docs/specs/analitica.md` §4.4, T5a).
 *
 * La asignación la hace el SERVIDOR (la cookie del visitante es `HttpOnly`) y llega en el arranque como
 * `boot.experiments`: `{ <clave>: <variante> }`, solo con experimentos vivos. Aquí no se reparte nada: se LEE la
 * variante y se CUENTA la exposición —`experiment_exposed`— una sola vez por carga y solo cuando la variante ha
 * pintado algo de verdad. Una pantalla que cae en el valor por defecto porque el experimento no existe no es
 * una exposición: nadie le enseñó nada distinto.
 *
 * El hecho sale por `JumpWeb.track`, que antes de que llegue `track.js` es el buzón de `cajon/index.js`: se
 * resuelve en cada llamada, no al crear, para no capturar el stub.
 *
 * Todo por parámetro (`CE-6`): quien lo use pide `variant()` y avisa con `expose()`.
 */
export const EXPERIMENTOS = Symbol('experimentos');

export const CONTROL = 'control';

/**
 * La variante asignada a este visitante, o el valor por defecto si el experimento no está vivo.
 *
 * @param {{experiments?: Record<string, unknown>}|null|undefined} boot
 * @param {string} key
 * @param {string} [fallback]
 * @returns {string}
 */
export function variantOf(boot, key, fallback = CONTROL) {
    const variant = boot?.experiments?.[key];

    return typeof variant === 'string' && variant !== '' ? variant : fallback;
}

/**
 * @param {{boot: object|null|undefined, track?: (name: string, props: object) => void}} deps
 * @returns {{variant: (key: string, fallback?: string) => string, expose: (key: string) => boolean, exposed: () => string[]}}
 */
export function createExperiments({ boot, track }) {
    const exposed = new Set();

    return {
        variant: (key, fallback = CONTROL) => variantOf(boot, key, fallback),

        /** Cuenta la exposición UNA vez por carga; devuelve si la contó. Sin variante asignada, nada. */
        expose(key) {
            const variant = boot?.experiments?.[key];

            if (typeof variant !== 'string' || variant === '' || exposed.has(key)) return false;

            exposed.add(key);
            track?.('experiment_exposed', { key, variant });

            return true;
        },

        exposed: () => [...exposed],
    };
}
