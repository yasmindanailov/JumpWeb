/**
 * **MI CUENTA EN LA ISLA, lo que decide** (T5a de `docs/specs/isla-y-landing-nueva.md` §4.13, `DECISIONES #773`): puro,
 * sin Vue (`CE-6`) y con su `node --test`. Lo que cambia y lo que pide al servidor, en `useSeccionCuenta.js`.
 *
 * Mi cuenta vive en la CAPA GRANDE de la isla, la de la compra (`piezas/CompraIsla.vue`): el mismo contenedor, la misma
 * flecha, la misma X y la acción abajo. Cada vista describe su banda con un `ck`, como los pasos de la compra
 * (`compra/pasos.js`). Las reglas son las del diseño (`paginas/mi-cuenta/cuenta.jsx`, «Mi cuenta · reglas»):
 *   · **La flecha, solo si hay algo detrás, y vuelve ahí**: abierta desde el menú de la isla, al menú; Tu QR abierto
 *     desde Mi cuenta, a Mi cuenta; abierta desde un correo, `/mi-cuenta` o la acción de la isla, solo la X.
 *   · **Una acción principal por vista**: Entrar y Crear la llevan en la isla; el inicio y Tu QR, ninguna.
 *   · **Sin sesión, Mi cuenta abre Entrar**, en la misma capa.
 */
import { t as texto } from '../../sidebar/i18n.js';
import { diaCorto, horaCorta } from '../compra/vista.js';

/** «Sáb 26»: el día corto de la compra (`diaCorto`), con mayúscula, como lo escribe el diseño. */
function diaConMayuscula(iso, locale) {
    const dia = diaCorto(iso, locale);

    return `${dia.charAt(0).toUpperCase()}${dia.slice(1)}`;
}

/**
 * Las vistas de la capa: las de la T5a; desde la T5b, Tu reserva y Cambiar o cancelar; desde la T5d, Añade a tus hijos,
 * su «Guardado» y la ficha de un hijo (firmar por él, quitarlo); desde la T5e, los pasos de Ajustes —lo que «necesita más
 * de un campo o una confirmación» (el diseño)—: la contraseña, el correo, cerrar las otras sesiones, desvincular Google,
 * firmar tu descargo y borrar la cuenta.
 */
export const VISTA = {
    INICIO: 'inicio',
    QR: 'qr',
    ENTRAR: 'entrar',
    CREAR: 'crear',
    ALTA_GOOGLE: 'alta-google',
    RESERVA: 'reserva',
    CAMBIAR: 'cambiar',
    HIJOS: 'hijos',
    HIJOS_LISTO: 'hijos-listo',
    HIJO: 'hijo',
    CLAVE: 'clave',
    CORREO: 'correo',
    OTRAS: 'otras-sesiones',
    DESVINCULAR: 'desvincular',
    FIRMA: 'firma',
    BORRAR: 'borrar',
};

/**
 * Las zonas del cajón que en la isla son un PLEGABLE de Ajustes (T5e): quien abre la cuenta en «Tus datos», la
 * contraseña, las sesiones o la privacidad llega a Mi cuenta con ese plegable abierto. Y el enlace
 * `#mi-cuenta/<plegable>` hace lo mismo (la vuelta de vincular Google es `#mi-cuenta/acceso`).
 */
const PLEGABLE_DE_ZONA = { profile: 'datos', password: 'acceso', sessions: 'acceso', privacy: 'privacidad' };
const PLEGABLES_DE_ENLACE = ['datos', 'acceso', 'privacidad', 'recibos'];

/**
 * La vista y el bloque con que se ABRE Mi cuenta, según la zona que pide la apertura (las puertas del servidor,
 * `Http\Sidebar\AccountDoor`; el menú de la isla; el enlace `#mi-cuenta/<bloque>`) y si hay sesión.
 *
 * ⚠️ Completar el alta que vuelve de Google (`google-signup`) va ANTES que la sesión: a esa puerta se llega sin ella, y
 *    es la única que lo hace a propósito. Sin sesión, todo lo demás es Entrar; «¿olvidaste tu contraseña?» también,
 *    porque en la isla el olvido sale de Entrar con el correo ya escrito (`PjcEntrar`).
 *
 * @param {string} zona  la del motor (`account/navigation.js::ZONES`)
 * @param {{sesion: boolean, bloque?: string}} contexto
 * @returns {{vista: string, bloque: string, plegable: string}}  `plegable`: el de Ajustes que se abre (T5e), o `''`
 */
export function vistaDeApertura(zona, { sesion, bloque = '' }) {
    if (zona === 'google-signup') return { vista: VISTA.ALTA_GOOGLE, bloque: '', plegable: '' };
    if (! sesion) return { vista: zona === 'register' ? VISTA.CREAR : VISTA.ENTRAR, bloque: '', plegable: '' };
    if (zona === 'card') return { vista: VISTA.QR, bloque: '', plegable: '' };
    // «Añade a tus hijos» (T5d): la zona de menores del motor —su puerta `/mi-cuenta/hijos`, la tarea de «Antes de venir»—.
    if (zona === 'dependents') return { vista: VISTA.HIJOS, bloque: '', plegable: '' };

    // Un plegable de Ajustes (T5e), por su zona del cajón o por el enlace: el inicio, en su bloque y con él abierto.
    const plegable = PLEGABLES_DE_ENLACE.includes(bloque) ? bloque : (PLEGABLE_DE_ZONA[zona] ?? '');

    if (plegable) return { vista: VISTA.INICIO, bloque: 'ajustes', plegable };

    // «Mis reservas» (`/mi-cuenta/pedidos`, a donde llevan los correos ya enviados): el inicio, en la próxima.
    return { vista: VISTA.INICIO, bloque: bloque || (zona === 'orders' ? 'proxima' : ''), plegable: '' };
}

/**
 * «Sáb 26 · 17:00 · Kids 1 hora · 2 niños»: la próxima reserva en una línea, arriba (el bloque «Arriba» del diseño,
 * `proximaCorta`). Antes de llegar las reservas, de `next_reservation` —el contexto ya sembrado, sin la cantidad—; con
 * ellas, con su `titulo` (qué y cuántos).
 *
 * @param {{date?: string, time_window?: string, product_name?: string}|null} reserva
 * @param {{locale?: string, titulo?: string}} [opciones]
 * @returns {string}  `''` sin reserva
 */
export function lineaProxima(reserva, { locale = 'es', titulo = '' } = {}) {
    if (! reserva?.date) return '';

    const hora = horaCorta(String(reserva.time_window ?? '').split(/[–-]/)[0].trim());

    return [diaConMayuscula(reserva.date, locale), hora, titulo || reserva.product_name].filter(Boolean).join(' · ');
}

/**
 * Los pasos de Ajustes (T5e): su rótulo, su acción (la clave de `acciones` que la hace) y lo que dice mientras espera.
 * Borrar no tiene acción en la isla (va en el contenido, sin naranja).
 */
const PASOS_DE_AJUSTES = {
    [VISTA.CLAVE]: { titulo: 'mi_cuenta.clave.titulo', accion: 'mi_cuenta.clave.guardar', hace: 'guardarClave', cargando: 'mi_cuenta.ajustes.guardando' },
    [VISTA.CORREO]: { titulo: 'mi_cuenta.correo.titulo', accion: 'mi_cuenta.correo.enviar', hace: 'enviarCorreo', cargando: 'mi_cuenta.correo.enviando' },
    [VISTA.OTRAS]: { titulo: 'mi_cuenta.otras_sesiones.titulo', accion: 'mi_cuenta.otras_sesiones.boton', hace: 'cerrarOtras', cargando: 'mi_cuenta.otras_sesiones.cerrando' },
    [VISTA.DESVINCULAR]: { titulo: 'mi_cuenta.desvincular.titulo', accion: 'mi_cuenta.desvincular.boton', hace: 'desvincular', cargando: 'mi_cuenta.desvincular.cargando' },
    [VISTA.FIRMA]: { titulo: 'mi_cuenta.descargo.titulo', accion: 'mi_cuenta.descargo.firmar', hace: 'firmar', cargando: 'mi_cuenta.hijo.firmando' },
    [VISTA.BORRAR]: { titulo: 'mi_cuenta.borrar.titulo', accion: null },
};

/**
 * La banda de cada vista: el rótulo (`step`), la flecha (`onBack`), la X (`onClose`) y la acción (`action`), con la
 * dirección de la entrada (`dir`). Lo pinta `CompraIsla.vue`.
 *
 * @param {{
 *   vista: string, subpaso?: string, desde?: string|null, qrDesde?: string|null, dir?: string|null,
 *   ocupado?: string|null, entrada?: {valor: string, clave: string}, textos: object,
 *   acciones: {cerrar: Function, alMenu: Function, aInicio: Function, aEntrar: Function, entrar: Function,
 *              crear: Function, completarGoogle: Function, volverDelDescargo: Function, aReserva: Function, escribir: Function},
 *   cambiar?: {desdeReserva: boolean, whatsapp: boolean},
 *   hijo?: {nombre: string, firmar: boolean},
 *   ajuste?: {enviado: boolean, firmar: boolean},
 *   rotulos?: {altaGoogle?: string, altaGoogleBoton?: string, altaGoogleEnviando?: string},
 *   altaGoogle?: {pendiente: boolean},
 * }} e
 */
export function ckDeCuenta(e) {
    const t = (clave) => texto(e.textos, clave);
    const { acciones } = e;
    const base = { key: `mc-${e.vista}${e.subpaso ? `-${e.subpaso}` : ''}`, dir: e.dir ?? null, onClose: acciones.cerrar, action: null };
    const delMenu = e.desde === 'menu' ? acciones.alMenu : null;

    // El descargo, leído sin salir del alta (Crea tu cuenta, el alta de Google): su flecha vuelve al formulario.
    if (e.subpaso === 'descargo') return { ...base, step: t('compra.datos.descargo'), onBack: acciones.volverDelDescargo };

    if (e.vista === VISTA.QR) {
        return { ...base, step: t('mi_cuenta.qr.titulo'), onBack: e.qrDesde === 'cuenta' ? acciones.aInicio : delMenu };
    }

    if (e.vista === VISTA.ENTRAR) {
        if (e.subpaso === 'olvido') return { ...base, step: t('compra.datos.olvido'), onBack: acciones.aEntrar };
        const lleno = Boolean(String(e.entrada?.valor ?? '').trim()) && Boolean(e.entrada?.clave);

        return {
            ...base, step: t('compra.entrar.titular'), onBack: delMenu,
            action: { label: t('compra.entrar.continuar'), onClick: acciones.entrar, disabled: ! lleno, loading: e.ocupado === 'entrar' ? t('compra.entrar.cargando') : false },
        };
    }

    if (e.vista === VISTA.CREAR) {
        return {
            ...base, step: t('mi_cuenta_alta.crear_titulo'), onBack: acciones.aEntrar,
            action: { label: t('mi_cuenta_alta.crear_boton'), onClick: acciones.crear, loading: e.ocupado === 'crear' ? t('mi_cuenta_alta.creando') : false },
        };
    }

    // Tu reserva (una de «Otras reservas»): su flecha vuelve a Mi cuenta, al mismo punto.
    if (e.vista === VISTA.RESERVA) return { ...base, step: t('mi_cuenta.reserva.titulo'), onBack: acciones.aInicio };

    // Cambiar o cancelar: desde la reserva abierta, la flecha vuelve a ella; desde la próxima, a la lista. Su acción
    // es escribir por WhatsApp con el mensaje ya escrito; sin teléfono del parque, ninguna (queda el texto).
    if (e.vista === VISTA.CAMBIAR) {
        return {
            ...base, step: t('mi_cuenta.cambiar.banda'), onBack: e.cambiar?.desdeReserva ? acciones.aReserva : acciones.aInicio,
            action: e.cambiar?.whatsapp ? { label: t('mi_cuenta.cambiar.boton'), onClick: acciones.escribir } : null,
        };
    }

    // Añade a tus hijos (T5d): su flecha vuelve a Mi cuenta; su acción, «Guardar», espera al servidor (uno a uno).
    if (e.vista === VISTA.HIJOS) {
        return {
            ...base, step: t('mi_cuenta.hijos.titulo'), onBack: acciones.aInicio,
            action: { label: t('mi_cuenta.hijos.boton'), onClick: acciones.guardarHijos, loading: e.ocupado === 'hijos' ? t('mi_cuenta.hijos.guardando') : false },
        };
    }
    if (e.vista === VISTA.HIJOS_LISTO) return { ...base, step: t('mi_cuenta.hijos.titulo'), onBack: acciones.aInicio };

    // La ficha de un hijo: firmar por él es su acción, cuando hace falta y se puede (con el correo sin verificar, no).
    if (e.vista === VISTA.HIJO) {
        return {
            ...base, step: e.hijo?.nombre ?? '', onBack: acciones.aInicio,
            action: e.hijo?.firmar ? { label: t('mi_cuenta.hijo.firmar'), onClick: acciones.firmarHijo, loading: e.ocupado === 'firmar' ? t('mi_cuenta.hijo.firmando') : false } : null,
        };
    }

    // Los pasos de Ajustes (T5e): su flecha vuelve a Mi cuenta, al mismo punto y con el plegable abierto; su acción espera
    // al servidor. ⚠️ Borrar la cuenta NO la lleva: «una acción destructiva no va en el naranja de seguir» (el diseño);
    // su botón vive en el contenido. El correo, ya enviado, tampoco: queda el desenlace.
    const paso = PASOS_DE_AJUSTES[e.vista];

    if (paso) {
        const conAccion = paso.accion && ! (e.vista === VISTA.CORREO && e.ajuste?.enviado) && ! (e.vista === VISTA.FIRMA && ! e.ajuste?.firmar);

        return {
            ...base, step: t(paso.titulo), onBack: acciones.aInicio,
            action: conAccion ? { label: t(paso.accion), onClick: acciones[paso.hace], loading: e.ocupado === e.vista ? t(paso.cargando) : false } : null,
        };
    }

    if (e.vista === VISTA.ALTA_GOOGLE) {
        const r = e.rotulos ?? {};

        return {
            ...base, step: r.altaGoogle ?? '', onBack: null,
            action: e.altaGoogle?.pendiente
                ? { label: r.altaGoogleBoton ?? '', onClick: acciones.completarGoogle, loading: e.ocupado === 'google' ? (r.altaGoogleEnviando ?? '') : false }
                : null,
        };
    }

    return { ...base, step: t('mi_cuenta.titulo'), onBack: delMenu };
}
