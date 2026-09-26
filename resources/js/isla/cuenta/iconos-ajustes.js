/**
 * Los iconos que SOLO pintan los Ajustes de Mi cuenta (T5e, `#778`): «Acceso», «Cerrar sesión» y la reserva que impide
 * borrar la cuenta. Se registran al cargar el trozo de Ajustes (lo importan su bloque y sus pasos), como los de Mi cuenta
 * desde el suyo (`iconos.js`): así no viajan con la primera pintura de Mi cuenta.
 */
import { registrarIconos } from '../ui/iconos.js';
import calendarX from '../../../icons/lucide/icons/calendar-x.svg?raw';
import keyRound from '../../../icons/lucide/icons/key-round.svg?raw';
import logOut from '../../../icons/lucide/icons/log-out.svg?raw';

registrarIconos({
    'calendar-x': calendarX,
    'key-round': keyRound,
    'log-out': logOut,
});
