import { test, describe } from 'node:test';
import assert from 'node:assert/strict';
import { createDriverLoader, maskPath, maskUrl, POSTHOG_OPTIONS } from './driver.js';

/**
 * T3a·2 de la analítica — el cargador del driver (`docs/specs/analitica.md` §4.3).
 *
 * Sin navegador: `win` y `doc` son fakes. Lo que se fija es el GATE —no carga sin driver, sin la categoría
 * `analytics` ni en una URL con credenciales— y lo que manda a la herramienta: la configuración de la spec,
 * los mismos eventos que el libro con la ruta enmascarada, la persona opaca solo si el servidor la puso, y el
 * `opt_out` al retirar el consentimiento.
 */

function montar({ dataset = {}, pathname = '/entradas', search = '', posthog = null, paq = null } = {}) {
    const oyentes = { doc: {}, win: {} };
    const scripts = [];
    const calls = [];
    const ph = posthog ?? {
        init: (key, opts) => calls.push(['init', key, opts]),
        capture: (name, props) => calls.push(['capture', name, props]),
        identify: (id) => calls.push(['identify', id]),
        opt_out_capturing: () => calls.push(['opt_out']),
        opt_in_capturing: () => calls.push(['opt_in']),
    };
    const head = { appendChild: (s) => scripts.push(s) };
    const doc = {
        body: { dataset },
        head,
        referrer: 'https://localhost:8081/reservas/123',
        createElement: () => ({}),
        addEventListener: (tipo, fn) => { oyentes.doc[tipo] = fn; },
    };
    const win = {
        location: { pathname, search, href: `https://localhost:8081${pathname}${search}` },
        addEventListener: (tipo, fn) => { oyentes.win[tipo] = fn; },
        posthog: ph,
    };
    if (paq) win._paq = paq;

    const loader = createDriverLoader({ win, doc });

    return {
        loader, win, doc, scripts, calls, oyentes,
        /** Simula que el script del driver ha cargado. */
        cargado: () => scripts.at(-1)?.onload?.(),
        tracked: (name, props) => oyentes.doc['jw:tracked']?.({ detail: { name, props } }),
        consent: (detail) => oyentes.win['cookies-updated']?.({ detail }),
    };
}

const POSTHOG = { analyticsDriver: 'posthog', analyticsKey: 'phc_abcdefghijklmnopqrstuvwxyz', analyticsHost: 'https://eu.i.posthog.com' };

describe('el gate', () => {
    test('sin driver en el body no se pide nada, ni con la categoría consentida', () => {
        const { loader, scripts } = montar({ dataset: { cookieAnalytics: '1' } });
        loader.install();

        assert.equal(scripts.length, 0);
        assert.equal(loader.state(), 'idle');
    });

    test('con driver pero sin la categoría, nada: el gate real es no inyectar el script', () => {
        const { loader, scripts } = montar({ dataset: { ...POSTHOG, cookieAnalytics: '' } });
        loader.install();

        assert.equal(scripts.length, 0);
    });

    test('sin ningún dato de cookies (una página ajena) todo es «no consentido»', () => {
        const { loader, scripts } = montar({ dataset: { ...POSTHOG } });
        loader.install();

        assert.equal(scripts.length, 0);
    });

    test('con driver y categoría se inyecta el script del host, async, y arranca con la configuración de la spec', () => {
        const { loader, scripts, calls, cargado } = montar({ dataset: { ...POSTHOG, cookieAnalytics: '1' } });
        loader.install();

        assert.equal(scripts.length, 1);
        assert.equal(scripts[0].src, 'https://eu.i.posthog.com/static/array.js');
        assert.equal(scripts[0].async, true);
        assert.equal(loader.state(), 'loading');

        cargado();
        assert.equal(loader.state(), 'on');
        assert.equal(calls[0][0], 'init');
        assert.equal(calls[0][1], 'phc_abcdefghijklmnopqrstuvwxyz');
        const opts = calls[0][2];
        assert.equal(opts.api_host, 'https://eu.i.posthog.com');
        assert.equal(opts.person_profiles, 'identified_only');
        assert.equal(opts.capture_pageview, false, 'sin vista automática: la URL cruda no sale de aquí');
        assert.equal(opts.autocapture, false);
        assert.equal(opts.ip, false);
        assert.equal(opts.session_recording.maskAllInputs, true, 'entradas enmascaradas');
        assert.equal(typeof opts.sanitize_properties, 'function');
    });

    test('en una URL con credenciales no carga, aunque haya driver y categoría', () => {
        for (const pathname of ['/invitacion/01HZX8K4N2P7Q9R3S5T6V8W0YA', '/reservas/123/invitados']) {
            const { loader, scripts } = montar({ dataset: { ...POSTHOG, cookieAnalytics: '1' }, pathname });
            loader.install();
            assert.equal(scripts.length, 0, pathname);
        }
        const firmado = montar({ dataset: { ...POSTHOG, cookieAnalytics: '1' }, pathname: '/mis-reservas', search: '?expires=1&signature=abc' });
        firmado.loader.install();
        assert.equal(firmado.scripts.length, 0);
    });

    test('si el script falla, se vuelve a idle y se puede reintentar', () => {
        const { loader, scripts } = montar({ dataset: { ...POSTHOG, cookieAnalytics: '1' } });
        loader.install();
        scripts[0].onerror();

        assert.equal(loader.state(), 'idle');
    });
});

describe('lo que se manda', () => {
    test('los mismos eventos que el libro, con la ruta enmascarada; antes de cargar, nada', () => {
        const { loader, calls, cargado, tracked } = montar({ dataset: { ...POSTHOG, cookieAnalytics: '1' }, pathname: '/reservas' });
        loader.install();

        tracked('page_viewed', { locale: 'es' });
        assert.equal(calls.length, 0, 'antes del onload no hay herramienta');

        cargado();
        tracked('drawer_opened', { reason: 'user' });
        const captura = calls.find((c) => c[0] === 'capture');
        assert.deepEqual(captura, ['capture', 'drawer_opened', { reason: 'user', route: '/reservas' }]);
    });

    test('la persona opaca se identifica SOLO si el servidor la puso', () => {
        const sin = montar({ dataset: { ...POSTHOG, cookieAnalytics: '1' } });
        sin.loader.install();
        sin.cargado();
        assert.equal(sin.calls.some((c) => c[0] === 'identify'), false);

        const con = montar({ dataset: { ...POSTHOG, cookieAnalytics: '1', analyticsPerson: 'a1b2c3' } });
        con.loader.install();
        con.cargado();
        assert.deepEqual(con.calls.find((c) => c[0] === 'identify'), ['identify', 'a1b2c3']);
    });

    test('sanitize_properties enmascara la URL, el path y el referer que la herramienta añade sola', () => {
        const { loader, calls, cargado } = montar({ dataset: { ...POSTHOG, cookieAnalytics: '1' } });
        loader.install();
        cargado();
        const sanitize = calls[0][2].sanitize_properties;

        const out = sanitize({
            $current_url: 'https://localhost:8081/reservas/123?signature=abc',
            $pathname: '/reservas/123',
            $referrer: 'https://localhost:8081/invitacion/01HZX8K4N2P7Q9R3S5T6V8W0YA',
            $initial_current_url: 'https://localhost:8081/?utm_source=x',
            other: 'stays',
        });

        assert.equal(out.$current_url, 'https://localhost:8081/reservas/{n}');
        assert.equal(out.$pathname, '/reservas/{n}');
        assert.equal(out.$referrer, 'https://localhost:8081/invitacion/{token}');
        assert.equal(out.$initial_current_url, 'https://localhost:8081/');
        assert.equal(out.other, 'stays');
    });

    test('retirar la categoría para de capturar; concederla de nuevo, vuelve', () => {
        const { loader, calls, cargado, tracked, consent } = montar({ dataset: { ...POSTHOG, cookieAnalytics: '1' } });
        loader.install();
        cargado();

        consent({ analytics: false });
        assert.equal(loader.state(), 'off');
        assert.equal(calls.at(-1)[0], 'opt_out');
        tracked('section_viewed', { section: 'x' });
        assert.equal(calls.some((c) => c[0] === 'capture'), false, 'apagado no captura');

        consent({ analytics: true });
        assert.equal(loader.state(), 'on');
        assert.equal(calls.at(-1)[0], 'opt_in');
    });

    test('conceder la categoría DESPUÉS de cargar la página trae el driver entonces', () => {
        const { loader, scripts, consent } = montar({ dataset: { ...POSTHOG, cookieAnalytics: '' } });
        loader.install();
        assert.equal(scripts.length, 0);

        consent({ analytics: true, maps: false });
        assert.equal(scripts.length, 1);
    });
});

describe('matomo', () => {
    test('la configuración va en _paq ANTES del script, con la URL enmascarada; los eventos, como trackEvent', () => {
        const paq = [];
        const { loader, scripts, cargado, tracked } = montar({
            dataset: { analyticsDriver: 'matomo', analyticsKey: '7', analyticsHost: 'https://stats.parque.es', cookieAnalytics: '1', analyticsPerson: 'p1' },
            pathname: '/reservas/42',
            paq,
        });
        loader.install();

        // ⚠️ `/reservas/42` lleva un número: es una URL «sensible» y NO carga. Se prueba con una limpia.
        assert.equal(scripts.length, 0);

        const limpio = montar({
            dataset: { analyticsDriver: 'matomo', analyticsKey: '7', analyticsHost: 'https://stats.parque.es', cookieAnalytics: '1', analyticsPerson: 'p1' },
            pathname: '/entradas',
            paq: [],
        });
        limpio.loader.install();
        assert.deepEqual(limpio.win._paq[0], ['setTrackerUrl', 'https://stats.parque.es/matomo.php']);
        assert.deepEqual(limpio.win._paq[1], ['setSiteId', '7']);
        assert.deepEqual(limpio.win._paq[2], ['setCustomUrl', 'https://localhost:8081/entradas']);
        assert.deepEqual(limpio.win._paq[3], ['setReferrerUrl', 'https://localhost:8081/reservas/{n}']);
        assert.equal(limpio.scripts[0].src, 'https://stats.parque.es/matomo.js');

        limpio.cargado();
        assert.deepEqual(limpio.win._paq.at(-1), ['setUserId', 'p1']);
        limpio.tracked('drawer_opened', { reason: 'user' });
        assert.deepEqual(limpio.win._paq.at(-1), ['trackEvent', 'jumpweb', 'drawer_opened', '/entradas']);
        void cargado; void tracked;
    });
});

describe('las máscaras', () => {
    test('maskPath sigue la regla del servidor', () => {
        assert.equal(maskPath('/reservas/123/invitados'), '/reservas/{n}/invitados');
        assert.equal(maskPath('/invitacion/01HZX8K4N2P7Q9R3S5T6V8W0YA'), '/invitacion/{token}');
        assert.equal(maskPath('/restablecer-contrasena-ahora-mismo'), '/restablecer-contrasena-ahora-mismo', 'un slug largo sin cifras es una página');
        assert.equal(maskPath('/'), '/');
    });

    test('maskUrl quita la query y el fragmento y enmascara el path; lo ilegible es vacío', () => {
        assert.equal(maskUrl('https://a.b/c/99?token=x#y'), 'https://a.b/c/{n}');
        assert.equal(maskUrl('no es una url'), '');
    });

    test('las opciones de PostHog no cambian sin que este test lo vea', () => {
        assert.deepEqual(Object.keys(POSTHOG_OPTIONS).sort(), ['autocapture', 'capture_heatmaps', 'capture_pageleave', 'capture_pageview', 'capture_performance', 'disable_surveys', 'ip', 'person_profiles', 'respect_dnt', 'session_recording']);
    });
});
