/**
 * EL AVISO (`ParkIsland.jsx`, bloque «Aviso»): la isla crece un momento con el mensaje y vuelve sola. Una vez por
 * mensaje —el mismo texto otra vez no se repite—, y el reloj se para mientras hay algo abierto.
 */
import { ref, watch } from 'vue';

export function useAviso(props, isOpen) {
    const shownNotice = ref(null);
    let noticePrevio = null;

    watch(() => props.notice, (aviso) => {
        if (!aviso) { noticePrevio = null; shownNotice.value = null; return; }
        if (noticePrevio === aviso) return;
        noticePrevio = aviso;
        shownNotice.value = aviso;
    }, { immediate: true });

    watch([shownNotice, isOpen], ([aviso, abierta], _antes, alLimpiar) => {
        if (!aviso || abierta) return;
        const reloj = setTimeout(() => { shownNotice.value = null; }, 4200);
        alLimpiar(() => clearTimeout(reloj));
    }, { immediate: true });

    return shownNotice;
}
