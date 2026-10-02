/**
 * LA CARA DE BARRA DE LA ACCIÓN, su lógica (`BarraAccion.vue` solo pinta: la regla `CE-6`). Es la rama `sub` de
 * `ActionButton` en `ParkIsland.jsx` (el experimento B3, zip (6); la Z6c de `isla-y-landing-nueva.md` §4.27): en la fila
 * de 64 px del B3, la frase va DENTRO de la acción. La cara del banner (cristal) con la etiqueta —el verbo— y, debajo, la
 * frase corta con el punto de su tono; y la acción, el círculo con la flecha. Se toca entera. Cuando cambian la etiqueta
 * o la frase, lo que se va se desenfoca encima mientras lo nuevo llega (`sale`, como la frase de la isla).
 *
 * ⚠️ **El círculo va SIEMPRE en naranja** (`#868`, `[DECIDIDO owner]` 02-10): el zip (6) lo ponía en secundaria con un botón
 * de la página a la vista, y había descartado «la flecha siempre en naranja»; el owner la quiere naranja aunque haya dos
 * naranjas en pantalla. Por eso esta cara no tiene `calm`.
 * ⚠️ **Va en su propio trozo y no importa piezas comunes** (`useIsla.js` la pide con el B3): la flecha le llega por su
 * ranura desde la isla, y sin «cargando» —la compra, la única acción que carga, nunca tiene barra—. Medido: importando
 * `CargaRebote` e `IconoLucide`, el empaquetador repartía de otra forma los trozos comunes y las calculadoras crecían +0,31.
 */
import { computed, onScopeDispose, ref, watch } from 'vue';

export const PROPS_BARRA = {
    label: { type: String, required: true },
    sub: { type: String, default: '' },
    /** El color del punto de la frase (`puntoDelTono`); sin él, sin punto. */
    dot: { type: String, default: null },
    /** La frase es de algo vivo (hoy, quedan huecos): su punto late. */
    live: { type: Boolean, default: false },
    href: { type: String, default: undefined },
    pulsar: { type: Function, default: null },
    expanded: { type: Boolean, default: undefined },
    disabled: { type: Boolean, default: false },
    entra: { type: Boolean, default: false },
};

/** Lo que dura la copia que se va cuando cambia la pareja etiqueta|frase. */
export const RELEVO_BARRA_MS = 260;

/** La cara entera: cristal sobre la tinta de la isla, como el banner; al tocarla, se hunde. */
export function estiloBarra({ hover = false, press = false, ring = false, bloqueado = false } = {}) {
    return {
        position: 'relative', flex: '1 1 auto', minWidth: 0, minHeight: '52px', display: 'flex', alignItems: 'center', gap: '10px',
        padding: '4px 5px 4px 18px', boxSizing: 'border-box', border: 0, borderRadius: 'var(--r-pill)', textAlign: 'left',
        textDecoration: 'none', fontFamily: 'var(--font-ui)', color: 'var(--text-strong)',
        cursor: bloqueado ? 'not-allowed' : 'pointer', opacity: bloqueado ? 0.42 : 1,
        background: hover && ! bloqueado ? 'var(--control-bg-hover)' : 'var(--control-bg)',
        boxShadow: ring ? 'inset 0 0 0 2px var(--isla-foco)' : 'inset 0 0 0 1px var(--ink-surface-border)',
        transform: press ? 'scale(var(--scale-press))' : 'none',
        transition: 'background-color var(--dur-fast) var(--ease-out), var(--t-press)',
    };
}

/** El círculo con la flecha: SIEMPRE la acción, en naranja (`#868`). */
export function estiloCirculo({ hover = false, bloqueado = false } = {}) {
    return {
        flex: '0 0 auto', width: '40px', height: '40px', borderRadius: '50%', display: 'grid', placeItems: 'center',
        background: hover && ! bloqueado ? 'var(--action-bg-hover)' : 'var(--action-bg)', color: 'var(--action-fg)',
        transition: 'var(--t-hover)',
    };
}

export const ETIQUETA = {
    fontSize: 'clamp(13.5px, 3.9vw, 15px)', fontWeight: 'var(--fw-bold)', letterSpacing: '-0.01em', lineHeight: 1.2,
    whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis',
};
export const FRASE = {
    display: 'flex', alignItems: 'center', gap: '6px', minWidth: 0, fontSize: '12.5px', fontWeight: 'var(--fw-semibold)',
    lineHeight: 1.25, color: 'var(--text-muted)',
};
export const FRASE_TEXTO = { minWidth: 0, whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' };

export function estiloPunto(dot, live) {
    return {
        width: '7px', height: '7px', borderRadius: '50%', flex: '0 0 auto', background: dot,
        animation: live ? 'isla-pulse 2.4s var(--ease-in-out) infinite' : 'none',
    };
}

export function useBarraAccion(props) {
    const hover = ref(false);
    const press = ref(false);
    const ring = ref(false);
    const bloqueado = computed(() => props.disabled);

    // El relevo de la pareja etiqueta|frase: la de antes se queda encima, desenfocándose, `RELEVO_BARRA_MS`.
    const sale = ref(null);
    let reloj = null;
    watch(() => [props.label, props.sub], ([label, sub], [antes, subAntes]) => {
        if (label === antes && sub === subAntes) return;
        sale.value = { clave: `${antes}|${subAntes}`, label: antes, sub: subAntes };
        clearTimeout(reloj);
        reloj = setTimeout(() => { sale.value = null; }, RELEVO_BARRA_MS);
    });
    onScopeDispose(() => clearTimeout(reloj));

    return {
        hover, press, ring, bloqueado, sale,
        clave: computed(() => `${props.label}|${props.sub}`),
        estilo: computed(() => estiloBarra({ hover: hover.value, press: press.value, ring: ring.value, bloqueado: bloqueado.value })),
        circulo: computed(() => estiloCirculo({ hover: hover.value, bloqueado: bloqueado.value })),
        punto: computed(() => (props.dot ? estiloPunto(props.dot, props.live) : null)),
        click: (e) => { if (! bloqueado.value && props.pulsar) props.pulsar(e); },
    };
}
