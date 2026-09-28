/**
 * **«Datos de la reserva» de la pantalla 0, diferido** (T6c·3, `#839`): solo un pack con algo que pedir al reservar lo
 * pinta, y viaja en el trozo de los pasos, con el campo del sistema (`pasos-diferidos.js`). Metido en la pantalla 0, la
 * compra pesaba +7 kB (`SidebarBundleBudgetTest`, medido).
 */
import { defineAsyncComponent } from 'vue';

export default defineAsyncComponent(() => import('./pasos-diferidos.js').then((m) => m.DatosReserva));
