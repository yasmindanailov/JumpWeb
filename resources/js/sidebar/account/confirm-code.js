/**
 * **Pedir el código que CONFIRMA una acción de Mi cuenta** (A4b de `docs/specs/acceso-con-codigo.md` §4.11, `#813`):
 * cerrar las otras sesiones, desvincular Google, cambiar el correo y borrar la cuenta ya no piden la contraseña, sino un
 * código al correo de la cuenta (`POST /me/confirm-code {action}`, A2a). Aquí, la petición y lo que la pieza enseña con su
 * respuesta; el estado, en `stores/confirm.js`.
 *
 * ⚠️⚠️ **El tope (`429`) ENSEÑA el campo igual**, como la isla (`isla/cuenta/useAjustesCuenta.js::pedirCodigo`): el límite es
 * de uno por minuto por cuenta y el código de confirmar no va atado a la acción (el correo dice para qué es), así que el
 * último enviado sirve. Bajo el campo, cuánto esperar para pedir otro, con el texto de la puerta (`#812`).
 *
 * Módulo PLANO, sin Vue ni Pinia (`CE-6`): `node --test` lo ejerce con un `api` de mentira.
 */

import { t, tp } from '../i18n.js';
import { formOutcome } from './form-outcome.js';

/** Las cuatro acciones que el servidor confirma con un código (`AccountCredentials::CONFIRM_ACTIONS`). */
export const CONFIRM_ACTIONS = Object.freeze({
    CLOSE_SESSIONS: 'close_sessions',
    UNLINK_GOOGLE: 'unlink_google',
    CHANGE_EMAIL: 'change_email',
    DELETE_ACCOUNT: 'delete_account',
});

/**
 * De la respuesta a lo que se enseña: si salió (`sent`), si el campo está a la vista (`shown`), el «no» bajo el código
 * (`error`), el de arriba (`notice`) y si la sesión caducó por el camino (`expired`).
 *
 * @param {{ok: boolean, status: number, error: ?object, offline: boolean}} response
 * @param {{account?: object, messages?: object, auth?: object}} texts  los diccionarios del montaje
 */
export function confirmCodeOutcome(response, { account = {}, messages = {}, auth = {} } = {}) {
    const base = { sent: false, shown: false, error: '', notice: '', expired: false };

    if (response?.ok) return { ...base, sent: true, shown: true };

    if (response?.status === 429) {
        return { ...base, shown: true, error: tp(account, 'login.code_wait', { n: response?.error?.params?.retry_after ?? 60 }) };
    }

    const outcome = formOutcome(response, { messages, auth });

    // Un «no» sin texto (un 422 de la acción, que esta pantalla no manda mal) no puede quedar mudo: el genérico.
    return { ...base, expired: outcome.expired, notice: outcome.expired ? '' : outcome.notice || t(messages, 'errors.try_later') };
}

/** Pide el código de `action` y devuelve lo que enseñar ({@link confirmCodeOutcome}). */
export async function requestConfirmCode(action, { api, ...texts }) {
    return confirmCodeOutcome(await api.post('/me/confirm-code', { action }), texts);
}
