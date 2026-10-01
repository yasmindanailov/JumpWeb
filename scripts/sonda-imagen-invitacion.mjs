/**
 * SONDA DE LA IMAGEN DE LA INVITACIÓN AL COMPARTIR (`#815`, `#816`; `fiesta-sistema-nuevo.md` §4.19, la I3): lo que ve el
 * robot de WhatsApp, que NO ejecuta JavaScript: la página servida, su `og:image` con sus medidas, y la imagen —un JPEG de
 * 1200 × 630, por debajo de 300 KB, `noindex` y `no-store`—, igual al pedirla dos veces y con la misma URL al recargar. Con
 * tres invitaciones de la local, una por tema; guarda cada imagen como foto para el ojo del owner.
 *
 * ⚠️ SOLO EN LOCAL y solo lee. Necesita el kit en la instancia (`fuentes.imagen` en su `instancia.json`) copiado a
 * `public/instancia/`. Fotos: `storage/app/audit/sonda-imagen-invitacion-<tema>.jpg`.
 *
 *   docker compose exec -u sail -T laravel.test node scripts/sonda-imagen-invitacion.mjs
 *
 * Sale con 1 si algún punto falla; el informe, en `storage/app/audit/sonda-imagen-invitacion.json`.
 */
import process from 'node:process';
import { writeFile, mkdir } from 'node:fs/promises';

const BASE = process.env.SONDA_BASE ?? 'http://localhost';
const SALIDA = 'storage/app/audit';
// Una por tema, de los montajes del ojo (`CARRIL-SPA.md` §8): confeti, fiesta y sereno.
const INVITACIONES = (process.env.SONDA_TOKENS ?? 'bKOAn3L9HPQT:confeti,WxYYnZkkNx7U:fiesta,jWXj5tBvsjMz:sereno')
    .split(',').map((par) => par.split(':'));

/** Ancho y alto de un JPEG, leídos de su marca SOF (sin dependencias). */
function medidasJpeg(buf) {
    if (buf[0] !== 0xff || buf[1] !== 0xd8) return null;
    let i = 2;
    while (i + 9 < buf.length) {
        if (buf[i] !== 0xff) return null;
        const marca = buf[i + 1];
        const largo = buf.readUInt16BE(i + 2);
        if (marca >= 0xc0 && marca <= 0xcf && ![0xc4, 0xc8, 0xcc].includes(marca)) {
            return { alto: buf.readUInt16BE(i + 5), ancho: buf.readUInt16BE(i + 7) };
        }
        i += 2 + largo;
    }
    return null;
}

const meta = (html, propiedad) => {
    const m = html.match(new RegExp(`property="${propiedad}" content="([^"]+)"`));
    return m ? m[1].replaceAll('&amp;', '&') : null;
};

const informe = { cuando: new Date().toISOString(), checks: [] };
const check = (nombre, ok, detalle = '') => informe.checks.push({ nombre, ok: Boolean(ok), detalle: String(detalle) });

await mkdir(SALIDA, { recursive: true });
for (const [token, tema] of INVITACIONES) {
    const pagina = await fetch(`${BASE}/invitacion/${token}`, { headers: { 'User-Agent': 'WhatsApp/2.23.20.0' } });
    const html = await pagina.text();
    const imagen = meta(html, 'og:image');
    const ancho = meta(html, 'og:image:width');
    const alto = meta(html, 'og:image:height');
    check(`${tema}: la página sirve su og:image GENERADA, con 1200 × 630`,
        pagina.status === 200 && imagen !== null && /\/invitacion\/[A-Za-z0-9]{12}\/imagen\.jpg\?l=[a-z]{2}&v=[0-9a-f]{16}$/.test(imagen) && ancho === '1200' && alto === '630',
        `${pagina.status} · ${imagen} · ${ancho}×${alto}`);
    if (imagen === null) continue;

    const url = imagen.replace(/^https?:\/\/[^/]+/, BASE);
    const r1 = await fetch(url);
    const jpeg = Buffer.from(await r1.arrayBuffer());
    const medidas = medidasJpeg(jpeg);
    check(`${tema}: la imagen es un JPEG de 1200 × 630 y pesa menos de 300 KB`,
        r1.status === 200 && r1.headers.get('content-type') === 'image/jpeg' && medidas?.ancho === 1200 && medidas?.alto === 630 && jpeg.length < 300 * 1024,
        `${r1.status} · ${r1.headers.get('content-type')} · ${medidas?.ancho}×${medidas?.alto} · ${Math.round(jpeg.length / 1024)} KB`);
    check(`${tema}: noindex y no-store (lleva el nombre de un menor)`,
        r1.headers.get('x-robots-tag') === 'noindex' && (r1.headers.get('cache-control') ?? '').includes('no-store'),
        `${r1.headers.get('x-robots-tag')} · ${r1.headers.get('cache-control')}`);

    const r2 = await fetch(url);
    const otra = Buffer.from(await r2.arrayBuffer());
    check(`${tema}: pedida otra vez, los mismos bytes (determinista)`, otra.equals(jpeg), `${otra.length} frente a ${jpeg.length}`);

    const recarga = meta(await (await fetch(`${BASE}/invitacion/${token}`)).text(), 'og:image');
    check(`${tema}: al recargar la página, la misma URL (la huella no baila)`, recarga === imagen, recarga);

    await writeFile(`${SALIDA}/sonda-imagen-invitacion-${tema}.jpg`, jpeg);
}

const inventado = await fetch(`${BASE}/invitacion/AAAAAAAAAAAA/imagen.jpg`);
check('un token inventado: 404, como la página', inventado.status === 404, inventado.status);

await writeFile(`${SALIDA}/sonda-imagen-invitacion.json`, `${JSON.stringify(informe, null, 2)}\n`);
let fallos = 0;
for (const { nombre, ok, detalle } of informe.checks) {
    if (!ok) fallos += 1;
    console.log(`  ${ok ? '✓' : '✗'} ${nombre}${!ok && detalle ? `  → ${detalle}` : ''}`);
}
console.log(`\n${fallos === 0 ? '✓' : '✗'} ${fallos} fallos · ${SALIDA}/sonda-imagen-invitacion.json`);
process.exit(fallos === 0 ? 0 : 1);
