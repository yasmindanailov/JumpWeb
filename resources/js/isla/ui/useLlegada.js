/**
 * **El primario LLEGA** (`Button.jsx` del 26-09, `arrive`; Z3, `#782`): la primera vez que entra en pantalla —un 25 % a la
 * vista— bota con el bote del sistema y cruza un solo brillo; es lo único que se mueve en ese momento, así que se ve sin
 * ser más grande. Nunca dentro de la isla ni de la compra (`data-surface="ink"`), ni con «reducir movimiento». Como el
 * diseño, se vuelve a armar si deja de querer y vuelve a querer (un «Reservar» bloqueado que se desbloquea a la vista).
 */
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { quieto } from '../movimiento.js';

export function useLlegada(el, quiere) {
    const llega = ref(false);
    let io = null;
    let reloj = 0;
    const parar = () => { if (io) io.disconnect(); io = null; clearTimeout(reloj); };

    function armar() {
        parar();
        const nodo = el.value;

        if (! quiere() || ! nodo || typeof IntersectionObserver === 'undefined' || quieto() || nodo.closest?.('[data-surface="ink"]')) return;
        io = new IntersectionObserver((es) => {
            if (! es.some((x) => x.isIntersecting)) return;
            parar();
            llega.value = true;
            reloj = setTimeout(() => { llega.value = false; }, 1600);
        }, { threshold: 0.25 });
        io.observe(nodo);
    }

    onMounted(armar);
    watch(quiere, (si, antes) => { if (si !== antes) armar(); });
    onBeforeUnmount(parar);

    return llega;
}
