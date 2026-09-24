/**
 * **EL CARGADOR DE LA HERRAMIENTA DE ANÁLISIS** (`docs/specs/analitica.md` §4.3, T3a·2; `DECISIONES #678`).
 *
 * Trae PostHog o Matomo —el que la instalación eligió en «Ajustes», publicado por el servidor en el `<body>`
 * como `data-analytics-*`— y SOLO si el visitante consintió la categoría `analytics` (`data-cookie-analytics="1"`)
 * o la concede después (`cookies-updated`). Es un trozo DIFERIDO que el tracker (`track.js`) pide al instalarse,
 * y únicamente cuando el `<body>` dice que hay driver: una página sin driver no descarga ni un byte de esto.
 *
 * Lo que hace, y nada más:
 *  · inyecta el script del driver `async` desde su host y lo arranca con la configuración de la spec: perfiles
 *    solo de personas IDENTIFICADAS, sin vista automática, sin autocaptura, sin IP, entradas de formulario
 *    enmascaradas en la grabación;
 *  · reenvía los MISMOS eventos que `track()` emite (`jw:tracked`), con la ruta enmascarada como en el libro
 *    (`RouteNormalizer`: `{n}`, `{token}`), y SANEA lo que la herramienta añade sola (`$current_url`, `$pathname`,
 *    `$referrer`): una URL con un token no sale de aquí;
 *  · identifica a la persona con el id OPACO que puso el servidor (`data-analytics-person`, un HMAC), solo si
 *    está —o sea, solo con sesión y con la categoría consentida—;
 *  · al retirar la categoría deja de capturar (`opt_out`), y al concederla de nuevo vuelve.
 *
 * ⚠️ **No carga en una página con credenciales en la URL** (`/invitacion/{token}`, un enlace firmado): aunque
 * la ruta se enmascare, la grabación de sesión vería la barra de direcciones. Tampoco sin `<body>` con datos
 * (una página ajena, F4): sin dato, todo es «no consentido».
 * ⚠️ Todo por parámetro (`CE-6`): `win` y `doc` llegan de fuera y `driver.test.js` lo prueba sin navegador.
 */

const TOKEN_MIN = 20;

/** Las opciones con las que arranca PostHog: la spec §4.3, en código. */
export const POSTHOG_OPTIONS = Object.freeze({
    person_profiles: 'identified_only',
    autocapture: false,
    capture_pageview: false,
    capture_pageleave: false,
    capture_performance: false,
    capture_heatmaps: false,
    disable_surveys: true,
    ip: false,
    respect_dnt: true,
    session_recording: { maskAllInputs: true },
});

/** La ruta como patrón, la misma regla que `RouteNormalizer::path()`: `{n}` para los números, `{token}` para lo opaco. */
export function maskPath(path) {
    return String(path || '/').split('/').map((segment) => {
        if (segment === '') return segment;
        if (/^\d+$/.test(segment)) return '{n}';
        if (segment.length >= TOKEN_MIN && /^[A-Za-z0-9_\-.=]+$/.test(segment) && /\d/.test(segment) && /[A-Za-z]/.test(segment)) return '{token}';

        return segment;
    }).join('/');
}

/** Una URL entera, con el path enmascarado y SIN query ni fragmento (ahí viajan firmas y correos). */
export function maskUrl(url) {
    try {
        const u = new URL(url);

        return `${u.origin}${maskPath(u.pathname)}`;
    } catch {
        return '';
    }
}

/**
 * @param {{win: Window, doc?: Document}} deps
 */
export function createDriverLoader({ win, doc = win.document }) {
    let state = 'idle';   // idle → loading → on ⇄ off
    let config = null;
    // La categoría concedida DESPUÉS de cargar la página llega por `cookies-updated`, no por el `<body>` (que
    // solo cambia al recargar): se recuerda aquí.
    let granted = false;

    const data = () => doc.body?.dataset ?? {};
    const consented = () => granted || data().cookieAnalytics === '1';

    function read() {
        const d = data();

        if (! d.analyticsDriver || ! d.analyticsKey || ! d.analyticsHost) return null;

        return { driver: d.analyticsDriver, key: d.analyticsKey, host: d.analyticsHost, person: d.analyticsPerson || null };
    }

    /** Una URL con credenciales: un segmento opaco en el path o una firma/token en la query. */
    function sensitive() {
        const path = win.location?.pathname ?? '/';
        const search = win.location?.search ?? '';

        return maskPath(path) !== path || /[?&](signature|token|expires)=/i.test(search);
    }

    function sanitize(properties) {
        const out = { ...properties };

        for (const key of ['$current_url', '$referrer', '$initial_referrer', '$initial_current_url']) {
            if (typeof out[key] === 'string') out[key] = maskUrl(out[key]);
        }
        for (const key of ['$pathname', '$initial_pathname']) {
            if (typeof out[key] === 'string') out[key] = maskPath(out[key]);
        }

        return out;
    }

    function inject(src, onload) {
        const script = doc.createElement('script');
        script.async = true;
        script.src = src;
        script.onload = onload;
        script.onerror = () => { state = 'idle'; };
        (doc.head ?? doc.body).appendChild(script);
    }

    function start() {
        state = 'on';

        if (config.driver === 'posthog') {
            win.posthog?.init?.(config.key, { api_host: config.host, ...POSTHOG_OPTIONS, sanitize_properties: sanitize });
        }
        if (config.person) identify(config.person);
    }

    function load() {
        if (state !== 'idle') return;

        config = read();

        if (! config || ! consented() || sensitive()) return;

        state = 'loading';

        if (config.driver === 'matomo') {
            // Matomo lee `_paq` al cargar: la configuración va ANTES del script.
            const q = win._paq = win._paq || [];
            q.push(['setTrackerUrl', `${config.host}/matomo.php`]);
            q.push(['setSiteId', config.key]);
            q.push(['setCustomUrl', maskUrl(win.location.href)]);
            q.push(['setReferrerUrl', maskUrl(doc.referrer ?? '')]);
            inject(`${config.host}/matomo.js`, start);

            return;
        }

        inject(`${config.host}/static/array.js`, start);
    }

    function identify(person) {
        if (state !== 'on' || ! person) return;

        if (config.driver === 'posthog') win.posthog?.identify?.(person);
        else win._paq?.push(['setUserId', person]);
    }

    /** El mismo evento que fue al libro, con la ruta enmascarada; nada que el libro no tenga. */
    function capture(name, props = {}) {
        if (state !== 'on') return;

        const route = maskPath(win.location?.pathname ?? '/');

        if (config.driver === 'posthog') win.posthog?.capture?.(name, { ...props, route });
        else win._paq?.push(['trackEvent', 'jumpweb', name, route]);
    }

    function stop() {
        if (state !== 'on') return;

        state = 'off';
        if (config.driver === 'posthog') win.posthog?.opt_out_capturing?.();
        else win._paq?.push(['optUserOut']);
    }

    function resume() {
        if (state !== 'off') return;

        state = 'on';
        if (config.driver === 'posthog') win.posthog?.opt_in_capturing?.();
        else win._paq?.push(['forgetUserOptOut']);
    }

    function onConsent(detail) {
        if (! detail) return;

        granted = !! detail.analytics;

        if (granted) {
            if (state === 'off') resume();
            else load();
        } else {
            stop();
        }
    }

    function install() {
        doc.addEventListener('jw:tracked', (e) => capture(e.detail?.name, e.detail?.props));
        win.addEventListener('cookies-updated', (e) => onConsent(e.detail));
        load();

        return api;
    }

    const api = { install, load, capture, stop, resume, state: () => state, config: () => config };

    return api;
}

/** Lo que `track.js` llama al traer el trozo. */
export function installDriver(deps) {
    return createDriverLoader(deps).install();
}
