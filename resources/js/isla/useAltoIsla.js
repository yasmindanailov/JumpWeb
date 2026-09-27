/**
 * LA ISLA PUBLICA SU ALTO EN REPOSO (`ParkIsland.jsx` del zip del 27-09, «Primera pantalla»; `isla-y-landing-nueva.md`
 * §4.15): lo escribe en `--island-h` del documento y lo anuncia con `pj-island:size` ({ h, top }). La cabecera de la
 * página mide con él —arriba, la banda de la isla es fija (aire + este alto); abajo, el último bloque entero de la
 * cabecera acaba a `--fold-gap` de la isla— y solo le hace caso arriba del todo: con la página bajada no se mueve.
 *
 * ⚠️ **Cuándo se mide: con un `ResizeObserver`** sobre la fila y la línea de encima, y al volver al reposo; no tras cada
 * pintado, como el diseño (`useLayoutEffect` sin dependencias). Medido el 27-09 con la CPU ×4 (`sonda-isla-rendimiento`,
 * desplazarse por /kids a 1280): la isla se repinta 26 veces en esa escena y medir en cada una forzaba la maquetación en
 * mitad del fotograma: 4–8 fotogramas de más de 20ms frente a 2 sin ella. El observador llega con la maquetación ya hecha
 * y solo cuando el tamaño CAMBIA. Si el valor no cambia, no se escribe ni se anuncia.
 * ⚠️ **No se borra al desmontarse**, al revés que el diseño: aquí la píldora de la página, la compra y Mi cuenta son TRES
 * montajes (`#782`) y, al relevarse, quitarlo devolvería el valor de la hoja (62px) a la cabecera que queda debajo, para
 * volver al instante. Se queda el último, como el diseño hace mientras la isla está abierta o en la compra.
 */
import { onBeforeUnmount, onMounted, watch } from 'vue';
import { altoEnReposo, publicaAlto } from './forma.js';

export function useAltoIsla({ rowRef, lineRowRef, estado }) {
    let ro = null;
    const publicar = () => {
        if (typeof document === 'undefined') return;
        const e = estado();
        if (! publicaAlto(e)) return;
        const h = altoEnReposo({ fila: rowRef.value?.getBoundingClientRect(), linea: lineRowRef.value?.getBoundingClientRect(), row: e.row });
        if (! h) return;
        const raiz = document.documentElement;
        if (raiz.style.getPropertyValue('--island-h') === `${h}px`) return;
        raiz.style.setProperty('--island-h', `${h}px`);
        window.dispatchEvent(new CustomEvent('pj-island:size', { detail: { h, top: e.top } }));
    };
    // La línea de encima entra y sale (`v-if`): se observa la que haya en cada momento.
    const observar = () => {
        if (! ro) return;
        ro.disconnect();
        [rowRef.value, lineRowRef.value].forEach((el) => { if (el) ro.observe(el); });
    };

    onMounted(() => {
        if (typeof ResizeObserver !== 'undefined') ro = new ResizeObserver(publicar);
        observar();
        publicar();
        if (document.fonts && document.fonts.ready) document.fonts.ready.then(publicar);
    });
    watch([rowRef, lineRowRef], observar, { flush: 'post' });
    // Al volver al reposo (se cierra un panel, deja de estar compacta) o al pasar de bloque a fila, sin que la fila cambie
    // de tamaño: se publica igual.
    watch(() => [publicaAlto(estado()), estado().row], ([publica]) => { if (publica) publicar(); }, { flush: 'post' });
    onBeforeUnmount(() => { if (ro) ro.disconnect(); });
}
