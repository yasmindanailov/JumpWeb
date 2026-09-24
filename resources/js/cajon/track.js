/**
 * **EL ÚNICO EMISOR DE LA ANALÍTICA** (`docs/specs/analitica.md` §4.2, `DECISIONES #678`).
 *
 * Es un trozo DIFERIDO: `cajon/index.js` lo trae con `import()` tras `load`, en un rato ocioso, así que llega a
 * las DOS entradas (`app.js` y `/cajon/paquete.js`) sin pesar en sus presupuestos; el suyo —min+gzip— lo vigila
 * `SidebarBundleBudgetTest`. Lo que pase ANTES de que llegue —el cajón que nace abierto, su primer paso, un
 * `JumpWeb.track()` temprano— lo recoge el buzón que `index.js` deja en `window.JumpWeb.track.pending`, y aquí
 * se vacía al instalarse, en orden, detrás de la primera vista.
 *
 * Lo que emite, y nada más (`AnalyticsContractTest` compara estos nombres con `Contract::EVENTS`):
 *  · `page_viewed` — la ruta, el idioma y, en la PRIMERA vista de la pestaña, la entrada y el host del referer;
 *    las `utm_*`, `ref` y los click ids siempre que vengan en la URL (una vuelta desde un anuncio es real);
 *  · `section_viewed` — cada `<section id>` (o `[data-jw-section]`) que ocupe la mitad de sí misma o media
 *    pantalla durante 500 ms, una vez por página;
 *  · el nombre que diga **`data-jw-track="…"`** al hacer clic —o al ENFOCAR, si va en un `<form>`, una vez—, y
 *    sin marcado los enlaces `tel:` (`call_clicked`), de WhatsApp (`whatsapp_clicked`) y de mapas (`map_clicked`);
 *  · `drawer_opened` · `step_entered` · `drawer_closed`, oyendo lo que el controlador anuncia (`jw:cajon:*`);
 *  · `request_failed` cuando `api.js` no consigue una respuesta buena (`jw:api:failed`);
 *  · `client_error` (un hash, cinco por página) · `consent_updated` · `batch_dropped`.
 *
 * ⚠️ Cola en `sessionStorage` (sobrevive a la navegación; en memoria si no hay almacén), envío cada 5 s o a los
 * 10 eventos con `keepalive`, y `sendBeacon` al ocultarse la página. Ante 429, 5xx o red reintenta 3 veces
 * doblando la espera y después SUELTA el lote y lo cuenta (`batch_dropped`) al frente del siguiente; un 4xx no
 * se reintenta. El mismo lote dos veces es UNA fila: el `event_id` es un ULID y el servidor ignora repetidos.
 * La ruta es stateless: sin CSRF, y la cookie del visitante viaja sola (o la acuña el `202`).
 *
 * ⚠️ Con `navigator.webdriver` SE EMITE IGUAL, marcado en `meta.webdriver`: el servidor lo guarda como bot y el
 * panel no lo cuenta — así la sonda verifica el camino entero (spec §6) sin ensuciar las cifras.
 * ⚠️ Ningún dato personal: aquí no viaja ni un campo de formulario ni un texto de error (solo su hash), y todo
 * valor de texto se corta a 100 caracteres. El servidor vacía además lo que parezca correo o teléfono.
 */

const ENDPOINT = '/api/v1/events';
const QUEUE = 'jw:ev';
const SEEN = 'jw:ev:seen';
const FLUSH_MS = 5000;
const FLUSH_AT = 10;
const BATCH_MAX = 50;
const RETRIES = 3;
const ERRORS_MAX = 5;
const VALUE_MAX = 100;
const ATTRIBUTION = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term', 'ref', 'gclid', 'fbclid', 'ttclid'];
/** Los clics que se reconocen SIN marcado; `data-jw-track` manda si está. */
const AUTO_CLICKS = [
    ['a[href^="tel:"]', 'call_clicked'],
    ['a[href*="wa.me"], a[href*="whatsapp.com"]', 'whatsapp_clicked'],
    ['a[href*="maps."], a[href*="/maps"]', 'map_clicked'],
];
const B32 = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

/** Un ULID: 10 caracteres de tiempo y 16 aleatorios, en Crockford — lo que `Visitor::isValid` admite. */
export function ulid(ms, bytes) {
    let t = ms;
    let out = '';

    for (let i = 0; i < 10; i++) {
        out = B32[t % 32] + out;
        t = Math.floor(t / 32);
    }

    for (let i = 0; i < 16; i++) out += B32[bytes[i] & 31];

    return out;
}

/** Un hash corto y estable (djb2) para `client_error`: se compara, no se lee. */
export function hash(text) {
    let h = 5381;

    for (let i = 0; i < text.length; i++) h = ((h * 33) ^ text.charCodeAt(i)) >>> 0;

    return h.toString(16);
}

/**
 * @param {{win: Window, doc?: Document, nav?: Navigator, fetch?: Function, now?: () => number,
 *   bytes?: (n: number) => Uint8Array, setTimer?: Function, clearTimer?: Function}} deps
 *   Todo por parámetro (`CE-6`): es lo que deja probarlo con `node --test` sin un navegador.
 */
export function createTracker({
    win,
    doc = win.document,
    nav = win.navigator,
    fetch = (url, init) => win.fetch(url, init),
    now = () => Date.now(),
    bytes = (n) => win.crypto.getRandomValues(new Uint8Array(n)),
    setTimer = (fn, ms) => win.setTimeout(fn, ms),
    clearTimer = (id) => win.clearTimeout(id),
}) {
    let queue = read();
    let timer = null;
    let inflight = false;
    let retries = 0;
    let errors = 0;
    let step = 1;
    let purchased = false;

    function read() {
        try {
            return JSON.parse(win.sessionStorage.getItem(QUEUE)) || [];
        } catch {
            return [];
        }
    }

    function save() {
        try {
            win.sessionStorage.setItem(QUEUE, JSON.stringify(queue));
        } catch {
            // Sin almacén (modo privado, cuota): la cola vive en memoria y se pierde al navegar.
        }
    }

    function event(name, props) {
        const clean = {};

        for (const key in props) {
            const value = props[key];

            if (value === undefined || value === null || value === '') continue;

            clean[key] = typeof value === 'string' ? value.slice(0, VALUE_MAX) : value;
        }

        return { event_id: ulid(now(), bytes(16)), name, route: win.location.pathname, props: clean, occurred_at: now() };
    }

    function track(name, props = {}) {
        const e = event(name, props);

        queue.push(e);
        save();

        // El mismo hecho, para el driver de análisis (`driver.js`, T3a·2): lo oye solo si está cargado.
        if (doc.dispatchEvent && win.CustomEvent) doc.dispatchEvent(new win.CustomEvent('jw:tracked', { detail: { name, props: e.props } }));

        if (queue.length >= FLUSH_AT) flush();
        else schedule(FLUSH_MS);
    }

    function schedule(ms) {
        if (timer !== null) return;

        timer = setTimer(() => {
            timer = null;
            flush();
        }, ms);
    }

    function meta() {
        const consent = {};
        const data = doc.body?.dataset ?? {};

        for (const key in data) {
            const m = /^cookie(?!Enabled$|Decided$|Endpoint$)([A-Z]\w*)$/.exec(key);

            if (m) consent[m[1].toLowerCase()] = data[key] === '1';
        }

        return { webdriver: !! nav.webdriver, consent };
    }

    /** Envía la cabeza de la cola. `final` = la página se va: `sendBeacon`, que no espera a nadie. */
    function flush(final = false) {
        if (! queue.length || (inflight && ! final)) return;

        // El envío por número de eventos ADELANTA al del reloj: el temporizador que hubiera se retira, o el
        // reintento que viene detrás se programaría en vano (`schedule` no dobla un temporizador vivo).
        if (timer !== null) {
            clearTimer(timer);
            timer = null;
        }

        const events = queue.slice(0, BATCH_MAX);
        const body = JSON.stringify({ events, meta: meta() });

        if (final) {
            if (nav.sendBeacon?.(ENDPOINT, new Blob([body], { type: 'application/json' }))) sent(events);

            return;
        }

        inflight = true;
        fetch(ENDPOINT, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
            body,
            credentials: 'same-origin',
            keepalive: true,
        }).then((res) => settle(events, res.status), () => settle(events, 0));
    }

    function settle(events, status) {
        inflight = false;

        if (status >= 200 && status < 300) {
            retries = 0;
            sent(events);
        } else if ((status === 0 || status === 429 || status >= 500) && retries < RETRIES) {
            retries++;
            schedule(FLUSH_MS * 2 ** retries);

            return;
        } else {
            retries = 0;
            drop(events, status);
        }

        if (queue.length) schedule(FLUSH_MS);
    }

    /** Se quita por `event_id`, no por posición: mientras viajaba pudieron entrar más al final. */
    function sent(events) {
        const ids = new Set(events.map((e) => e.event_id));

        queue = queue.filter((e) => ! ids.has(e.event_id));
        save();
    }

    function drop(events, status) {
        sent(events);

        // Un lote que era SOLO el aviso de otro lote perdido no genera otro aviso: sin esto, un servidor caído
        // haría crecer la cola con avisos de avisos.
        const count = events.filter((e) => e.name !== 'batch_dropped').length;

        if (count) {
            queue.unshift(event('batch_dropped', { count, status }));
            save();
        }
    }

    function pageView() {
        const loc = win.location;
        const query = new URLSearchParams(loc.search);
        const props = { locale: doc.documentElement?.lang };
        let first = true;

        try {
            first = ! win.sessionStorage.getItem(SEEN);
            win.sessionStorage.setItem(SEEN, '1');
        } catch {
            // Sin almacén, toda vista es la primera: mejor una entrada de más que una sesión sin entrada.
        }

        if (first) {
            props.entry = loc.pathname;
            const host = (doc.referrer || '').split('/')[2];

            if (host && host !== loc.host) props.referrer_host = host;
        }

        for (const key of ATTRIBUTION) props[key] = query.get(key);

        track('page_viewed', props);
    }

    function sections() {
        const Observer = win.IntersectionObserver;

        if (! Observer) return;

        const timers = new Map();
        const observer = new Observer((entries) => {
            for (const { target: el, intersectionRatio: ratio, intersectionRect: rect } of entries) {
                // Una sección más alta que la pantalla nunca llega al 50 % de sí misma: cuenta cuando ocupa
                // media pantalla.
                const visible = ratio >= 0.5 || rect.height >= win.innerHeight / 2;

                if (visible && ! timers.has(el)) {
                    timers.set(el, setTimer(() => {
                        observer.unobserve(el);
                        track('section_viewed', { section: el.dataset.jwSection || el.id });
                    }, 500));
                } else if (! visible && timers.has(el)) {
                    clearTimer(timers.get(el));
                    timers.delete(el);
                }
            }
        }, { threshold: [0.1, 0.2, 0.3, 0.4, 0.5] });

        doc.querySelectorAll('section[id], [data-jw-section]').forEach((el) => observer.observe(el));
    }

    function onCajon(type, detail = {}) {
        if (type === 'open') {
            purchased = false;
            track('drawer_opened', { reason: detail.reason || 'user', product: detail.product });
        } else if (type === 'step') {
            step = detail.to;
            track('step_entered', { from: detail.from, to: detail.to });
        } else if (type === 'purchased') {
            purchased = true;
        } else if (type === 'close') {
            track('drawer_closed', { step, outcome: purchased ? 'purchased' : undefined, reloading: !! detail.reloading });
        }
    }

    function onClick(e) {
        const target = e.target;
        const marked = target?.closest?.('[data-jw-track]');

        if (marked && marked.tagName !== 'FORM') return track(marked.dataset.jwTrack);

        for (const [selector, name] of AUTO_CLICKS) {
            if (target?.closest?.(selector)) return track(name);
        }
    }

    function onFocus(e) {
        const form = e.target?.closest?.('form[data-jw-track]');

        if (! form || form.dataset.jwTracked === '1') return;

        form.dataset.jwTracked = '1';
        track(form.dataset.jwTrack);
    }

    function onError(text) {
        if (errors++ < ERRORS_MAX) track('client_error', { hash: hash(text) });
    }

    function install() {
        const stub = win.JumpWeb?.track;

        win.JumpWeb = { ...(win.JumpWeb ?? {}), track };

        for (const type of ['open', 'step', 'purchased', 'close']) {
            doc.addEventListener(`jw:cajon:${type}`, (e) => onCajon(type, e.detail));
        }
        doc.addEventListener('jw:api:failed', (e) => track('request_failed', { route: e.detail?.route, status: e.detail?.status, offline: !! e.detail?.offline }));
        doc.addEventListener('click', onClick);
        doc.addEventListener('focusin', onFocus);
        doc.addEventListener('visibilitychange', () => {
            if (doc.visibilityState === 'hidden') flush(true);
        });
        win.addEventListener('pagehide', () => flush(true));
        win.addEventListener('error', (e) => onError(`${e.message}|${e.filename}|${e.lineno}`));
        win.addEventListener('unhandledrejection', (e) => onError(String(e.reason?.message ?? e.reason)));
        win.addEventListener('cookies-updated', (e) => {
            const prefs = e.detail || {};

            track('consent_updated', { categories: Object.keys(prefs).filter((k) => prefs[k]).join(',') || 'none' });
        });

        pageView();

        // El buzón: lo que el cajón anunció y lo que alguien quiso contar antes de que este trozo llegara.
        for (const [type, detail] of stub?.pending ?? []) {
            if (type.startsWith('jw:cajon:')) onCajon(type.slice(9), detail);
            else track(type, detail);
        }
        if (stub) stub.pending = null;

        sections();

        // La herramienta de análisis (T3a·2): otro trozo diferido, y solo si el `<body>` dice que hay driver.
        // Ella misma comprueba la categoría `analytics`; que no cargue no es un fallo del tracker.
        if (doc.body?.dataset?.analyticsDriver) import('./driver.js').then((m) => m.installDriver({ win, doc })).catch(() => {});

        return api;
    }

    const api = { track, flush, install };

    return api;
}

/** Lo que `index.js` llama al traer el trozo. */
export function installTracker(deps) {
    return createTracker(deps).install();
}
