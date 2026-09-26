/**
 * **El camino de una página que NO es del producto** (F4 · T3b, `docs/specs/cajon-empaquetable.md` §4.1).
 *
 * Aquí vive lo único que una landing ajena necesita y una página del producto no usa jamás: **pedirle el
 * arranque a la API** —porque no hay `data-boot` que leer— y **construir la carcasa** —porque no hay marcado
 * que adoptar—.
 *
 * ⚠️⚠️ **Y por eso es un módulo aparte, no un trozo de la entrada.** `SidebarBundleBudgetTest` vigila lo que
 * pesa el JS que descarga TODA página pública del producto, y metido en la entrada este código subía el
 * presupuesto de 26 a 27,3 kB para que las páginas del producto pagaran una rama que nunca ejecutan. Se trae
 * con `import()` desde `bootSpaEngine()`, en la misma ventana en la que ya se está trayendo el motor.
 */

const SUPPORTED = ['es', 'en', 'fr'];

/** El idioma del documento, acotado a los que la API acepta. */
export function bootLang(doc = document) {
    const declared = (doc.documentElement?.lang || '').slice(0, 2).toLowerCase();

    return SUPPORTED.includes(declared) ? declared : 'es';
}

/**
 * El arranque, pedido a la API (`GET /api/v1/sidebar/boot` y `/sidebar/session`, §4.5).
 *
 * Las dos lecturas van en PARALELO: no dependen una de otra y manda la lenta.
 *
 * ⚠️ El idioma viaja en la URL y sale de `<html lang>`, que es de la página que aloja el cajón. Una landing sin
 * `lang` es un defecto suyo de accesibilidad antes que nuestro: se cae al idioma de la instalación en vez de no
 * arrancar.
 *
 * ⚠️⚠️ `session` va con la cookie (`same-origin`) y **consume** el desenlace de un pago pendiente, igual que lo
 * consume pintar una página del producto: se pide UNA vez y quien llama guarda el resultado.
 *
 * @returns {Promise<object|null>} el payload, o **`null`** si no contestó NI UNA de las dos. `null` no es
 *   «vacío»: es «no se pudo», y con eso no se construye una carcasa sin título ni nombre accesible en su ×.
 */
export async function readBootFromApi({ doc = document, fetch: get = fetch, base = '/api/v1' } = {}) {
    const lang = bootLang(doc);
    const pedir = async (ruta, init) => {
        const res = await get(`${base}/sidebar/${ruta}?lang=${lang}`, { headers: { Accept: 'application/json' }, ...init });

        return res.ok ? res.json() : {};
    };

    const [shared, personal] = await Promise.all([
        pedir('boot').catch(() => null),
        pedir('session', { credentials: 'same-origin' }).catch(() => null),
    ]);

    // Que falle UNA se aguanta: sin la privada el cajón abre como invitado, y sin la pública abre sin
    // rótulos pero con su estado. Que fallen las DOS es que no hay servidor, y entonces no hay cajón.
    if (shared === null && personal === null) return null;

    return mergeBoot(shared ?? {}, personal ?? {});
}

/**
 * Las dos mitades, fundidas en el orden de claves del layout.
 *
 * ⚠️ Es la MISMA fusión que hace `SidebarBoot::forCurrentRequest()` en PHP, y por eso
 * `SidebarBootTest::test_the_two_api_reads_rebuild_exactly_what_the_layout_paints` la fija allí: si las dos
 * divergen, el cajón de una landing ajena pinta otra cosa que el del producto y nada falla.
 */
export function mergeBoot(shared = {}, personal = {}) {
    const locales = personal.locales ?? [];

    return {
        outcome: personal.outcome ?? null,
        orderCode: personal.orderCode ?? null,
        messages: shared.messages ?? {},
        ui: shared.ui ?? {},
        account: { ...(shared.account ?? {}), ...(personal.account ?? {}) },
        ...(locales.length ? { locales } : {}),
        auth: shared.auth ?? {},
        userId: personal.userId ?? null,
        accountContext: personal.accountContext ?? null,
        urls: { ...(shared.urls ?? {}), ...(personal.urls ?? {}) },
        // ⚠️⚠️ **La carcasa y los rótulos de la isla, al final como en PHP** (T3e·2, `DECISIONES #682`). Sin estas
        // dos líneas la fusión se los comía, y en una página ajena la isla no se encendía NUNCA: el controlador
        // leía la carcasa de este arranque y encontraba el cajón. Lo cazó el navegador, no una prueba.
        shell: shared.shell ?? 'cajon',
        // Con sesión, los de Mi cuenta dentro del mismo grupo (T5, `#773`), como `$shared['isla'] + $personal['isla']`.
        ...(shared.isla ? { isla: { ...shared.isla, ...(personal.isla ?? {}) } } : {}),
    };
}

/** Un nodo con sus clases, sus atributos y su texto. Se construye con `textContent`, nunca interpolando HTML. */
function nodo(doc, etiqueta, clase, { texto, ...atributos } = {}) {
    const el = doc.createElement(etiqueta);

    if (clase) el.className = clase;
    for (const [k, v] of Object.entries(atributos)) el.setAttribute(k, v);
    if (texto !== undefined) el.textContent = texto;

    return el;
}

/**
 * **La carcasa CONSTRUIDA, para una página que no la trae.**
 *
 * Es el mismo árbol que pinta `components/layout.blade.php`, nodo a nodo, porque lo viste la MISMA hoja: una
 * clase que falte aquí es una pieza sin estilo, y eso no lo ve ningún test de Node (se mira con la sonda y lo
 * fijará el trinquete de la T4).
 *
 * ⚠️ **El SUELO del bloque de cuenta se construye solo si se puede construir ENTERO**: hacen falta titular
 * (`boot.userId`), a dónde POSTea (`boot.urls.logout`) y un token CSRF en la página
 * (`<meta name="csrf-token">`). Sin las tres, un formulario a medias sería un botón que no cierra sesión y que
 * miente más que no estar. Una landing de instancia que quiera ese suelo publica el meta: es una línea, y va en
 * el contrato de instancia (F5).
 *
 * ⚠️ El suelo va SIN el icono de la versión de Blade: es el SUELO, no una copia del bloque (lo pinta Vue).
 *
 * @returns {Element} la raíz `.sidecart`, todavía SIN insertar
 */
export function createShell(boot = {}, doc = document) {
    const titulo = boot.messages?.title ?? '';
    const root = nodo(doc, 'div', 'sidecart');

    root.append(nodo(doc, 'div', 'sidecart__backdrop'));

    const panel = nodo(doc, 'aside', 'sidecart__panel', { role: 'dialog', 'aria-modal': 'true', 'aria-label': titulo });
    const head = nodo(doc, 'header', 'sidecart__head');

    head.append(
        nodo(doc, 'span', 'sidecart__title', { texto: titulo }),
        nodo(doc, 'button', 'sidecart__close', { type: 'button', 'aria-label': boot.account?.close ?? '', texto: '×' }),
    );

    const hueco = nodo(doc, 'div', 'acct acct--pending', { id: 'sidecart-account' });
    const suelo = logoutFloor(boot, doc);

    if (suelo) hueco.append(suelo);

    const body = nodo(doc, 'div', 'sidecart__body');
    const motor = nodo(doc, 'div', null, { id: 'sidecart-spa' });

    motor.append(loadingVeil(boot.ui?.loading ?? '', doc));
    body.append(motor);
    panel.append(head, hueco, body);
    root.append(panel);

    return root;
}

/** El velo de carga, con el mismo árbol que emite `<x-ui.spinner size="lg">` dentro de `.purchase-loading`. */
function loadingVeil(rotulo, doc) {
    const velo = nodo(doc, 'div', 'purchase-loading');
    const conjunto = nodo(doc, 'span', 'jj-spinner-with-label');
    const spinner = nodo(doc, 'span', 'jj-spinner jj-spinner--lg', { role: 'status' });

    spinner.append(nodo(doc, 'span', 'jj-spinner__sr', { texto: rotulo }));
    conjunto.append(spinner, nodo(doc, 'span', 'jj-spinner-label', { texto: rotulo, 'aria-hidden': 'true' }));
    velo.append(conjunto);

    return velo;
}

/** El suelo de cerrar sesión, o `null` si no se puede construir entero (ver {@see createShell}). */
function logoutFloor(boot, doc) {
    const csrf = doc.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    const accion = boot.urls?.logout;

    if (! boot.userId || ! accion || ! csrf) return null;

    const inner = nodo(doc, 'div', 'acct__inner');
    const cta = nodo(doc, 'div', 'acct__cta');
    const form = nodo(doc, 'form', 'acct__logout-form', { method: 'POST', action: accion });

    form.append(
        nodo(doc, 'input', null, { type: 'hidden', name: '_token', value: csrf }),
        nodo(doc, 'button', 'acct__btn acct__btn--primary', { type: 'submit', texto: boot.account?.nav?.sign_out ?? '' }),
    );
    cta.append(form);
    inner.append(cta);

    return inner;
}
