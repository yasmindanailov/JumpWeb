/**
 * Los iconos del PIE DEL MENÚ (Z6a): el idioma y las cookies. Los registra el menú al cargarse
 * (`ui/iconos.js::registrarIconos`), como Mi cuenta los suyos: así las calculadoras de las páginas, que comparten el
 * registro de iconos pero no llevan la isla, no pagan dos dibujos que no pintan (medido: +0,94 kB en cada una, que las
 * sacaba de su techo en `SidebarBundleBudgetTest`).
 */
import { registrarIconos } from '../ui/iconos.js';
import cookie from '../../../icons/lucide/icons/cookie.svg?raw';
import languages from '../../../icons/lucide/icons/languages.svg?raw';

registrarIconos({ cookie, languages });
