/**
 * Los iconos que pinta la isla, del set versionado de Lucide (`resources/icons/lucide/`, `DECISIONES #686`).
 *
 * El `Icon` del diseño los pedía a jsDelivr en cada visita y pintaba un hueco hasta que llegaban; aquí van
 * dentro del paquete, así que salen a la primera. Son los que la isla usa, uno a uno: importar el set entero
 * metería 1.865 dibujos en el paquete para pintar dieciséis. Un icono que falte aquí pinta el hueco vacío,
 * como el diseño cuando no encuentra el suyo.
 */
import { transformar } from './lucide.js';
import arrowRight from '../../../icons/lucide/icons/arrow-right.svg?raw';
import arrowUpRight from '../../../icons/lucide/icons/arrow-up-right.svg?raw';
import check from '../../../icons/lucide/icons/check.svg?raw';
import chevronDown from '../../../icons/lucide/icons/chevron-down.svg?raw';
import chevronLeft from '../../../icons/lucide/icons/chevron-left.svg?raw';
import chevronRight from '../../../icons/lucide/icons/chevron-right.svg?raw';
import chevronUp from '../../../icons/lucide/icons/chevron-up.svg?raw';
import clock from '../../../icons/lucide/icons/clock.svg?raw';
import house from '../../../icons/lucide/icons/house.svg?raw';
import logIn from '../../../icons/lucide/icons/log-in.svg?raw';
import menu from '../../../icons/lucide/icons/menu.svg?raw';
import messageCircle from '../../../icons/lucide/icons/message-circle.svg?raw';
import phone from '../../../icons/lucide/icons/phone.svg?raw';
import qrCode from '../../../icons/lucide/icons/qr-code.svg?raw';
import userRound from '../../../icons/lucide/icons/user-round.svg?raw';
import x from '../../../icons/lucide/icons/x.svg?raw';

const SVG = {
    'arrow-right': arrowRight,
    'arrow-up-right': arrowUpRight,
    check,
    'chevron-down': chevronDown,
    'chevron-left': chevronLeft,
    'chevron-right': chevronRight,
    'chevron-up': chevronUp,
    clock,
    house,
    'log-in': logIn,
    menu,
    'message-circle': messageCircle,
    phone,
    'qr-code': qrCode,
    'user-round': userRound,
    x,
};

/** El SVG listo para incrustar, o cadena vacía si el icono no está en el registro. */
export function dibujo(nombre, relleno = false) {
    const svg = SVG[nombre];
    return svg ? transformar(svg, relleno) : '';
}
