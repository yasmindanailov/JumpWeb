/**
 * **LO QUE LA COMPRA DEJÓ, EN LA ISLA DE LA PÁGINA** (`#867`; `isla-y-landing-nueva.md` §4.27, «Los tres usos de los
 * banners»): la reserva a medias («Sigue con tu reserva», que vuelve a su paso), la hecha («¡Reservado!» · «Toca para ver tu
 * QR») y, mientras la compra llega en el primer toque, «Preparando tu reserva».
 *
 *   · Lo dice la compra al cerrarse (`compra-cerrada.js`: en vivo, o en la pestaña si cerrar recargó la página) y vale
 *     MIENTRAS DURE LA PÁGINA, como «una por visita» de la Z6b·1. Se va al reabrir la compra (al cerrarse lo dirá otra vez),
 *     no al abrir Mi cuenta; lo hecho, también al tocarlo (`gastar`).
 *   · «Preparando» solo con la compra EN LA ISLA y si tarda más de `UMBRAL_PREPARANDO`: rápida (con `precarga.js`, ~350 ms en
 *     4G) no se ve; se va cuando su capa la releva o se cierra (`listo`). Mi cuenta no es una reserva: no lo dice.
 *
 * Las aperturas y los cierres los oye `usePaginaIsla.js`, que ya escucha al controlador, y los pasa aquí. `win` y `ahora`,
 * para probarlo fuera de un componente con relojes falsos (`compra-cerrada.test.js`).
 */
import { onScopeDispose, ref } from 'vue';
import { EVENTO, tomar } from './compra-cerrada.js';
import { almacenDeLaPestana } from '../../sidebar/marca-compra.js';

/** Lo que la compra puede tardar en relevar a la isla sin que haga falta decir nada (el umbral de Doherty, 400 ms). */
export const UMBRAL_PREPARANDO = 400;

export function useCompraCerrada({ win = globalThis.window, ahora = () => Date.now() } = {}) {
    // Lo dejado antes de una recarga (entró en su cuenta dentro de la compra y la cerró): UNA vez, y solo en esta página.
    const deja = ref(tomar(almacenDeLaPestana(win), { ruta: win?.location?.pathname ?? '', ahora: ahora() }));
    const preparando = ref(false);
    let reloj = 0;

    const alCerrarse = (ev) => { deja.value = ev?.detail ?? null; };
    win?.addEventListener?.(EVENTO, alCerrarse);

    function listo() {
        win?.clearTimeout?.(reloj);
        reloj = 0;
        preparando.value = false;
    }
    onScopeDispose(() => { win?.removeEventListener?.(EVENTO, alCerrarse); listo(); });

    /**
     * Se abre una capa: la compra se lleva lo que dejó; Mi cuenta, no. Abierta en la isla, si tarda, «Preparando».
     *
     * @param {{cuenta?: boolean, enLaIsla?: boolean}} apertura
     */
    function alAbrir({ cuenta = false, enLaIsla = false } = {}) {
        if (cuenta) return;
        deja.value = null;
        listo();
        if (enLaIsla) reloj = win.setTimeout(() => { preparando.value = true; }, UMBRAL_PREPARANDO);
    }

    return { deja, preparando, alAbrir, listo, gastar: () => { deja.value = null; } };
}
