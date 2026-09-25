/**
 * **LA ISLA VIVA EN UNA PÁGINA, en marcha** (T4e de `docs/specs/isla-y-landing-nueva.md` §4.12): lo que cambia mientras
 * se mira la página y lo que hace cada botón; lo que DECIDE está en `pagina.js`, puro y probado.
 *
 *   · **Qué se ve**: la geometría del diseño en cada desplazamiento (un fotograma por ráfaga), sobre los primarios de la
 *     página (`data-isla-cta`, contrato página↔isla §4.3) y la línea [Hoy] de la pieza 6 (`data-hoy-linea`).
 *   · **Los huecos de hoy**: UNA petición (`POST /availability/{id}/times`, la primera fila de la zona) tras `load` y en
 *     un rato ocioso: la página no la espera, y hasta que llega la isla dice «Hoy abrimos…» sin prometer huecos. Se
 *     descartó resolverlo en el servidor: `ProductCatalog::product()` sin memorizar hacía 176 consultas y 160–180 ms por
 *     visita (medido el 25-09), más que todos los hechos de la página juntos.
 *   · **El aviso de cookies**: el MISMO almacén que la web de siempre (`ui/cookie-consent.js`): lo lee del `<body>`
 *     (`site/body-state`, T4b·4), lo guarda con su POST atómico (`RGPD-05`) y dispara `cookies-updated`, que es lo que
 *     esperan los cargadores del driver y de los píxeles. Con la compra abierta, espera.
 *   · **La calculadora** (otra app): cuenta lo que falta y lo elegido (`jw:calculadora`); «Reservar para hoy» le pide
 *     hoy (`jw:calculadora:hoy`).
 *   · **La compra**: mientras el cajón la tiene abierta (`jw:cajon:open` / `jw:cajon:close`), esta isla se aparta y
 *     la de la compra ocupa su sitio.
 */
import { computed, onBeforeUnmount, onMounted, reactive, watch } from 'vue';
import { api } from '../../sidebar/api.js';
import { createCookiesStore } from '../../ui/cookie-consent.js';
import { medirVista, propsDeLaIsla, quedanHuecos } from './pagina.js';

export function usePaginaIsla({ config, textos, doc = document, win = window }) {
    const e = reactive({ vista: { cta: false, hoy: false }, calculo: null, huecos: false, compraAbierta: false });
    const cookies = reactive(createCookiesStore({ doc, win, purchase: () => ({ isOpen: e.compraAbierta }) }));

    let ctas = [];
    let fotograma = 0;
    const rect = (el) => (el ? el.getBoundingClientRect() : null);
    const medir = () => {
        fotograma = 0;
        if (! ctas.length || ctas.some((el) => ! el.isConnected)) ctas = Array.from(doc.querySelectorAll('[data-isla-cta]'));
        e.vista = medirVista({
            ctas: ctas.map(rect), hoyLinea: rect(doc.querySelector('[data-hoy-linea] > p')),
            isla: rect(doc.querySelector('[data-situation]')), alto: win.innerHeight,
        });
    };
    const mover = () => { if (! fotograma) fotograma = win.requestAnimationFrame(medir); };

    async function pedirHuecos() {
        if (! config.today || ! config.productoHoy) return;
        const respuesta = await api.post(`/availability/${config.productoHoy}/times`, { date: config.today.date }).catch(() => null);

        e.huecos = Boolean(respuesta?.ok) && quedanHuecos(respuesta.data);
    }

    const alCalcular = (ev) => { e.calculo = ev.detail ?? null; };
    const alAbrir = () => { e.compraAbierta = true; };
    const alCerrar = () => { e.compraAbierta = false; };
    const irA = (selector) => {
        const el = doc.querySelector(selector);
        if (! el) return;
        win.scrollTo({ top: el.getBoundingClientRect().top + win.scrollY - (win.innerWidth >= 900 ? 92 : 16), behavior: 'smooth' });
    };

    const acciones = {
        // «Reservar» / «Reservar para hoy»: el enlace ya lleva a `#precio`; para hoy, la calculadora elige el día.
        reservar: (paraHoy) => { if (paraHoy) doc.dispatchEvent(new win.CustomEvent('jw:calculadora:hoy')); },
        irAlResumen: () => irA('[data-jw-calculadora-lado]'),
        aceptarCookies: () => cookies.acceptAll(),
        rechazarCookies: () => cookies.rejectAll(),
        // ⚠️ El mockup no dibuja la segunda capa (las categorías una a una): hasta que la dibuje, la política, donde se
        // configuran (pendiente del owner, spec §4.12 T4e).
        configurarCookies: () => { win.location.href = config.cookiesUrl; },
        politicaCookies: () => { win.location.href = config.cookiesUrl; },
        navegar: (it) => { if (it?.href) win.location.href = it.href; },
        // La cuenta, en su zona del lateral (`cajon.openAccount`, el mismo camino que el menú de siempre); sin el
        // cargador del cajón, la puerta de entrar.
        abrirCuenta: (zona) => {
            const cajon = win.JumpWeb?.cajon;
            if (cajon?.openAccount) cajon.openAccount({ preventDefault() {} }, zona);
            else win.location.href = '/login';
        },
    };

    const props = computed(() => propsDeLaIsla({
        config, textos, acciones,
        estado: { vista: e.vista, calculo: e.calculo, huecos: e.huecos, cookies: cookies.showing },
    }));

    // El hecho `consent_shown`, una vez por página y cuando el aviso se enseña de verdad (como el banner de siempre).
    watch(() => cookies.showing, (enPantalla) => { if (enPantalla) cookies.noteShown(); }, { immediate: true });

    onMounted(() => {
        win.addEventListener('scroll', mover, { passive: true });
        win.addEventListener('resize', mover);
        doc.addEventListener('jw:calculadora', alCalcular);
        doc.addEventListener('jw:cajon:open', alAbrir);
        doc.addEventListener('jw:cajon:close', alCerrar);
        win.setTimeout(medir, 300);
        const ocioso = win.requestIdleCallback ?? ((fn) => win.setTimeout(fn, 1200));
        if (doc.readyState === 'complete') ocioso(pedirHuecos);
        else win.addEventListener('load', () => ocioso(pedirHuecos), { once: true });
    });
    onBeforeUnmount(() => {
        win.removeEventListener('scroll', mover);
        win.removeEventListener('resize', mover);
        doc.removeEventListener('jw:calculadora', alCalcular);
        doc.removeEventListener('jw:cajon:open', alAbrir);
        doc.removeEventListener('jw:cajon:close', alCerrar);
        if (fotograma) win.cancelAnimationFrame(fotograma);
    });

    return { props, visible: computed(() => ! e.compraAbierta) };
}
