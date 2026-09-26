/**
 * **El aviso que dejó el servidor al volver a esta página** (T5e·2 de `specs/isla-y-landing-nueva.md` §4.13, `#779`): el
 * `status` de la sesión —la vuelta de Google al entrar o al vincular, un cambio de correo— con su texto y su tono
 * (`Http\Cuenta\AvisoDeSesion`). Viaja en la configuración de la isla de la página (`#jw-isla-pagina`, `config.aviso`):
 * es lo único que se pinta en la MISMA petición, y el motor pide su arranque después, con el `status` ya gastado.
 *
 * Lo TOMA una sola vez quien se abra primero: Mi cuenta si la página se abre en ella (`#mi-cuenta…`, una puerta), la
 * compra que vuelve de Google (`?compra=reanudar`) o, si no se abre ninguna capa, el «Aviso» de la isla. Tomarlo lo marca
 * en el propio `<script>`: la isla de la página y el motor son entradas distintas, sin módulos en común, pero con el
 * mismo documento. Módulo plano, con el documento y la dirección por parámetro (`CE-6`, `node --test`).
 */

const TONOS = ['success', 'danger', 'info'];

/**
 * El aviso, la primera vez que se pide; después, y sin él, `null`. Con `si`, solo se toma si le vale a quien lo pide (la
 * isla de la página solo confirma): si no, se queda para el siguiente (Mi cuenta, al abrirse).
 *
 * @param {Document} [doc]
 * @param {{si?: (aviso: {texto: string, tono: string}) => boolean}} [opciones]
 * @returns {{texto: string, tono: 'success'|'danger'|'info'}|null}
 */
export function tomarAvisoDelServidor(doc = globalThis.document, { si = () => true } = {}) {
    const el = doc?.getElementById?.('jw-isla-pagina');

    if (! el || el.dataset?.avisoTomado) return null;

    let crudo;

    try {
        crudo = JSON.parse(el.textContent ?? '')?.config?.aviso ?? null;
    } catch {
        return null;
    }
    if (! crudo?.texto) return null;

    const aviso = { texto: String(crudo.texto), tono: TONOS.includes(crudo.tono) ? crudo.tono : 'info' };

    if (! si(aviso)) return null;
    el.dataset.avisoTomado = '1';

    return aviso;
}

/**
 * ¿Se abre una capa al cargar la página, que tomará el aviso ella? Mi cuenta (el enlace `#mi-cuenta…` o una puerta de la
 * cuenta, `data-account-zone`) o la compra que vuelve de Google (`?compra=reanudar`).
 *
 * @param {{hash?: string, search?: string}} [loc]
 * @param {Document} [doc]
 */
export function loTomaUnaCapa(loc = globalThis.location, doc = globalThis.document) {
    return /^#mi-cuenta(\/|$)/.test(loc?.hash ?? '')
        || new URLSearchParams(loc?.search ?? '').get('compra') === 'reanudar'
        || Boolean(doc?.body?.dataset?.accountZone);
}
