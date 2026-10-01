/**
 * EL AVISO (`ParkIsland.jsx`, bloques «Aviso» y «el aviso, a isla entera»; la Z6b·2 de `isla-y-landing-nueva.md` §4.27): la
 * isla crece un momento con el mensaje y vuelve sola. Una vez por mensaje —el mismo texto otra vez no se repite—.
 *
 *   · **A isla entera** (`entero`) sin panel abierto, sin la compra y sin las cookies: el aviso ocupa la isla y la fila se
 *     esconde (`piezas/AvisoIsla.vue`). Con un panel o con las cookies, la tira de siempre (`BloqueAviso`).
 *   · **Su reloj** (4,2 s) se para con un panel abierto, con el ratón encima o con el foco dentro (`pausa`, WCAG 2.2.1), y
 *     SIGUE DONDE IBA (zip (6)): antes, cerrar un panel lo volvía a empezar.
 *   · **La barra de lo que le queda** empieza en `transcurrido()` al montarse: el aviso entero vuelve a pintarse tras
 *     cerrar un panel o al irse las cookies, con el reloj ya corrido. En el diseño volvía LLENA y se iba a medias.
 *
 * `ahora`, para probarlo con relojes falsos (`aviso.test.js`).
 */
import { computed, ref, watch } from 'vue';

export const AVISO_MS = 4200;

export function useAviso(props, { isOpen, inCheckout }, { ahora = () => Date.now() } = {}) {
    const shownNotice = ref(null);
    let noticePrevio = null;

    watch(() => props.notice, (aviso) => {
        if (! aviso) { noticePrevio = null; shownNotice.value = null; return; }
        if (noticePrevio === aviso) return;
        noticePrevio = aviso;
        shownNotice.value = aviso;
    }, { immediate: true });

    const entero = computed(() => Boolean(shownNotice.value) && ! isOpen.value && ! inCheckout.value && ! props.cookies);

    // La pausa es del aviso A LA VISTA: si deja de verse entero (se toca, se va, lo tapa un panel) se olvida, porque quitar
    // un nodo no avisa de que el ratón o el foco se han ido, y el siguiente aviso nacería parado.
    const pausa = ref(false);
    watch(entero, (visto) => { if (! visto) pausa.value = false; });

    // `resto`: lo que le quedaba al empezar la carrera en curso; `desde`: cuándo empezó (`null`, parado). Cada aviso nuevo
    // empieza con los 4,2 s enteros, y cada parada descuenta lo corrido.
    let resto = AVISO_MS;
    let desde = null;
    let deQuien = null;
    watch([shownNotice, isOpen, pausa], ([aviso, abierta, pausado], _antes, alLimpiar) => {
        if (aviso !== deQuien) { deQuien = aviso; resto = AVISO_MS; }
        if (! aviso || abierta || pausado) return;
        desde = ahora();
        const reloj = setTimeout(() => { shownNotice.value = null; }, resto);
        alLimpiar(() => {
            clearTimeout(reloj);
            resto = Math.max(0, resto - (ahora() - desde));
            desde = null;
        });
    }, { immediate: true });

    return {
        shownNotice,
        entero,
        pausa,
        quitar: () => { shownNotice.value = null; },
        transcurrido: () => Math.min(AVISO_MS, AVISO_MS - resto + (desde === null ? 0 : ahora() - desde)),
    };
}
