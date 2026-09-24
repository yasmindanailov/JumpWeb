import { test, describe } from 'node:test';
import assert from 'node:assert/strict';
import { createCookiesStore, FALLBACK_CATEGORIES } from './cookie-consent.js';

/**
 * La red del almacén de cookies (`COOKIES.md` §4; `analitica.md` §4.3, T3a).
 *
 * ⚠️ Lo que se prueba aquí es lo que hasta hoy solo se «verificaba por revisión»: que el banner no se da por
 * decidido sin el `res.ok` del servidor (`RGPD-05`), que las categorías salen del `<body>` y no de una lista
 * escrita en el JS, que «aceptar todo» acepta TODAS, que la tarjeta calla con el cajón delante y que
 * `consent_shown` se cuenta una sola vez.
 */

function fakeDoc({ categories = 'maps,social,analytics,marketing', enabled = '1', decided = '', prefs = {}, csrf = 'tok' } = {}) {
    const dataset = { cookieEnabled: enabled, cookieDecided: decided, cookieEndpoint: '/cookies/consentimiento', consentCategories: categories };
    for (const [cat, on] of Object.entries(prefs)) dataset['cookie' + cat.charAt(0).toUpperCase() + cat.slice(1)] = on ? '1' : '';

    return {
        body: { dataset },
        querySelector: (sel) => (sel === 'meta[name="csrf-token"]' ? { content: csrf } : null),
    };
}

function fakeWin() {
    const events = [];
    const tracked = [];

    return {
        events,
        tracked,
        CustomEvent: class { constructor(type, init) { this.type = type; this.detail = init?.detail; } },
        dispatchEvent(e) { events.push(e); },
        JumpWeb: { track: (name) => tracked.push(name) },
    };
}

const okFetch = (calls) => (url, init) => { calls.push({ url, init }); return Promise.resolve({ ok: true }); };

describe('las categorías salen del body', () => {
    test('las cuatro de la T3a, con su estado inicial', () => {
        const store = createCookiesStore({ doc: fakeDoc({ prefs: { maps: true, analytics: true } }), win: fakeWin(), purchase: () => null });

        assert.deepEqual(store.categories, ['maps', 'social', 'analytics', 'marketing']);
        assert.deepEqual(store.prefs, { maps: true, social: false, analytics: true, marketing: false });
    });

    test('sin lista en el body, las dos de siempre', () => {
        const store = createCookiesStore({ doc: fakeDoc({ categories: '' }), win: fakeWin(), purchase: () => null });

        assert.deepEqual(store.categories, FALLBACK_CATEGORIES);
    });

    test('aceptar todo acepta TODAS y rechazar todo las rechaza todas: ninguna lista aparte', async () => {
        const calls = [];
        const store = createCookiesStore({ doc: fakeDoc(), win: fakeWin(), purchase: () => null, fetchFn: okFetch(calls) });

        await store.acceptAll();
        assert.deepEqual(JSON.parse(calls[0].init.body), { maps: true, social: true, analytics: true, marketing: true });

        await store.rejectAll();
        assert.deepEqual(JSON.parse(calls[1].init.body), { maps: false, social: false, analytics: false, marketing: false });
    });

    test('conceder una categoría desde su placeholder no toca las demás', async () => {
        const calls = [];
        const store = createCookiesStore({ doc: fakeDoc({ prefs: { analytics: true } }), win: fakeWin(), purchase: () => null, fetchFn: okFetch(calls) });

        await store.grant('maps');

        assert.deepEqual(JSON.parse(calls[0].init.body), { maps: true, social: false, analytics: true, marketing: false });
    });

    test('el panel guarda solo las categorías conocidas; una clave inventada no viaja', async () => {
        const calls = [];
        const store = createCookiesStore({ doc: fakeDoc(), win: fakeWin(), purchase: () => null, fetchFn: okFetch(calls) });

        await store.savePanel({ maps: true, evil: true });

        assert.deepEqual(JSON.parse(calls[0].init.body), { maps: true, social: false, analytics: false, marketing: false });
        assert.equal(calls[0].init.headers['X-CSRF-TOKEN'], 'tok');
        assert.equal(calls[0].url, '/cookies/consentimiento');
    });
});

describe('RGPD-05: la decisión solo vale si el servidor la confirmó', () => {
    test('con res.ok: decidido, el panel cerrado y el aviso a los contenidos gateados con las prefs', async () => {
        const win = fakeWin();
        const store = createCookiesStore({ doc: fakeDoc(), win, purchase: () => null, fetchFn: okFetch([]) });
        store.panel = true;

        assert.equal(await store.savePanel({ maps: true }), true);
        assert.equal(store.decided, true);
        assert.equal(store.panel, false);
        assert.equal(win.events.length, 1);
        assert.equal(win.events[0].type, 'cookies-updated');
        assert.deepEqual(win.events[0].detail, { maps: true, social: false, analytics: false, marketing: false });
    });

    test('con un 419/429/500 (`fetch` NO rechaza): sigue sin decidir y no se avisa a nadie', async () => {
        const win = fakeWin();
        const store = createCookiesStore({ doc: fakeDoc(), win, purchase: () => null, fetchFn: () => Promise.resolve({ ok: false, status: 419 }) });

        assert.equal(await store.acceptAll(), false);
        assert.equal(store.decided, false);
        assert.equal(store.visible, true, 'el banner sigue visible: la próxima visita vuelve a pedir la decisión');
        assert.equal(win.events.length, 0);
    });

    test('con un fallo de red, lo mismo', async () => {
        const win = fakeWin();
        const store = createCookiesStore({ doc: fakeDoc(), win, purchase: () => null, fetchFn: () => Promise.reject(new Error('offline')) });

        assert.equal(await store.acceptAll(), false);
        assert.equal(store.decided, false);
        assert.equal(win.events.length, 0);
    });
});

describe('cuándo se enseña la tarjeta', () => {
    test('la primera capa: habilitado y sin decisión', () => {
        assert.equal(createCookiesStore({ doc: fakeDoc(), win: fakeWin(), purchase: () => null }).visible, true);
        assert.equal(createCookiesStore({ doc: fakeDoc({ decided: '1' }), win: fakeWin(), purchase: () => null }).visible, false);
        assert.equal(createCookiesStore({ doc: fakeDoc({ enabled: '' }), win: fakeWin(), purchase: () => null }).visible, false);
    });

    test('con el cajón de compra abierto la tarjeta calla, y vuelve al cerrarse', () => {
        const drawer = { isOpen: true };
        const store = createCookiesStore({ doc: fakeDoc(), win: fakeWin(), purchase: () => drawer });

        assert.equal(store.visible, true);
        assert.equal(store.showing, false, 'el aviso espera a que el cajón se cierre');

        drawer.isOpen = false;
        assert.equal(store.showing, true);
    });

    test('el panel reabierto desde el pie también espera al cajón', () => {
        const drawer = { isOpen: true };
        const store = createCookiesStore({ doc: fakeDoc({ decided: '1' }), win: fakeWin(), purchase: () => drawer });
        store.openPanel();

        assert.equal(store.showing, false);
        drawer.isOpen = false;
        assert.equal(store.showing, true);
    });

    test('sin store de compra (una página ajena) se enseña igual', () => {
        assert.equal(createCookiesStore({ doc: fakeDoc(), win: fakeWin(), purchase: () => undefined }).showing, true);
    });
});

describe('consent_shown', () => {
    test('se cuenta UNA vez, por el buzón de JumpWeb.track', () => {
        const win = fakeWin();
        const store = createCookiesStore({ doc: fakeDoc(), win, purchase: () => null });

        store.noteShown();
        store.noteShown();

        assert.deepEqual(win.tracked, ['consent_shown']);
    });

    test('no se cuenta si ya había decisión, si el banner está apagado o con el cajón delante', () => {
        const decided = fakeWin();
        createCookiesStore({ doc: fakeDoc({ decided: '1' }), win: decided, purchase: () => null }).noteShown();
        assert.deepEqual(decided.tracked, []);

        const off = fakeWin();
        createCookiesStore({ doc: fakeDoc({ enabled: '' }), win: off, purchase: () => null }).noteShown();
        assert.deepEqual(off.tracked, []);

        const covered = fakeWin();
        createCookiesStore({ doc: fakeDoc(), win: covered, purchase: () => ({ isOpen: true }) }).noteShown();
        assert.deepEqual(covered.tracked, []);
    });

    test('sin tracker ni buzón no revienta', () => {
        const win = fakeWin();
        win.JumpWeb = undefined;

        assert.doesNotThrow(() => createCookiesStore({ doc: fakeDoc(), win, purchase: () => null }).noteShown());
    });
});
