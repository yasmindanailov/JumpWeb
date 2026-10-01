/**
 * EL BANNER DE LA ISLA, su lógica (`BannerRazon.vue` solo pinta: la regla `CE-6`). Es el `ReasonBanner` de
 * `ParkIsland.jsx` (Z6b, zip (6), opción C «Da la razón»): ocupa el sitio de la acción, sin naranja, y se toca entero.
 * Delante, su tipo: la RAZÓN, con su icono en lima (el dibujo lo manda el servidor, `svg`); lo VIVO, el punto lima que
 * late (un dato real que cambia: los días con hueco); la ESPERA, la bola de la carga («Confirmando tu pago»); y lo HECHO,
 * el check lima («¡Reservado!»). Detrás, el dato y el alivio: el título y el matiz, que nunca se cortan —en el móvil
 * pasan a dos renglones y la isla crece con su morph—. En escritorio, el icono en su círculo y, en una razón, la flecha.
 * `quieto`: la copia que se va en el relevo del hueco (`useHueco`), sin tocar ni latir.
 */
import { computed, ref } from 'vue';

export const PROPS_BANNER = {
    bn: { type: Object, required: true },
    top: { type: Boolean, default: false },
    alto: { type: Number, default: 46 },
    quieto: { type: Boolean, default: false },
};

export function useBannerRazon(props) {
    const hover = ref(false);
    const press = ref(false);
    const tipo = computed(() => props.bn.type || 'razon');

    const estilo = computed(() => ({
        flex: props.top ? '0 0 auto' : '1 1 auto', minWidth: props.top ? '280px' : 0, maxWidth: props.top ? '380px' : 'none',
        minHeight: `${props.alto}px`, display: 'flex', alignItems: 'center', gap: props.top ? '10px' : '8px',
        padding: props.top ? '5px 14px 5px 6px' : '6px 18px', borderRadius: 'var(--r-pill)', border: 0, boxSizing: 'border-box',
        cursor: 'pointer', textAlign: 'left', font: 'inherit',
        background: hover.value ? 'var(--control-bg-hover)' : 'var(--control-bg)',
        boxShadow: 'inset 0 0 0 1px var(--ink-surface-border)', color: 'var(--text-strong)',
        transform: press.value ? 'scale(var(--scale-press))' : 'none',
        transition: 'background-color var(--dur-fast) var(--ease-out), var(--t-press)',
    }));
    // El sitio del tipo: en escritorio, un círculo de 34; en el móvil, en línea y pequeño (el punto, a su tamaño).
    const caja = computed(() => ({
        flex: 'none', display: 'grid', placeItems: 'center', borderRadius: '50%', color: 'var(--isla-vivo)',
        width: `${props.top ? 34 : tipo.value === 'vivo' ? 8 : 20}px`, height: `${props.top ? 34 : 20}px`,
        background: props.top ? 'var(--control-bg-hover)' : 'transparent',
    }));
    const punto = computed(() => ({
        width: '8px', height: '8px', borderRadius: '50%', background: 'var(--isla-vivo)',
        animation: props.quieto ? 'none' : 'isla-pulse 2.4s var(--ease-in-out) infinite',
    }));
    const hecho = computed(() => ({
        width: `${props.top ? 26 : 20}px`, height: `${props.top ? 26 : 20}px`, borderRadius: '50%', display: 'grid',
        placeItems: 'center', background: 'var(--isla-vivo)', color: 'var(--isla-tinta)',
    }));
    // El título y el matiz: en escritorio, en una línea cada uno (caben: hasta 380 px); en el móvil, en los renglones que
    // hagan falta, equilibrados.
    const titulo = computed(() => ({
        fontSize: props.top ? '15px' : '14px', fontWeight: 700, letterSpacing: '-0.01em', lineHeight: 1.25,
        whiteSpace: props.top && props.bn.sub ? 'nowrap' : 'normal', overflow: props.top ? 'hidden' : 'visible',
        textWrap: 'balance', fontVariantNumeric: 'tabular-nums',
    }));
    const matiz = computed(() => ({
        fontSize: props.top ? '13px' : '12.5px', fontWeight: 500, lineHeight: 1.3, color: 'var(--text-muted)',
        whiteSpace: props.top ? 'nowrap' : 'normal', overflow: props.top ? 'hidden' : 'visible', textWrap: 'balance',
    }));

    return { hover, press, tipo, estilo, caja, punto, hecho, titulo, matiz };
}
