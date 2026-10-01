/**
 * **ENTRAR EN EL CAJÓN CON UN CÓDIGO AL CORREO** (A4a de `docs/specs/acceso-con-codigo.md` §4.11, `#848`/`#849`): la
 * PUERTA (`POST /auth/code`), ENTRAR con el código (`POST /auth/login`) y sus «no», sin estado y con su `node --test`.
 * Sustituye al login con contraseña: ningún cliente la escribe ya (`#847`).
 *
 * ⚠️⚠️ **UNA puerta**: el correo decide. Con cuenta, el servidor manda YA el código (`next: code`) y se escribe en el mismo
 * sitio; nuevo, `next: register` y la pantalla del alta, sin código —el alta no espera a ningún correo: en la puerta del
 * parque hay cola (`#849`)—. Decir si un correo tiene cuenta lo acotan los límites del servidor (`SEC-06`), como el alta de
 * siempre (`#31`).
 *
 * ⚠️ **El tope del CORREO no es un «no»** (`429` con `params.next = code`): el servidor acaba de mandar uno a ese buzón, así
 * que se va a escribirlo sin aviso. El de la IP sí lo es, y va al banner con su espera.
 *
 * ⚠️ **El texto del «no» sale del DICCIONARIO, no del `message` del sobre, y eso está MEDIDO** (desde `#588`): ramificar
 * sobre el CÓDIGO del error y pintar el literal de `lang/` es lo que el contrato pide de un cliente —los códigos son
 * estables; los mensajes, para humanos sin diccionario— y lo único que el diff de árbol no vería cambiar. El código mal
 * escrito va BAJO SU CAMPO, con la frase de siempre: no dice si caducó, se gastó o no casa —a quien lo escribe le basta
 * «pide otro»— (la de la isla, que el owner ya vio).
 *
 * ⚠️ **`INVALID_CREDENTIALS` se exporta y NO cambia de nombre**: la isla lo importa (`isla/compra/acceso.js`).
 */

import { t, tp, firstMessage } from './i18n.js';

/** Los códigos del sobre que la puerta y el código saben distinguir. El resto cae en el aviso genérico. */
export const INVALID_CREDENTIALS = 'invalid_credentials';
export const TOO_MANY_REQUESTS = 'too_many_requests';
export const VALIDATION_FAILED = 'validation_failed';

/** Lo que responde la puerta: con cuenta, el código ya va de camino; nuevo, el alta. */
export const NEXT_CODE = 'code';
export const NEXT_REGISTER = 'register';

/**
 * Lo que el formulario enseña tras un intento fallido.
 *
 * @typedef {{global: string, fields: {email?: string, code?: string}}} DoorErrors
 */

/** Sin errores. Siempre la misma forma, para que la vista no compruebe si algo existe. */
function clean() {
    return { global: '', fields: {} };
}

/**
 * El «no» de la PUERTA o de ENTRAR CON EL CÓDIGO, en lo que pinta el formulario.
 *
 * @param {{ok: boolean, status: number, data: any, error: object|null}} response
 * @param {{messages: object, auth: object, account: object}} texts
 * @returns {DoorErrors}
 */
export function doorErrors(response, { messages = {}, auth = {}, account = {} } = {}) {
    if (response?.ok) {
        return clean();
    }

    const code = response?.error?.code ?? null;

    // La validación la escribe el servidor con sus reglas y sus nombres de campo: se pinta tal cual, bajo su campo.
    if (code === VALIDATION_FAILED) {
        const fields = response?.error?.fields ?? {};

        return {
            global: '',
            fields: {
                ...(firstMessage(fields.email) ? { email: firstMessage(fields.email) } : {}),
                ...(firstMessage(fields.code) ? { code: firstMessage(fields.code) } : {}),
            },
        };
    }

    // ⚠️ Bajo el CÓDIGO y con el literal del cajón: el código no vale (mal escrito, caducado o gastado, que no se distinguen).
    if (code === INVALID_CREDENTIALS) {
        return { global: '', fields: { code: t(account, 'login.code_wrong') } };
    }

    // El limitador de la IP (o el de entrar), al banner con los segundos que publica el sobre.
    if (code === TOO_MANY_REQUESTS) {
        return { global: tp(auth, 'throttle', { seconds: response?.error?.params?.retry_after ?? 0 }), fields: {} };
    }

    // Un corte de red, un 5xx o un código que no se conoce: el genérico del cajón (esperar y reintentar vale para todos).
    return { global: t(messages, 'errors.try_later'), fields: {} };
}

/**
 * LA PUERTA: ¿tiene cuenta este correo? Con cuenta, el servidor le manda ya el código.
 *
 * `api` entra por parámetro, como en `admission.js`: la secuencia se prueba en Node sin red (`CE-6`).
 *
 * @param {{email: string, api: {post: Function}, messages?: object, auth?: object, account?: object}} deps
 * @returns {Promise<{next: ?string, response: object, errors: DoorErrors}>} `next` es `code`, `register` o `null` (un «no»)
 */
export async function runDoor({ email, api, messages = {}, auth = {}, account = {} }) {
    const response = await api.post('/auth/code', { email: String(email ?? '').trim() });
    const next = response.ok ? response.data?.next : response.error?.params?.next;

    if (next === NEXT_CODE || next === NEXT_REGISTER) {
        return { next, response, errors: clean() };
    }

    // Un 200 que no dice a dónde ir no es un estado del contrato: sin el genérico, la pantalla se quedaría muda.
    const errors = response.ok ? { global: t(messages, 'errors.try_later'), fields: {} } : doorErrors(response, { messages, auth, account });

    return { next: null, response, errors };
}

/**
 * «PEDIR OTRO CÓDIGO», al mismo correo: la puerta otra vez. Siempre a mano, como en la isla (`#812`): no hay cuenta atrás.
 *
 * ⚠️⚠️ **Aquí el tope del correo SÍ es un «no»**, al revés que en la puerta: allí `429` con `next: code` quiere decir «hay uno
 * recién enviado, escríbelo»; aquí quiere decir que NO ha salido ninguno nuevo, y decir «te hemos enviado otro» mentiría.
 * Dice cuánto esperar, bajo el código (el servidor admite uno por minuto y correo, `EmailCodeLogin`).
 *
 * @returns {Promise<{next: ?string, sent: boolean, response: object, errors: DoorErrors}>} `sent`: salió un código nuevo
 */
export async function runResend({ email, api, messages = {}, auth = {}, account = {} }) {
    const door = await runDoor({ email, api, messages, auth, account });

    if (door.response.ok) {
        return { ...door, sent: door.next === NEXT_CODE };
    }

    if (door.response.error?.code === TOO_MANY_REQUESTS) {
        const seconds = door.response.error?.params?.retry_after ?? 60;

        return { next: null, sent: false, response: door.response, errors: { global: '', fields: { code: tp(account, 'login.code_wait', { n: seconds }) } } };
    }

    return { ...door, next: null, sent: false };
}

/**
 * ENTRAR con el código: el servidor abre la sesión y devuelve el perfil con la forma de `GET /me` (quien llama lo pasa por
 * la misma tubería de identidad, sin una petición más). El dispositivo queda RECORDADO solo con `remember` —la casilla
 * «Mantener la sesión iniciada en este dispositivo», SIN marcar de serie (`#858`)—: una cookie de autenticación
 * persistente la pide quien la quiere.
 *
 * @param {{email: string, code: string, remember?: boolean, api: {post: Function}, messages?: object, auth?: object, account?: object}} deps
 * @returns {Promise<{ok: boolean, response: object, errors: DoorErrors}>}
 */
export async function runCodeLogin({ email, code, remember = false, api, messages = {}, auth = {}, account = {} }) {
    const response = await api.post('/auth/login', {
        email: String(email ?? '').trim(),
        code: String(code ?? '').trim(),
        remember: remember === true,
    });

    return { ok: response.ok === true, response, errors: doorErrors(response, { messages, auth, account }) };
}
