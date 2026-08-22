/**
 * **De la respuesta de una gestión de credenciales a lo que la pantalla enseña**
 * (`specs/area-cliente.md` §9, tanda 2 · paso 6b).
 *
 * Las dos gestiones —cambiar la contraseña y cerrar las demás sesiones— responden con la misma
 * forma: `204` si salió, `422` con errores **por campo**, `429` con su espera, `401` si la sesión
 * caducó por el camino. Traducir eso es composición, no pintado: vive aquí, con `node --test`.
 *
 * ⚠️ **Ninguna regla vive en el cliente** (`CE-4`): si la contraseña es correcta, si la nueva cumple
 * la política y cuántos intentos quedan lo decide el SERVIDOR. Aquí solo se coloca lo que dijo.
 */

import { firstMessage, t, tp } from '../i18n.js';

/** Lo que la pantalla necesita saber tras intentar una gestión. */
function outcome({ ok = false, fields = {}, notice = '', expired = false } = {}) {
    return { ok, fields, notice, expired };
}

/**
 * @param {{ok: boolean, status: number, error: object|null, offline: boolean}} response
 * @param {{messages: object, auth: object}} ctx  los diccionarios del montaje
 */
export function credentialOutcome(response, { messages = {}, auth = {} } = {}) {
    if (response?.ok) return outcome({ ok: true });

    // ⚠️ **La red caída va PRIMERO y no se mezcla con un rechazo del servidor.** `api.js` los separa
    // a propósito —el primero se reintenta, el segundo se enseña—, y fundirlos aquí le diría al
    // cliente «revisa los datos» cuando lo que pasa es que no hay conexión.
    // ⚠️ `errors.try_later` y NO una clave nueva: es la que el cajón ya usa para red, 5xx y códigos
    // desconocidos (`login.js`), y su consejo —esperar y reintentar— vale para los tres. Inventar
    // `errors.network` habría añadido una clave en tres idiomas para decir lo mismo… y la primera
    // versión de este módulo la usó **sin que existiera**, así que el aviso salía VACÍO: `t()`
    // devuelve cadena vacía cuando falta una clave, y nada avisa.
    if (response?.offline) return outcome({ notice: t(messages, 'errors.try_later') });

    // La sesión se perdió con el cajón abierto: no es un dato que corregir, es volver a entrar.
    if (response?.status === 401) return outcome({ expired: true });

    if (response?.status === 429) {
        // El servidor dice cuántos segundos faltan; el texto es el MISMO que usa el login para lo
        // mismo (`auth.throttle`), y no una segunda redacción de la misma idea.
        const seconds = Number(response?.error?.params?.retry_after ?? 0);

        return outcome({ notice: tp(auth, 'throttle', { seconds }) });
    }

    const fields = response?.error?.fields;

    if (fields && typeof fields === 'object' && ! Array.isArray(fields)) {
        return outcome({ fields });
    }

    // Cualquier otra cosa: se enseña lo que el servidor dijo, no una traducción inventada.
    return outcome({ notice: String(response?.error?.message ?? '') });
}

/** El error de un campo concreto, listo para pintar bajo su input. */
export function fieldError(fields, name) {
    return firstMessage(fields?.[name]);
}
