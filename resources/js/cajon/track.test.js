import { test, describe } from 'node:test';
import assert from 'node:assert/strict';
import { createTracker, hash, ulid } from './track.js';

/**
 * T1b de la analítica — el ÚNICO emisor (`docs/specs/analitica.md` §4.2, `DECISIONES #678`).
 *
 * Sin navegador: la ventana, el documento, `fetch`, el reloj, el azar y los temporizadores llegan por parámetro
 * (`CE-6`). Lo que se fija es el CONTRATO con el servidor —la forma del lote, el ULID, la cola que sobrevive,
 * el reintento que se rinde y lo cuenta— y que cada superficie emite lo que dice la spec, con las `props`
 * exactas, porque `EventIngestor` descarta en silencio una clave que no esté en `Contract::EVENTS`.
 */

const AHORA = 1_758_600_000_000;

function montar({ dataset = {}, search = '', referrer = '', pathname = '/entradas', pending = null, storage = new Map(), webdriver = false, lang = 'es' } = {}) {
    const oyentes = {};
    const on = (tipo, fn) => { oyentes[tipo] = fn; };
    const balizas = [];
    const envios = [];
    const timers = [];
    const cancelados = [];
    let secciones = [];

    const doc = {
        body: { dataset },
        documentElement: { lang },
        referrer,
        visibilityState: 'visible',
        addEventListener: on,
        querySelectorAll: () => secciones,
    };
    const win = {
        location: { pathname, search, host: 'localhost:8081' },
        navigator: { webdriver, sendBeacon: (url, blob) => { balizas.push({ url, blob }); return true; } },
        sessionStorage: { getItem: (k) => storage.get(k) ?? null, setItem: (k, v) => storage.set(k, v) },
        JumpWeb: pending ? { track: Object.assign(() => {}, { pending }) } : {},
        addEventListener: on,
        innerHeight: 800,
        IntersectionObserver: null,
    };

    let respuesta = null;
    const tracker = createTracker({
        win,
        doc,
        fetch: (url, init) => {
            envios.push({ url, init, body: JSON.parse(init.body) });

            return new Promise((resolve, reject) => { respuesta = { resolve, reject }; });
        },
        now: () => AHORA,
        bytes: (n) => new Uint8Array(n).fill(7),
        setTimer: (fn, ms) => { timers.push({ fn, ms }); return timers.length; },
        clearTimer: (id) => cancelados.push(id),
    });

    /** Deja que el `then` del envío corra. */
    const espera = () => new Promise((r) => setImmediate(r));
    /** Dispara el ÚLTIMO temporizador programado. */
    const salta = () => { const t = timers.pop(); t.fn(); return t.ms; };
    const cola = () => JSON.parse(storage.get('jw:ev') ?? '[]');
    const emitidos = () => cola().map((e) => e.name);
    const evento = (tipo, detail = {}) => oyentes[tipo]({ type: tipo, detail });

    return {
        tracker, win, doc, oyentes, balizas, envios, timers, cancelados, storage,
        espera, salta, cola, emitidos, evento,
        responde: async (status) => { respuesta.resolve({ status }); await espera(); },
        seCae: async () => { respuesta.reject(new TypeError('Failed to fetch')); await espera(); },
        observa: (lista) => { secciones = lista; },
    };
}

describe('el identificador y el hash', () => {
    test('ulid() da 26 caracteres Crockford —lo que `Visitor::isValid` admite— y el tiempo va delante', () => {
        const ceros = new Uint8Array(16).fill(0);
        const a = ulid(AHORA, ceros);
        const b = ulid(AHORA + 1000, ceros);

        assert.match(a, /^[0-9A-HJKMNP-TV-Z]{26}$/);
        assert.match(ulid(AHORA, new Uint8Array(16).fill(31)), /^[0-9A-HJKMNP-TV-Z]{26}$/);
        // ⚠️ Con el MISMO azar: si el orden viniera de los 16 caracteres aleatorios y no de los 10 del tiempo,
        // este caso pasaría igual (lo dijo el arnés de mutación: la primera versión ordenaba por el azar).
        assert.ok(a < b, 'ordena por tiempo');
        assert.notEqual(a.slice(0, 10), b.slice(0, 10), 'los diez primeros son el tiempo');
        assert.equal(ulid(32, ceros).slice(0, 10), '0000000010', 'base 32 Crockford, el tiempo delante');
        assert.equal(a.slice(10), '0000000000000000');
        assert.equal(ulid(AHORA, new Uint8Array(16).fill(31)).slice(10), 'ZZZZZZZZZZZZZZZZ');
    });

    test('hash() es estable, corto y no contiene el texto', () => {
        assert.equal(hash('TypeError: x is undefined|app.js|12'), hash('TypeError: x is undefined|app.js|12'));
        assert.notEqual(hash('a'), hash('b'));
        assert.match(hash('correo ana@example.com'), /^[0-9a-f]{1,8}$/);
    });
});

describe('la primera vista', () => {
    test('lleva la entrada, el referer externo, las UTM y click ids de la URL y el idioma', () => {
        const { tracker, cola } = montar({
            search: '?utm_source=google&utm_medium=cpc&gclid=abc123&ref=gbp&x=1',
            referrer: 'https://www.google.com/search?q=parque',
        });

        tracker.install();

        const [vista] = cola();

        assert.equal(vista.name, 'page_viewed');
        assert.equal(vista.route, '/entradas');
        assert.equal(vista.occurred_at, AHORA);
        assert.match(vista.event_id, /^[0-9A-HJKMNP-TV-Z]{26}$/);
        assert.deepEqual(vista.props, {
            locale: 'es', entry: '/entradas', referrer_host: 'www.google.com',
            utm_source: 'google', utm_medium: 'cpc', gclid: 'abc123', ref: 'gbp',
        });
    });

    test('la segunda vista de la misma pestaña NO repite la entrada ni el referer, pero sí una UTM que venga', () => {
        const storage = new Map();

        montar({ storage, referrer: 'https://t.co/x' }).tracker.install();
        const { cola } = montar({ storage, pathname: '/precios', search: '?utm_campaign=verano', referrer: 'https://localhost:8081/entradas' });
        montar({ storage, pathname: '/precios', search: '?utm_campaign=verano' }).tracker.install();

        const vistas = cola().filter((e) => e.name === 'page_viewed');

        assert.equal(vistas.length, 2);
        assert.deepEqual(vistas[1].props, { locale: 'es', utm_campaign: 'verano' });
    });

    test('un referer del MISMO host no es una fuente', () => {
        const { tracker, cola } = montar({ referrer: 'http://localhost:8081/precios' });

        tracker.install();

        assert.equal(cola()[0].props.referrer_host, undefined);
    });
});

describe('el lote', () => {
    test('a los diez eventos se envía: la forma del cuerpo es la del contrato, con `meta` y sin CSRF', async () => {
        const { tracker, envios, cola, responde } = montar({ dataset: { cookieEnabled: '1', cookieDecided: '1', cookieMaps: '1', cookieSocial: '', cookieEndpoint: '/x' }, webdriver: true });

        tracker.install();
        for (let i = 0; i < 9; i++) tracker.track('section_viewed', { section: `s${i}` });

        assert.equal(envios.length, 1);
        const { url, init, body } = envios[0];

        assert.equal(url, '/api/v1/events');
        assert.equal(init.method, 'POST');
        assert.equal(init.credentials, 'same-origin');
        assert.equal(init.keepalive, true);
        assert.equal(init.headers['Content-Type'], 'application/json');
        assert.equal(init.headers['X-XSRF-TOKEN'], undefined, 'la ruta es stateless');
        assert.equal(body.events.length, 10);
        assert.deepEqual(body.meta, { webdriver: true, consent: { maps: true, social: false } });
        assert.deepEqual(Object.keys(body.events[0]), ['event_id', 'name', 'route', 'props', 'occurred_at']);

        await responde(202);

        assert.deepEqual(cola(), [], 'aceptado: la cola se vacía');
    });

    test('con menos de diez espera 5 s y la cola sobrevive en `sessionStorage` mientras tanto', async () => {
        const { tracker, envios, timers, cola, salta } = montar();

        tracker.install();
        tracker.track('call_clicked');

        assert.equal(envios.length, 0);
        assert.equal(timers.length, 1);
        assert.equal(cola().length, 2, 'lo que no se ha enviado está guardado');

        assert.equal(salta(), 5000);
        assert.equal(envios.length, 1);
    });

    test('lo que quedó en la cola de la página anterior se envía con la siguiente', () => {
        const storage = new Map([['jw:ev', JSON.stringify([{ event_id: '01ARZ3NDEKTSV4RRFFQ69G5FAV', name: 'drawer_closed', route: '/', props: {}, occurred_at: 1 }])]]);
        const { tracker, emitidos } = montar({ storage });

        tracker.install();

        assert.deepEqual(emitidos(), ['drawer_closed', 'page_viewed']);
    });

    test('ante 5xx, 429 o red reintenta tres veces doblando la espera, y a la cuarta suelta el lote y lo cuenta', async () => {
        const { tracker, envios, salta, responde, seCae, cola } = montar();

        tracker.install();
        for (let i = 0; i < 9; i++) tracker.track('call_clicked');

        await responde(503);
        assert.equal(salta(), 10000);
        await responde(429);
        assert.equal(salta(), 20000);
        await seCae();
        assert.equal(salta(), 40000);
        assert.equal(envios.length, 4);
        assert.deepEqual(envios[3].body.events.map((e) => e.event_id), envios[0].body.events.map((e) => e.event_id), 'el MISMO lote, con los mismos ids: el servidor ignora repetidos');

        await responde(500);

        assert.deepEqual(cola().map((e) => [e.name, e.props]), [['batch_dropped', { count: 10, status: 500 }]], 'se rinde y deja el aviso al frente de la cola');
    });

    test('un 4xx no se reintenta: el lote se suelta a la primera', async () => {
        const { tracker, envios, responde, cola } = montar();

        tracker.install();
        for (let i = 0; i < 9; i++) tracker.track('call_clicked');
        await responde(422);

        assert.equal(envios.length, 1);
        assert.deepEqual(cola().map((e) => e.name), ['batch_dropped']);
    });

    test('un lote que era SOLO el aviso de otro lote perdido no genera otro aviso', async () => {
        const { tracker, responde, salta, cola } = montar();

        tracker.install();
        for (let i = 0; i < 9; i++) tracker.track('call_clicked');
        await responde(400);
        assert.deepEqual(cola().map((e) => e.name), ['batch_dropped']);

        salta();
        await responde(400);

        assert.deepEqual(cola(), []);
    });

    test('al ocultarse la página o descargarse va por `sendBeacon`, como JSON, y la cola se vacía', () => {
        const { tracker, balizas, cola, evento, doc } = montar();

        tracker.install();
        tracker.track('map_clicked');
        doc.visibilityState = 'hidden';
        evento('visibilitychange');

        assert.equal(balizas.length, 1);
        assert.equal(balizas[0].url, '/api/v1/events');
        assert.equal(balizas[0].blob.type, 'application/json');
        assert.deepEqual(cola(), []);

        evento('pagehide');
        assert.equal(balizas.length, 1, 'sin nada que mandar no se manda nada');
    });
});

describe('las props', () => {
    test('se descartan las vacías y el texto se corta a 100 caracteres', () => {
        const { tracker, cola } = montar();

        tracker.install();
        tracker.track('section_viewed', { section: 'x'.repeat(200), vacio: '', nulo: null, nada: undefined, n: 0, si: false });

        const props = cola().at(-1).props;

        assert.equal(props.section.length, 100);
        assert.deepEqual(Object.keys(props), ['section', 'n', 'si']);
    });
});

describe('el cajón', () => {
    test('abrir, cada paso y cerrar, con lo que el controlador anuncia', () => {
        const { tracker, evento, cola } = montar();

        tracker.install();
        evento('jw:cajon:open', { reason: 'deeplink', product: undefined });
        evento('jw:cajon:step', { from: 1, to: 2 });
        evento('jw:cajon:step', { from: 2, to: 3 });
        evento('jw:cajon:close', { reloading: false });

        assert.deepEqual(cola().slice(1).map((e) => [e.name, e.props]), [
            ['drawer_opened', { reason: 'deeplink' }],
            ['step_entered', { from: 1, to: 2 }],
            ['step_entered', { from: 2, to: 3 }],
            ['drawer_closed', { step: 3, reloading: false }],
        ]);
    });

    test('un cierre tras una compra lleva su desenlace, y la siguiente apertura empieza limpia', () => {
        const { tracker, evento, cola } = montar();

        tracker.install();
        evento('jw:cajon:open', { product: 7 });
        evento('jw:cajon:step', { from: 1, to: 6 });
        evento('jw:cajon:purchased', { orderCode: 'R-ABC' });
        evento('jw:cajon:close', { reloading: true });
        evento('jw:cajon:open', {});
        evento('jw:cajon:close', {});

        const cierres = cola().filter((e) => e.name === 'drawer_closed').map((e) => e.props);

        assert.deepEqual(cierres, [
            { step: 6, outcome: 'purchased', reloading: true },
            { step: 6, reloading: false },
        ]);
        assert.deepEqual(cola()[1].props, { reason: 'user', product: 7 });
    });

    test('el buzón de `index.js` se vacía al instalarse, en orden y detrás de la primera vista, y se retira', () => {
        const pending = [['jw:cajon:open', { reason: 'return' }], ['jw:cajon:step', { from: 1, to: 6 }], ['product_chosen', { product: 3 }]];
        const { tracker, win, emitidos, cola } = montar({ pending });
        const buzon = win.JumpWeb.track;

        tracker.install();

        assert.deepEqual(emitidos(), ['page_viewed', 'drawer_opened', 'step_entered', 'product_chosen']);
        assert.equal(cola()[1].props.reason, 'return');
        assert.equal(buzon.pending, null, 'el buzón deja de recoger');
        assert.equal(win.JumpWeb.track, tracker.track, 'y `JumpWeb.track` ya es el emisor de verdad');
    });
});

describe('la landing', () => {
    test('`data-jw-track` cuenta el clic con su nombre; sin atributo, tel:, WhatsApp y mapas se reconocen solos', () => {
        const { tracker, oyentes, emitidos } = montar();

        tracker.install();
        const el = (dataset, selectores) => ({ closest: (s) => (s === '[data-jw-track]' && dataset ? { dataset, tagName: 'A' } : selectores.includes(s) ? {} : null) });

        oyentes.click({ target: el({ jwTrack: 'whatsapp_clicked' }, []) });
        oyentes.click({ target: el(null, ['a[href^="tel:"]']) });
        oyentes.click({ target: el(null, ['a[href*="maps."], a[href*="/maps"]']) });
        oyentes.click({ target: el(null, []) });

        assert.deepEqual(emitidos().slice(1), ['whatsapp_clicked', 'call_clicked', 'map_clicked']);
    });

    test('un formulario con `data-jw-track` cuenta al ENFOCARLO, una sola vez', () => {
        const { tracker, oyentes, emitidos } = montar();
        const form = { dataset: { jwTrack: 'contact_form_started' }, tagName: 'FORM' };
        const dentro = { closest: (s) => (s === 'form[data-jw-track]' ? form : null) };

        tracker.install();
        oyentes.focusin({ target: dentro });
        oyentes.focusin({ target: dentro });
        oyentes.click({ target: { closest: (s) => (s === '[data-jw-track]' ? form : null) } });

        assert.deepEqual(emitidos().slice(1), ['contact_form_started']);
    });

    test('una sección se cuenta cuando ocupa la mitad de sí misma o media pantalla durante 500 ms, una vez', () => {
        const { tracker, observa, timers, cancelados, emitidos, win } = montar();
        let callback = null;
        const vistos = [];
        const olvidados = [];

        win.IntersectionObserver = function (fn, opciones) {
            callback = fn;
            assert.deepEqual(opciones.threshold, [0.1, 0.2, 0.3, 0.4, 0.5]);

            return { observe: (el) => vistos.push(el), unobserve: (el) => olvidados.push(el) };
        };
        const hero = { id: 'hero', dataset: {} };
        const alta = { id: 'zones', dataset: { jwSection: 'zonas' } };

        observa([hero, alta]);
        tracker.install();
        assert.deepEqual(vistos, [hero, alta]);

        // Entra al 60 % y sale antes de los 500 ms: no cuenta.
        callback([{ target: hero, intersectionRatio: 0.6, intersectionRect: { height: 200 } }]);
        callback([{ target: hero, intersectionRatio: 0.2, intersectionRect: { height: 60 } }]);
        assert.equal(cancelados.length, 1);

        // Una sección de 3 pantallas ocupa media pantalla con solo el 17 % de sí misma visible.
        callback([{ target: alta, intersectionRatio: 0.17, intersectionRect: { height: 410 } }]);
        assert.equal(timers.at(-1).ms, 500);
        timers.at(-1).fn();
        callback([{ target: alta, intersectionRatio: 0.3, intersectionRect: { height: 720 } }]);

        assert.deepEqual(emitidos().slice(1), ['section_viewed']);
        assert.deepEqual(olvidados, [alta]);
    });

    test('un fallo de `api.js` se cuenta con su ruta, su estado y si fue la red', () => {
        const { tracker, evento, cola } = montar();

        tracker.install();
        evento('jw:api:failed', { route: '/orders/R-ABC', status: 0, offline: true });

        assert.deepEqual(cola().at(-1).props, { route: '/orders/R-ABC', status: 0, offline: true });
        assert.equal(cola().at(-1).name, 'request_failed');
    });

    test('un error de JS se cuenta por su hash, y como mucho cinco por página', () => {
        const { tracker, evento, cola } = montar();

        tracker.install();
        for (let i = 0; i < 4; i++) evento('error', undefined);
        evento('unhandledrejection', undefined);
        evento('unhandledrejection', undefined);

        const errores = cola().filter((e) => e.name === 'client_error');

        assert.equal(errores.length, 5);
        assert.match(errores[0].props.hash, /^[0-9a-f]+$/);
    });

    test('un cambio de consentimiento cuenta las categorías concedidas', () => {
        const { tracker, evento, cola } = montar();

        tracker.install();
        evento('cookies-updated', { maps: true, social: false, analytics: true });
        evento('cookies-updated', {});

        assert.deepEqual(cola().slice(1).map((e) => e.props), [{ categories: 'maps,analytics' }, { categories: 'none' }]);
    });
});
