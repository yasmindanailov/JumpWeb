/**
 * Los iconos que SOLO pinta Mi cuenta (T5a), del set versionado de Lucide (`DECISIONES #686`). Se registran al cargar
 * su trozo (`ui/iconos.js::registrarIconos`), antes de pintar nada: así no viajan con la compra, que se descarga con
 * la isla en cada página. Los que comparte con la compra ya están en el registro común.
 */
import { registrarIconos } from '../ui/iconos.js';
import maximize2 from '../../../icons/lucide/icons/maximize-2.svg?raw';
import triangleAlert from '../../../icons/lucide/icons/triangle-alert.svg?raw';

registrarIconos({ 'maximize-2': maximize2, 'triangle-alert': triangleAlert });
