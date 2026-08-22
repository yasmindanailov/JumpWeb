/**
 * **RECUPERAR LA CONTRASEÑA desde el cajón** (`docs/specs/auth-en-cajon.md` §4.2).
 *
 * Es la única de las tres pantallas de auth que el cajón **no sabía hacer**: entrar y darse de alta
 * viven aquí desde 4.4a·2 y 4.4b·1, y recuperar seguía solo en el modal de la cabecera. El servidor no
 * pone ningún obstáculo: `POST /api/v1/auth/password/forgot` existe desde Fase 3 · paso 3c y consume
 * **el mismo** `Identity\Services\PasswordRecovery` que el modal, así que el limitador por IP, la
 * rotación del `remember_token` y la no-enumeración son literalmente el mismo código. Aquí no se
 * comprueba nada de eso: se envía, y se traduce el «no».
 *
 * ⚠️⚠️ **LA REGLA CENTRAL ES QUE NO HAY NADA QUE DECIDIR, y eso es `SEC-06`.** El endpoint responde
 * **202 exista o no la cuenta** —«aceptado, ya veremos», que es literalmente lo que ocurre—, así que
 * este módulo **no tiene sobre qué ramificar** y la pantalla de «revisa tu correo» es la misma en
 * todos los casos. No es una simplificación: es la propiedad. El día que alguien añada aquí un `if`
 * que distinga —un mensaje distinto, un estado distinto, un tiempo de espera distinto— habrá
 * construido el oráculo de enumeración que el servidor se cuida de no dar.
 * ▶ Por eso el ÚNICO desenlace que se distingue es el **429**, que no dice nada de si la cuenta
 * existe: dice que quien pregunta lo ha hecho demasiadas veces.
 *
 * ⚠️ **Y el aviso del limitador va bajo el campo EMAIL, no en el banner** —como en el alta y al revés
 * que en el login—. No es un descuido: `Auth\ForgotPassword::sendLink()` lanza `auth.throttle` en la
 * clave `email`, mientras `Auth\Login` la lanza en `_global`. Se transcribe como está, porque lo que
 * este trabajo mueve es DÓNDE vive la pantalla, no qué dice.
 */

import { t, tp, firstMessage } from './i18n.js';

/** Los códigos del sobre que este formulario sabe distinguir. El resto cae en el aviso genérico. */
export const VALIDATION_FAILED = 'validation_failed';
export const TOO_MANY_REQUESTS = 'too_many_requests';

/**
 * Lo que el formulario enseña tras un intento fallido.
 *
 * Misma forma que la del login —`{global, fields}`— a propósito: son dos formularios de un campo y
 * medio en la misma sección, y darles formas distintas obligaría a cada componente a recordar cuál le
 * toca.
 *
 * @typedef {{global: string, fields: {email?: string}}} ForgotErrors
 */

/** Sin errores. Se devuelve siempre la misma forma para que la vista no compruebe si algo existe. */
function clean() {
    return { global: '', fields: {} };
}

/**
 * El «no» del servidor traducido a lo que pinta el formulario. Espejo de `ForgotPassword::sendLink()`.
 *
 * @param {{ok: boolean, status: number, data: any, error: object|null}} response
 * @param {{messages: object, auth: object}} texts
 * @returns {ForgotErrors}
 */
export function forgotErrors(response, { messages = {}, auth = {} } = {}) {
    if (response?.ok) {
        return clean();
    }

    const code = response?.error?.code ?? null;

    // La validación la escribe el servidor con las MISMAS reglas que el componente Livewire
    // (`required|string|email`), así que sus textos ya coinciden: se pintan tal cual. Reescribirlos
    // aquí sería una segunda traducción de las reglas de Laravel.
    if (code === VALIDATION_FAILED) {
        const message = firstMessage(response?.error?.fields?.email);

        return { global: '', fields: message ? { email: message } : {} };
    }

    // ⚠️ Bajo el campo, no en el banner: es donde lo pone `ForgotPassword`. Y lleva los segundos que
    // publica el sobre, que son el MISMO número que el componente interpola en `:seconds`.
    if (code === TOO_MANY_REQUESTS) {
        return {
            global: '',
            fields: { email: tp(auth, 'throttle', { seconds: response?.error?.params?.retry_after ?? 0 }) },
        };
    }

    // Un corte de red, un 5xx o un código que este formulario no conoce. No es un estado que el
    // componente Livewire pueda tener —allí el fallo tumba la petición entera—, así que no hay
    // paridad que respetar: se usa el genérico del cajón, cuyo consejo (esperar y reintentar) es el
    // correcto para los tres.
    return { global: t(messages, 'errors.try_later'), fields: {} };
}

/**
 * Pide el enlace de recuperación y devuelve qué pasó.
 *
 * `api` entra por parámetro, como en `login.js` y por lo mismo: así la secuencia se prueba en Node sin
 * red ni navegador (`CE-6`).
 *
 * ⚠️ **`sent` es exactamente `ok`, y no un cálculo sobre la respuesta.** Se devuelve con nombre propio
 * porque es lo que la pantalla pregunta, pero **no puede llegar a ser otra cosa**: cualquier lógica que
 * lo derive de algo más que «el servidor aceptó» es un sitio donde meter, sin querer, una diferencia
 * observable entre una cuenta que existe y una que no.
 *
 * @param {{
 *   email: string,
 *   api: {post: (path: string, body: object) => Promise<object>},
 *   messages: object,
 *   auth: object,
 * }} deps
 * @returns {Promise<{ok: boolean, sent: boolean, response: object, errors: ForgotErrors}>}
 */
export async function runForgot({ email, api, messages = {}, auth = {} }) {
    const response = await api.post('/auth/password/forgot', { email: email ?? '' });
    const ok = response.ok === true;

    return { ok, sent: ok, response, errors: forgotErrors(response, { messages, auth }) };
}
