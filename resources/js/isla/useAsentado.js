/**
 * LO QUE TRAE EL SCROLL, ASENTADO (Z6a; `useAsentado` de `ParkIsland.jsx`, zip (6)): el valor nuevo manda cuando
 * lleva `ms` quieto; con `ms = 0`, al momento. Bajando deprisa, la isla no parpadea entre situaciones: salta a la
 * última. Con la misma clave que la que ya manda, siempre el valor más reciente (los manejadores no se quedan
 * viejos).
 *
 * Recibe tres funciones (el valor, su clave y la espera) y devuelve el valor asentado. Si mientras espera llega otra
 * clave, el reloj vuelve a empezar; si solo cambia el valor con la misma clave pendiente, no: al cumplirse, manda el
 * último (el `ultimo` del diseño).
 */
import { computed, onScopeDispose, ref, watch } from 'vue';

export function useAsentado(valor, clave, espera) {
    const manda = ref({ clave: clave(), valor: valor() });
    let pendiente = null;
    let reloj = null;
    const parar = () => { clearTimeout(reloj); reloj = null; pendiente = null; };

    watch(() => [valor(), clave(), espera()], ([v, k, ms]) => {
        if (! ms || manda.value.clave === k) {
            parar();
            manda.value = { clave: k, valor: v };
            return;
        }
        if (pendiente && pendiente.clave === k && pendiente.ms === ms) return;
        parar();
        pendiente = { clave: k, ms };
        reloj = setTimeout(() => { parar(); manda.value = { clave: clave(), valor: valor() }; }, ms);
    }, { flush: 'sync' });

    onScopeDispose(parar);

    return computed(() => manda.value.valor);
}
