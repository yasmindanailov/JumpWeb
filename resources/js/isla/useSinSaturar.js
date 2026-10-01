/**
 * **SIN SATURAR, POR PRIORIDAD** (Z6b, zip (6): el bloque «Sin saturar» de `ParkIsland.jsx`, 29-09). Lo que trae el
 * scroll —la RAZÓN de la pieza que se lee y la FRASE que quita su miedo (situación 5, `#866`)— tiene presupuesto, y se
 * gasta donde más vende:
 *   · Nada al llegar: hasta el primer desplazamiento (40 px) no hay razón; la isla está quieta y manda la cabecera.
 *   · Las piezas de DECISIÓN (`decision`: calcular o el precio, las dudas y el cierre) y la razón del pago (con día y hora
 *     elegidos, `chosen`) siempre tienen la suya: a los 1,2 s con el botón a la vista (lo elegido, a los 0,6 s).
 *   · El resto comparte UN banner y UNA frase por visita; lo visto no vuelve; y entre un cambio y otro, 6 s de calma.
 * Lo que provoca la persona (abrir, la compra, el pago) no pasa por aquí: no tiene límite.
 *
 * Es el port del diseño tal cual, también en su asimetría: una razón que se va y vuelve cuenta como vista (salvo las de
 * decisión), y la ÚLTIMA frase que se dijo vuelve al momento si se vuelve a su pieza.
 *
 * Recibe getters (`reason`, `reassurance`, `chosen`) y devuelve `razon` y `frase`: lo que la isla puede decir AHORA, o
 * `null`. `win` y `ahora`, para probarlo con relojes falsos.
 */
import { computed, onScopeDispose, ref, watch } from 'vue';

export const DESPLAZADO = 40;
export const ESPERA_DECISION = 1200;
export const ESPERA_ELEGIDO = 600;
export const CALMA = 6000;

const textoDe = (r) => (r && typeof r === 'object' ? r.text || null : r || null);

export function useSinSaturar({ reason, reassurance, chosen }, { win = typeof window === 'undefined' ? null : window, ahora = () => Date.now() } = {}) {
    const gasto = { vistas: new Set(), frases: new Set(), resto: { razon: 0, frase: 0 }, ultimo: 0 };
    const calma = () => Math.max(ESPERA_DECISION, gasto.ultimo + CALMA - ahora());

    // Nada al llegar: la razón espera al primer desplazamiento, y una vez movida la página ya no vuelve a esperar.
    const movido = ref(Boolean(win) && win.scrollY > DESPLAZADO);
    const alMover = () => {
        if (win.scrollY <= DESPLAZADO) return;
        movido.value = true;
        win.removeEventListener('scroll', alMover);
    };
    if (win && ! movido.value) win.addEventListener('scroll', alMover, { passive: true });
    onScopeDispose(() => { if (win) win.removeEventListener('scroll', alMover); });

    // ── La razón ──
    const razonOk = ref(null);
    const razonClave = computed(() => (movido.value ? textoDe(reason()) : null));
    const deDecision = computed(() => Boolean(chosen()) || Boolean(reason()?.decision));
    watch([razonClave, deDecision], ([clave, siempre], _antes, alLimpiar) => {
        if (! clave) { razonOk.value = null; return; }
        if (razonOk.value === clave) return;
        if (! siempre && (gasto.vistas.has(clave) || gasto.resto.razon >= 1)) { razonOk.value = null; return; }
        const reloj = setTimeout(() => {
            if (! siempre) { gasto.vistas.add(clave); gasto.resto.razon += 1; }
            gasto.ultimo = ahora();
            razonOk.value = clave;
        }, chosen() ? ESPERA_ELEGIDO : siempre ? ESPERA_DECISION : calma());
        alLimpiar(() => clearTimeout(reloj));
    }, { immediate: true });

    // ── La frase ──
    const dicha = ref(null);
    const fraseTexto = computed(() => textoDe(reassurance()));
    const fraseDeDecision = computed(() => Boolean(reassurance()?.decision));
    watch([fraseTexto, fraseDeDecision], ([texto, siempre], _antes, alLimpiar) => {
        if (! texto || dicha.value === texto) return;
        if (! siempre && (gasto.frases.has(texto) || gasto.resto.frase >= 1)) return;
        const reloj = setTimeout(() => {
            if (! siempre) { gasto.frases.add(texto); gasto.resto.frase += 1; }
            gasto.ultimo = ahora();
            dicha.value = texto;
        }, siempre ? ESPERA_DECISION : calma());
        alLimpiar(() => clearTimeout(reloj));
    }, { immediate: true });

    return {
        razon: computed(() => (razonClave.value && razonOk.value === razonClave.value ? reason() : null)),
        frase: computed(() => (fraseTexto.value && dicha.value === fraseTexto.value ? fraseTexto.value : null)),
        movido,
    };
}
