/**
 * DÓNDE va la isla (`ParkIsland.jsx`, bloque «Colocación»). Ya no se encoge al bajar: la frase va siempre (Z6a, zip (6)).
 */
import { onBeforeUnmount, onMounted, ref } from 'vue';

/** Abajo al alcance del pulgar, arriba desde 900px: `true` es «ancha» (arriba) con `placement: 'auto'`. */
export function useAncho(props) {
    const wide = ref(props.placement === 'top');
    let mq = null;
    const leerAncho = () => { wide.value = mq.matches; };

    onMounted(() => {
        if (props.placement === 'auto' && typeof window !== 'undefined' && window.matchMedia) {
            mq = window.matchMedia('(min-width: 900px)');
            leerAncho();
            mq.addEventListener('change', leerAncho);
        }
    });
    onBeforeUnmount(() => { if (mq) mq.removeEventListener('change', leerAncho); });

    return wide;
}
