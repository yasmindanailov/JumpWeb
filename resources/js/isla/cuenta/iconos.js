/**
 * Los iconos que SOLO pinta Mi cuenta (T5a, T5b), del set versionado de Lucide (`DECISIONES #686`). Se registran al
 * cargar su trozo (`ui/iconos.js::registrarIconos`), antes de pintar nada: así no viajan con la compra, que se descarga
 * con la isla en cada página. Los que comparte con la compra ya están en el registro común.
 */
import { registrarIconos } from '../ui/iconos.js';
import calendarClock from '../../../icons/lucide/icons/calendar-clock.svg?raw';
import history from '../../../icons/lucide/icons/history.svg?raw';
import mapPin from '../../../icons/lucide/icons/map-pin.svg?raw';
import maximize2 from '../../../icons/lucide/icons/maximize-2.svg?raw';
import packageIcon from '../../../icons/lucide/icons/package.svg?raw';
import receipt from '../../../icons/lucide/icons/receipt.svg?raw';
import store from '../../../icons/lucide/icons/store.svg?raw';
import triangleAlert from '../../../icons/lucide/icons/triangle-alert.svg?raw';

registrarIconos({
    'calendar-clock': calendarClock,
    history,
    'map-pin': mapPin,
    'maximize-2': maximize2,
    package: packageIcon,
    receipt,
    store,
    'triangle-alert': triangleAlert,
});
