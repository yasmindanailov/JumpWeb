/**
 * LA ACCIÓN DE LA ISLA, su lógica (`BotonAccion.vue` solo pinta: la regla `CE-6`, `SidebarComponentBudgetTest`). Es el
 * `ActionButton` del diseño; desde la Z6a (zip (6)):
 *  · `calm`: en SECUNDARIA (quiet sobre la tinta) porque la página enseña su botón; vuelve a naranja solo con el color
 *    (`--t-island-tone`), sin cambiar de ancho ni botar. El hover sigue rápido.
 *  · `entra` / `sale`: la etiqueta hace el cruce de la frase (lo que se va se desenfoca encima).
 *  · La etiqueta nunca se corta: abajo, si a 16px no cabe («Reservar y pagar la señal» en un móvil de 360px, con el
 *    menú y la cuenta), baja a 15px —el tamaño de escritorio— y el aire a 12px. Se mide con el ancho que le deja la fila.
 *  · Ya no lleva la frase dentro (`sublabel`: va siempre en su renglón) ni el bote de la acción que vuelve (`llega`: la
 *    acción ya no se va, `#783`).
 * `loading` la bloquea y pone la bola pequeña delante del texto; si es un texto, es lo que se espera («Comprobando tus
 * datos y guardando tu hora»), para el lector de pantalla. `big`: la de la compra.
 */
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

export const PROPS_BOTON = {
    top: { type: Boolean, default: false },
    label: { type: String, required: true },
    href: { type: String, default: undefined },
    pulsar: { type: Function, default: null },
    expanded: { type: Boolean, default: undefined },
    big: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
    loading: { type: [Boolean, String], default: false },
    calm: { type: Boolean, default: false },
    entra: { type: Boolean, default: false },
    sale: { type: String, default: null },
};

/** El cruce de la etiqueta: lo nuevo entra enfocándose; lo que se va, encima, se desenfoca. */
export const ENTRA = 'isla-swap calc(var(--dur-island) * 0.8) var(--ease-out) calc(var(--dur-island) * 0.12) both';
export const SALE = 'isla-swap-out calc(var(--dur-island) * 0.45) var(--ease-out) both';

export function useBotonAccion(props) {
    const hover = ref(false);
    const press = ref(false);
    const ring = ref(false);
    const bloqueado = computed(() => props.disabled || Boolean(props.loading));

    // El giro de tono (secundaria ↔ naranja), con su transición durante 700ms; después, la del hover.
    const virando = ref(false);
    let relojGiro = null;
    watch(() => props.calm, () => {
        virando.value = true;
        clearTimeout(relojGiro);
        relojGiro = setTimeout(() => { virando.value = false; }, 700);
    });

    // La etiqueta que nunca se corta.
    const tagRef = ref(null);
    const lblRef = ref(null);
    const apretada = ref(false);
    let ro = null;
    function medir() {
        const b = tagRef.value;
        const l = lblRef.value;
        if (props.top || props.big || ! b || ! l) { apretada.value = false; return; }
        const actual = parseFloat(getComputedStyle(l).fontSize) || 16;
        apretada.value = l.scrollWidth * (16 / actual) + 28 > b.clientWidth + 0.5;
    }
    onMounted(() => {
        if (typeof ResizeObserver === 'undefined' || ! tagRef.value) return;
        ro = new ResizeObserver(medir);
        ro.observe(tagRef.value);
        medir();
    });
    watch(() => [props.label, props.top, props.big], () => { nextTick(medir); });
    onBeforeUnmount(() => { if (ro) ro.disconnect(); clearTimeout(relojGiro); });

    const estilo = computed(() => ({
        position: 'relative', display: 'flex', alignItems: 'center', justifyContent: 'center',
        flex: props.top ? '0 0 auto' : '1 1 auto', minWidth: 0, minHeight: props.big ? '54px' : '46px',
        padding: props.top ? '0 24px' : apretada.value ? '5px 12px' : '5px 14px',
        border: 'none', borderRadius: 'var(--r-pill)',
        background: props.calm
            ? (hover.value && ! bloqueado.value ? 'rgba(255,255,255,0.18)' : 'var(--action-quiet-bg)')
            : (hover.value && ! bloqueado.value ? 'var(--action-bg-hover)' : 'var(--action-bg)'),
        color: props.calm ? 'var(--action-quiet-fg)' : 'var(--action-fg)',
        fontFamily: 'var(--font-ui)', fontWeight: 'var(--fw-bold)', fontSize: props.top ? '15px' : props.big ? '17px' : '16px',
        letterSpacing: '-0.01em', textDecoration: 'none', cursor: bloqueado.value ? 'not-allowed' : 'pointer', overflow: 'hidden', opacity: bloqueado.value ? 0.42 : 1,
        transform: press.value ? 'scale(var(--scale-press))' : 'none',
        boxShadow: ring.value
            ? `inset 0 0 0 2px ${props.calm ? 'var(--isla-foco)' : 'var(--isla-tinta)'}`
            : props.calm ? 'inset 0 0 0 1px var(--action-quiet-border)' : hover.value ? 'var(--shadow-cta)' : 'none',
        transition: virando.value ? 'var(--t-island-tone), transform var(--dur-instant) var(--ease-out)' : 'var(--t-hover)',
    }));

    const click = (e) => { if (! bloqueado.value && props.pulsar) props.pulsar(e); };

    return { hover, press, ring, bloqueado, apretada, tagRef, lblRef, estilo, click };
}
