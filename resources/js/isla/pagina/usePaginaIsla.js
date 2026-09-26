/**
 * **LA ISLA VIVA EN UNA PÁGINA, en marcha** (T4e de `docs/specs/isla-y-landing-nueva.md` §4.12): lo que cambia mientras
 * se mira la página y lo que hace cada botón; lo que DECIDE está en `pagina.js`, puro y probado.
 *
 *   · **Qué se ve**: la geometría del diseño en cada desplazamiento (un fotograma por ráfaga), sobre los primarios de la
 *     página (`data-isla-cta`, contrato página↔isla §4.3) y la línea [Hoy] de la pieza 6 (`data-hoy-linea`).
 *   · **Los huecos de hoy** llegan con la página (`today.slots`, del hecho `availability_today`): los mismos que dicen su
 *     cabecera, «dónde y cuándo» y su cierre, sin petición desde aquí ni salto al llegar la respuesta.
 *   · **El aviso de cookies**: el MISMO almacén que la web de siempre (`ui/cookie-consent.js`): lo lee del `<body>`
 *     (`site/body-state`, T4b·4), lo guarda con su POST atómico (`RGPD-05`) y dispara `cookies-updated`, que es lo que
 *     esperan los cargadores del driver y de los píxeles. Con la compra abierta, espera.
 *   · **La calculadora** (otra app): cuenta lo que falta y lo elegido (`jw:calculadora`); «Reservar para hoy» le pide
 *     hoy (`jw:calculadora:hoy`).
 *   · **La compra**: mientras el cajón la tiene abierta (`jw:cajon:open` / `jw:cajon:close`), esta isla se aparta y
 *     la de la compra ocupa su sitio.
 */
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, watch } from 'vue';
import { createCookiesStore } from '../../ui/cookie-consent.js';
import { medirVista, preferenciasDeCookies, propsDeLaIsla } from './pagina.js';

export function usePaginaIsla({ config, textos, doc = document, win = window }) {
    const e = reactive({ vista: { cta: false, hoy: false }, calculo: null, compraAbierta: false, aviso: null });
    const cookies = reactive(createCookiesStore({ doc, win, purchase: () => ({ isOpen: e.compraAbierta }) }));

    // «Guardado»: el aviso de la isla, que se enseña al CAMBIAR su texto (por eso se vacía antes).
    const avisar = (texto) => { e.aviso = null; nextTick(() => { e.aviso = texto; }); };
    /**
     * La segunda capa guarda al momento, con el POST atómico del almacén (`RGPD-05`). Si el servidor no lo confirma,
     * las finalidades vuelven a como estaban: el interruptor no puede decir algo que no se ha guardado.
     */
    const guardar = (decidir) => {
        const antes = { ...cookies.prefs };

        return decidir().then((confirmado) => {
            if (confirmado) avisar(textos?.cookies?.guardado ?? '');
            else cookies.prefs = antes;
        });
    };

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

    const alCalcular = (ev) => { e.calculo = ev.detail ?? null; };
    // Un contenido de la página que necesita una categoría («Cargar el mapa») se la pide a la isla, dueña del almacén;
    // al confirmarla el servidor, el almacén dispara `cookies-updated` y el contenido se carga.
    const alPedirCategoria = (ev) => { if (ev.detail?.categoria) cookies.grant(ev.detail.categoria); };
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
        // «Configurar»: la segunda capa, dentro de la isla («Tus cookies»; el mockup no la dibuja y el owner la encargó).
        configurarCookies: () => { win.dispatchEvent(new win.CustomEvent('isla:abrir', { detail: { panel: 'cookies' } })); },
        politicaCookies: () => { win.location.href = config.cookiesUrl; },
        navegar: (it) => { if (it?.href) win.location.href = it.href; },
        // La cuenta, en su zona (`cajon.openAccount`, el mismo camino que el menú de siempre): con la isla, en su capa de
        // Mi cuenta (T5), que abierta desde el menú vuelve a él con su flecha. Sin el cargador del cajón, la puerta de entrar.
        abrirCuenta: (zona, desde) => {
            const cajon = win.JumpWeb?.cajon;
            if (cajon?.openAccount) cajon.openAccount({ preventDefault() {} }, zona, { desde: desde === 'menu' ? 'menu' : null });
            else win.location.href = '/login';
        },
    };

    const preferencias = computed(() => preferenciasDeCookies({
        categorias: cookies.categories, prefs: cookies.prefs, legales: config.cookiesPanel ?? {}, textos,
        acciones: {
            cambiar: (id, activa) => guardar(() => cookies.persist({ ...cookies.prefs, [id]: activa })),
            aceptarTodas: () => guardar(() => cookies.acceptAll()),
            rechazarTodas: () => guardar(() => cookies.rejectAll()),
            politica: () => { win.location.href = config.cookiesUrl; },
        },
    }));

    const props = computed(() => propsDeLaIsla({
        config, textos, acciones,
        estado: { vista: e.vista, calculo: e.calculo, cookies: cookies.showing, preferencias: preferencias.value, aviso: e.aviso },
    }));

    // El hecho `consent_shown`, una vez por página y cuando el aviso se enseña de verdad (como el banner de siempre).
    watch(() => cookies.showing, (enPantalla) => { if (enPantalla) cookies.noteShown(); }, { immediate: true });

    onMounted(() => {
        win.addEventListener('scroll', mover, { passive: true });
        win.addEventListener('resize', mover);
        doc.addEventListener('jw:calculadora', alCalcular);
        doc.addEventListener('jw:cookies:conceder', alPedirCategoria);
        doc.addEventListener('jw:cajon:open', alAbrir);
        doc.addEventListener('jw:cajon:close', alCerrar);
        win.setTimeout(medir, 300);
    });
    onBeforeUnmount(() => {
        win.removeEventListener('scroll', mover);
        win.removeEventListener('resize', mover);
        doc.removeEventListener('jw:calculadora', alCalcular);
        doc.removeEventListener('jw:cookies:conceder', alPedirCategoria);
        doc.removeEventListener('jw:cajon:open', alAbrir);
        doc.removeEventListener('jw:cajon:close', alCerrar);
        if (fotograma) win.cancelAnimationFrame(fotograma);
    });

    return { props, visible: computed(() => ! e.compraAbierta) };
}
