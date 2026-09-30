/**
 * SONDA DEL SEO (`docs/specs/seo.md`): lo que Google ve de cada página pública y cuánto tarda en verse en un móvil.
 *
 * Por página, a 390×844 con la red y la CPU de la prueba móvil de Lighthouse (4G lenta: 150ms de ida y vuelta y 1,6 Mbps
 * de bajada; CPU ×4) y SIN caché, como quien llega por primera vez desde Google:
 *   · los Core Web Vitals de LABORATORIO: LCP (bueno < 2,5 s) y CLS (bueno < 0,1), con el primer pintado (FCP) al lado.
 *     INP no se mide en laboratorio (es de campo); su vecino aquí es el tiempo del hilo principal ocupado al cargar.
 *   · lo que pesa: bytes transferidos y peticiones, y lo que pesa el HTML.
 *   · lo que se lee: el título y su largo, la descripción y su largo, la canónica, el H1, la `og:image` (formato y medidas),
 *     `hreflang` y los tipos de JSON-LD.
 * No juzga todavía: imprime la tabla para la auditoría (§1 de la spec). ⚠️ Es LABORATORIO: Google mide en el CAMPO (el
 * informe CrUX, percentil 75 de visitas reales); esto compara páginas y versiones en la misma máquina.
 *
 *   docker compose exec -u sail -T -e PLAYWRIGHT_BROWSERS_PATH=/home/sail/pw-browsers laravel.test \
 *       node scripts/sonda-seo.mjs [ruta …]
 *   Base: `SONDA_BASE` o `http://localhost` (el `80` del contenedor).
 */
/* global console, document, window, PerformanceObserver, performance -- Node y, dentro de `evaluate`, el navegador */
import process from 'node:process';
import { chromium } from 'playwright-core';

const BASE = process.env.SONDA_BASE ?? 'http://localhost';
const RUTAS = process.argv.slice(2).length ? process.argv.slice(2) : ['/', '/cumpleanos', '/kids', '/jump', '/colegios', '/visitanos', '/normas'];

// La prueba móvil de Lighthouse: 4G lenta (150ms RTT, 1,6 Mbps de bajada, 750 kbps de subida) y CPU ×4.
const RED = { offline: false, latency: 150, downloadThroughput: (1.6 * 1024 * 1024) / 8, uploadThroughput: (750 * 1024) / 8 };

const browser = await chromium.launch();
const filas = [];

for (const ruta of RUTAS) {
    const ctx = await browser.newContext({ viewport: { width: 390, height: 844 }, deviceScaleFactor: 3, isMobile: true, hasTouch: true, locale: 'es-ES' });
    const page = await ctx.newPage();
    const cdp = await ctx.newCDPSession(page);
    await cdp.send('Network.enable');
    await cdp.send('Network.setCacheDisabled', { cacheDisabled: true });
    await cdp.send('Network.emulateNetworkConditions', RED);
    await cdp.send('Emulation.setCPUThrottlingRate', { rate: 4 });

    let bytes = 0;
    let peticiones = 0;
    let bytesHtml = 0;
    cdp.on('Network.loadingFinished', (e) => { bytes += e.encodedDataLength; peticiones += 1; });
    page.on('response', async (res) => {
        if (res.url() === `${BASE}${ruta}` || res.url() === `${BASE}${ruta}/`) {
            bytesHtml = Number(res.headers()['content-length'] ?? 0) || (await res.body().catch(() => Buffer.alloc(0))).length;
        }
    });

    // Los observadores, antes de que llegue nada: LCP (el último candidato antes de la primera interacción) y CLS.
    await page.addInitScript(() => {
        window.__lcp = 0;
        window.__cls = 0;
        new PerformanceObserver((l) => {
            for (const e of l.getEntries()) {
                window.__lcp = e.startTime;
                // Qué es el LCP: la etiqueta, su recurso (imagen, póster o fondo) y su tamaño en pantalla.
                const el = e.element;
                window.__lcpQue = el ? `${el.tagName.toLowerCase()}${el.id ? '#' + el.id : ''} ${e.url ? e.url.split('/').pop() : '(texto)'} ${e.size}px²` : '?';
            }
        }).observe({ type: 'largest-contentful-paint', buffered: true });
        new PerformanceObserver((l) => { for (const e of l.getEntries()) if (! e.hadRecentInput) window.__cls += e.value; }).observe({ type: 'layout-shift', buffered: true });
    });

    const t0 = Date.now();
    await page.goto(`${BASE}${ruta}`, { waitUntil: 'load', timeout: 120000 });
    await page.waitForTimeout(3000);
    const carga = Date.now() - t0;

    const m = await page.evaluate(() => {
        const meta = (sel) => document.querySelector(sel)?.getAttribute('content') ?? null;
        const fcp = performance.getEntriesByName('first-contentful-paint')[0]?.startTime ?? null;
        const ld = [...document.querySelectorAll('script[type="application/ld+json"]')].flatMap((s) => {
            try {
                const d = JSON.parse(s.textContent);
                const items = Array.isArray(d) ? d : (d['@graph'] ?? [d]);

                return items.map((i) => i['@type']);
            } catch { return ['(roto)']; }
        });

        return {
            lcp: window.__lcp, lcpQue: window.__lcpQue ?? '?', cls: window.__cls, fcp,
            titulo: document.title,
            descripcion: meta('meta[name="description"]'),
            canonica: document.querySelector('link[rel="canonical"]')?.href ?? null,
            h1: [...document.querySelectorAll('h1')].map((h) => h.textContent.trim().replace(/\s+/g, ' ')),
            ogImage: meta('meta[property="og:image"]'),
            hreflang: document.querySelectorAll('link[rel="alternate"][hreflang]').length,
            lang: document.documentElement.lang,
            ld,
        };
    });

    let og = '—';
    if (m.ogImage) {
        const r = await ctx.request.get(m.ogImage).catch(() => null);
        if (r?.ok()) {
            const cuerpo = await r.body();
            og = `${(r.headers()['content-type'] ?? '?').replace('image/', '')} ${Math.round(cuerpo.length / 1024)} KB`;
        } else {
            og = 'no carga';
        }
    }

    filas.push({
        ruta,
        'LCP s': (m.lcp / 1000).toFixed(2),
        CLS: m.cls.toFixed(3),
        'FCP s': m.fcp ? (m.fcp / 1000).toFixed(2) : '—',
        'carga s': (carga / 1000).toFixed(1),
        'KB': Math.round(bytes / 1024),
        pet: peticiones,
        'HTML KB': Math.round(bytesHtml / 1024),
        'título (largo)': `${m.titulo.length}`,
        'desc (largo)': `${m.descripcion?.length ?? 0}`,
        h1: m.h1.length,
        og,
        hreflang: m.hreflang,
        'JSON-LD': [...new Set(m.ld)].join('+'),
    });
    filas.at(-1).__detalle = m;
    await ctx.close();
}

await browser.close();
console.table(filas.map(({ __detalle, ...f }) => f));
for (const f of filas) {
    console.log(`\n${f.ruta}\n  título: ${f.__detalle.titulo}\n  desc:   ${f.__detalle.descripcion}\n  H1:     ${f.__detalle.h1.join(' | ')}\n  og:     ${f.__detalle.ogImage}\n  LCP:    ${f.__detalle.lcpQue}`);
}
