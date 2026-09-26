/**
 * **EL RELEVO de una isla** (`relevo.js`, Z3, `#782`): al montarse, crece desde la isla que había —la píldora de la página
 * si se abre la compra o Mi cuenta; la capa grande si se cierra y vuelve la píldora— y, si esa llevaba velo, lo funde
 * (`veloSaliente`). Una capa grande que releva avisa (`isla:relevada`) para que la píldora se aparte ya: hasta entonces
 * la página la deja en su sitio, y así no queda un hueco sin isla mientras llega el motor.
 *
 * ⚠️ La caja de la otra se lee AL MONTARSE (sigue en pantalla); el tamaño final, tras el `nextTick`: en el primer
 * pintado la isla aún no sabe si va arriba (`useAncho` lo dice en su `onMounted`) ni lo que mide, y medida ahí crecía
 * hasta 1184px y saltaba a 379 (medido en escritorio). Entre los dos no se pinta nada: es la misma tarea.
 */
import { nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import { desde, registrar, soltar, tomarVuelo } from './relevo.js';
import { crecerDesde, volar } from './movimiento.js';

export function useRelevo({ islandRef, isOpen, inCheckout, top }) {
    const veloSaliente = ref(false);
    let pararVuelo = () => {};
    let vivo = true;

    onMounted(() => {
        const el = islandRef.value;
        const caja = desde(el);

        registrar(el);
        nextTick(() => {
            if (! vivo) return;
            if (caja) crecerDesde(el, caja, { abrir: isOpen.value, arriba: top.value });
            if (caja?.velo && ! isOpen.value) veloSaliente.value = true;
            if (! inCheckout.value) return;
            window.dispatchEvent(new CustomEvent('isla:relevada'));
            // Del selector a la compra, el nombre del plan elegido viaja a la cabecera del paso (02b).
            pararVuelo = volar(tomarVuelo(), el.querySelector('#isla-compra-paso'));
        });
    });
    onBeforeUnmount(() => { vivo = false; pararVuelo(); soltar(islandRef.value); });

    return { veloSaliente };
}
