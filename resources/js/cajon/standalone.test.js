import { test, describe } from 'node:test';
import assert from 'node:assert/strict';
import { bootLang, createShell, mergeBoot, readBootFromApi } from './standalone.js';

/**
 * F4 · T3b — el camino de una página que NO es del producto: pedirle el arranque a la API y construir la
 * carcasa (`docs/specs/cajon-empaquetable.md` §4.1 y §4.5).
 */

const docCon = (lang) => ({ documentElement: { lang } });

/** Un `fetch` de mentira que apunta lo que se le pide y responde por ruta. */
function fetchDoble(respuestas, { falla = [] } = {}) {
    const pedidas = [];
    const get = async (url, init) => {
        pedidas.push({ url, init });

        const clave = Object.keys(respuestas).find((k) => url.includes(k));

        if (falla.includes(clave)) throw new Error('red caída');

        return clave ? { ok: true, json: async () => respuestas[clave] } : { ok: false, json: async () => ({}) };
    };

    return { get, pedidas };
}

describe('el idioma del arranque', () => {
    test('sale del `<html lang>` y se acota a los que la API acepta', () => {
        assert.equal(bootLang(docCon('fr')), 'fr');
        assert.equal(bootLang(docCon('en-GB')), 'en');
    });

    /** Una landing sin `lang` es un defecto suyo: el cajón arranca en el idioma de la instalación, no falla. */
    test('sin `lang`, o con uno que no servimos, cae a español', () => {
        assert.equal(bootLang(docCon('')), 'es');
        assert.equal(bootLang(docCon('de')), 'es');
        assert.equal(bootLang({}), 'es');
    });
});

describe('pedirle el arranque a la API', () => {
    test('pide las DOS lecturas, con su idioma en la URL, y las funde', async () => {
        const { get, pedidas } = fetchDoble({
            'sidebar/boot': { messages: { title: 'Réservations' }, ui: { loading: 'Chargement…' }, account: { close: 'Fermer' }, auth: {}, urls: { home: '/' } },
            'sidebar/session': { outcome: 'confirmed', orderCode: 'JW-1', account: { purchases: {} }, locales: [], userId: 7, accountContext: null, urls: {} },
        });

        const boot = await readBootFromApi({ doc: docCon('fr'), fetch: get });

        assert.deepEqual(pedidas.map((p) => p.url), ['/api/v1/sidebar/boot?lang=fr', '/api/v1/sidebar/session?lang=fr']);
        assert.equal(boot.messages.title, 'Réservations');
        assert.equal(boot.userId, 7);
        assert.equal(boot.outcome, 'confirmed');
        assert.deepEqual(boot.account, { close: 'Fermer', purchases: {} }, 'las dos mitades del grupo `account` se funden');
    });

    /** Lleva al titular y CONSUME el desenlace del pago: no puede viajar sin la cookie de sesión. */
    test('la mitad privada se pide con la cookie de sesión y la pública no', async () => {
        const { get, pedidas } = fetchDoble({ 'sidebar/boot': {}, 'sidebar/session': {} });

        await readBootFromApi({ doc: docCon('es'), fetch: get });

        assert.equal(pedidas[0].init.credentials, undefined);
        assert.equal(pedidas[1].init.credentials, 'same-origin');
    });

    /** Que falle UNA se aguanta: sin la privada el cajón abre como invitado; sin la pública, sin rótulos. */
    test('si falla una lectura, el arranque sale con lo que sí llegó', async () => {
        const { get } = fetchDoble({ 'sidebar/boot': { messages: { title: 'Reservas' } }, 'sidebar/session': {} }, { falla: ['sidebar/session'] });

        const boot = await readBootFromApi({ doc: docCon('es'), fetch: get });

        assert.equal(boot.messages.title, 'Reservas');
        assert.equal(boot.userId, null);
    });

    /**
     * ⚠️ Que fallen las DOS es que no hay servidor: devuelve `null`, no un objeto vacío. La diferencia importa
     * —con el vacío se construiría una carcasa sin título y sin nombre accesible en su ×—.
     */
    test('si fallan las dos, devuelve null y no un arranque vacío', async () => {
        const { get } = fetchDoble({ 'sidebar/boot': {}, 'sidebar/session': {} }, { falla: ['sidebar/boot', 'sidebar/session'] });

        assert.equal(await readBootFromApi({ doc: docCon('es'), fetch: get }), null);
    });
});

describe('fundir las dos mitades', () => {
    /**
     * ⚠️ El orden de claves es el del layout, y no es manía: `SidebarBootTest` compara en PHP lo que pinta el
     * layout con lo que reconstruyen las dos lecturas. Si las dos fusiones divergen, el cajón de una landing
     * ajena recibe otra cosa que el del producto y nada falla.
     */
    test('conserva el orden de claves del layout', () => {
        const fundido = mergeBoot({ messages: {}, ui: {}, account: {}, auth: {}, urls: {} }, { outcome: null, orderCode: null, account: {}, locales: [], userId: null, accountContext: null, urls: {} });

        assert.deepEqual(Object.keys(fundido), ['outcome', 'orderCode', 'messages', 'ui', 'account', 'auth', 'userId', 'accountContext', 'urls']);
    });

    /** `locales` solo viaja con sesión, igual que en el layout: sin ella la clave no está, no está vacía. */
    test('`locales` solo aparece si hay alguno', () => {
        assert.equal('locales' in mergeBoot({}, { locales: [] }), false);
        assert.deepEqual(mergeBoot({}, { locales: [{ value: 'es' }] }).locales, [{ value: 'es' }]);
    });

    test('sin nada que fundir, devuelve la forma entera con sus vacíos', () => {
        const vacio = mergeBoot();

        assert.equal(vacio.userId, null);
        assert.deepEqual(vacio.messages, {});
        assert.deepEqual(vacio.urls, {});
    });
});

// ── La carcasa CONSTRUIDA ──────────────────────────────────────────────────────────────────────

/** Un DOM de mentira, lo justo para levantar un árbol y poder leerlo. */
function domFalso({ meta = null } = {}) {
    const crear = (etiqueta) => ({
        etiqueta,
        className: '',
        hijos: [],
        atributos: {},
        textContent: undefined,
        append(...nodos) { this.hijos.push(...nodos); },
        setAttribute(k, v) { this.atributos[k] = v; },
    });

    return {
        createElement: crear,
        body: crear('body'),
        querySelector: (sel) => (sel === 'meta[name="csrf-token"]' && meta ? { getAttribute: () => meta } : null),
    };
}

/** Busca en el árbol por clase o por id. */
function buscar(nodo, pred) {
    if (pred(nodo)) return nodo;

    for (const hijo of nodo.hijos ?? []) {
        const hallado = buscar(hijo, pred);
        if (hallado) return hallado;
    }

    return null;
}

const porClase = (c) => (n) => (n.className || '').split(' ').includes(c);
const porId = (id) => (n) => n.atributos?.id === id;

const BOOT = {
    messages: { title: 'Reservas' },
    ui: { loading: 'Cargando…' },
    account: { close: 'Cerrar', nav: { sign_out: 'Cerrar sesión' } },
    urls: { logout: '/salir' },
    userId: null,
};

describe('la carcasa construida', () => {
    test('levanta el mismo árbol que pinta el layout, con sus rótulos', () => {
        const root = createShell(BOOT, domFalso());

        assert.equal(root.className, 'sidecart');
        assert.ok(buscar(root, porClase('sidecart__backdrop')), 'sin telón no hay dónde pulsar para cerrar');

        const panel = buscar(root, porClase('sidecart__panel'));
        assert.equal(panel.atributos.role, 'dialog');
        assert.equal(panel.atributos['aria-modal'], 'true');
        assert.equal(panel.atributos['aria-label'], 'Reservas');

        assert.equal(buscar(root, porClase('sidecart__title')).textContent, 'Reservas');
        assert.equal(buscar(root, porClase('sidecart__close')).atributos['aria-label'], 'Cerrar');
        assert.ok(buscar(root, porId('sidecart-account')), 'falta el hueco del bloque de cuenta');
        assert.ok(buscar(root, porId('sidecart-spa')), 'falta el hueco del motor');
    });

    /** El hueco de cuenta nace COLAPSADO: hasta que el motor llega no hay nada que enseñar. */
    test('el hueco de cuenta nace colapsado y el del motor con su velo', () => {
        const root = createShell(BOOT, domFalso());

        assert.equal(buscar(root, porId('sidecart-account')).className, 'acct acct--pending');
        assert.equal(buscar(root, porClase('jj-spinner-label')).textContent, 'Cargando…');
        assert.equal(buscar(root, porClase('jj-spinner')).atributos.role, 'status');
        assert.equal(buscar(root, porClase('jj-spinner__sr')).textContent, 'Cargando…');
    });

    /**
     * ⚠️⚠️ **El suelo de logout se construye ENTERO o no se construye**: hacen falta titular, URL y token CSRF.
     * A medias sería un botón que no cierra sesión — y miente más que no estar.
     */
    test('el suelo de logout aparece con titular, URL y token, y no sin ellos', () => {
        const conTitular = { ...BOOT, userId: 7 };

        assert.equal(buscar(createShell(conTitular, domFalso()), porClase('acct__logout-form')), null, 'sin token CSRF no se construye');
        assert.equal(buscar(createShell(BOOT, domFalso({ meta: 'tok' })), porClase('acct__logout-form')), null, 'sin titular no se construye');
        assert.equal(buscar(createShell({ ...conTitular, urls: {} }, domFalso({ meta: 'tok' })), porClase('acct__logout-form')), null, 'sin URL no se construye');

        const form = buscar(createShell(conTitular, domFalso({ meta: 'tok' })), porClase('acct__logout-form'));

        assert.equal(form.atributos.method, 'POST');
        assert.equal(form.atributos.action, '/salir');
        assert.equal(form.hijos[0].atributos.name, '_token');
        assert.equal(form.hijos[0].atributos.value, 'tok');
        assert.equal(form.hijos[1].textContent, 'Cerrar sesión');
    });

    /** El envoltorio que recorta: sin él el suelo se sale de la fila mientras el hueco se colapsa. */
    test('el suelo va dentro de `.acct__inner`', () => {
        assert.ok(buscar(createShell({ ...BOOT, userId: 7 }, domFalso({ meta: 'tok' })), porClase('acct__inner')));
    });
});
