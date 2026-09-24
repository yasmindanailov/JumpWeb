/**
 * **EL CARGADOR DE LOS PÍXELES DE ANUNCIOS** (`docs/specs/analitica.md` §4.3, T3b·1; `DECISIONES #678`).
 *
 * Trae Google Ads (gtag), Meta y TikTok —los que la instalación configuró en «Ajustes», publicados por el
 * servidor en el `<body>` como `data-pixel-*`— y SOLO si el visitante consintió la categoría `marketing`
 * (`data-cookie-marketing="1"`) o la concede después (`cookies-updated`). Es un trozo DIFERIDO que el tracker
 * (`track.js`) pide al instalarse, y únicamente cuando el `<body>` trae algún píxel: sin píxeles, ni un byte.
 *
 * Lo que hace, y nada más:
 *  · gtag con **Consent Mode v2 BÁSICO**: el script no existe en el DOM sin `marketing`; al cargarlo, el
 *    `consent default` va todo DENEGADO y el `update` concede solo lo de anuncios (`ad_storage`, `ad_user_data`,
 *    `ad_personalization`); `analytics_storage` queda SIEMPRE denegado —la medición es del libro propio—;
 *  · tres hechos por plataforma: la vista de página al cargar, el inicio del pago (`pay_started`) y la COMPRA
 *    (`jw:cajon:purchased`), con el código del pedido como id del evento para que la API de conversiones del
 *    servidor (T3b·2) no la cuente dos veces;
 *  · al retirar la categoría deja de mandar (gtag `consent update` denegado, `fbq('consent','revoke')`, y
 *    TikTok simplemente no recibe más), y al concederla de nuevo vuelve.
 *
 * ⚠️ Nada personal: ni correo ni teléfono ni nombre salen de aquí (el emparejamiento avanzado, hasheado, es del
 * servidor). ⚠️ No carga en una página con credenciales en la URL (un enlace firmado), como el driver.
 * ⚠️ Todo por parámetro (`CE-6`): `win` y `doc` llegan de fuera y `pixels.test.js` lo prueba sin navegador.
 */

import { maskPath } from './driver.js';

export const GTAG_SRC = 'https://www.googletagmanager.com/gtag/js';
export const META_SRC = 'https://connect.facebook.net/en_US/fbevents.js';
export const TIKTOK_SRC = 'https://analytics.tiktok.com/i18n/pixel/events.js';
const PAY_KEY = 'jw:px:pay';

/** Consent Mode v2: lo que se declara ANTES de que gtag hable, y lo que se concede con `marketing`. */
export const CONSENT_DEFAULT = Object.freeze({
    ad_storage: 'denied', ad_user_data: 'denied', ad_personalization: 'denied', analytics_storage: 'denied',
});
export const CONSENT_GRANTED = Object.freeze({
    ad_storage: 'granted', ad_user_data: 'granted', ad_personalization: 'granted', analytics_storage: 'denied',
});

/** Céntimos → unidades, como quieren las tres plataformas. */
export function toValue(cents) {
    const n = Number(cents);

    return Number.isFinite(n) ? Math.round(n) / 100 : undefined;
}

/**
 * @param {{win: Window, doc?: Document}} deps
 */
export function createPixelsLoader({ win, doc = win.document }) {
    let state = 'idle';   // idle → on ⇄ off
    let pixels = null;
    let granted = false;

    const data = () => doc.body?.dataset ?? {};
    const consented = () => granted || data().cookieMarketing === '1';

    function read() {
        const d = data();
        const found = {};

        if (d.pixelGoogleAds) found.googleAds = d.pixelGoogleAds;
        if (d.pixelMeta) found.meta = d.pixelMeta;
        if (d.pixelTiktok) found.tiktok = d.pixelTiktok;

        return Object.keys(found).length ? found : null;
    }

    function sensitive() {
        const path = win.location?.pathname ?? '/';
        const search = win.location?.search ?? '';

        return maskPath(path) !== path || /[?&](signature|token|expires)=/i.test(search);
    }

    function inject(src) {
        const script = doc.createElement('script');
        script.async = true;
        script.src = src;
        (doc.head ?? doc.body).appendChild(script);
    }

    // ── Google Ads (gtag) ─────────────────────────────────────────────────────────────────────────────
    // ⚠️ `arguments` y no un array: gtag.js lee objetos `arguments`, como en su propio fragmento.
    function gtag() {
        win.dataLayer = win.dataLayer || [];
        win.dataLayer.push(arguments);
    }

    function startGoogle(id) {
        const account = id.split('/')[0];

        // El orden es el de Consent Mode: `default` antes de cualquier `config`, y el `update` después; con
        // `marketing` concedido el `default` sigue siendo denegado a propósito (`analytics_storage` no se concede).
        gtag('consent', 'default', { ...CONSENT_DEFAULT });
        gtag('consent', 'update', { ...CONSENT_GRANTED });
        gtag('js', new Date());
        gtag('config', account, { allow_google_signals: false, allow_ad_personalization_signals: true });
        inject(`${GTAG_SRC}?id=${encodeURIComponent(account)}`);
    }

    // ── Meta ──────────────────────────────────────────────────────────────────────────────────────────
    function fbq(...args) {
        if (typeof win.fbq === 'function') return win.fbq(...args);

        const f = function () { f.queue.push(arguments); };
        f.queue = [];
        f.loaded = true;
        f.version = '2.0';
        win.fbq = win._fbq = f;
        f.queue.push(args);
    }

    function startMeta(id) {
        fbq('init', id);
        fbq('track', 'PageView');
        inject(META_SRC);
    }

    // ── TikTok ────────────────────────────────────────────────────────────────────────────────────────
    function ttq() {
        if (! win.ttq) {
            const q = [];
            const methods = ['page', 'track', 'identify', 'instances', 'debug', 'on', 'off', 'once', 'ready', 'alias', 'group', 'enableCookie', 'disableCookie'];
            const t = { _q: q, methods };
            for (const m of methods) t[m] = (...args) => q.push([m, ...args]);
            win.ttq = t;
        }

        return win.ttq;
    }

    function startTiktok(id) {
        const t = ttq();
        t.page();
        inject(`${TIKTOK_SRC}?sdkid=${encodeURIComponent(id)}&lib=ttq`);
    }

    function load() {
        if (state !== 'idle') return;

        pixels = read();

        if (! pixels || ! consented() || sensitive()) return;

        state = 'on';

        if (pixels.googleAds) startGoogle(pixels.googleAds);
        if (pixels.meta) startMeta(pixels.meta);
        if (pixels.tiktok) startTiktok(pixels.tiktok);
    }

    /**
     * El importe del pago en curso sobrevive a la ida y vuelta a la pasarela (una navegación entera) en
     * `sessionStorage`: la compra se anuncia al volver, antes de que llegue el resumen del pedido, y sin esto
     * el `Purchase` del píxel iría sin valor.
     */
    function remember(cents) {
        try {
            win.sessionStorage.setItem(PAY_KEY, String(cents));
        } catch {
            // Sin almacén: la compra irá sin valor, que es honesto.
        }
    }

    function remembered() {
        try {
            return win.sessionStorage.getItem(PAY_KEY) ?? undefined;
        } catch {
            return undefined;
        }
    }

    /** El inicio del pago, en el vocabulario de cada plataforma. */
    function checkout(props = {}) {
        if (props.amount_cents !== undefined) remember(props.amount_cents);
        if (state !== 'on') return;

        const value = toValue(props.amount_cents);
        const money = value === undefined ? {} : { value, currency: 'EUR' };

        if (pixels.googleAds) gtag('event', 'begin_checkout', { ...money });
        if (pixels.meta) fbq('track', 'InitiateCheckout', { ...money });
        if (pixels.tiktok) ttq().track('InitiateCheckout', { ...money });
    }

    /**
     * La COMPRA, con el código del pedido como id del evento: la API de conversiones del servidor manda el
     * mismo id y la plataforma la cuenta UNA vez. El valor es lo PAGADO en línea (el detalle, o el importe
     * recordado al iniciar el pago): una señal no es el total de la reserva.
     */
    function purchase(detail = {}) {
        if (state !== 'on' || ! detail.orderCode) return;

        const value = toValue(detail.total_cents ?? remembered());
        const money = value === undefined ? {} : { value, currency: detail.currency || 'EUR' };

        if (pixels.googleAds) gtag('event', 'purchase', { ...money, transaction_id: detail.orderCode, send_to: pixels.googleAds });
        if (pixels.meta) fbq('track', 'Purchase', { ...money }, { eventID: detail.orderCode });
        if (pixels.tiktok) ttq().track('CompletePayment', { ...money, content_type: 'product' }, { event_id: detail.orderCode });
    }

    function stop() {
        if (state !== 'on') return;

        state = 'off';
        if (pixels.googleAds) gtag('consent', 'update', { ...CONSENT_DEFAULT });
        if (pixels.meta) fbq('consent', 'revoke');
    }

    function resume() {
        if (state !== 'off') return;

        state = 'on';
        if (pixels.googleAds) gtag('consent', 'update', { ...CONSENT_GRANTED });
        if (pixels.meta) fbq('consent', 'grant');
    }

    function onConsent(detail) {
        if (! detail) return;

        granted = !! detail.marketing;

        if (granted) {
            if (state === 'off') resume();
            else load();
        } else {
            stop();
        }
    }

    function install() {
        doc.addEventListener('jw:tracked', (e) => {
            if (e.detail?.name === 'pay_started') checkout(e.detail.props);
        });
        doc.addEventListener('jw:cajon:purchased', (e) => purchase(e.detail));
        win.addEventListener('cookies-updated', (e) => onConsent(e.detail));
        load();

        return api;
    }

    const api = { install, load, checkout, purchase, stop, resume, state: () => state, pixels: () => pixels };

    return api;
}

/** Lo que `track.js` llama al traer el trozo. */
export function installPixels(deps) {
    return createPixelsLoader(deps).install();
}
