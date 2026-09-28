/**
 * **LLEVAR LA CAPA A LO QUE FALTA** (el owner, 28-09: «al abrir la isla con la reserva, hacer scroll hasta donde hace falta
 * rellenar un campo; lo mismo en cualquier situación: siempre mover el scroll hasta donde es necesario el campo o la acción
 * para continuar con el pago»). La compra de la isla desplaza SU caja (`[data-isla-scroll]`), no la página: el bloque queda
 * arriba con su aire y, si es un campo, se enfoca (en un móvil abre el teclado: es lo que hay que hacer ahí).
 *
 * ⚠️ Lo que se busca puede no estar pintado todavía —la pantalla sale tras «preparando», y los datos de la reserva llegan en
 * su trozo diferido (`datos-reserva.js`)—: se reintenta hasta dos segundos, como `irAlBloque` de Mi cuenta.
 * ⚠️ Con «reducir movimiento», sin animación.
 */
import { nextTick } from 'vue';

const CAJA = '[data-isla-scroll]';
const AIRE = 12;

/** Cuánto hay que desplazar la caja para que el bloque quede arriba con su aire (nunca por encima del principio). */
export function arribaDe(caja, bloque, scrollTop) {
    return Math.max(0, Math.round(bloque.top - caja.top + scrollTop - AIRE));
}

const suave = () => (window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth');

/** Lleva la caja al bloque o campo de `id` y enfoca el campo; sin `id`, nada. */
export function irA(id, intentos = 20) {
    if (! id) return;
    nextTick(() => window.setTimeout(() => {
        const caja = document.querySelector(CAJA);
        const el = document.getElementById(id);

        if (! caja || ! el || ! caja.contains(el)) {
            if (intentos > 0) irA(id, intentos - 1);

            return;
        }
        caja.scrollTo({ top: arribaDe(caja.getBoundingClientRect(), el.getBoundingClientRect(), caja.scrollTop), behavior: suave() });
        if (el.matches('input, textarea, select')) el.focus({ preventScroll: true });
    }, intentos === 20 ? 60 : 100));
}

/** Lleva la caja a su principio: donde la pantalla pinta el «no» del servidor (su aviso). */
export function alPrincipio() {
    nextTick(() => document.querySelector(CAJA)?.scrollTo({ top: 0, behavior: suave() }));
}
