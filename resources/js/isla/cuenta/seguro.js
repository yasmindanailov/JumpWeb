/**
 * **Los datos de cada bloque de Mi cuenta, protegidos** (T5f de `docs/specs/isla-y-landing-nueva.md` §4.13; la regla del
 * diseño: «Cada bloque va protegido: si uno falla, deja su hueco y el resto sigue»).
 *
 * ⚠️ Proteger solo la PINTURA (`BloqueSeguro.vue`) no bastaba: lo que pinta cada bloque se compone ANTES, en el `computed`
 * del inicio (`useSeccionCuenta.js`), y una reserva con un dato que no se puede leer (una fecha rota: `Intl` lanza) tumbaba
 * ese `computed` entero —y con él Mi cuenta—. Aquí cada bloque se compone por su cuenta: el que revienta sale ROTO.
 */

/** Lo que devuelve un bloque cuyos datos no se pudieron componer: `BloqueSeguro` lo pinta como su hueco. */
export const ROTO = Object.freeze({ roto: true });

/**
 * Compone lo de UN bloque; si revienta, lo apunta y devuelve `roto` (por defecto, `ROTO`).
 *
 * @template T
 * @param {string} nombre  el bloque, para la consola
 * @param {() => T} compone
 * @param {{roto?: any, avisar?: (nombre: string, error: unknown) => void}} [opciones]
 * @returns {T|any}
 */
export function protegido(nombre, compone, { roto = ROTO, avisar = (n, error) => globalThis.console?.error?.('Mi cuenta · bloque', n, error?.message) } = {}) {
    try {
        return compone();
    } catch (error) {
        avisar(nombre, error);

        return roto;
    }
}

/** ¿Salió roto? */
export const estaRoto = (x) => x === ROTO;
