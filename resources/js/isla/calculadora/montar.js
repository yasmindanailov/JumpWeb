/**
 * **LA ENTRADA DE LA CALCULADORA DE UNA PÁGINA** (T4d·4 de `docs/specs/isla-y-landing-nueva.md` §4.12): una entrada de
 * Vite propia, que la página pide con `scripts` de `<x-pagina>` (contrato de página). Monta la calculadora —su app de
 * Vue con su Pinia, `useCalculadora`— donde la pieza le deja sitio: las preguntas en `[data-jw-calculadora]` (con lo
 * que la página le da, en su atributo) y el total en el `[data-jw-calculadora-lado]` de la misma sección.
 *
 * ⚠️ **Se PINTA al cargar y se PIDE al acercarse** (medido: la pieza 3 empieza a 1,03 pantallas en 1280 y a 1,22 en
 * 390, así que «una pantalla antes» era «al llegar»). La vista sale entera con lo que da la página —sus filas con su
 * «desde», sus textos, sus calcetines—: sin hueco y sin salto. Lo que pide a la API (los días, la ficha, las horas)
 * espera a que la pieza esté a 300 px de la vista, al primer toque dentro de ella, o a nada si se llega a `#precio`:
 * quien no baja no le cuesta al servidor ni una petición.
 * ⚠️ Lo que el producto sabe y la página no (sus textos de `lang/<idioma>/isla.php`, el titular de la cesta —el mismo
 * `auth()->id()` que el arranque del motor— y el idioma) llega del layout en `#jw-calculadora-motor`.
 */
import { createApp, h } from 'vue';
import { createPinia } from 'pinia';
import '../isla.css';
import CalculadoraEntradas from './CalculadoraEntradas.vue';
import { useCalculadora } from './useCalculadora.js';
import { CLAVE_TEXTOS } from '../piezas/textos.js';

/**
 * Monta la calculadora en su sitio (pintada, sin pedir nada). Devuelve `{ app, arrancar }` —`arrancar()` hace las
 * peticiones, una vez—, o `null` sin sitio, sin su lado o sin datos.
 */
export function montarCalculadora(sitio, { textos = {}, owner = null, locale = 'es' } = {}) {
    let pagina = null;

    try { pagina = JSON.parse(sitio?.dataset?.jwCalculadora ?? 'null'); } catch { pagina = null; }
    const lado = sitio?.closest('section')?.querySelector('[data-jw-calculadora-lado]');

    if (! pagina?.filas?.length || ! lado) return null;
    let calculadora = null;
    const app = createApp({
        setup() {
            calculadora = useCalculadora({ pagina, textos, locale, owner });

            return () => h(CalculadoraEntradas, { v: calculadora.vista.value, lado, onCambiar: calculadora.cambiar, onReservar: calculadora.reservar });
        },
    });

    app.use(createPinia());
    app.provide(CLAVE_TEXTOS, () => textos);
    app.mount(sitio);

    return { app, lado, arrancar: () => calculadora.arrancar() };
}

/** Lo del motor que da el layout, o lo mínimo si no está. */
function motorDeLaPagina(doc) {
    try {
        return JSON.parse(doc.getElementById('jw-calculadora-motor')?.textContent ?? '{}');
    } catch {
        return {};
    }
}

/** Monta ya y arranca al acercarse, al primer toque o en el acto si se llega a la pieza (`#precio`). */
export function montarYArrancarAlAcercarse({ doc = document, win = window } = {}) {
    const sitio = doc.querySelector('[data-jw-calculadora]');
    const montada = sitio ? montarCalculadora(sitio, motorDeLaPagina(doc)) : null;

    if (! montada) return null;
    if (win.location.hash === '#precio' || typeof win.IntersectionObserver !== 'function') {
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
