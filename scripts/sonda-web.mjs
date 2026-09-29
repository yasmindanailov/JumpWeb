/**
 * **LA WEB ENTERA, EN VIVO** (T6f·4 de `docs/specs/isla-y-landing-nueva.md` §4.22; `DECISIONES #843`): lo que la T6f cambió
 * —las rutas viejas que una página del paquete SUSTITUYE con un 301, el sitemap y las páginas que quedan— en un navegador y
 * por HTTP, contra lo que DECLARA el paquete (`InstancePages`, leído con tinker: la sonda no teclea rutas de un cliente).
 *   1. LAS RUTAS VIEJAS: cada una de `InstancePages::SUSTITUIBLES` que el paquete sustituye responde 301 a la URL de su
 *      página CON su consulta, y esa URL da 200 sin otro salto; la que ninguna página sustituye, 200.
 *   2. EL SITEMAP: ninguna URL redirige ni falla; ninguna es una ruta sustituida; están las páginas declaradas que entran
 *      y no las que se declaran fuera.
 *   3. CADA PÁGINA (las del sitemap y `/entradas`) en es, en y fr: 200, su `lang`, una `h1`, sin salirse de ancho, sin
 *      marcadores sin rellenar ni claves sueltas (también en el `<title>`), su canónica —la de `/entradas`, la portada— y
 *      la consola limpia.
 *   4. LOS ENLACES INTERNOS: ninguno lleva a una ruta sustituida (un salto de más) ni a una que falle. ⚠️ Las legales, con
 *      el armazón VIEJO (su menú y su pie enlazan a las rutas viejas), van como divergencia DECLARADA: ❓ del owner (§4.22).
 *   5. `/entradas` sigue siendo el enlace que abre la compra: la isla en «Cuándo y cuántos».
 * Solo lectura. Sale con 1 si algo falla; las fotos, en `storage/app/audit/web-<ancho>-*.png`.
 *
 *   docker compose exec -u sail -T -e PLAYWRIGHT_BROWSERS_PATH=/home/sail/pw-browsers laravel.test \
 *       node scripts/sonda-web.mjs [390|1280]
 */
/* global console, document, window, fetch -- Node y, dentro de `evaluate`, el navegador */
import process from 'node:process';
import { Buffer } from 'node:buffer';
import { URL } from 'node:url';
import { execFileSync } from 'node:child_process';
import { chromium } from 'playwright-core';
import { mkdir } from 'node:fs/promises';

const BASE = process.env.SONDA_BASE ?? 'http://localhost';
const ANCHO = Number(process.argv[2] ?? 390);
const SALIDA = 'storage/app/audit';
const POLITICA = '2026-09-24'; // CookieConsent::POLICY_VERSION
/**
 * Desbordes DECLARADOS, a la espera de una decisión: «idioma ruta» → por qué. ⚠️ **Solo encoge**: una declaración que ya no
 * desborda pone la sonda en rojo, para que se retire. ▶ Vacía desde `#844`: el botón de la calculadora («Reservar y pagar la
 * señal», 294 px) no cabía en español a 360 ni en francés a 390, y el de ancho completo parte ahora su texto si no cabe (el
 * owner lo eligió así). Esta sonda se corre también a 360, el ancho donde se vio.
 */
const DESBORDES_DECLARADOS = {};
const filas = [];
const ok = (nombre, cierto, detalle = '') => filas.push(`${cierto ? '✓' : '✗'} ${nombre}${detalle ? ` — ${String(detalle).replace(/\s+/g, ' ').slice(0, 200)}` : ''}`);
const errores = [];
const camino = (u) => { const x = new URL(u, BASE); return x.pathname.replace(/\/+$/, '') || '/'; };
/**
 * Una petición sin seguir redirecciones: su estado y adónde manda. ⚠️ El cuerpo se CONSUME siempre: sin leerlo, `undici`
 * (el `fetch` de Node 24) revienta con `assert(!this.paused)` al cerrarse el socket (medido: la primera corrida murió así).
 */
const pedir = async (ruta) => {
    const r = await fetch(new URL(ruta, BASE), { redirect: 'manual' });
    await r.arrayBuffer();

    return { estado: r.status, a: r.headers.get('location') };
};

// ── Lo que declara el paquete, visto por el producto ─────────────────────────────────────────────────────────────
const php = `$p = app(App\\Http\\Instancia\\InstancePages::class); $rutas = [];
foreach (App\\Http\\Instancia\\InstancePages::SUSTITUIBLES as $r) { $d = $p->redireccionDe($r); $rutas[$r] = ['desde' => route($r, [], false), 'a' => $d === null ? null : (parse_url($d, PHP_URL_PATH) ?: '/')]; }
$paginas = []; foreach ($p->todas() as $pg) { $n = $pg->ocupa ?? $pg->ruta(); $paginas[] = ['slug' => $pg->slug, 'ruta' => Illuminate\\Support\\Facades\\Route::has($n) ? route($n, [], false) : null, 'sitemap' => $pg->sitemap, 'ocupa' => $pg->ocupa]; }
echo json_encode(['rutas' => $rutas, 'paginas' => $paginas]);`;
const declarado = JSON.parse(execFileSync('php', ['artisan', 'tinker', '--execute', php], { encoding: 'utf8' }).trim().split('\n').pop());
const sustituidas = Object.values(declarado.rutas).filter((r) => r.a !== null);

await mkdir(SALIDA, { recursive: true });
const navegador = await chromium.launch();

async function contexto(lang = null) {
    const ctx = await navegador.newContext({ viewport: { width: ANCHO, height: ANCHO < 600 ? 844 : 900 }, locale: 'es-ES', deviceScaleFactor: 1 });
    await ctx.addCookies([{ name: 'cookie_consent', value: Buffer.from(JSON.stringify({ v: POLITICA, cats: {} })).toString('base64'), url: BASE }]);
    const page = await ctx.newPage();
    page.on('pageerror', (e) => errores.push(`${page.url()} · ${e.message}`));
    page.on('console', (m) => { if (m.type() === 'error' && ! /status of 401/.test(m.text())) errores.push(`${page.url()} · ${m.text()}`); });
    await page.route('http://localhost:8081/**', (r) => r.continue({ url: r.request().url().replace('localhost:8081', new URL(BASE).host) }));
    if (lang) await page.goto(`${BASE}/lang/${lang}`, { waitUntil: 'domcontentloaded' });

    return { ctx, page };
}

try {
    // ── 1 · Las rutas viejas ─────────────────────────────────────────────────────────────────────────────────────────
    ok(`el paquete sustituye ${sustituidas.length} de las ${Object.keys(declarado.rutas).length} rutas que el producto suelta`, sustituidas.length > 0,
        Object.values(declarado.rutas).map((r) => `${r.desde}→${r.a ?? '(la suya)'}`).join(' · '));
    for (const [nombre, r] of Object.entries(declarado.rutas)) {
        if (r.a === null) {
            const { estado } = await pedir(r.desde);
            ok(`${r.desde}: nadie la sustituye y pinta lo suyo`, estado === 200 || (nombre === 'bar' && estado === 404), estado);
            continue;
        }
        const consulta = '?utm_campaign=sonda-t6f&utm_source=web';
        const { estado, a } = await pedir(r.desde + consulta);
        const destino = a ? new URL(a, BASE) : null;
        const final = destino ? await pedir(destino.pathname + destino.search) : null;
        ok(`${r.desde} → 301 a ${r.a}, con su consulta, y allí 200 sin otro salto`,
            estado === 301 && destino && camino(destino) === r.a && destino.search === consulta && final?.estado === 200, `${estado} ${a ?? ''} · ${final?.estado ?? '-'}`);
    }

    // ── 2 · El sitemap ─────────────────────────────────────────────────────────────────────────────────────────────────
    const xml = await (await fetch(new URL('/sitemap.xml', BASE))).text();
    const locs = [...xml.matchAll(/<loc>([^<]+)<\/loc>/g)].map((m) => camino(m[1]));
    const estados = [];
    for (const l of locs) estados.push([l, (await pedir(l)).estado]);
    const malos = estados.filter(([, e]) => e !== 200);
    ok(`el sitemap: sus ${locs.length} URL responden 200, sin redirigir`, locs.length > 0 && malos.length === 0, malos.map(([l, e]) => `${l} ${e}`).join(' · '));
    const anunciadas = sustituidas.filter((r) => locs.includes(r.desde));
    ok('el sitemap no anuncia ninguna ruta sustituida', anunciadas.length === 0, anunciadas.map((r) => r.desde).join(' '));
    const dentro = declarado.paginas.filter((p) => p.sitemap && p.ruta);
    const faltan = dentro.filter((p) => ! locs.includes(camino(p.ruta)));
    const fuera = declarado.paginas.filter((p) => ! p.sitemap && p.ruta && locs.includes(camino(p.ruta)));
    ok('están las páginas declaradas que entran, y no las que se declaran fuera', faltan.length === 0 && fuera.length === 0,
        [...faltan.map((p) => `falta ${p.ruta}`), ...fuera.map((p) => `sobra ${p.ruta}`)].join(' · ') || `${dentro.length} páginas`);

    // ── 3 · Cada página, en los tres idiomas ─────────────────────────────────────────────────────────────────────────
    const paginas = [...new Set([...locs, '/entradas'])];
    const enlaces = new Map(); // camino enlazado → páginas que lo enlazan
    const desbordes = new Set();
    for (const lang of ['es', 'en', 'fr']) {
        const { ctx, page } = await contexto(lang);
        const mal = [];
        for (const ruta of paginas) {
            // ⚠️ `load` y una espera, NO `networkidle`: la analítica envía con `fetch(…, { keepalive: true })` y Playwright no
            // ve cuándo acaba esa petición (medido: el evento SÍ queda grabado en la BD, 5 s después), así que la red «nunca»
            // reposa y la página se come los 30 s a ratos. Cada página falla POR SU NOMBRE: una no tumba la sonda entera.
            const r = await page.goto(`${BASE}${ruta}`, { waitUntil: 'load' }).catch((e) => { mal.push(`${ruta}: ${e.message.split('\n')[0]}`); return null; });
            if (r === null) continue;
            await page.waitForTimeout(1200);
            const v = await page.evaluate(() => {
                const raiz = (document.querySelector('main') ?? document.body).cloneNode(true);
                raiz.querySelectorAll('script, style').forEach((s) => s.remove());

                return {
                    lang: document.documentElement.lang,
                    // Las de la PÁGINA: la capa de la isla (la compra abierta en `/entradas`) lleva su propio título.
                    h1: [...document.querySelectorAll('h1')].filter((h) => ! h.closest('[data-isla], [data-jw-isla]')).length,
                    ancho: document.documentElement.scrollWidth <= window.innerWidth,
                    texto: `${document.title} ${raiz.textContent}`.replace(/\s+/g, ' '),
                    canonica: document.querySelector('link[rel=canonical]')?.getAttribute('href') ?? null,
                    enlaces: [...document.querySelectorAll('a[href]')].map((a) => a.getAttribute('href')),
                };
            });
            const sueltos = [...new Set(v.texto.match(/:[a-z_]{3,}\b|\b(?:paginas|piezas|landing|site)\.[a-z_]+\.[a-z_.]+/g) ?? [])];
            const canonicaEsperada = ruta === '/entradas' ? '/' : ruta;
            if (! v.ancho) desbordes.add(`${lang} ${ruta}`);
            const fallos = [
                r?.status() !== 200 && `estado ${r?.status()}`,
                v.lang !== lang && `lang ${v.lang}`,
                v.h1 !== 1 && `${v.h1} h1`,
                ! v.ancho && ! DESBORDES_DECLARADOS[`${lang} ${ruta}`] && 'se sale de ancho',
                sueltos.length && `sin rellenar: ${sueltos.join(' ')}`,
                (v.canonica === null || camino(v.canonica) !== canonicaEsperada) && `canónica ${v.canonica}`,
            ].filter(Boolean);
            if (fallos.length) mal.push(`${ruta}: ${fallos.join(', ')}`);
            if (lang === 'es') {
                for (const h of v.enlaces) {
                    if (! h || /^(#|mailto:|tel:|javascript:)/.test(h)) continue;
                    const u = new URL(h, `${BASE}${ruta}`);
                    if (u.host !== new URL(BASE).host && u.host !== 'localhost:8081') continue;
                    if (/^\/(lang|api|logout|auth|cajon|resenas|storage|images|img|videos|instancia|build)\b/.test(u.pathname)) continue;
                    const c = camino(u.pathname);
                    if (! enlaces.has(c)) enlaces.set(c, new Set());
                    enlaces.get(c).add(ruta);
                }
            }
        }
        ok(`en ${lang}: las ${paginas.length} páginas en 200, en su idioma, con una h1, sin salirse, sin marcadores y con su canónica`, mal.length === 0, mal.join(' | '));
        if (lang === 'es') {
            await page.goto(`${BASE}/`, { waitUntil: 'load' });
            await page.waitForTimeout(1200);
            await page.screenshot({ path: `${SALIDA}/web-${ANCHO}-portada.png` });
        }
        await ctx.close();
    }

    const declaradas = Object.keys(DESBORDES_DECLARADOS);
    const sobran = declaradas.filter((d) => ! desbordes.has(d));
    ok(`DECLARADO (❓ del owner, §4.22): ${declaradas.length} desbordes conocidos — y ninguna declaración sobra`, sobran.length === 0,
        sobran.length ? `ya no desbordan, retíralas: ${sobran.join(' · ')}` : declaradas.map((d) => `${d}: ${DESBORDES_DECLARADOS[d]}`).join(' · ') || 'ninguno a este ancho');

    // ── 4 · Los enlaces internos ─────────────────────────────────────────────────────────────────────────────────────
    // ▶ Desde la T6h (`#844`) también las legales van con el sistema nuevo: ya no hay excepción declarada.
    const aSustituidas = sustituidas.map((r) => r.desde);
    const saltos = [...enlaces].filter(([c]) => aSustituidas.includes(c));
    ok('ningún enlace de ninguna página lleva a una ruta sustituida (un salto de más)', saltos.length === 0,
        saltos.map(([c, d]) => `${c} desde ${[...d].join(',')}`).join(' · '));
    const rotos = [];
    for (const [c, desde] of enlaces) {
        if (aSustituidas.includes(c)) continue;
        const { estado, a } = await pedir(c);
        const bien = estado === 200 || (estado === 302 && a && camino(a) === '/login');
        if (! bien) rotos.push(`${c} ${estado} (desde ${[...desde][0]})`);
    }
    ok(`los ${enlaces.size} destinos internos responden (200, o la puerta de entrar si piden sesión)`, rotos.length === 0, rotos.join(' · '));

    // ── 5 · /entradas abre la compra ─────────────────────────────────────────────────────────────────────────────────
    const { ctx, page } = await contexto();
    await page.goto(`${BASE}/entradas`, { waitUntil: 'load' });
    const paso = await page.locator('#isla-compra-paso').waitFor({ timeout: 10000 }).then(() => page.locator('#isla-compra-paso').innerText(), () => '');
    ok('/entradas sigue abriendo la compra (la isla en «Cuándo y cuántos»)', paso.trim() === 'Cuándo y cuántos', paso);
    await page.screenshot({ path: `${SALIDA}/web-${ANCHO}-entradas.png` });
    await ctx.close();
} catch (e) {
    ok('la sonda terminó sin excepciones', false, e.message.split('\n')[0]);
} finally {
    await navegador.close();
}

ok('sin errores en la consola de ninguna página', errores.length === 0, errores.slice(0, 3).join(' | '));
console.log(`\nSONDA DE LA WEB · ${ANCHO}px\n${filas.join('\n')}`);
const fallos = filas.filter((f) => f.startsWith('✗')).length;
console.log(`\n${filas.length - fallos}/${filas.length}`);
process.exit(fallos ? 1 : 0);
