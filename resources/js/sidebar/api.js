/**
 * El cliente HTTP del cajón contra `/api/v1` (Fase 4 · paso 4.1).
 *
 * Un `fetch` envuelto, no una dependencia: la API es de PRIMERA parte, mismo origen y modo SPA de
 * Sanctum, así que lo único que hace falta es no equivocarse en cuatro detalles. Los cuatro están
 * MEDIDOS en `docs/specs/api-v1.md` §10, y tres de ellos los encontró un `curl`, no la suite.
 *
 * 1. **`credentials: 'same-origin'`** — sin esto no viaja la cookie de sesión y `GET /me` responde
 *    401 aunque el cliente acabe de iniciar sesión.
 * 2. **`Accept: application/json`** — sin él Laravel negocia HTML y un 422 vuelve como página de
 *    error, no como el sobre que el contrato documenta.
 * 3. **CSRF por cabecera** (`X-XSRF-TOKEN`, leída de la cookie que pone `/sanctum/csrf-cookie`).
 *    ⚠️ La cookie viene **url-encoded**: mandarla sin decodificar da un 419 que parece «sesión
 *    caducada» y no lo es.
 * 4. **Un 419 se reintenta UNA vez** tras renovar el token. La sesión de una landing puede estar
 *    abierta horas, y ese caso es indistinguible de un fallo real para quien mira el cajón.
 *
 * ⚠️ **`Origin`/`Referer` no se ponen aquí y no es un olvido**: son cabeceras que el navegador
 * controla y prohíbe fijar por script. En mismo origen las manda solas —verificado en §1.1: la
 * `Referrer-Policy` del sitio manda `Referer` completo—, que es justo lo que `EnsureFrontendRequestsAreStateful`
 * necesita para tratar la petición como *stateful*.
 *
 * **No interpreta reglas de negocio.** Traduce HTTP a un resultado con forma fija; qué significa
 * cada `error.code` lo decide quien llama (`CE-4`).
 */

const BASE = '/api/v1';

/** Lee una cookie y la DECODIFICA. El valor de `XSRF-TOKEN` viaja url-encoded (trampa 3). */
function cookie(name) {
    const match = document.cookie.match(new RegExp('(?:^|; )' + name.replace(/([.$?*|{}()[\]\\/+^])/g, '\\$1') + '=([^;]*)'));

    return match ? decodeURIComponent(match[1]) : null;
}

/**
 * Pide la cookie de CSRF. Sanctum la emite en una ruta propia, fuera de `/api/v1`.
 *
 * Se llama sola cuando hace falta; no hay que acordarse de invocarla al arrancar, que es como se
 * olvida en el primer POST de una sesión recién abierta.
 */
async function refreshCsrf() {
    await fetch('/sanctum/csrf-cookie', {
        credentials: 'same-origin',
        headers: { Accept: 'application/json' },
    });
}

/**
 * Resultado de una llamada. **Forma fija pase lo que pase**, incluido un fallo de red: quien llama
 * no tiene que envolver nada en `try`, y un error de conexión no puede confundirse con un rechazo
 * del servidor —el primero se reintenta, el segundo se enseña—.
 *
 * @typedef {{ok: boolean, status: number, data: any, error: {code: string, message: string, fields?: object}|null, offline: boolean}} ApiResult
 */

/** @returns {ApiResult} */
function result(ok, status, data, error = null, offline = false) {
    return { ok, status, data, error, offline };
}

/**
 * Un fallo, además de devolverse, se le CUENTA a la página (`jw:api:failed`) para que la analítica lo mida
 * (`cajon/track.js` → `request_failed`, `docs/specs/analitica.md` §4.2) sin que este cliente sepa que existe.
 * Viaja la ruta SIN query, el estado y si fue la red: nunca el cuerpo ni el error.
 *
 * ⚠️ Guardado para el render en servidor (`scripts/render-sidebar.mjs`), donde no hay `document`.
 *
 * @returns {ApiResult}
 */
function failed(path, status, data, error = null, offline = false) {
    if (typeof document !== 'undefined') {
        document.dispatchEvent(new CustomEvent('jw:api:failed', { detail: { route: path.split('?')[0], status, offline } }));
    }

    return result(false, status, data, error, offline);
}

/**
 * Llama a la API.
 *
 * @param {string} path  ruta relativa a `/api/v1`, con barra inicial
 * @param {{method?: string, body?: object, signal?: AbortSignal, locale?: string}} options
 * @returns {Promise<ApiResult>}
 */
export async function request(path, { method = 'GET', body = null, signal = null, locale = null } = {}) {
    return call(path, { method, body, signal, locale }, true);
}

async function call(path, { method, body, signal, locale }, mayRetry) {
    const headers = {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    };

    if (body !== null) headers['Content-Type'] = 'application/json';

    // El idioma lo negocia el servidor, que además mira la SESIÓN primero (§10.ter): mandarlo es
    // una preferencia, no una orden, y por eso el cliente no traduce por su cuenta.
    if (locale) headers['Accept-Language'] = locale;

    // Solo los métodos que cambian algo necesitan CSRF; un GET con token de más no molesta, pero
    // pedir la cookie para leer el catálogo sí añadiría una petición a la primera visita.
    if (method !== 'GET' && method !== 'HEAD') {
        let token = cookie('XSRF-TOKEN');

        if (! token) {
            await refreshCsrf();
            token = cookie('XSRF-TOKEN');
        }

        if (token) headers['X-XSRF-TOKEN'] = token;
    }

    let response;

    try {
        response = await fetch(BASE + path, {
            method,
            headers,
            credentials: 'same-origin',
            signal,
            body: body === null ? undefined : JSON.stringify(body),
        });
    } catch (e) {
        // Abortar es una decisión de quien llama (cambió de pantalla), no un fallo que enseñar.
        if (e?.name === 'AbortError') throw e;

        return failed(path, 0, null, null, true);
    }

    // 419 = el token de CSRF caducó. Pasa de verdad: una landing puede quedarse abierta horas. Se
    // renueva y se repite UNA vez — repetir sin tope convertiría un fallo permanente en un bucle.
    if (response.status === 419 && mayRetry) {
        await refreshCsrf();

        return call(path, { method, body, signal, locale }, false);
    }

    // 204 y demás respuestas sin cuerpo: `json()` lanzaría sobre una cadena vacía.
    const text = await response.text();
    let payload = null;

    if (text !== '') {
        try {
            payload = JSON.parse(text);
        } catch {
            // Un cuerpo que no es JSON en una API que solo emite JSON significa que algo se
            // interpuso (una página de error, un portal cautivo). Se trata como fallo de red: no
            // hay `error.code` que enseñar.
            return failed(path, response.status, null, null, true);
        }
    }

    if (response.ok) return result(true, response.status, payload);

    // El sobre de error del contrato (spec §4.3). Si no viene con esa forma, se compone uno para
    // que quien llama nunca tenga que comprobar si `error` existe.
    const error = payload?.error ?? { code: 'unknown', message: '' };

    return failed(path, response.status, payload, error);
}

/** Atajos. Existen para que las llamadas se lean, no para esconder nada. */
export const api = {
    get: (path, options = {}) => request(path, { ...options, method: 'GET' }),
    post: (path, body, options = {}) => request(path, { ...options, method: 'POST', body }),
    put: (path, body, options = {}) => request(path, { ...options, method: 'PUT', body }),
    // ⚠️ `PATCH` y `DELETE` llegan con la tanda 2 (`specs/area-cliente.md` §9.3): el perfil se
    // actualiza por partes —de ahí `PATCH` y no `PUT`, que significaría «reemplaza el recurso
    // entero»— y el cambio de correo pendiente se descarta con `DELETE`. Pasan por el MISMO
    // `request()`, así que heredan las cuatro trampas ya resueltas: la cookie, el `Accept`, el CSRF
    // url-decodificado y el reintento del 419.
    patch: (path, body, options = {}) => request(path, { ...options, method: 'PATCH', body }),
    // ⚠️ `delete` acepta CUERPO desde el paso 8: `DELETE /me` manda la contraseña de reconfirmación,
    // que en `DELETE` es legal aunque poco común. La alternativa —pasarla por query— la dejaría
    // escrita en los logs del servidor y en el historial del navegador, así que no se contempla.
    // Sigue valiendo llamarlo sin cuerpo (`api.delete('/me/pending-email')`).
    delete: (path, body = null, options = {}) => request(path, { ...options, method: 'DELETE', body }),
};
