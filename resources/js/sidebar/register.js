/**
 * El REGISTRO embebido del cajón (Fase 4 · paso 4.4b·1).
 *
 * El paso 5 de la web monta `<livewire:auth.register :embedded="true">`; el motor SPA habla con
 * `POST /api/v1/auth/register` declarando **el contexto que le pase quien llama** —`purchase` en el
 * embudo, `standalone` en el área de cliente—, que es lo que el servidor traduce en política
 * (`DECISIONES #31`). Las cuatro capas de defensa del alta —honeypot, límite por IP, límite por correo
 * y anti-bot— viven en `Identity\Services\SelfSignup`, el mismo servicio que usa el modal de la web.
 *
 * ⚠️ **La respuesta NO dice si se creó la cuenta, y es a propósito.** `POST auth/register` devuelve
 * **201 sin cuerpo siempre**: el primer borrador del contrato devolvía el perfil cuando había cuenta y
 * nada cuando se fingía, y así **un bot distinguía las dos respuestas de un vistazo** y el señuelo
 * dejaba de servir para lo único que sirve. La consecuencia para este cliente es que después del 201
 * hay que **preguntar `GET /me`**: si hay sesión, la cuenta existe y la compra sigue; si no la hay, el
 * señuelo actuó y toca la pantalla de «revisa tu correo», exactamente como hace la web.
 *
 * ⚠️ **Y el aviso del limitador va bajo el campo EMAIL, no en el banner** —al revés que en el login—.
 * No es un descuido de la web: `Register::reportSignup()` lanza `auth.throttle` en la clave `email`
 * mientras `Login` la lanza en `_global`. Se transcribe como está.
 */

import { t, tp } from './i18n.js';

/**
 * **Los DOS contextos de alta, y cuál se manda NO puede quedar quemado aquí** (`DECISIONES #31`,
 * `specs/auth-en-cajon.md` §4.3).
 *
 * · `purchase` → **pay-first**: el servidor NO manda correo de verificación y **abre sesión**, porque
 *   el pago sustituye a la verificación —un bot no paga—. Es el del paso 5 del embudo.
 * · `standalone` → el alta suelta: manda el correo de verificación y **no** abre sesión.
 *
 * ⚠️⚠️ **Hasta el 2026-08-23 este módulo mandaba `purchase` QUEMADO**, porque su único cliente era el
 * embudo. Al traer el alta al área de cliente, reutilizar el módulo «tal cual» habría convertido el
 * alta suelta en pay-first: **sin correo de verificación y con sesión abierta**, un cambio de política
 * que nadie decidió y que ninguna pantalla delata. Lo destapó la revisión de la spec, no un test.
 */
export const CONTEXT_PURCHASE = 'purchase';
export const CONTEXT_STANDALONE = 'standalone';

/**
 * ¿El alta de esta instalación exige captcha? Se lee de `GET /config`.
 *
 * ⚠️ **No nulo ⟺ el anti-bot está ACTIVO**, y esa equivalencia costó un arreglo del contrato: el
 * endpoint publicaba la clave pública aunque faltara la secreta, estado en el que la web **no pinta el
 * widget** y el servidor no verifica ningún token.
 *
 * Desde 4.4b·2 este bit ya NO decide «si delegar en el modal de Livewire», sino **si montar el widget
 * en el propio cajón**. La razón por la que sigue leyéndose de aquí y no de la clave cruda es la misma
 * de arriba: solo cuando el anti-bot está COMPLETO tiene sentido pintar el widget. Con la clave a
 * medias se pintaría un formulario que **rechazaría a todo el mundo** con «no eres un robot», sin
 * correo y sin una sola línea de log —`Turnstile::verify('')` corta antes del POST y antes de su
 * `Log::warning`—, que es exactamente el fallo que esta guarda existe para evitar.
 */
export function signupRequiresCaptcha(config) {
    const key = config?.turnstile_site_key;

    return typeof key === 'string' && key !== '';
}

export const VALIDATION_FAILED = 'validation_failed';
export const TOO_MANY_REQUESTS = 'too_many_requests';

/**
 * Lo que el formulario enseña tras un intento fallido.
 *
 * `summary` es la lista del banner: la web pinta **todos** los mensajes juntos arriba **y** cada uno
 * bajo su campo. Las dos cosas, no una — el banner deja ver el conjunto de un formulario largo y los
 * de debajo permiten corregir uno a uno (A11y).
 *
 * @typedef {{summary: string[], fields: Record<string, string>}} RegisterErrors
 */

/** El orden en que la web lista los avisos del banner: el de las reglas de validación. */
const FIELD_ORDER = ['name', 'email', 'phone', 'password', 'accept_privacy', 'accept_terms', 'waiver_document_id', 'marketing'];

function clean() {
    return { summary: [], fields: {} };
}

/**
 * El «no» del servidor traducido a lo que pinta el formulario. Espejo de `Register::reportSignup()`.
 *
 * ⚠️ **Los literales de negocio los manda el SERVIDOR y aquí se pintan tal cual**, al revés que en el
 * login. Y es correcto en los dos sitios: el registro publica en `fields.email` exactamente
 * `account.register.already_exists`, `exists_unverified` o `bot_check_failed` —los mismos que pinta el
 * Blade—, mientras que el login publica un `message` propio que NO coincide con `auth.failed`.
 * ⚠️ Lo comprobaba campo a campo `SidebarRegisterParityTest`, que se retiró con el modal
 * (`DECISIONES #122`); hoy los literales los fija `Api\V1\AuthRegistrationTest` desde el servidor.
 *
 * @param {{ok: boolean, status: number, data: any, error: object|null}} response
 * @param {{messages: object, auth: object}} texts
 * @returns {RegisterErrors}
 */
export function registerErrors(response, { messages = {}, auth = {} } = {}) {
    if (response?.ok) {
        return clean();
    }

    const code = response?.error?.code ?? null;

    if (code === VALIDATION_FAILED) {
        const fields = {};

        for (const [key, value] of Object.entries(response?.error?.fields ?? {})) {
            const message = firstOf(value);

            if (message !== '') fields[key] = message;
        }

        return { summary: summaryOf(fields), fields };
    }

    // ⚠️ Bajo el campo EMAIL, no en el banner: es donde lo pone `Register`, y difiere del login a
    // propósito. El banner lo lista igual, porque la web lista todo lo que hay en el bag.
    if (code === TOO_MANY_REQUESTS) {
        const fields = { email: tp(auth, 'throttle', { seconds: response?.error?.params?.retry_after ?? 0 }) };

        return { summary: summaryOf(fields), fields };
    }

    // Corte de red, 5xx o un código que este formulario no conoce. No es un estado que el componente
    // Livewire pueda tener, así que no hay paridad que respetar: el genérico del cajón, cuyo consejo
    // —esperar y reintentar— vale para los tres. Va solo al banner: no es culpa de ningún campo.
    return { summary: [t(messages, 'errors.try_later')], fields: {} };
}

/**
 * La lista del banner, en el ORDEN de las reglas.
 *
 * ⚠️ El orden importa para el diff de árbol tanto como el número: son `<li>` hermanos, y el
 * normalizador cuenta nodos. `Object.entries` conserva el orden de inserción del JSON, que es el del
 * servidor, pero fijarlo aquí lo hace independiente de cómo serialice el sobre.
 */
function summaryOf(fields) {
    const known = FIELD_ORDER.filter((key) => fields[key]).map((key) => fields[key]);
    const rest = Object.keys(fields).filter((key) => ! FIELD_ORDER.includes(key)).map((key) => fields[key]);

    return [...known, ...rest];
}

function firstOf(value) {
    if (typeof value === 'string') return value;

    return Array.isArray(value) && typeof value[0] === 'string' ? value[0] : '';
}

/**
 * Envía el alta y averigua **si de verdad hubo cuenta**.
 *
 * Dos peticiones y la segunda no es opcional: el 201 es idéntico para un alta real y para un señuelo
 * que actuó, así que `GET /me` es la única forma de saber a qué pantalla ir. La web lo sabe sin
 * preguntar porque tiene el veredicto del dominio en la mano; este cliente no puede tenerlo sin
 * romper el señuelo para todo el mundo.
 *
 * ⚠️ **`context` por defecto es `standalone`, y el default se eligió por SEGURIDAD, no por gusto.**
 * Es el mismo que aplica el servidor cuando el campo no viaja, y es el conservador: quien olvide
 * pasarlo en el embudo verá al cliente parado en «revisa tu correo» —visible, y se arregla—, mientras
 * que el default contrario habría saltado la verificación de correo **en silencio**.
 *
 * ⚠️ **La casilla del waiver viaja con el `id` del texto que el servidor SIRVIÓ, o no viaja**
 * (Fase 6, `specs/waiver-probatorio.md` §4.4). `waiver` es el documento de `GET /legal/waiver` que el
 * formulario tiene en memoria; sin él, la casilla no se pinta y aquí se manda desmarcada. Aceptar sin
 * decir qué texto se leyó no prueba nada, y el servidor lo rechaza.
 *
 * @param {{
 *   form: object,
 *   api: {post: Function, get: Function},
 *   messages: object,
 *   auth: object,
 *   context: string,
 *   waiver: {id: number}|null,
 * }} deps
 * @returns {Promise<{ok: boolean, identified: boolean, me: object|null, errors: RegisterErrors}>}
 */
export async function runRegister({ form, api, messages = {}, auth = {}, context = CONTEXT_STANDALONE, waiver = null }) {
    const acceptWaiver = form?.accept_waiver === true && Number.isInteger(waiver?.id);

    const response = await api.post('/auth/register', {
        name: form?.name ?? '',
        email: form?.email ?? '',
        phone: form?.phone ?? '',
        password: form?.password ?? '',
        marketing: form?.marketing === true,
        accept_privacy: form?.accept_privacy === true,
        accept_terms: form?.accept_terms === true,
        accept_waiver: acceptWaiver,
        waiver_document_id: acceptWaiver ? waiver.id : null,
        context,
        // El señuelo viaja igual que en la web: un cliente legítimo lo deja vacío.
        website: form?.website ?? '',
        // ⚠️ Cadena vacía, NUNCA `null`: `openapi/v1.yaml` declara `turnstile_token` como
        // `type: string` sin `nullable` y `RegisterRequest` es `additionalProperties: false`, así que
        // Spectator valida también la PETICIÓN y un `null` haría fallar por esquema, no por lógica.
        turnstile_token: form?.turnstile_token ?? '',
    });

    if (! response.ok) {
        return { ok: false, identified: false, me: null, errors: registerErrors(response, { messages, auth }) };
    }

    const me = await api.get('/me');

    return { ok: true, identified: me.ok === true, me, errors: clean() };
}
