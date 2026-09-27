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
 *     la de la compra ocupa su sitio. Si se abre EN LA ISLA (Z3, `#782`), no se aparta hasta que la otra la releva
 *     (`isla:relevada`: ya creció desde esta píldora), con un tope (`ESPERA_RELEVO`) por si el motor no llega; así no
 *     queda un hueco sin isla la primera vez, mientras se descarga (medido: ~290ms). Con el cajón lateral, al momento.
 */
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, watch } from 'vue';
import { createCookiesStore } from '../../ui/cookie-consent.js';
import { cajaSiVisible, medirVista, preferenciasDeCookies, propsDeLaIsla } from './pagina.js';
import { loTomaUnaCapa, tomarAvisoDelServidor } from './aviso-servidor.js';
import { precargarCompra } from './precarga.js';

/** Lo más que la píldora espera a que la releve la isla de la compra (la primera apertura descarga el motor). */
export const ESPERA_RELEVO = 2500;

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
    // La franja de la isla cambia de alto sin que la página se mueva —el aviso de cookies que se va, un aviso que crece—
    // y lo que se ve cambia con ella: se vuelve a medir (Z3, `#782`). Medido: rechazadas las cookies arriba del todo, la
    // isla seguía sin ceder su acción al botón de la cabecera hasta que alguien se desplazaba.
    let ro = null;
    let observada = null;
    const observar = (el) => {
        if (el === observada || typeof win.ResizeObserver === 'undefined') return;
        if (ro) ro.disconnect();
        observada = el;
        ro = el ? new win.ResizeObserver(() => mover()) : null;
        if (ro) ro.observe(el);
    };
    const rect = (el) => (el ? el.getBoundingClientRect() : null);
    const medir = () => {
        fotograma = 0;
        if (! ctas.length || ctas.some((el) => ! el.isConnected)) ctas = Array.from(doc.querySelectorAll('[data-isla-cta]'));
        const isla = doc.querySelector('[data-situation]');
        observar(isla);
        // Lo que la llegada limpia esconde (`[data-llegada="oculto"]`, la cabecera del zip del 27-09) no se ve: no cuenta.
        const vista = medirVista({
            ctas: ctas.map(cajaSiVisible), hoyLinea: cajaSiVisible(doc.querySelector('[data-hoy-linea] > p')),
            isla: rect(isla), alto: win.innerHeight,
        });
        // Solo si CAMBIA (`#783`): un objeto nuevo en cada fotograma de scroll repintaba la isla entera en cada uno
        // (medido con la CPU ×4: 788 cambios de su estilo en 265 fotogramas de desplazarse, sin que cambiara nada).
        if (vista.cta !== e.vista.cta || vista.hoy !== e.vista.hoy) e.vista = vista;
    };
    const mover = () => { if (! fotograma) fotograma = win.requestAnimationFrame(medir); };

    const alCalcular = (ev) => { e.calculo = ev.detail ?? null; };
    // Un contenido de la página que necesita una categoría («Cargar el mapa») se la pide a la isla, dueña del almacén;
    // al confirmarla el servidor, el almacén dispara `cookies-updated` y el contenido se carga.
    const alPedirCategoria = (ev) => { if (ev.detail?.categoria) cookies.grant(ev.detail.categoria); };
    let espera = 0;
    const apartarse = () => { win.clearTimeout(espera); e.compraAbierta = true; };
    // Con la isla como carcasa (lo dice el servidor, `config.carcasa`: la primera vez, el aviso dice «cajón» porque el
    // motor aún no ha arrancado), la píldora espera a que la releven; con el cajón lateral, se aparta ya.
    const alAbrir = (ev) => {
        if (config.carcasa !== 'isla' && ev?.detail?.surface !== 'isla') { apartarse(); return; }
        win.clearTimeout(espera);
        espera = win.setTimeout(apartarse, ESPERA_RELEVO);
    };
    // Cualquier capa grande que se monta la releva: también la que nace abierta (la vuelta del banco), cuyo aviso de
    // apertura sonó antes de que esta isla escuchara.
    const alRelevar = () => apartarse();
    const alCerrar = () => { win.clearTimeout(espera); espera = 0; e.compraAbierta = false; };
    const irA = (selector) => {
        const el = doc.querySelector(selector);
        if (! el) return;
        // Arriba, lo que ocupa la banda fija de la isla (16 + 62) y su aire (16): el `irA` del diseño (zip del 27-09).
        win.scrollTo({ top: el.getBoundingClientRect().top + win.scrollY - (win.innerWidth >= 900 ? 94 : 16), behavior: 'smooth' });
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
        // Un plan del selector (T6a): la compra con su intención (`cajon.openWith`, el mismo camino que la calculadora).
        comprar: (intencion) => { win.JumpWeb?.cajon?.openWith?.(intencion); },
    };

    /**
     * Los «Reservar» de la PÁGINA que abren el selector (T6a; el contrato página↔isla, §4.3): los marca `data-isla-planes`
     * —la cabecera y el cierre de la portada, el visor de vídeos— y su `href` es la salida sin JavaScript. Solo si la
     * página trae selector; si no, el enlace sigue su camino.
     */
    const alPulsar = (ev) => {
        const boton = ev.target?.closest?.('[data-isla-planes]');

        if (! boton || ! props.value.plans) return;
        ev.preventDefault();
        win.dispatchEvent(new win.CustomEvent('isla:abrir', { detail: { panel: 'plans', trigger: boton } }));
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
        doc.addEventListener('click', alPulsar);
        win.addEventListener('isla:relevada', alRelevar);
        // La cabecera avisa al terminar de medirse (`pj-hero:medida`): con las fuentes o la versión compacta, su botón
        // puede entrar o salir de la pantalla sin scroll ni resize, y la isla vuelve a mirar si cede el suyo.
        win.addEventListener('pj-hero:medida', medir);
        win.setTimeout(medir, 300);
        // La compra, adelantada en segundo plano con la página quieta (`#783`): la primera apertura no la espera.
        precargarCompra(config.precargar, { doc, win });
        // El aviso que dejó el servidor al volver aquí (T5e·2, `#779`), si no lo toma una capa que se abre al cargar. El
        // «Aviso» de la isla crece un momento y se va solo: sirve para CONFIRMAR («Tu cuenta ha sido eliminada»), no para
        // un «no se pudo» largo, que se leería a medias (WCAG 2.2.1): ése se queda para Mi cuenta, al abrirla.
        if (! loTomaUnaCapa(win.location, doc)) {
            const aviso = tomarAvisoDelServidor(doc, { si: (a) => a.tono === 'success' });

            if (aviso) avisar(aviso.texto);
        }
    });
    onBeforeUnmount(() => {
        win.removeEventListener('scroll', mover);
        win.removeEventListener('resize', mover);
        doc.removeEventListener('jw:calculadora', alCalcular);
        doc.removeEventListener('jw:cookies:conceder', alPedirCategoria);
        doc.removeEventListener('jw:cajon:open', alAbrir);
        doc.removeEventListener('jw:cajon:close', alCerrar);
        doc.removeEventListener('click', alPulsar);
        win.removeEventListener('isla:relevada', alRelevar);
        win.removeEventListener('pj-hero:medida', medir);
        win.clearTimeout(espera);
        if (ro) ro.disconnect();
        if (fotograma) win.cancelAnimationFrame(fotograma);
    });

    return { props, visible: computed(() => ! e.compraAbierta) };
}
