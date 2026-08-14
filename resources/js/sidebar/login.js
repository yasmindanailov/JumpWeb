/**
 * El LOGIN embebido del cajón (Fase 4 · paso 4.4a·2).
 *
 * El paso 5 de la web monta `<livewire:auth.login :embedded="true">` dentro del cajón; el motor SPA
 * habla con `POST /api/v1/auth/login`, que desde Fase 3 · paso 3b consume **el mismo**
 * `Identity\Services\PasswordLogin` — así que los dos limitadores de `SEC-06`, la comprobación de
 * credenciales y el sello de última entrada son literalmente el mismo código. Aquí no se comprueba
 * nada de eso: se envía, y se traduce el «no».
 *
 * ⚠️ **El texto del «no» sale del DICCIONARIO, no del `message` del sobre, y eso está MEDIDO.** Los
 * dos motores dicen hoy cosas distintas para el mismo rechazo:
 *  - web (`auth.failed`): «Estas credenciales no coinciden con nuestros registros.»
 *  - API (`invalid_credentials`): «El correo o la contraseña no son correctos.»
 *  - web (`auth.throttle`): «Demasiados intentos. Inténtalo de nuevo en 59 segundos.»
 *  - API (`too_many_requests`): «Has hecho demasiadas peticiones seguidas…» + `params.retry_after`
 *
 * Pintar el `message` sería cambiar la copia del cajón sin que ningún gate lo viera —el diff de árbol
 * descarta los nodos de texto—. Ramificar sobre el CÓDIGO y pintar el literal es además lo que el
 * contrato de la API pide de un cliente: los códigos son estables, los mensajes son para humanos que
 * no tienen diccionario.
 *
 * ⚠️ **Y el reparto de los dos avisos tampoco es cosmético** (hallazgo L-02 de la auditoría del
 * origen): el del limitador va al banner `_global` y el de credenciales **bajo el campo email**. Si el
 * del limitador cayera bajo el input se mezclaría con «credenciales incorrectas», que es genérico a
 * propósito para no revelar si el correo existe.
 */

import { t, tp } from './i18n.js';

/** Los códigos del sobre que este formulario sabe distinguir. El resto cae en el aviso genérico. */
export const INVALID_CREDENTIALS = 'invalid_credentials';
export const TOO_MANY_REQUESTS = 'too_many_requests';
export const VALIDATION_FAILED = 'validation_failed';

/**
 * Lo que el formulario enseña tras un intento fallido.
 *
 * @typedef {{global: string, fields: {email?: string, password?: string}}} LoginErrors
 */

/** Sin errores. Se devuelve siempre la misma forma para que la vista no compruebe si algo existe. */
function clean() {
    return { global: '', fields: {} };
}

/**
 * El «no» del servidor traducido a lo que pinta el formulario. Espejo de `Auth\Login::login()`.
 *
 * @param {{ok: boolean, status: number, data: any, error: object|null}} response
 * @param {{messages: object, auth: object}} texts
 * @returns {LoginErrors}
 */
export function loginErrors(response, { messages = {}, auth = {} } = {}) {
    if (response?.ok) {
        return clean();
    }

    const code = response?.error?.code ?? null;

    // La validación la escribe el servidor con las MISMAS reglas que el componente Livewire
    // (`required|string|email`), así que sus textos ya coinciden: se pintan tal cual. Reescribirlos
    // aquí sería una segunda traducción de las reglas de Laravel.
    if (code === VALIDATION_FAILED) {
        const fields = response?.error?.fields ?? {};

        return {
            global: '',
            fields: {
                ...(firstOf(fields.email) ? { email: firstOf(fields.email) } : {}),
                ...(firstOf(fields.password) ? { password: firstOf(fields.password) } : {}),
            },
        };
    }

    // ⚠️ Bajo el campo, no en el banner, y con el literal de la web: es un mensaje genérico a
    // propósito —no revela si el correo existe— y compartirlo es lo que mantiene la copia igual.
    if (code === INVALID_CREDENTIALS) {
        return { global: '', fields: { email: t(auth, 'failed') } };
    }

    // ⚠️ El limitador va al banner y lleva los segundos que publica el sobre. `retry_after` es el
    // MISMO número que el componente Livewire interpola en `:seconds`.
    if (code === TOO_MANY_REQUESTS) {
        return {
            global: tp(auth, 'throttle', { seconds: response?.error?.params?.retry_after ?? 0 }),
            fields: {},
        };
    }

    // Un corte de red, un 5xx o un código que este formulario no conoce. No es un estado que el
    // componente Livewire pueda tener —allí el fallo tumba la petición entera—, así que no hay
    // paridad que respetar: se usa el genérico del cajón, cuyo consejo (esperar y reintentar) es el
    // correcto para los tres.
    return { global: t(messages, 'errors.try_later'), fields: {} };
}

/** El primer mensaje de un campo, que es lo que pinta `@error` en el Blade. */
function firstOf(value) {
    if (typeof value === 'string') return value;

    return Array.isArray(value) && typeof value[0] === 'string' ? value[0] : '';
}

/**
 * Envía las credenciales y devuelve qué pasó.
 *
 * `api` entra por parámetro, como en `admission.js` y por lo mismo: así la secuencia se prueba en
 * Node sin red ni navegador (`CE-6`).
 *
 * ⚠️ **La respuesta se devuelve entera**, no solo el `id`: quien llama la pasa por la misma tubería de
 * identidad que `GET /me` —`POST auth/login` devuelve el perfil con la misma forma, justo para que
 * identificarse no cueste una petición más—.
 *
 * @param {{
 *   credentials: {email: string, password: string, remember: boolean},
 *   api: {post: (path: string, body: object) => Promise<object>},
 *   messages: object,
 *   auth: object,
 * }} deps
 * @returns {Promise<{ok: boolean, response: object, errors: LoginErrors}>}
 */
export async function runLogin({ credentials, api, messages = {}, auth = {} }) {
    const response = await api.post('/auth/login', {
        email: credentials?.email ?? '',
        password: credentials?.password ?? '',
        remember: credentials?.remember === true,
    });

    return { ok: response.ok === true, response, errors: loginErrors(response, { messages, auth }) };
}
