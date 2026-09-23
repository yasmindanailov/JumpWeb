/**
 * **Abrir el cajón SIN escribir JavaScript** (F4 · T2, `docs/specs/cajon-empaquetable.md` §4.2).
 *
 * Quien diseña la landing de una instancia escribe HTML. Estos atributos son la forma de decir «esto abre el
 * cajón» sin tocar el motor ni saber que existe Alpine:
 *
 *   data-jw-open                    abre el cajón (el catálogo)
 *   data-jw-open="packs"            lo abre en la sección de packs
 *   data-jw-open-zone="kids"        lo abre en las entradas de esa zona (su slug)
 *   data-jw-open-product="100"      lo abre en ese producto, listo para elegir día
 *   data-jw-open-account="orders"   lo abre en esa zona de la cuenta
 *
 * Y uno que NO abre nada: **`data-jw-track="call_clicked"`** cuenta el clic con ese nombre en la analítica
 * (`docs/specs/analitica.md` §4.2; en un `<form>`, cuenta al enfocarlo, una vez). Lo oye `track.js`, no este
 * módulo; los enlaces `tel:`, de WhatsApp y de mapas se cuentan solos, sin atributo. Los nombres válidos los
 * cierra `Platform\Services\Analytics\Contract`: uno que no exista se descarta en el servidor, sin romper nada.
 *
 * Es UN solo oyente delegado en el documento: vale también para el marcado que llegue después (un carrusel que
 * se pinta tarde, una sección cargada con `fetch`), que es justo lo que un oyente por elemento se deja.
 *
 * ⚠️ **El `href` del enlace se conserva y solo se previene aquí**, igual que en el puente de la cabecera
 * (`DECISIONES #117`): un clic central, un «abrir en pestaña nueva» o un navegador sin JS acaban en la misma
 * pantalla por el camino largo, porque las rutas de `/entradas`, `/login` o `/mi-cuenta` siguen siendo puertas.
 * Por eso un clic con modificador o con otro botón NO se toca.
 *
 * ⚠️ El cajón se pide con una FUNCIÓN y no se captura: con Alpine, `window.JumpWeb.cajon` cambia tras
 * `alpine:init` al proxy reactivo del store, y quien se hubiera quedado con el primero abriría «por dentro» sin
 * que la carcasa se moviera.
 *
 * @param {() => object|null|undefined} getCajon
 * @param {Document} doc
 */
export function installDeclarativeOpeners(getCajon, doc = document) {
    doc.addEventListener('click', (event) => {
        if (event.defaultPrevented || event.button > 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;

        const el = event.target?.closest?.('[data-jw-open], [data-jw-open-zone], [data-jw-open-product], [data-jw-open-account]');
        if (! el) return;

        const cajon = getCajon();
        if (! cajon) return;          // sin cajón en la página, el enlace hace lo que dice su `href`

        const { jwOpen, jwOpenZone, jwOpenProduct, jwOpenAccount } = el.dataset;

        if (jwOpenAccount) {
            cajon.openAccount(event, jwOpenAccount);   // previene él: es el mismo puente que la cabecera

            return;
        }

        event.preventDefault();

        if (jwOpenProduct) {
            cajon.openWith({ type: 'product', id: Number(jwOpenProduct) });
        } else if (jwOpenZone) {
            cajon.openWith({ type: 'zone', slug: jwOpenZone });
        } else if (jwOpen === 'packs') {
            cajon.openWith({ type: 'packs' });
        } else {
            cajon.open();
        }
    });
}
