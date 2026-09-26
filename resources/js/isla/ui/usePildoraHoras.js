/**
 * **La hora elegida es una PÍLDORA que se desliza** de una casilla a otra con el muelle, en vez de encenderse y apagarse
 * (`TimeSlotPicker.jsx` del 26-09; Z3, `#782`): se entiende que solo hay una elegida. La primera vez se coloca sin
 * moverse, y también cuando la rejilla cambia de tamaño (una columna más no es una elección).
 *
 * ⚠️ Una diferencia con el diseño, a propósito: allí, tras colocarla sin transición, se hace `style.transition = ""`
 * sobre un estilo EN LÍNEA —React no lo vuelve a poner porque su valor no cambia—, así que la píldora dejaba de
 * deslizarse después de la primera vez. Aquí se repone la transición escrita (`TRANSICION_PILDORA`).
 *
 * También sabe qué plazas han BAJADO desde la última vez (`bajadas`): la casilla destella, la cifra baja y, si se llena,
 * la hora se tacha. Solo con datos que de verdad cambian; con «reducir movimiento», nada se mueve.
 */
import { onBeforeUnmount, onMounted, watch } from 'vue';
import { quieto } from '../movimiento.js';

export const TRANSICION_PILDORA = 'transform var(--dur-slow) var(--ease-spring), width var(--dur-slow) var(--ease-spring), height var(--dur-slow) var(--ease-spring), opacity var(--dur-fast) var(--ease-out)';

export function usePildoraHoras({ rejilla, pildora, valor, cuantas }) {
    let puesta = false;
    let ro = null;

    function colocar(sinMover) {
        const g = rejilla.value;
        const p = pildora.value;

        if (! g || ! p) return;
        const b = valor() != null ? Array.from(g.querySelectorAll('[data-hora]')).find((x) => x.dataset.hora === String(valor())) : null;

        if (! b) { p.style.opacity = '0'; return; }
        const quieta = sinMover || ! puesta || quieto();

        if (quieta) p.style.transition = 'none';
        Object.assign(p.style, { opacity: '1', width: `${b.offsetWidth}px`, height: `${b.offsetHeight}px`, transform: `translate(${b.offsetLeft}px,${b.offsetTop}px)` });
        if (quieta) { void p.offsetWidth; p.style.transition = TRANSICION_PILDORA; }
        puesta = true;
    }

    watch([valor, cuantas], () => colocar(false), { flush: 'post' });
    onMounted(() => {
        colocar(false);
        if (typeof ResizeObserver !== 'undefined' && rejilla.value) {
            ro = new ResizeObserver(() => colocar(true));
            ro.observe(rejilla.value);
        }
    });
    onBeforeUnmount(() => { if (ro) ro.disconnect(); });
}

/**
 * Qué le ha pasado a una hora desde la vez anterior (`antes`: hora → plazas que quedaban): `baja` si quedan menos, y
 * `seLlena` si además ya no queda ninguna. `vivo` es falso con «reducir movimiento»: entonces no se cuenta nada.
 */
export function cambioDeHora(hora, antes, vivo) {
    const previas = antes[hora.time];
    const baja = vivo && previas != null && hora.left != null && hora.left < previas;

    return { baja, seLlena: baja && hora.left === 0 };
}
