/**
 * **«ANTES DE VENIR», sin estado** (T5c de `docs/specs/isla-y-landing-nueva.md` §4.13, `DECISIONES #776`): de las tareas
 * que publica `GET /me/reservations/{id}/before-visit` a lo que pinta el bloque (`paginas/mi-cuenta/bloques.jsx`,
 * `PmcAntes`: «una tarea cada vez»). La siguiente por hacer, entera; el resto, en filas con su plazo o su estado (las
 * hechas, al final y apagadas); lo informativo (las autorizaciones) y lo opcional (los extras) no cuentan como
 * pendientes. Con todo hecho, una línea verde.
 *
 * ⚠️ Ninguna regla se decide aquí: qué está hecho, qué dice cada tarea y a dónde lleva lo compone el SERVIDOR
 * (`Http\Cuenta\AntesDeVenir`); aquí se elige el orden de lo que se ve y los textos del bloque. Módulo plano, con su
 * `node --test`.
 */
import { tp, t as texto } from '../../sidebar/i18n.js';
import { diaDelPlazo } from '../compra/vista.js';

const TAREA = 'task';

/** El icono de cada tarea, del set de Lucide (los del mockup). */
const ICONOS = { guest_form: 'clipboard-list', invitation: 'send', extras: 'cake', authorizations: 'file-signature' };

/** Plegadas a partir de la tercera fila: «Ver las N». */
const FILAS_A_LA_VISTA = 2;

/** «Formulario de invitados, hasta el jueves 24: quién viene…» → la cabeza en negrita y el resto (`partes()` del mockup). */
function partes(frase) {
    const k = frase.indexOf(':');

    return k > 0 ? { cabeza: `${frase.slice(0, k)}: `, resto: frase.slice(k + 1).trim() } : { cabeza: '', resto: frase };
}

const conIcono = (t) => ({ ...t, icon: ICONOS[t.kind] ?? 'circle-check' });

/**
 * Lo que pinta el bloque, o `null` si la reserva no tiene nada antes de venir (el bloque no sale).
 *
 * @param {object[]} tareas  las de la API, en su orden
 * @param {{textos?: object, locale?: string, fecha?: string}} deps  `fecha`: el día de la reserva (`Y-m-d`)
 */
export function antesDe(tareas, { textos = {}, locale = 'es', fecha = '' } = {}) {
    const lista = (tareas ?? []).map(conIcono);
    const porHacer = lista.filter((t) => t.type === TAREA);
    const opcional = lista.find((t) => t.type === 'optional') ?? null;
    const estado = lista.find((t) => t.type === 'status') ?? null;

    if (! porHacer.length && ! opcional && ! estado) return null;

    const pendientes = porHacer.filter((t) => ! t.done);
    const sig = pendientes[0] ?? null;
    // El resto: lo pendiente primero, en su orden; lo hecho, al final.
    const resto = porHacer.filter((t) => t !== sig).sort((a, b) => Number(a.done) - Number(b.done));
    const hechas = porHacer.length - pendientes.length;

    return {
        progreso: porHacer.length > 1 ? { texto: tp(textos, 'mi_cuenta.antes.hechas', { a: hechas, b: porHacer.length }), por: hechas / porHacer.length } : null,
        siguiente: sig ? { ...sig, ...partes(sig.text), overline: texto(textos, 'mi_cuenta.antes.siguiente') } : null,
        // Con todo hecho, basta la línea verde: las hechas no se repiten.
        todoListo: ! sig && porHacer.length ? tp(textos, 'mi_cuenta.antes.todo_listo', { dia: fecha ? diaDelPlazo(fecha, locale) : '' }) : '',
        filas: sig ? resto.map((t) => ({ ...t, note: t.done ? '' : (t.note ?? ''), cta: t.done ? texto(textos, 'mi_cuenta.antes.hecho') : '' })) : [],
        plegable: sig !== null && resto.length > FILAS_A_LA_VISTA,
        aLaVista: FILAS_A_LA_VISTA,
        verMas: resto.length - FILAS_A_LA_VISTA === 1
            ? texto(textos, 'mi_cuenta.antes.ver_otra')
            : tp(textos, 'mi_cuenta.antes.ver_mas', { n: resto.length - FILAS_A_LA_VISTA }),
        verMenos: texto(textos, 'mi_cuenta.antes.ver_menos'),
        estado,
        opcional,
    };
}

/** «Siguiente: Formulario de invitados», el chip de arriba de Mi cuenta; vacío sin nada pendiente. */
export function chipDe(tareas, { textos = {} } = {}) {
    const sig = (tareas ?? []).find((t) => t.type === TAREA && ! t.done);

    return sig ? tp(textos, 'mi_cuenta.antes.siguiente_chip', { n: sig.title }) : '';
}
