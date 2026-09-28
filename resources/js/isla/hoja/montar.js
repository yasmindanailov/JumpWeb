/**
 * **LA HOJA PARA DIRECCIÓN, con las cifras del GRUPO** (T6c·4b de `docs/specs/isla-y-landing-nueva.md` §4.19, `#837`). La
 * página imprimible de colegios (en la instancia) se pinta en el SERVIDOR con lo que dicen sus hechos; con un cálculo en el
 * enlace (`?c=`, el que escribe la calculadora), este script pide al SERVIDOR la línea de ese grupo —la misma pregunta que
 * la calculadora, `POST /catalog/products/{id}/addons`— y escribe sus cifras: cuántos, el precio por alumno, el total y
 * cuándo (`PAY-12`: aquí no se calcula dinero). Sin respuesta —la hora se ocupó, sin red—, la hoja se queda como está: con
 * sus cifras generales, nunca unas inventadas.
 *
 * ⚠️⚠️ **SIN IMPORTAR NADA, y medido**: importando `api.js`, `calculadora/vista.js` y `compra/vista.js` —módulos que
 * comparten el motor, la compra y las calculadoras—, Vite los reagrupaba en trozos nuevos y los TRES pasaban de su techo
 * sin tocarlos (`SidebarBundleBudgetTest`; control: sin esta entrada, bajo el techo). Así que lleva sus COPIAS mínimas de
 * tres reglas (leer el cálculo, escribir euros y fechas) y de la petición con CSRF de `api.js`; `montar.test.js` las
 * compara con las originales, para que no diverjan en silencio.
 *
 * Marcas de la vista: `[data-jw-hoja]` (JSON: las filas de la calculadora, `hoy` del parque y el texto de `cuando`),
 * `[data-jw-hoja-grupo]` (oculto hasta tener cifras), `[data-jw-hoja-general]` (lo que se oculta entonces),
 * `[data-jw-hoja-cifra="alumnos|porAlumno|total"]`, `[data-jw-hoja-cuando]` y `[data-jw-hoja-imprimir]` (el botón que
 * guarda el PDF). Con `?imprimir=1`, imprime sola al terminar: «Descargarla» de la página.
 */

/** COPIA de `calculadora/vista.js::leerCalculo`: el cálculo del enlace para estas filas, o `null`. */
export function leerCalculo(texto, pagina, hoy) {
    const [n, fila, dia, hora] = String(texto ?? '').split('_');
    const suya = (pagina?.filas ?? []).find((f) => String(f.id) === fila);
    const cuantos = Number.parseInt(n, 10);

    if (! suya || ! Number.isInteger(cuantos) || cuantos < (suya.min ?? 1) || cuantos > (suya.max ?? Infinity)) return null;
    const elDia = /^\d{4}-\d{2}-\d{2}$/.test(dia ?? '') && dia >= hoy ? dia : null;

    return { fila: suya.id, n: cuantos, dia: elDia, hora: elDia && /^\d{2}:\d{2}$/.test(hora ?? '') ? `${hora}:00` : null };
}

/** COPIA de `compra/vista.js::euros`: sin decimales si es redondo («8 €»), con dos si no («6,40 €»). */
export function euros(cents, locale = 'es') {
    return new Intl.NumberFormat(locale, {
        style: 'currency', currency: 'EUR', minimumFractionDigits: Number(cents) % 100 === 0 ? 0 : 2, maximumFractionDigits: 2,
    }).format(Number(cents) / 100);
}

/** COPIA de `compra/vista.js::diaLargo`: «Sábado 26 de septiembre», con mayúscula y sin la coma tras el nombre. */
export function diaLargo(iso, locale = 'es') {
    const escrito = new Intl.DateTimeFormat(locale, { weekday: 'long', day: 'numeric', month: 'long' }).formatToParts(new Date(`${iso}T12:00:00`))
        .map((x, i) => (i === 1 && x.type === 'literal' ? x.value.replace(/^,\s*/, ' ') : x.value))
        .join('');

    return escrito.charAt(0).toUpperCase() + escrito.slice(1);
}

/** La petición de `api.js`, en mínimo: mismo origen, JSON y el token de CSRF de su cookie (url-encoded). */
async function publicar(ruta, cuerpo, doc) {
    const token = () => decodeURIComponent((doc.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/) ?? [])[1] ?? '');

    if (! token()) await fetch('/sanctum/csrf-cookie', { credentials: 'same-origin', headers: { Accept: 'application/json' } });
    const r = await fetch(`/api/v1${ruta}`, {
        method: 'POST', credentials: 'same-origin', body: JSON.stringify(cuerpo),
        headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-XSRF-TOKEN': token() },
    });

    return { ok: r.ok, data: r.ok ? await r.json() : null };
}

/** Escribe en la hoja las cifras del grupo del enlace, del servidor. Devuelve si pudo. */
export async function rellenarHoja({ doc = document, win = window, peticion = { post: (ruta, cuerpo) => publicar(ruta, cuerpo, doc) } } = {}) {
    let pagina;

    try { pagina = JSON.parse(doc.querySelector('[data-jw-hoja]')?.dataset.jwHoja ?? 'null'); } catch { pagina = null; }
    if (! pagina?.filas?.length) return false;
    const calculo = leerCalculo(new URLSearchParams(win.location.search).get('c'), pagina, pagina.hoy ?? '');

    if (! calculo?.dia || ! calculo.hora) return false;
    const r = await peticion.post(`/catalog/products/${calculo.fila}/addons`, { quantity: calculo.n, date: calculo.dia, time: calculo.hora, addons: [], choices: [] }).catch(() => null);
    const linea = r?.ok ? r.data?.line : null;

    if (! linea) return false;
    const locale = doc.documentElement.lang || 'es';
    const fila = pagina.filas.find((f) => f.id === calculo.fila) ?? {};
    const poner = (sel, texto) => doc.querySelectorAll(sel).forEach((el) => { el.textContent = texto; });

    poner('[data-jw-hoja-cifra="alumnos"]', String(calculo.n));
    poner('[data-jw-hoja-cifra="porAlumno"]', euros(linea.unit_price_cents, locale));
    poner('[data-jw-hoja-cifra="total"]', euros(linea.total_cents, locale));
    poner('[data-jw-hoja-cuando]', String(pagina.cuando ?? '').replace(':dia', diaLargo(calculo.dia, locale)).replace(':hora', calculo.hora.slice(0, 5)).replace(':duracion', fila.label ?? ''));
    doc.querySelectorAll('[data-jw-hoja-grupo]').forEach((el) => { el.hidden = false; });
    doc.querySelectorAll('[data-jw-hoja-general]').forEach((el) => { el.hidden = true; });

    return true;
}

/** El botón de guardar en PDF (imprimir) y, con `?imprimir=1`, imprimir sola cuando la hoja ya tiene sus cifras. */
export function montarHoja({ doc = document, win = window } = {}) {
    doc.querySelectorAll('[data-jw-hoja-imprimir]').forEach((b) => b.addEventListener('click', () => win.print()));
    const lista = rellenarHoja({ doc, win });

    if (new URLSearchParams(win.location.search).get('imprimir') === '1') lista.finally(() => win.setTimeout(() => win.print(), 300));

    return lista;
}

if (typeof document !== 'undefined') montarHoja();
