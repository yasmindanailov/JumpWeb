/**
 * **«Datos de la reserva» de la pantalla 0, diferido** (T6c·3, `#839`): solo un pack con algo que pedir al reservar lo
 * pinta, y viaja en el trozo de los pasos, con el campo del sistema (`pasos-diferidos.js`). Metido en la pantalla 0, la
 * compra pesaba +7 kB (`SidebarBundleBudgetTest`, medido).
 *
 * Y **la lista de complementos de la pantalla 0** (`#880`), por lo mismo y en el mismo trozo, que la compra pide al montarse:
 * dentro, la compra pesaba 194,66 kB contra su techo de 188 (medido el 02-10): la fila de la mejora (`ui/FilaMejora.vue`) es
 * también de la calculadora de la fiesta y arrastraba su trozo compartido.
 */
import { defineAsyncComponent } from 'vue';

export default defineAsyncComponent(() => import('./pasos-diferidos.js').then((m) => m.DatosReserva));

export const ComplementosCompra = defineAsyncComponent(() => import('./pasos-diferidos.js').then((m) => m.ComplementosCompra));
