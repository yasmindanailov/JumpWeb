/**
 * **EL TECLADO DEL MÓVIL EN LA CAPA GRANDE**, en marcha (`useTeclado`, `alIntro` y las pistas de `ParkIsland.jsx` del
 * zip del 26-09; `isla-y-landing-nueva.md` §4.16). Lo que decide, en `teclado.js`. Dos piezas, como en el diseño:
 *
 *   · `useTeclado(activo)` —en la isla, que pinta la RAÍZ—: `kb`, la ventana visible con el teclado abierto
 *     (`visualViewport`), o `null`. Con él, la raíz se ciñe a lo que se ve (`forma.js::estiloRaiz`) y la capa mide ese
 *     alto: la acción queda siempre encima del teclado, y el pie se queda en ella.
 *   · `useIntro({ cajaRef, accion, kb })` —en la capa—: Intro pasa al campo siguiente y, en el último, hace la acción del
 *     paso (sin cerrar el teclado); el teclado lo dice («Siguiente» / «Ir», `enterkeyhint`, salvo donde la pantalla ya lo
 *     decidió); y el campo que se escribe no queda tapado cuando el teclado se abre o se mueve.
 * Solo abajo (móvil): arriba (escritorio) la capa no ocupa la pantalla y el teclado es físico.
 */
import { onBeforeUnmount, onMounted, onUpdated, ref, watch } from 'vue';
import { CAMPOS, alPulsarIntro, desplazarHastaVer, esCampoDeIntro, leerTeclado, pistasDeIntro } from './teclado.js';

export function useTeclado(activo) {
    const kb = ref(null);
    let fotograma = 0;
    let quitar = null;

    const leer = () => {
        fotograma = 0;
        const v = window.visualViewport;
        kb.value = v ? leerTeclado({ alto: window.innerHeight, visible: v.height, desplazado: v.offsetTop }, kb.value) : null;
    };
    const pedir = () => { if (! fotograma) fotograma = window.requestAnimationFrame(leer); };
    const escuchar = (si) => {
        if (quitar) { quitar(); quitar = null; }
        if (! si || typeof window === 'undefined' || ! window.visualViewport) { kb.value = null; return; }
        const v = window.visualViewport;
        v.addEventListener('resize', pedir);
        v.addEventListener('scroll', pedir);
        quitar = () => {
            v.removeEventListener('resize', pedir);
            v.removeEventListener('scroll', pedir);
            if (fotograma) { window.cancelAnimationFrame(fotograma); fotograma = 0; }
        };
        leer();
    };

    onMounted(() => escuchar(activo()));
    watch(activo, escuchar);
    onBeforeUnmount(() => escuchar(false));

    return { kb };
}

export function useIntro({ cajaRef, accion, kb }) {
    // El campo que se escribe no puede quedar tapado: al abrirse o moverse el teclado, la caja se desplaza lo justo.
    let reloj = 0;
    watch(() => (kb() ? `${kb().h}|${kb().top}` : ''), (clave) => {
        window.clearTimeout(reloj);
        if (! clave) return;
        reloj = window.setTimeout(() => {
            const caja = cajaRef.value;
            const a = document.activeElement;
            if (! caja || ! a || ! caja.contains(a)) return;
            caja.scrollTop += desplazarHastaVer(caja.getBoundingClientRect(), a.getBoundingClientRect());
        }, 60);
    });

    // Las pistas del teclado, en cada pintado de la capa (un paso nuevo trae sus campos). Solo donde nadie las puso.
    const pistas = () => {
        const caja = cajaRef.value;
        if (! caja) return;
        const campos = Array.from(caja.querySelectorAll(CAMPOS));
        const p = pistasDeIntro(campos.length);
        campos.forEach((x, i) => {
            if (x.getAttribute('enterkeyhint') && ! x.dataset.islaIntro) return;
            x.dataset.islaIntro = '1';
            x.setAttribute('enterkeyhint', p[i]);
        });
    };

    /** Intro en un campo de la capa: al siguiente; en el último, la acción del paso si se puede pulsar. */
    function alIntro(e) {
        if (e.key !== 'Enter' || e.isComposing || ! esCampoDeIntro(e.target)) return;
        const campos = Array.from(e.currentTarget.querySelectorAll(CAMPOS)).filter((x) => x.offsetParent !== null);
        const que = alPulsarIntro(campos, e.target);
        if (! que) return;
        e.preventDefault();
        if (que.que === 'siguiente') { campos[que.indice].focus(); return; }
        e.target.blur();
        const a = accion();
        if (a && ! a.disabled && ! a.loading && a.onClick) a.onClick();
    }

    onMounted(pistas);
    onUpdated(pistas);
    onBeforeUnmount(() => window.clearTimeout(reloj));

    return { alIntro };
}
