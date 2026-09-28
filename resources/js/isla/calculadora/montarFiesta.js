/**
 * **LA ENTRADA DE LA CALCULADORA DE LA FIESTA** (T6b·3 de `docs/specs/isla-y-landing-nueva.md` §4.18, `#836`): una
 * entrada de Vite propia —`calculadora-fiesta` en los `scripts` de `<x-pagina>`—, para que Kids y Jump no carguen la
 * fiesta ni Cumpleaños las entradas. Como `montar.js`: las preguntas en `[data-jw-calculadora-fiesta]` (con lo que la
 * página le da, en su atributo) y el total en el `[data-jw-calculadora-lado]` de la misma sección; lo que solo sabe el
 * producto, en `#jw-calculadora-motor`.
 *
 * ⚠️ **Se PINTA al cargar y se PIDE al acercarse** (300 px antes, al primer toque, o en el acto si se llega a `#calcula`).
 * ▶ Con la ISLA, por un evento del documento: lo que falta y lo elegido van a ella (`jw:calculadora`). Con la PÁGINA, por
 * su marca: un enlace con `data-jw-calculadora-dia="AAAA-MM-DD"` («Próximos fines de semana con hueco») elige ese día
 * aquí al pulsarlo, y su `href` la trae a la vista. La página no programa nada: declara, como con `data-jw-open`.
 */
import { createApp, h, watch } from 'vue';
import { createPinia } from 'pinia';
import '../isla.css';
import CalculadoraFiesta from './CalculadoraFiesta.vue';
import { useCalculadoraFiesta } from './useCalculadoraFiesta.js';
import { CLAVE_TEXTOS } from '../piezas/textos.js';

/** Monta la calculadora de la fiesta en su sitio (pintada, sin pedir nada), o `null` sin sitio, sin su lado o sin packs. */
export function montarCalculadoraFiesta(sitio, { textos = {}, owner = null, locale = 'es', marcas = [] } = {}) {
    let pagina = null;

    try { pagina = JSON.parse(sitio?.dataset?.jwCalculadoraFiesta ?? 'null'); } catch { pagina = null; }
    const lado = sitio?.closest('section')?.querySelector('[data-jw-calculadora-lado]');

    if (! pagina?.packs?.length || ! lado) return null;
    let calculadora = null;
    const app = createApp({
        setup() {
            calculadora = useCalculadoraFiesta({ pagina, textos, locale, owner });
            watch(() => calculadora.vista.value.isla, (detail) => document.dispatchEvent(new CustomEvent('jw:calculadora', { detail })), { deep: true });
            document.addEventListener('click', (ev) => {
                const dia = ev.target?.closest?.('[data-jw-calculadora-dia]')?.dataset.jwCalculadoraDia;

                if (/^\d{4}-\d{2}-\d{2}$/.test(dia ?? '')) calculadora.elegirDia(dia);
            });

            return () => h(CalculadoraFiesta, { v: calculadora.vista.value, lado, marcas, onCambiar: calculadora.cambiar, onReservar: calculadora.reservar });
        },
    });

    app.use(createPinia());
    app.provide(CLAVE_TEXTOS, () => textos);
    app.mount(sitio);

    return { app, lado, arrancar: () => calculadora.arrancar() };
}

function motorDeLaPagina(doc) {
    try {
        return JSON.parse(doc.getElementById('jw-calculadora-motor')?.textContent ?? '{}');
    } catch {
        return {};
    }
}

/** Monta ya y arranca al acercarse, al primer toque o en el acto si se llega a la pieza (`#calcula`). */
export function montarYArrancarAlAcercarse({ doc = document, win = window } = {}) {
    const sitio = doc.querySelector('[data-jw-calculadora-fiesta]');
    const montada = sitio ? montarCalculadoraFiesta(sitio, motorDeLaPagina(doc)) : null;

    if (! montada) return null;
    if (win.location.hash === '#calcula' || typeof win.IntersectionObserver !== 'function') {
        montada.arrancar();
        return montada;
    }
    const observador = new win.IntersectionObserver((vistas) => {
        if (! vistas.some((v) => v.isIntersecting)) return;
        observador.disconnect();
        montada.arrancar();
    }, { rootMargin: '300px 0px' });

    observador.observe(sitio);
    for (const zona of [sitio, montada.lado]) {
        zona.addEventListener('pointerdown', montada.arrancar, { once: true, passive: true });
        zona.addEventListener('focusin', montada.arrancar, { once: true });
    }

    return montada;
}

montarYArrancarAlAcercarse();
