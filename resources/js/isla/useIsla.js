/**
 * EL ESTADO DE LA ISLA: compone sus comportamientos (colocación, pila de paneles, morfeo, compacta, aviso) con la
 * situación que resuelve `situacion.js`, y devuelve exactamente lo que pinta `IslaFlotante.vue` (`CE-6`: el
 * componente pinta; esto decide). Sigue el orden de `ParkIsland.jsx` bloque a bloque: cuando el diseño cambie, se
 * compara este fichero —y los `use*` que llama— con el suyo.
 *
 * ⚠️ El orden de las llamadas NO es cosmético. Los `onMounted` corren en el orden en que se registran (el ancho,
 * luego la primera medida, luego el scroll, luego `isla:abrir`) y los `watch` también: es el orden del port de una
 * pieza, y el banco de la isla lo midió así (52/52 a 0 px).
 */
import { computed } from 'vue';
import { resolverSituacion, reparto } from './situacion.js';
import { t as texto } from '../sidebar/i18n.js';
import { estiloIsla, estiloMedida, estiloRaiz, tamano } from './forma.js';
import { useAncho, useCompacta } from './useColocacion.js';
import { useMorfeo } from './useMorfeo.js';
import { useAviso } from './useAviso.js';
import { useCapa, usePila } from './usePaneles.js';
import { useCompraCapa } from './useCompraCapa.js';

export function useIsla(props, { wrapRef, islandRef, sizerRef, panelRef }) {
    const t = (clave) => texto(props.textos, clave);
    const wide = useAncho(props);
    const pila = usePila();
    const { stack, view, plansFromToday, alternarPanel } = pila;

    // Con el menú abierto, el velo tapa la página: su botón ya no se ve, así que la isla no cede la acción.
    const entrada = (ctaVisible) => ({
        page: props.page, today: props.today, offer: props.offer, reassurance: props.reassurance, quote: props.quote,
        chosen: props.chosen, filling: props.filling, task: props.task, resume: props.resume, payment: props.payment,
        paymentText: props.paymentText, bookingToday: props.bookingToday, checkout: props.checkout, ctaVisible,
        onRetry: props.onRetry, onPayBizum: props.onPayBizum, onManual: props.onManual, onDismiss: props.onDismiss,
    });
    const s0 = computed(() => resolverSituacion(entrada(props.ctaVisible), props.textos));
    const menuOpen = computed(() => view.value !== null && view.value !== s0.value.opens);
    const s = computed(() => (menuOpen.value && props.ctaVisible ? resolverSituacion(entrada(false), props.textos) : s0.value));
    const inCheckout = computed(() => s.value.id === 'compra');
    const isOpen = computed(() => view.value !== null || inCheckout.value);
    const top = computed(() => props.placement === 'top' || (props.placement === 'auto' && wide.value));
    // Un panel abierto por la acción (el selector, Mi QR): mientras está abierto, el botón de acción desaparece.
    const actionView = computed(() => view.value !== null && (view.value === 'plans' || Boolean(s.value.action && s.value.action.panel === view.value)));
    const titleInRow = computed(() => actionView.value && view.value !== null);

    const { box, animate, calmNow, remedirCuando } = useMorfeo({ wrapRef, sizerRef, isOpen });
    const scrolledDown = useCompacta(props, wrapRef);
    const shownNotice = useAviso(props, isOpen);
    const { alTeclear } = useCapa({ islandRef, panelRef, isOpen, inCheckout, checkout: () => props.checkout, pila });
    const { anuncio } = useCompraCapa({
        islandRef, inCheckout, clave: computed(() => (inCheckout.value && props.checkout ? props.checkout.key : null)), bloquea: () => props.bloqueaPagina,
    });

    // ── Reparto de la línea de situación ──
    const r = computed(() => reparto({
        s: s.value, top: top.value, compact: props.compact, scrolledDown: scrolledDown.value, isOpen: isOpen.value,
        menuOpen: menuOpen.value, inCheckout: inCheckout.value, mobileContext: props.mobileContext,
        cookies: props.cookies, shownNotice: shownNotice.value,
    }));
    remedirCuando([top, () => r.value.row, inCheckout, isOpen, () => s.value.id, view, () => stack.value.length]);

    const grown = computed(() => isOpen.value || Boolean(props.cookies) || Boolean(shownNotice.value) || (r.value.hasLine && !r.value.row) || box.value.h > 70);
    const openRow = computed(() => isOpen.value && !inCheckout.value);
    const stretch = computed(() => (openRow.value || Boolean(s.value.extra) || Boolean(shownNotice.value) || Boolean(props.cookies)) && !(r.value.row && r.value.hasLine));
    const pendiente = computed(() => Boolean(props.account.pending) || Boolean(props.bookingToday));

    const panelTitle = computed(() => (inCheckout.value ? null
        : view.value === 'menu' ? t('panel.menu')
            : view.value === 'plans' ? (props.plans && props.plans.title) || t('panel.planes')
                : view.value === 'resumen' ? t('panel.calculo')
                    : view.value === 'qr' ? t('panel.qr')
                        : view.value === 'help' ? t('panel.ayuda')
                            : view.value === 'cuenta' ? t('panel.cuenta') : null));

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

    const elegirPlan = (o) => { stack.value = []; if (o.onClick) o.onClick({ fromToday: plansFromToday.value }); };
    const abrirCapa = (fn) => { stack.value = []; fn({ from: 'menu' }); };
    const navegar = (it, e) => { stack.value = []; if (props.onNavigate) props.onNavigate(it, e); };

    const panelProps = computed(() => ({
        vista: view.value, titulo: panelTitle.value, tituloEnFila: titleInRow.value, top: top.value,
        menuItems: props.menuItems, homeLabel: props.homeLabel, contact: props.contact, lang: props.lang,
        account: props.account, bookingToday: props.bookingToday, help: props.help, cookies: props.cookies,
        plans: props.plans, plansFromToday: plansFromToday.value, quote: props.quote,
    }));

    return {
        t, s, stack, view, top, r, isOpen, inCheckout, openRow, stretch, pendiente, titleInRow, panelTitle, shownNotice,
        hayLinea, lineaAbre, accion, accionHref, accionAbierta, pulsarAccion, alTeclear, alternarPanel, panelProps, anuncio,
        cerrar: pila.cerrar, atras: pila.atras, apilarPanel: pila.apilarPanel, elegirPlan, abrirCapa, navegar,
        tamano: computed(() => tamano({ inCheckout: inCheckout.value, isOpen: isOpen.value, notice: shownNotice.value, isCompact: r.value.isCompact })),
        estiloRaiz: computed(() => estiloRaiz({ gutter: props.gutter, top: top.value, inCheckout: inCheckout.value })),
        estiloIsla: computed(() => estiloIsla({
            row: r.value.row, box: box.value, alert: s.value.tone === 'alert', grown: grown.value, animate: animate.value, calm: calmNow.value,
        })),
        estiloMedida: computed(() => estiloMedida({ row: r.value.row, top: top.value, isOpen: isOpen.value, cap: box.value.cap, maxWidth: props.maxWidth, inCheckout: inCheckout.value })),
    };
}
