/**
 * Los iconos que SOLO pinta Mi cuenta (T5a→T5d), del set versionado de Lucide (`DECISIONES #686`). Se registran al
 * cargar su trozo (`ui/iconos.js::registrarIconos`), antes de pintar nada: así no viajan con la compra, que se descarga
 * con la isla en cada página. Los que comparte con la compra ya están en el registro común. Los de Ajustes (T5e), en
 * SU trozo: `iconos-ajustes.js`.
 */
import { registrarIconos } from '../ui/iconos.js';
import cake from '../../../icons/lucide/icons/cake.svg?raw';
import calendarClock from '../../../icons/lucide/icons/calendar-clock.svg?raw';
import clipboardList from '../../../icons/lucide/icons/clipboard-list.svg?raw';
import fileSignature from '../../../icons/lucide/icons/file-signature.svg?raw';
import history from '../../../icons/lucide/icons/history.svg?raw';
import info from '../../../icons/lucide/icons/info.svg?raw';
import mailWarning from '../../../icons/lucide/icons/mail-warning.svg?raw';
import mapPin from '../../../icons/lucide/icons/map-pin.svg?raw';
import maximize2 from '../../../icons/lucide/icons/maximize-2.svg?raw';
import packageIcon from '../../../icons/lucide/icons/package.svg?raw';
import receipt from '../../../icons/lucide/icons/receipt.svg?raw';
import send from '../../../icons/lucide/icons/send.svg?raw';
import store from '../../../icons/lucide/icons/store.svg?raw';
import triangleAlert from '../../../icons/lucide/icons/triangle-alert.svg?raw';

registrarIconos({
    cake,
    'calendar-clock': calendarClock,
    'clipboard-list': clipboardList,
    'file-signature': fileSignature,
    history,
    info,
    'mail-warning': mailWarning,
    'map-pin': mapPin,
    'maximize-2': maximize2,
    package: packageIcon,
    receipt,
    send,
    store,
    'triangle-alert': triangleAlert,
});
