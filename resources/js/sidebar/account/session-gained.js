/**
 * **Lo que pasa cuando el cajón consigue sesión SIN recargar la página**
 * (`docs/specs/account-context-vue.md` §4.6).
 *
 * ⚠️⚠️ **Sustituye al evento `logged-in` de Livewire, que MUERE aquí** (2026-08-23). Aquel evento
 * existía por una sola razón: el bloque de cuenta era un componente Livewire **fuera** del motor, y
 * repintarlo sin recargar exigía hablarle por su propio bus —`window.Livewire.dispatch`—. Con el
 * bloque dentro del cajón esa vuelta ya no tiene sentido: el estado está a un store de distancia.
 *
 * Lo que hay que hacer al conseguir sesión son **tres cosas**, y ninguna sobra:
 *
 *  1. **repintar el bloque de cuenta**, que hasta ese instante saluda como invitado. Se pide el
 *     contexto al servidor porque el login solo devuelve el PERFIL: ni las reservas próximas ni los
 *     formularios pendientes salen de ahí, y **derivarlos en el cliente sería repetir reglas del
 *     dominio**;
 *  2. **invalidar las próximas reservas**, porque el índice del área tiene su propio store
 *     (`stores/reservations.js`) y su `ensure()` solo pide «si no las tiene». Sin esto, quien entra
 *     dentro del embudo y luego abre «Mi cuenta» vería el índice de un invitado: vacío y sin que nada
 *     fallara;
 *  3. **marcar `authChanged` en el store de Alpine**, que es lo que hace que CERRAR el cajón recargue
 *     la página. El resto de la web —el nav, con su saludo y su puntito de aviso— se pintó como
 *     invitado y solo una carga nueva lo devuelve a la realidad.
 *
 * ⚠️ **El `window` va por parámetro** (`CE-6`, mismo patrón que `account/after-auth.js`): es lo que
 * permite probarlo con `node --test` sin navegador.
 *
 * ⚠️⚠️ **Y lo que NINGUNA guarda estática puede decir es que alguien LLAME a esto.** Que el módulo
 * haga lo correcto lo prueba su `node --test`; que el motor sepa pedir el contexto lo prueba el
 * centinela `/me/account-context` del bundle; que exista un consumidor **en producción** lo prueba
 * `SidebarIntentWiringTest`. Pero que el efecto se VEA —que quien entra en el paso 5 y vuelve al
 * catálogo encuentre su nombre— solo lo dice el navegador (`V21`).
 */

import { cajonHost } from '../host-bridge.js';
import { useAccountContextStore } from '../stores/accountContext.js';
import { useReservationsStore } from '../stores/reservations.js';

/**
 * ⚠️ **Los stores se resuelven aquí por DEFECTO**, no se los pasa el componente. Es el mismo patrón
 * que `api = httpClient` en el resto del cajón: el llamante no tiene que saber de qué estado depende
 * esto —solo que hay sesión nueva—, y un test los sustituye sin montar Pinia. De paso, la sección de
 * compra no engorda con dos imports y dos instanciaciones que no son suyas (`CE-6`: la lógica en el
 * módulo, el componente delgado).
 *
 * @param {{context?: {refresh: Function}, reservations?: {invalidate: Function}, win?: Window}} deps
 * @returns {Promise<void>}
 */
export async function sessionGained({
    context = useAccountContextStore(),
    reservations = useReservationsStore(),
    win = window,
} = {}) {
    // ⚠️ El aviso al resto de la página va PRIMERO y sin esperar a nadie: es un booleano, no puede
    // fallar, y de él depende que cerrar el cajón recargue. Colgarlo detrás de una petición lo
    // pondría a merced de que esa petición saliera bien.
    // ⚠️ Al ANFITRIÓN del cajón, no a Alpine (F4 · T2): es el mismo objeto —con Alpine, su proxy reactivo—, y
    // así el motor no nombra un framework que una página ajena no tiene por qué cargar.
    const host = cajonHost(win);
    if (host) host.authChanged = true;

    reservations?.invalidate?.();

    await context?.refresh?.();
}
