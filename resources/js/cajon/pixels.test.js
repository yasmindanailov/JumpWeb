import { test, describe } from 'node:test';
import assert from 'node:assert/strict';
import { CONSENT_DEFAULT, CONSENT_GRANTED, GTAG_SRC, META_SRC, TIKTOK_SRC, createPixelsLoader, toValue } from './pixels.js';

/**
 * T3b·1 de la analítica — el cargador de los píxeles (`docs/specs/analitica.md` §4.3).
 *
 * Sin navegador: `win` y `doc` son fakes. Lo que se fija es el GATE —no carga sin píxel configurado, sin la
 * categoría `marketing` ni en una URL con credenciales—, el Consent Mode BÁSICO de gtag (`default` todo denegado,
 * `update` solo lo de anuncios, `analytics_storage` nunca), los tres hechos por plataforma con el código del
 * pedido como id de la compra, y la retirada.
 */

function montar({ dataset = {}, pathname = '/entradas', search = '' } = {}) {
    const oyentes = { doc: {}, win: {} };
    const scripts = [];
    const doc = {
        body: { dataset },
        head: { appendChild: (s) => scripts.push(s) },
        createElement: () => ({}),
        addEventListener: (tipo, fn) => { oyentes.doc[tipo] = fn; },
    };
    const almacen = new Map();
    const win = {
        location: { pathname, search, href: `https://localhost:8081${pathname}${search}` },
        addEventListener: (tipo, fn) => { oyentes.win[tipo] = fn; },
        sessionStorage: { getItem: (k) => almacen.get(k) ?? null, setItem: (k, v) => almacen.set(k, v) },
    };
    const loader = createPixelsLoader({ win, doc });

    return {
        loader, win, doc, scripts, oyentes, almacen,
        srcs: () => scripts.map((s) => s.src),
        gtag: () => (win.dataLayer ?? []).map((args) => Array.from(args)),
        fbq: () => (win.fbq?.queue ?? []).map((args) => Array.from(args)),
        ttq: () => win.ttq?._q ?? [],
        tracked: (name, props) => oyentes.doc['jw:tracked']?.({ detail: { name, props } }),
        purchased: (detail) => oyentes.doc['jw:cajon:purchased']?.({ detail }),
        consent: (detail) => oyentes.win['cookies-updated']?.({ detail }),
    };
}

const TODOS = { pixelGoogleAds: 'AW-123456789/AbCdEfGh', pixelMeta: '1234567890123456', pixelTiktok: 'C9ABCDEFGHIJKLMNOPQR' };

describe('el gate', () => {
    test('sin píxel en el body no se pide nada, ni con la categoría', () => {
        const { loader, scripts } = montar({ dataset: { cookieMarketing: '1' } });
        loader.install();

        assert.equal(scripts.length, 0);
        assert.equal(loader.state(), 'idle');
    });

    test('con píxeles pero sin la categoría «marketing», nada: ni gtag en el DOM', () => {
        const { loader, scripts, win } = montar({ dataset: { ...TODOS, cookieAnalytics: '1' } });
        loader.install();

        assert.equal(scripts.length, 0);
        assert.equal(win.dataLayer, undefined, 'Consent Mode BÁSICO: gtag no existe sin marketing');
        assert.equal(win.fbq, undefined);
        assert.equal(win.ttq, undefined);
    });

    test('en una URL con credenciales no carga aunque la categoría esté', () => {
        for (const url of [{ pathname: '/invitacion/AbCdEf1234567890XyZ12345' }, { pathname: '/reservas/7', search: '?signature=abc' }]) {
            const { loader, scripts } = montar({ dataset: { ...TODOS, cookieMarketing: '1' }, ...url });
            loader.install();

            assert.equal(scripts.length, 0, JSON.stringify(url));
        }
    });

    test('con la categoría concedida, los tres scripts llegan desde sus hosts', () => {
        const m = montar({ dataset: { ...TODOS, cookieMarketing: '1' } });
        m.loader.install();

        assert.equal(m.loader.state(), 'on');
        assert.deepEqual(m.srcs(), [`${GTAG_SRC}?id=AW-123456789`, META_SRC, `${TIKTOK_SRC}?sdkid=C9ABCDEFGHIJKLMNOPQR&lib=ttq`]);
        assert.ok(m.scripts.every((s) => s.async === true));
    });

    test('solo carga los píxeles configurados', () => {
        const m = montar({ dataset: { pixelMeta: '1234567890123456', cookieMarketing: '1' } });
        m.loader.install();

        assert.deepEqual(m.srcs(), [META_SRC]);
        assert.equal(m.win.dataLayer, undefined);
    });
});

describe('Consent Mode v2 básico', () => {
    test('el default va todo denegado ANTES del config, y el update concede solo lo de anuncios', () => {
        const m = montar({ dataset: { pixelGoogleAds: 'AW-123456789', cookieMarketing: '1' } });
        m.loader.install();

        const llamadas = m.gtag();
        assert.deepEqual(llamadas[0], ['consent', 'default', CONSENT_DEFAULT]);
        assert.deepEqual(llamadas[1], ['consent', 'update', CONSENT_GRANTED]);
        assert.equal(llamadas[2][0], 'js');
        assert.deepEqual(llamadas[3], ['config', 'AW-123456789', { allow_google_signals: false, allow_ad_personalization_signals: true }]);
        assert.equal(CONSENT_GRANTED.analytics_storage, 'denied', 'la medición es del libro propio, nunca de Google');
    });

    test('retirar la categoría deniega de nuevo y revoca en Meta; concederla vuelve', () => {
        const m = montar({ dataset: { ...TODOS, cookieMarketing: '1' } });
        m.loader.install();

        m.consent({ marketing: false });
        assert.equal(m.loader.state(), 'off');
        assert.deepEqual(m.gtag().at(-1), ['consent', 'update', CONSENT_DEFAULT]);
        assert.deepEqual(m.fbq().at(-1), ['consent', 'revoke']);

        m.purchased({ orderCode: 'R-OFF' });
        assert.ok(! m.fbq().some((c) => c[1] === 'Purchase'), 'apagado no manda nada');

        m.consent({ marketing: true });
        assert.equal(m.loader.state(), 'on');
        assert.deepEqual(m.gtag().at(-1), ['consent', 'update', CONSENT_GRANTED]);
        assert.deepEqual(m.fbq().at(-1), ['consent', 'grant']);
    });

    test('concederla DESPUÉS de cargar la página lo trae entonces', () => {
        const m = montar({ dataset: { ...TODOS } });
        m.loader.install();
        assert.equal(m.scripts.length, 0);

        m.consent({ marketing: true, analytics: false });

        assert.equal(m.loader.state(), 'on');
        assert.equal(m.scripts.length, 3);
    });
});

describe('los hechos', () => {
    test('la vista de página va a Meta y a TikTok al arrancar', () => {
        const m = montar({ dataset: { ...TODOS, cookieMarketing: '1' } });
        m.loader.install();

        assert.deepEqual(m.fbq().slice(0, 2), [['init', '1234567890123456'], ['track', 'PageView']]);
        assert.deepEqual(m.ttq()[0], ['page']);
    });

    test('el inicio del pago va con el importe en unidades', () => {
        const m = montar({ dataset: { ...TODOS, cookieMarketing: '1' } });
        m.loader.install();

        m.tracked('pay_started', { amount_cents: 2550 });

        assert.deepEqual(m.gtag().at(-1), ['event', 'begin_checkout', { value: 25.5, currency: 'EUR' }]);
        assert.deepEqual(m.fbq().at(-1), ['track', 'InitiateCheckout', { value: 25.5, currency: 'EUR' }]);
        assert.deepEqual(m.ttq().at(-1), ['track', 'InitiateCheckout', { value: 25.5, currency: 'EUR' }]);
    });

    test('otros hechos del libro no van a los píxeles', () => {
        const m = montar({ dataset: { ...TODOS, cookieMarketing: '1' } });
        m.loader.install();
        const antes = m.fbq().length;

        m.tracked('section_viewed', { section: 'info' });
        m.tracked('identified', { method: 'google' });

        assert.equal(m.fbq().length, antes);
    });

    test('la compra lleva el código del pedido como id del evento, y la etiqueta de Google Ads', () => {
        const m = montar({ dataset: { ...TODOS, cookieMarketing: '1' } });
        m.loader.install();

        m.purchased({ orderCode: 'R-ABC123', total_cents: 4800, currency: 'EUR' });

        assert.deepEqual(m.gtag().at(-1), ['event', 'purchase', { value: 48, currency: 'EUR', transaction_id: 'R-ABC123', send_to: 'AW-123456789/AbCdEfGh' }]);
        assert.deepEqual(m.fbq().at(-1), ['track', 'Purchase', { value: 48, currency: 'EUR' }, { eventID: 'R-ABC123' }]);
        assert.deepEqual(m.ttq().at(-1), ['track', 'CompletePayment', { value: 48, currency: 'EUR', content_type: 'product' }, { event_id: 'R-ABC123' }]);
    });

    test('sin importe la compra viaja igual, con su id y sin inventar un valor', () => {
        const m = montar({ dataset: { pixelMeta: '1234567890123456', cookieMarketing: '1' } });
        m.loader.install();

        m.purchased({ orderCode: 'R-SIN' });

        assert.deepEqual(m.fbq().at(-1), ['track', 'Purchase', {}, { eventID: 'R-SIN' }]);
        m.purchased({});
        assert.deepEqual(m.fbq().at(-1), ['track', 'Purchase', {}, { eventID: 'R-SIN' }], 'sin código no hay compra');
    });

    /**
     * La compra se anuncia al VOLVER de la pasarela —otra carga de página— y antes del resumen del pedido: el
     * importe que se vio al iniciar el pago sobrevive en `sessionStorage` y es el valor del `Purchase`.
     */
    test('el importe del inicio del pago sobrevive a la pasarela y vale para la compra', () => {
        const antes = montar({ dataset: { pixelMeta: '1234567890123456', cookieMarketing: '1' } });
        antes.loader.install();
        antes.tracked('pay_started', { amount_cents: 2550 });
        assert.equal(antes.almacen.get('jw:px:pay'), '2550');

        // La vuelta: otra página, el mismo almacén de la pestaña.
        const vuelta = montar({ dataset: { pixelMeta: '1234567890123456', cookieMarketing: '1' } });
        vuelta.almacen.set('jw:px:pay', '2550');
        vuelta.loader.install();
        vuelta.purchased({ orderCode: 'R-VUELTA' });

        assert.deepEqual(vuelta.fbq().at(-1), ['track', 'Purchase', { value: 25.5, currency: 'EUR' }, { eventID: 'R-VUELTA' }]);

        // Y el detalle, si trae el importe, manda sobre lo recordado.
        vuelta.purchased({ orderCode: 'R-OTRA', total_cents: 1000 });
        assert.deepEqual(vuelta.fbq().at(-1)[2], { value: 10, currency: 'EUR' });
    });

    test('el importe se recuerda aunque los píxeles estén apagados (el consentimiento puede llegar después)', () => {
        const m = montar({ dataset: { pixelMeta: '1234567890123456' } });
        m.loader.install();

        m.tracked('pay_started', { amount_cents: 999 });

        assert.equal(m.almacen.get('jw:px:pay'), '999');
        assert.equal(m.win.fbq, undefined, 'sin marketing, nada sale');
    });

    test('céntimos → unidades', () => {
        assert.equal(toValue(2550), 25.5);
        assert.equal(toValue('4800'), 48);
        assert.equal(toValue(undefined), undefined);
        assert.equal(toValue('nada'), undefined);
    });
});
