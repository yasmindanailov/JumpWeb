/**
 * **El guardián común de los formularios del área de cliente**: limpia, llama, coloca el veredicto.
 *
 * ⚠️ **Nace en el paso 8 porque iba a ser la TERCERA copia.** `stores/credentials.js` y
 * `stores/profile.js` tenían cada uno su `run()` con el mismo cuerpo, y el borrado de cuenta pedía
 * otro igual. Es la sexta extracción de esta tanda por el mismo motivo —el rótulo de día, la política
 * de contraseñas, el campo de contraseña, `form-outcome.js` y la lista de idiomas fueron las cinco
 * anteriores—: **antes de la segunda copia, no después de la cuarta** (`DECISIONES #120(r)`).
 *
 * ⚠️ **Lo que de verdad protege que viva en un solo sitio** es el orden de las dos primeras líneas:
 * se limpia **ANTES** de llamar, no después. Si al reintentar quedara el error anterior en pantalla
 * mientras la petición está en vuelo, el cliente lo leería como el resultado del intento nuevo — y
 * eso ya dio un verde falso en el navegador (`DECISIONES #120(p)`: «mirar *hay un error* nunca
 * distingue el nuevo del viejo»). Una copia que limpiara al final no rompería ningún test de la otra.
 *
 * Módulo PLANO, sin Vue ni Pinia (`CE-6`): recibe el store como un objeto cualquiera, así que
 * `node --test` lo ejerce con un literal.
 */

import { formOutcome } from './form-outcome.js';

/**
 * El estado que TODO formulario del área necesita. Se derrama en el `state()` de cada store.
 *
 * Que sea una función y no un objeto no es estilo: un objeto compartido entre stores sería el mismo
 * `fields` para los tres, y limpiar uno limpiaría los otros.
 */
export const formState = () => ({
    /** ¿Hay una petición en vuelo? */
    busy: false,

    /** Errores por campo, tal cual los mandó el servidor. */
    fields: {},

    /** Aviso general: red caída, límite alcanzado o un fallo que no señala campo. */
    notice: '',

    /** La última gestión salió bien. Lo lee la pantalla para confirmar y limpiarse. */
    done: false,

    /** La sesión se perdió por el camino: no se reintenta, se vuelve a entrar. */
    expired: false,
});

/** Deja el estado como si nunca se hubiera intentado nada. */
export function resetForm(store) {
    Object.assign(store, formState());
}

/**
 * Corre una gestión y coloca su veredicto en el store. Devuelve si salió bien.
 *
 * @param {object} store  el store (o cualquier objeto con los campos de `formState()`)
 * @param {() => Promise<object>} call  la llamada a la API
 * @param {{messages?: object, auth?: object}} ctx  los diccionarios del montaje
 * @param {?(response: object) => Promise<void>|void} after  qué hacer con la respuesta si salió bien
 */
export async function runForm(store, call, ctx, after = null) {
    store.busy = true;
    store.fields = {};
    store.notice = '';
    store.done = false;
    store.expired = false;

    try {
        const response = await call();
        const outcome = formOutcome(response, ctx);

        store.fields = outcome.fields;
        store.notice = outcome.notice;
        store.expired = outcome.expired;
        store.done = outcome.ok;

        if (outcome.ok && after) await after(response);

        return outcome.ok;
    } finally {
        store.busy = false;
    }
}
