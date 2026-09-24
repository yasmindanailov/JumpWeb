/**
 * LA COMPRA, ACCESIBLE (`ParkIsland.jsx`, bloques «Con la capa grande abierta» y «La capa grande, accesible»).
 * Es un diálogo: con ella abierta la página de detrás no se mueve (el scroll es solo del paso); el foco va al
 * titular de cada paso y se anuncia («Paso 2 de 2 · Pagar. Repasa y paga»); y al cerrar vuelve a quien la abrió.
 * Escape la cierra sin perder nada, igual que la X (`useCapa`).
 */
import { onMounted, ref, watch } from 'vue';

export function useCompraCapa({ islandRef, inCheckout, clave }) {
    const anuncio = ref('');
    let abridor = null;

    watch(inCheckout, (dentro, _antes, alLimpiar) => {
        if (! dentro || typeof document === 'undefined') return;
        const raiz = document.documentElement;
        const previo = raiz.style.overflow;
        raiz.style.overflow = 'hidden';
        alLimpiar(() => { raiz.style.overflow = previo; });
    }, { immediate: true });

    watch(inCheckout, (dentro, _antes, alLimpiar) => {
        if (dentro) {
            const a = document.activeElement;
            if (a && a !== document.body && ! (islandRef.value && islandRef.value.contains(a) && a.closest('[data-isla-scroll]'))) abridor = a;
            return;
        }
        const o = abridor;
        abridor = null;
        if (! o) return;
        const reloj = setTimeout(() => {
            if (o.isConnected && o.offsetParent !== null) o.focus({ preventScroll: true });
            else if (islandRef.value) islandRef.value.querySelector('button')?.focus({ preventScroll: true });
        }, 60);
        alLimpiar(() => clearTimeout(reloj));
    }, { immediate: true });

    const alCambiarDePaso = (k, _antes, alLimpiar) => {
        if (! k || ! islandRef.value) return;
        const reloj = setTimeout(() => {
            const caja = islandRef.value?.querySelector('[data-isla-scroll]');
            if (! caja) return;
            const titular = caja.querySelector('h1');
            (titular || caja).focus({ preventScroll: true });
            const paso = islandRef.value.querySelector('#isla-compra-paso')?.textContent || '';
            const tt = titular ? titular.textContent : '';
            anuncio.value = paso && tt && paso.includes(tt) ? paso : [paso, tt].filter(Boolean).join('. ');
        }, 80);
        alLimpiar?.(() => clearTimeout(reloj));
    };
    // El primero, al montar: la compra puede nacer abierta y el titular aún no existía durante el `setup`.
    onMounted(() => alCambiarDePaso(clave.value));
    watch(clave, alCambiarDePaso, { flush: 'post' });

    return { anuncio };
}
