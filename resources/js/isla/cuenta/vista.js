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

/** Las vistas de la capa en la T5a. Las de las tandas siguientes (Tu reserva, los ajustes…) se suman aquí. */
export const VISTA = {
    INICIO: 'inicio',
    QR: 'qr',
    ENTRAR: 'entrar',
    CREAR: 'crear',
    ALTA_GOOGLE: 'alta-google',
};

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
 * @returns {{vista: string, bloque: string}}
 */
export function vistaDeApertura(zona, { sesion, bloque = '' }) {
    if (zona === 'google-signup') return { vista: VISTA.ALTA_GOOGLE, bloque: '' };
    if (! sesion) return { vista: zona === 'register' ? VISTA.CREAR : VISTA.ENTRAR, bloque: '' };
    if (zona === 'card') return { vista: VISTA.QR, bloque: '' };

    // «Mis reservas» (`/mi-cuenta/pedidos`, a donde llevan los correos ya enviados): el inicio, en la próxima.
    return { vista: VISTA.INICIO, bloque: bloque || (zona === 'orders' ? 'proxima' : '') };
}

/**
 * «Sáb 26 · 17:00 · Kids 1 hora»: la próxima reserva en una línea, arriba (el bloque «Arriba» del diseño,
 * `proximaCorta`). De `next_reservation`, el contexto de cuenta que ya viene sembrado: sin petición.
 *
 * @param {{date?: string, time_window?: string, product_name?: string}|null} reserva
 * @param {{locale?: string}} [opciones]
 * @returns {string}  `''` sin reserva
 */
export function lineaProxima(reserva, { locale = 'es' } = {}) {
    if (! reserva?.date) return '';

    const hora = horaCorta(String(reserva.time_window ?? '').split(/[–-]/)[0].trim());

    return [diaConMayuscula(reserva.date, locale), hora, reserva.product_name].filter(Boolean).join(' · ');
}

/**
 * La banda de cada vista: el rótulo (`step`), la flecha (`onBack`), la X (`onClose`) y la acción (`action`), con la
 * dirección de la entrada (`dir`). Lo pinta `CompraIsla.vue`.
 *
 * @param {{
 *   vista: string, subpaso?: string, desde?: string|null, qrDesde?: string|null, dir?: string|null,
 *   ocupado?: string|null, entrada?: {valor: string, clave: string}, textos: object,
 *   acciones: {cerrar: Function, alMenu: Function, aInicio: Function, aEntrar: Function, entrar: Function,
 *              crear: Function, completarGoogle: Function, volverDelDescargo: Function},
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
