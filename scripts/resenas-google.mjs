// **Copia las reseñas de una ficha de Google Maps a un JSON** (`DECISIONES #771`: el owner copia las de SU ficha como
// reseñas propias, mientras Google no aprueba la conexión con el Perfil de Empresa).
//
// Uso (dentro del contenedor, con el Chromium de la sonda):
//   docker compose exec -u sail laravel.test node scripts/resenas-google.mjs '<enlace de la ficha>' storage/app/resenas/ficha.json
//
// Abre la ficha, pasa el aviso de cookies de Google («Rechazar todo»), abre «Reseñas», las ordena por «Más recientes»,
// baja hasta que no cargan más, despliega los «Más» y, de cada una, copia: su id, el autor, su línea («Local Guide ·
// 12 reseñas»), su foto, el enlace a su perfil, las estrellas, la fecha tal cual la dice Google («Hace 2 meses»), el
// texto, las fotos que adjuntó y la respuesta del propietario. Las imágenes van como URL de Google, ya en tamaño
// grande: las DESCARGA el importador (`php artisan reviews:import`), que es quien las sirve desde nuestro servidor.
//
// ⚠️ Las clases de Google cambian sin aviso: si una tanda sale vacía, mira las capturas de `storage/app/resenas/`.
import { chromium } from 'playwright-core';
import { mkdirSync, writeFileSync } from 'node:fs';
import { dirname } from 'node:path';

const [url, salida = 'storage/app/resenas/ficha.json'] = process.argv.slice(2);
if (!url) {
    console.error('Uso: node scripts/resenas-google.mjs <enlace de la ficha> [salida.json]');
    process.exit(2);
}
mkdirSync(dirname(salida), { recursive: true });
const captura = (p, nombre) => p.screenshot({ path: `${dirname(salida)}/${nombre}.png` }).catch(() => {});

const navegador = await chromium.launch();
const contexto = await navegador.newContext({ locale: 'es-ES', viewport: { width: 1280, height: 1000 }, timezoneId: 'Europe/Madrid' });
const p = await contexto.newPage();
await p.goto(url, { waitUntil: 'domcontentloaded', timeout: 60000 });

// El aviso de cookies de Google (consent.google.com), si sale.
if (/consent\./.test(p.url())) {
    await p.getByRole('button', { name: /Rechazar todo|Reject all/i }).first().click();
    await p.waitForURL((u) => !/consent\./.test(u.toString()), { timeout: 30000 });
}
await p.waitForTimeout(3000);

// La pestaña «Reseñas».
const pestana = p.locator('button[role="tab"]').filter({ hasText: /Reseñas|Reviews/i }).first();
await pestana.click({ timeout: 20000 });
await p.waitForTimeout(2500);

// «Ordenar» → «Más recientes».
const ordenar = p.locator('button[aria-label*="Ordenar"], button[data-value="Ordenar"]').first();
if (await ordenar.count()) {
    await ordenar.click();
    await p.waitForTimeout(800);
    await p.locator('[role="menuitemradio"]').filter({ hasText: /recientes|Newest/i }).first().click().catch(() => {});
    await p.waitForTimeout(2500);
}

// El panel que se desplaza es el antepasado de las reseñas con barra propia.
const total = await p.evaluate(() => {
    const texto = document.body.innerText.match(/([\d.]+)\s+reseñas/);
    return texto ? Number(texto[1].replace(/\./g, '')) : null;
});
let vistas = 0;
let quietas = 0;
for (let vuelta = 0; vuelta < 400 && quietas < 8; vuelta++) {
    const ahora = await p.evaluate(() => {
        const una = document.querySelector('[data-review-id]');
        let caja = una;
        while (caja && !(caja.scrollHeight > caja.clientHeight + 10 && getComputedStyle(caja).overflowY !== 'visible')) caja = caja.parentElement;
        if (caja) caja.scrollTop = caja.scrollHeight;
        return new Set([...document.querySelectorAll('div[data-review-id]')].map((e) => e.getAttribute('data-review-id'))).size;
    });
    quietas = ahora === vistas ? quietas + 1 : 0;
    vistas = ahora;
    if (total && vistas >= total) break;
    await p.waitForTimeout(900);
}
console.log(`cargadas ${vistas} de ${total ?? '¿?'}`);

// Los «Más» de los textos largos.
for (const boton of await p.locator('button.w8nwRe, button[aria-label="Ver más"]').all()) {
    await boton.click().catch(() => {});
}
await p.waitForTimeout(800);
await captura(p, 'resenas');

const resenas = await p.evaluate(() => {
    const grande = (src, tam) => (src ? src.replace(/=[swh]\d+[^/]*$/, '=' + tam) : null);
    const fondo = (el) => {
        const m = (el.getAttribute('style') || '').match(/url\(["']?([^"')]+)["']?\)/);
        return m ? m[1] : null;
    };
    const unicas = new Map();
    for (const el of document.querySelectorAll('div.jftiEf[data-review-id], div[data-review-id][aria-label]')) {
        const id = el.getAttribute('data-review-id');
        if (!id || unicas.has(id)) continue;
        const estrellas = el.querySelector('span[role="img"][aria-label*="estrella"], span.kvMYJc');
        const nota = estrellas ? Number((estrellas.getAttribute('aria-label') || '').match(/(\d)/)?.[1] ?? 0) : null;
        const cuerpo = el.querySelector('.MyEned .wiI7pd') || el.querySelector('.wiI7pd');
        const respuesta = el.querySelector('.CDe7pd .wiI7pd');
        const perfil = el.querySelector('button.WEBjve, button.al6Kxe');
        unicas.set(id, {
            id,
            author: el.querySelector('.d4r55')?.textContent.trim() ?? el.getAttribute('aria-label') ?? '',
            author_meta: el.querySelector('.RfnDt')?.textContent.trim() ?? null,
            author_url: perfil?.getAttribute('data-href') ?? null,
            avatar_url: grande(el.querySelector('img.NBa7we')?.getAttribute('src'), 's160-c'),
            rating: nota,
            when: el.querySelector('.rsqaWe')?.textContent.trim() ?? null,
            text: cuerpo?.textContent.trim() ?? '',
            translated: Boolean(el.querySelector('button[aria-label*="original"], .kyuRq')),
            photos: [...el.querySelectorAll('button.Tya61d')].map((b) => grande(fondo(b), 'w1200')).filter(Boolean),
            reply: respuesta?.textContent.trim() ?? null,
        });
    }
    return [...unicas.values()];
});

writeFileSync(salida, JSON.stringify({ source: url, place_url: p.url(), total, copied_at: new Date().toISOString(), reviews: resenas }, null, 2));
console.log(`${resenas.length} reseñas → ${salida}`);
console.log(`con texto ${resenas.filter((r) => r.text).length} · con foto de autor ${resenas.filter((r) => r.avatar_url).length} · con fotos ${resenas.filter((r) => r.photos.length).length} · con respuesta ${resenas.filter((r) => r.reply).length}`);
await navegador.close();
