/**
 * EL ESTADO DE LA ISLA: compone sus comportamientos (colocación, pila de paneles, morph, asentado, cruce, aviso) con la
 * situación que resuelve `situacion.js`, y devuelve exactamente lo que pinta `IslaFlotante.vue` (`CE-6`: el
 * componente pinta; esto decide). Sigue el orden de `ParkIsland.jsx` bloque a bloque: cuando el diseño cambie, se
 * compara este fichero —y los `use*` que llama— con el suyo.
 *
 * **Z6a** (zip (6), «tres huecos, cristal y morph», `isla-y-landing-nueva.md` §4.27): la barra tiene tres huecos fijos —el
 * menú, la acción y la cuenta—; la frase va siempre y la acción también (con un botón de la página a la vista, en
 * secundaria); la tarea y la reserva de hoy ya no mandan en la barra: ponen el punto en la cuenta.
 *
 * ⚠️ El orden de las llamadas NO es cosmético. Los `onMounted` corren en el orden en que se registran (el ancho,
 * luego la primera medida, luego `isla:abrir`) y los `watch` también: es el orden del port de una pieza, y el banco de
 * la isla lo midió así (52/52 a 0 px).
 */
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { resolverSituacion, reparto } from './situacion.js';
import { t as texto } from '../sidebar/i18n.js';
import { estiloIsla, estiloMedida, estiloRaiz, tamano } from './forma.js';
import { useAncho } from './useColocacion.js';
import { useMorfeo } from './useMorfeo.js';
import { useAviso } from './useAviso.js';
import { useCapa, usePila } from './usePaneles.js';
import { useCompraCapa } from './useCompraCapa.js';
import { useRelevo } from './useRelevo.js';
import { useAltoIsla } from './useAltoIsla.js';
import { useTeclado } from './useTeclado.js';
import { useAsentado } from './useAsentado.js';
import { useCruce } from './useCruce.js';
import { useSinSaturar } from './useSinSaturar.js';
import { useHueco } from './useHueco.js';
import { dejarVuelo } from './relevo.js';
import { salidaDePanel, vueloDesde } from './movimiento.js';

export function useIsla(props, { wrapRef, islandRef, sizerRef, panelRef, rowRef, lineRowRef }) {
    const t = (clave) => texto(props.textos, clave);
    const wide = useAncho(props);
    const pila = usePila();
    const { stack, view, plansFromToday, alternarPanel } = pila;

    // SIN SATURAR (Z6b): la razón de la pieza que se lee y la frase que quita su miedo, repartidas por prioridad (nada al
    // llegar, las de decisión siempre, el resto una por visita y con 6 s de calma). La tabla recibe lo que toca decir YA.
    const sinSaturar = useSinSaturar({ reason: () => props.reason, reassurance: () => props.reassurance, chosen: () => props.chosen });

    // Con el menú abierto, el velo tapa la página: su botón ya no se ve, así que la acción de la isla va en principal.
    const entrada = (ctaVisible) => ({
        page: props.page, today: props.today, offer: props.offer, reassurance: sinSaturar.frase.value, quote: props.quote,
        chosen: props.chosen, filling: props.filling, task: props.task, resume: props.resume, payment: props.payment,
        paymentText: props.paymentText, bookingToday: props.bookingToday, checkout: props.checkout, ctaVisible,
        reason: sinSaturar.razon.value, waiting: props.waiting,
        onRetry: props.onRetry, onPayBizum: props.onPayBizum, onManual: props.onManual, onDismiss: props.onDismiss,
    });
    const s0 = computed(() => resolverSituacion(entrada(props.ctaVisible), props.textos));
    const menuOpen = computed(() => view.value !== null && view.value !== s0.value.opens);
    const sRaw = computed(() => (menuOpen.value && props.ctaVisible ? resolverSituacion(entrada(false), props.textos) : s0.value));
    const inCheckout = computed(() => sRaw.value.id === 'compra');
    const isOpen = computed(() => view.value !== null || inCheckout.value);
    const top = computed(() => props.placement === 'top' || (props.placement === 'auto' && wide.value));

    // `usaAncho`: el ancho medido solo manda en fila (`r`, más abajo; se lee al medir, ya montada).
    const { box, animate, calmaCapa, reservado, remedirCuando } = useMorfeo({ wrapRef, sizerRef, isOpen, inCheckout, usaAncho: () => r.value.row });

    // Lo que trae el scroll espera a ASENTARSE (Z6a): la frase y la etiqueta, 250ms; el tono de la acción, 200ms. Bajando
    // deprisa, la isla no parpadea entre situaciones: salta a la última. Lo que provoca la persona (abrir, la compra, el
    // pago fallido, la reserva a medias) va al momento, y la llegada también (aún no anima).
    const inmediato = () => ! animate.value || isOpen.value || Boolean(sRaw.value.locked) || Boolean(sRaw.value.urgent);
    const sBase = useAsentado(
        () => sRaw.value,
        () => `${sRaw.value.id}|${sRaw.value.line || ''}|${sRaw.value.note || ''}|${sRaw.value.action ? sRaw.value.action.label : ''}`,
        () => (inmediato() ? 0 : 250),
    );
    const calmA = useAsentado(() => Boolean(sRaw.value.calm), () => (sRaw.value.calm ? '1' : '0'), () => (inmediato() ? 0 : 200));
    const s = computed(() => ({ ...sBase.value, calm: calmA.value }));

    // Un panel abierto por la acción (el selector, Mi QR): mientras está abierto, el botón de acción desaparece.
    const actionView = computed(() => view.value !== null && (view.value === 'plans' || Boolean(s.value.action && s.value.action.panel === view.value)));
    const titleInRow = computed(() => actionView.value && view.value !== null);

    const shownNotice = useAviso(props, isOpen);
    const { alTeclear } = useCapa({ islandRef, panelRef, isOpen, inCheckout, checkout: () => props.checkout, pila });
    const { anuncio } = useCompraCapa({
        islandRef, inCheckout, clave: computed(() => (inCheckout.value && props.checkout ? props.checkout.key : null)), bloquea: () => props.bloqueaPagina,
    });
    const { veloSaliente } = useRelevo({ islandRef, isOpen, inCheckout, top });
    // Al cerrar un panel, su velo se va fundido (el de la compra lo funde la píldora que la releva).
    watch(isOpen, (abierta, antes) => { if (antes && ! abierta && props.scrim) veloSaliente.value = true; });
    // Cerrar: el contenido se va en 100ms antes de que la isla vuelva (si entretanto se abre otra cosa, se deshace).
    pila.alSalir((hecho, abortado) => {
        const pn = panelRef.value?.elemento?.();
        salidaDePanel(pn, () => { if (abortado() && pn) pn.getAnimations().forEach((a) => a.cancel()); hecho(); });
    });

    // El CRUCE (Z6a): cuando cambia lo que dice o lo que ofrece, lo viejo se desenfoca encima mientras lo nuevo llega.
    const accionKey = computed(() => (s.value.action && ! actionView.value ? String(s.value.action.label || '') : ''));
    const cruce = useCruce({ s, accionKey, animate });

    // Tocar la píldora la hunde (0,97) antes de crecer: el bote empieza en el dedo (02b). Solo cerrada.
    const hundida = ref(false);
    const hundir = (e) => { if (!isOpen.value && !inCheckout.value && e.target.closest && e.target.closest('button, a')) hundida.value = true; };
    const soltar = () => { hundida.value = false; };

    // DOS VELOCIDADES (zip (6)): lo que provoca quien la toca —o con un panel abierto—, rápido (`--dur-island`); lo que trae
    // el scroll, en calma (`--dur-island-calma`). El toque cuenta 1,2s.
    const tocado = ref(false);
    let relojToque = null;
    const tocar = () => { tocado.value = true; clearTimeout(relojToque); relojToque = setTimeout(() => { tocado.value = false; }, 1200); };
    onBeforeUnmount(() => clearTimeout(relojToque));
    const rapido = computed(() => isOpen.value || tocado.value);

    // ── Reparto de la frase: siempre (Z6a), salvo con el banner a la vista: una sola voz (Z6b) ──
    const r = computed(() => reparto({ s: s.value, top: top.value, menuOpen: menuOpen.value, inCheckout: inCheckout.value, isOpen: isOpen.value }));
    remedirCuando([top, () => r.value.row, inCheckout, isOpen, () => s.value.id, view, () => stack.value.length]);
    // El teclado del móvil en la capa grande (§4.16): la raíz se ciñe a lo que se ve y la capa mide ese alto.
    const { kb } = useTeclado(() => inCheckout.value && ! top.value);
    // La primera pantalla (zip del 27-09): el alto en reposo, publicado para la cabecera (`--island-h`).
    useAltoIsla({
        rowRef, lineRowRef,
        estado: () => ({ isOpen: isOpen.value, inCheckout: inCheckout.value, extra: s.value.extra, row: r.value.row, top: top.value }),
    });

    const grown = computed(() => isOpen.value || Boolean(props.cookies) || Boolean(shownNotice.value) || (r.value.hasLine && !r.value.row) || box.value.h > 70);
    const openRow = computed(() => isOpen.value && !inCheckout.value);
    const stretch = computed(() => (openRow.value || Boolean(s.value.extra) || Boolean(shownNotice.value) || Boolean(props.cookies)) && !(r.value.row && r.value.hasLine));
    // En calma, sin rebote: la capa grande y el pago fallido. Lo demás —también abrir y cerrar un panel— es el morph.
    const calmaCaja = computed(() => inCheckout.value || calmaCapa.value || s.value.id === 'pago-fallido');

    // LA CUENTA (Z6a): el control de la derecha, siempre. Sin sesión, la silueta («Cuenta», abre Entrar); con sesión, el QR
    // («Mi QR», abre Tu QR). El punto usa el código de la frase: lima, reserva hoy (situación 14); naranja, algo que hacer
    // (situación 13 o `account.pending`). Con una capa abierta, su sitio es de la X: se cierra tocando donde se abrió.
    const sesion = computed(() => props.account.state === 'session');
    const avisoCuenta = computed(() => (props.bookingToday
        ? { color: 'var(--isla-vivo)', texto: props.bookingToday.text + (props.bookingToday.extra ? ` · ${props.bookingToday.extra}` : '') }
        : props.task || props.account.pending
            ? { color: 'var(--isla-alerta)', texto: (props.task && props.task.text) || props.account.pendingText || '' }
            : null));
    const cuenta = computed(() => ({
        label: (sesion.value ? t('control.mi_qr') : t('control.cuenta')) + (avisoCuenta.value && avisoCuenta.value.texto ? ` · ${avisoCuenta.value.texto}` : ''),
        icon: sesion.value ? 'qr-code' : 'user-round',
        dot: avisoCuenta.value && ! isOpen.value ? avisoCuenta.value.color : null,
    }));
    function pulsarCuenta(e) {
        const capa = sesion.value ? props.account.onQr : props.account.onClick;
        if (capa) { pila.poner([]); capa({ from: 'isla' }); return; }
        alternarPanel(sesion.value ? 'qr' : 'cuenta', e);
    }
    // «¿Lo hablamos?» (situación 12 en la barra): cuando la página ve que se atasca, una nota bajo la frase que abre la
    // ayuda. No añade un control ni quita la acción.
    const ayudaEnFrase = computed(() => Boolean(props.help && props.help.stuck && ! isOpen.value));

    // La sexta vista, la RAZÓN abierta (Z6b): sin título en su cabecera —el sobretítulo va dentro—, como el diseño.
    const panelTitle = computed(() => (inCheckout.value || view.value === 'razon' ? null
        : view.value === 'menu' ? t('panel.menu')
            : view.value === 'plans' ? (props.plans && props.plans.title) || t('panel.planes')
                : view.value === 'resumen' ? t('panel.calculo')
                    : view.value === 'qr' ? t('panel.qr')
                        : view.value === 'help' ? t('panel.ayuda')
                            : view.value === 'cuenta' ? t('panel.cuenta')
                                : view.value === 'cookies' ? t('panel.cookies') : null));

    const hayLinea = computed(() => r.value.hasLine);
    const lineaAbre = computed(() => (s.value.opens ? (e) => alternarPanel(s.value.opens, e) : null));

    // La acción: con selector de plan, «Reservar» (y «Reservar para hoy») lo abren en vez de navegar.
    const conSelector = computed(() => Boolean(props.plans) && (s.value.id === 'desde' || s.value.id === 'hoy'));
    const accion = computed(() => (s.value.action && !actionView.value ? s.value.action : null));
    const accionHref = computed(() => (accion.value && (accion.value.panel || conSelector.value) ? undefined : accion.value && accion.value.href));
    const accionAbierta = computed(() => view.value === 'plans' || (accion.value && accion.value.panel ? view.value === accion.value.panel : false));
    function pulsarAccion(e) {
        const a = accion.value;
        if (a.panel) { alternarPanel(a.panel, e); return; }
        if (conSelector.value) {
            plansFromToday.value = s.value.id === 'hoy' && a.label === t('accion.reservar_hoy');
            alternarPanel('plans', e);
            return;
        }
        if (a.onClick) a.onClick(e);
    }

    // Del selector a la compra, el nombre del plan viajará a la cabecera del paso (02b): lo toma la isla de la compra.
    const elegirPlan = (o, e) => { dejarVuelo(vueloDesde(e?.currentTarget, o.title)); pila.poner([]); if (o.onClick) o.onClick({ fromToday: plansFromToday.value }); };
    const navegar = (it, e) => { pila.poner([]); if (props.onNavigate) props.onNavigate(it, e); };

    // EL BANNER (Z6b) en el sitio de la acción: la razón (con un botón de la página a la vista), la espera o lo hecho. Con
    // la isla abierta, no: el velo tapa el botón de la página y la acción vuelve, en naranja.
    const banner = computed(() => (s.value.bn && ! isOpen.value ? s.value.bn : null));
    // Se toca entero. Lo que trae su propio destino (la espera, lo hecho: `onClick`) va a él; una razón se ABRE en su vista,
    // que la recuerda mientras está abierta (abierta, la isla ya no la tiene). ⚠️ También la VIVA («Sáb 3 y dom 4,
    // libres»): en el diseño solo se abría la de tipo `razon`, y tocar la viva no hacía nada (`bnClick` de `ParkIsland`).
    const razonAbierta = ref(null);
    function pulsarBanner(e) {
        const bn = banner.value;
        if (! bn) return;
        if (bn.onClick) { bn.onClick(e); return; }
        razonAbierta.value = bn;
        alternarPanel('razon', e);
    }
    // El relevo del hueco: acción ⇄ banner, o un banner por otro (`useHueco.js`).
    const hueco = computed(() => (banner.value
        ? { clave: `bn|${banner.value.type}|${banner.value.text}`, bn: banner.value }
        : accion.value ? { clave: 'act', accion: { label: accion.value.label, calm: Boolean(s.value.calm) && ! isOpen.value } } : null));
    const huecoSale = useHueco(() => hueco.value);

    const panelProps = computed(() => ({
        vista: view.value, titulo: panelTitle.value, tituloEnFila: titleInRow.value, top: top.value,
        menuItems: props.menuItems, homeLabel: props.homeLabel, contact: props.contact, lang: props.lang, onLanguage: props.onLanguage,
        account: props.account, help: props.help, cookies: props.cookies, razon: razonAbierta.value,
        preferencias: props.cookiePrefs, plans: props.plans, plansFromToday: plansFromToday.value, quote: props.quote,
    }));

    return {
        t, s, stack, view, top, r, isOpen, inCheckout, openRow, stretch, titleInRow, panelTitle, shownNotice,
        hayLinea, lineaAbre, accion, accionHref, accionAbierta, pulsarAccion, alTeclear, alternarPanel, panelProps, anuncio,
        cerrar: pila.cerrar, atras: pila.atras, apilarPanel: pila.apilarPanel, elegirPlan, navegar,
        banner, pulsarBanner, hueco, huecoSale,
        cruce, cuenta, pulsarCuenta, ayudaEnFrase, tocar, hundir, soltar, veloSaliente, kb,
        tono: computed(() => (s.value.calm && ! isOpen.value ? 'secundaria' : 'principal')),
        tamano: computed(() => tamano({ inCheckout: inCheckout.value, isOpen: isOpen.value, notice: shownNotice.value })),
        estiloRaiz: computed(() => estiloRaiz({ gutter: props.gutter, top: top.value, inCheckout: inCheckout.value, reservado: reservado.value, kb: kb.value })),
        // Entre páginas, la isla de la página se queda (`view-transition-name`); la compra y Mi cuenta no cruzan de página.
        estiloIsla: computed(() => estiloIsla({
            row: r.value.row, box: box.value, alert: s.value.tone === 'alert', grown: grown.value, animate: animate.value, calm: calmaCaja.value,
            lee: inCheckout.value, inCheckout: inCheckout.value, rapido: rapido.value, hundida: hundida.value, nombre: inCheckout.value ? null : 'isla',
        })),
        estiloMedida: computed(() => estiloMedida({ row: r.value.row, top: top.value, isOpen: isOpen.value, cap: box.value.cap, maxWidth: props.maxWidth, inCheckout: inCheckout.value })),
    };
}
