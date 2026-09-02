/**
 * **Completar un alta que viene de Google** (`docs/specs/auth-con-google.md` §7, tanda T2).
 *
 * Módulo PLANO, sin Vue (`CE-6`): se prueba entero con `node --test`, y por eso la pantalla puede ser
 * un componente de veinte líneas.
 *
 * ⚠️⚠️ **Aquí NO viajan ni el correo ni el `sub`, y no es un olvido**: los pone el servidor desde la
 * sesión donde los dejó el retorno de Google (§6.3·3). Si viajaran por el navegador, cualquiera
 * crearía una cuenta con la identidad verificada de otro — y a esa cuenta se le firma un descargo
 * probatorio. Lo único que se manda es lo que Google no sabe.
 *
 * ⚠️ **Los avisos del servidor se pintan con `registerErrors()`**, el mismo traductor que el alta con
 * contraseña: los dos formularios reciben el mismo `422` con `error.fields`, y dos traductores para
 * una respuesta acabarían diciendo cosas distintas del mismo «no».
 */

import { registerErrors } from '../register.js';

/** El código que el servidor devuelve cuando el texto del descargo ya no es el vigente. */
export const WAIVER_STALE = 'waiver_document_stale';

/**
 * Lo que devuelve un envío.
 *
 * `expired` es un desenlace, no un error: significa que **ya no hay nada que completar** —la sesión
 * caducó, el alta se completó en otra pestaña, o se llegó a esta pantalla sin volver de Google— y la
 * única salida es empezar otra vez. Se distingue de un fallo porque lo que hay que enseñar es otra
 * cosa: un botón, no un aviso bajo un campo.
 *
 * @typedef {{ok: boolean, expired: boolean, stale: boolean, errors: {summary: string[], fields: Record<string, string>}}} GoogleSignupOutcome
 */

function clean() {
    return { summary: [], fields: {} };
}

/**
 * Pide el perfil que espera. Devuelve `null` cuando no hay ninguno —incluida la instalación sin
 * Google, donde el endpoint responde 404—, que es exactamente el caso «vuelve a empezar».
 *
 * @param {{api: {get: Function}}} deps
 * @returns {Promise<{name: string, email: string}|null>}
 */
export async function loadGooglePending({ api }) {
    const response = await api.get('/auth/google/pending');

    if (! response?.ok) {
        return null;
    }

    const email = response.data?.email;

    // Sin correo no hay pantalla que pintar: es el dato que la identifica y el único que no se puede
    // teclear. Un cuerpo raro se trata como «no hay nada», no como un perfil a medias.
    return typeof email === 'string' && email !== ''
        ? { name: typeof response.data?.name === 'string' ? response.data.name : '', email }
        : null;
}

/**
 * Envía el alta.
 *
 * ⚠️ **La casilla del descargo viaja con el `id` del texto que el servidor SIRVIÓ, o no viaja**
 * (Fase 6, `specs/waiver-probatorio.md` §4.4): `waiver` es el documento que el store tiene en
 * memoria. Aceptar sin decir qué texto se leyó no prueba nada, y el servidor lo rechaza.
 *
 * @param {{
 *   form: object,
 *   api: {post: Function},
 *   waiver: {id: number}|null,
 *   messages: object,
 *   auth: object,
 * }} deps
 * @returns {Promise<GoogleSignupOutcome>}
 */
export async function runGoogleSignup({ form, api, waiver = null, messages = {}, auth = {} }) {
    const acceptWaiver = form?.accept_waiver === true && Number.isInteger(waiver?.id);

    const response = await api.post('/auth/google/complete', {
        // ⚠️ **Solo el nombre y el descargo, desde la T8·c** (§21.4.3): el teléfono y las condiciones
        // los pide el checkout. `GoogleSignupRequest` es `additionalProperties: false`, así que
        // mandarlos aquí sería un 422 por ESQUEMA, no por lógica.
        name: form?.name ?? '',
        accept_waiver: acceptWaiver,
        waiver_document_id: acceptWaiver ? waiver.id : null,
    });

    if (response?.ok) {
        return { ok: true, expired: false, stale: false, errors: clean() };
    }

    // 404 = no hay perfil esperando. Ver el `typedef`: es un desenlace con su propia pantalla.
    if (response?.status === 404) {
        return { ok: false, expired: true, stale: false, errors: clean() };
    }

    // El texto se republicó mientras rellenaba. Quien llama tiene que RELEERLO y desmarcar la
    // casilla: lo que se leyó ya no es lo que se firmaría (la lección de `#175` y CAJ-2).
    if (response?.error?.code === WAIVER_STALE) {
        return { ok: false, expired: false, stale: true, errors: clean() };
    }

    return { ok: false, expired: false, stale: false, errors: registerErrors(response, { messages, auth }) };
}

/**
 * **El estado de la pantalla, y su SECUENCIA** — que vive aquí y no en el componente por la misma
 * razón que la del alta suelta vive en el store (`DECISIONES #120(r)`): cuando el techo de
 * componentes aprieta, la pregunta es qué sobra ahí. Lo que sobra es siempre la secuencia —qué se
 * pide, qué se guarda, qué queda en pantalla—, y aquí además se prueba con `node --test` en vez de
 * montando un componente.
 *
 * Cuatro campos y ninguno es de adorno: quién espera, si ya no hay nada que completar, si hay una
 * petición en vuelo y qué avisos enseñar.
 *
 * @typedef {{pending: {name: string, email: string}|null, expired: boolean, busy: boolean, errors: {summary: string[], fields: Record<string, string>}}} GoogleScreenState
 */

/** @returns {GoogleScreenState} */
export function emptyGoogleScreen() {
    return { pending: null, expired: false, busy: false, errors: clean() };
}

/**
 * El estado con el que se pinta la pantalla al abrirla.
 *
 * ⚠️ **Sin perfil, `expired`**: no es un fallo que se avise bajo un campo, es que no hay nada que
 * completar y la única salida es empezar otra vez.
 *
 * @param {{api: {get: Function}}} deps
 * @returns {Promise<GoogleScreenState>}
 */
export async function loadGoogleScreen({ api }) {
    const pending = await loadGooglePending({ api });

    return { ...emptyGoogleScreen(), pending, expired: pending === null };
}

/**
 * Envía y devuelve **el estado siguiente y el desenlace**, en ese orden de importancia: la pantalla
 * pinta lo primero y decide con lo segundo (releer el descargo, navegar a la cuenta).
 *
 * ⚠️ `busy` vuelve a `false` pase lo que pase — también cuando la petición falla—: un botón que se
 * queda deshabilitado para siempre es la forma más silenciosa de romper una pantalla.
 *
 * @param {{form: object, api: {post: Function}, waiver: {id: number}|null, messages: object, auth: object, state: GoogleScreenState}} deps
 * @returns {Promise<{state: GoogleScreenState, result: GoogleSignupOutcome}>}
 */
export async function submitGoogleScreen({ state, form, api, waiver = null, messages = {}, auth = {} }) {
    const result = await runGoogleSignup({ form, api, waiver, messages, auth });

    return {
        result,
        state: {
            pending: result.expired ? null : state.pending,
            expired: result.expired,
            busy: false,
            errors: result.errors,
        },
    };
}
