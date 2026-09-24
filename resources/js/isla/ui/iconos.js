/**
 * Los iconos que pinta la isla, del set versionado de Lucide (`resources/icons/lucide/`, `DECISIONES #686`).
 *
 * El `Icon` del diseño los pedía a jsDelivr en cada visita y pintaba un hueco hasta que llegaban; aquí van
 * dentro del paquete, así que salen a la primera. Son los que la isla y su compra usan, uno a uno: importar el
 * set entero metería 1.865 dibujos en el paquete para pintar treinta y dos. Un icono que falte aquí pinta el
 * hueco vacío, como el diseño cuando no encuentra el suyo.
 */
import { transformar } from './lucide.js';
import arrowRight from '../../../icons/lucide/icons/arrow-right.svg?raw';
import arrowUpRight from '../../../icons/lucide/icons/arrow-up-right.svg?raw';
import calendarCheck from '../../../icons/lucide/icons/calendar-check.svg?raw';
import check from '../../../icons/lucide/icons/check.svg?raw';
import chevronDown from '../../../icons/lucide/icons/chevron-down.svg?raw';
import chevronLeft from '../../../icons/lucide/icons/chevron-left.svg?raw';
import chevronRight from '../../../icons/lucide/icons/chevron-right.svg?raw';
import chevronUp from '../../../icons/lucide/icons/chevron-up.svg?raw';
import circleAlert from '../../../icons/lucide/icons/circle-alert.svg?raw';
import circleCheck from '../../../icons/lucide/icons/circle-check.svg?raw';
import circleUserRound from '../../../icons/lucide/icons/circle-user-round.svg?raw';
import clock from '../../../icons/lucide/icons/clock.svg?raw';
import clockAlert from '../../../icons/lucide/icons/clock-alert.svg?raw';
import download from '../../../icons/lucide/icons/download.svg?raw';
import eye from '../../../icons/lucide/icons/eye.svg?raw';
import eyeOff from '../../../icons/lucide/icons/eye-off.svg?raw';
import footprints from '../../../icons/lucide/icons/footprints.svg?raw';
import house from '../../../icons/lucide/icons/house.svg?raw';
import lock from '../../../icons/lucide/icons/lock.svg?raw';
import logIn from '../../../icons/lucide/icons/log-in.svg?raw';
import menu from '../../../icons/lucide/icons/menu.svg?raw';
import messageCircle from '../../../icons/lucide/icons/message-circle.svg?raw';
import minus from '../../../icons/lucide/icons/minus.svg?raw';
import partyPopper from '../../../icons/lucide/icons/party-popper.svg?raw';
import phone from '../../../icons/lucide/icons/phone.svg?raw';
import plus from '../../../icons/lucide/icons/plus.svg?raw';
import qrCode from '../../../icons/lucide/icons/qr-code.svg?raw';
import shieldCheck from '../../../icons/lucide/icons/shield-check.svg?raw';
import userRound from '../../../icons/lucide/icons/user-round.svg?raw';
import userRoundPlus from '../../../icons/lucide/icons/user-round-plus.svg?raw';
import users from '../../../icons/lucide/icons/users.svg?raw';
import wallet from '../../../icons/lucide/icons/wallet.svg?raw';
import x from '../../../icons/lucide/icons/x.svg?raw';

const SVG = {
    'arrow-right': arrowRight,
    'arrow-up-right': arrowUpRight,
    'calendar-check': calendarCheck,
    check,
    'chevron-down': chevronDown,
    'chevron-left': chevronLeft,
    'chevron-right': chevronRight,
    'chevron-up': chevronUp,
    'circle-alert': circleAlert,
    'circle-check': circleCheck,
    'circle-user-round': circleUserRound,
    clock,
    'clock-alert': clockAlert,
    download,
    eye,
    'eye-off': eyeOff,
    footprints,
    house,
    lock,
    'log-in': logIn,
    menu,
    'message-circle': messageCircle,
    minus,
    'party-popper': partyPopper,
    phone,
    plus,
    'qr-code': qrCode,
    'shield-check': shieldCheck,
    'user-round': userRound,
    'user-round-plus': userRoundPlus,
    users,
    wallet,
    x,
};

/** El SVG listo para incrustar, o cadena vacía si el icono no está en el registro. */
export function dibujo(nombre, relleno = false) {
    const svg = SVG[nombre];
    return svg ? transformar(svg, relleno) : '';
}
