/**
 * DÓNDE va la isla y CÓMO de grande (`ParkIsland.jsx`, bloques «Colocación» y «Se encoge al bajar»).
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

/**
 * Se encoge al bajar y vuelve entera al subir: nunca esconde la acción. Escucha el primer contenedor con scroll
 * de verdad por encima de la isla (o la ventana), con un umbral de 8px contra el temblor del dedo.
 */
export function useCompacta(props, wrapRef) {
    const scrolledDown = ref(false);
    let quitarScroll = null;

    onMounted(() => {
        if (props.compact !== 'auto') return;
        const wrap = wrapRef.value;
        let nodo = wrap && wrap.parentElement;
        while (nodo && nodo !== document.body) {
            const ov = getComputedStyle(nodo).overflowY;
            if ((ov === 'auto' || ov === 'scroll') && nodo.scrollHeight > nodo.clientHeight + 4) break;
            nodo = nodo.parentElement;
        }
        const objetivo = nodo && nodo !== document.body ? nodo : window;
        const leerY = () => (objetivo === window ? window.scrollY : objetivo.scrollTop);
        let ultimo = leerY();
        let marco = 0;
        const alDesplazar = () => {
            if (marco) return;
            marco = requestAnimationFrame(() => {
                marco = 0;
                const y = leerY();
                if (Math.abs(y - ultimo) < 8) return;
                scrolledDown.value = y > ultimo && y > 360;
                ultimo = y;
            });
        };
        objetivo.addEventListener('scroll', alDesplazar, { passive: true });
        quitarScroll = () => { objetivo.removeEventListener('scroll', alDesplazar); if (marco) cancelAnimationFrame(marco); };
    });
    onBeforeUnmount(() => { if (quitarScroll) quitarScroll(); });

    return scrolledDown;
}
