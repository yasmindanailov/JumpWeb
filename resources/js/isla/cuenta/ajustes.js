/**
 * **LOS AJUSTES DE MI CUENTA, lo que decide** (T5e de `docs/specs/isla-y-landing-nueva.md` §4.13, `DECISIONES #778`): de lo
 * que publican `GET /me`, `/me/identities`, `/me/waiver` y `/me/orders` a lo que pintan los cuatro plegables del bloque
 * «Ajustes» (`paginas/mi-cuenta/bloques-2.jsx`, `PmcAjustes`) y sus pasos (`cuenta.jsx`). Puro, sin Vue (`CE-6`), con su
 * `node --test`; lo que pide y lo que guarda, en `useAjustesCuenta.js`.
 *
 * Lo que decide la verdad y no el diseño (`#630`, `#773`·d):
 *   · **«Crear una contraseña» no se distingue de «Cambiarla»**: el servidor no sabe si una cuenta nacida con Google
 *     tiene contraseña propia (`DEUDA.md`, la de `password_set_at`). Una sola fila, y dentro, el enlace para crearla.
 *   · **Cambiar el correo, cerrar las otras sesiones, desvincular Google y borrar la cuenta piden la contraseña actual.**
 *   · **Con una reserva por celebrar, la cuenta no se borra** (`AccountPrivacy::anonymize`, `#284`): se dice, y el botón
 *     no se ofrece —uno que solo puede fallar es la familia de `#117`—.
 *   · **«Descargar mis datos» descarga** (`GET /me/export`): su aviso es «descargado», no «te los enviamos».
 *   · **Un recibo es el LIBRO del pedido**: sus líneas de dinero con los rótulos del servidor (`#305`: el cliente y el
 *     operador ven lo mismo), no un «Pagado · Señal · Resto» compuesto aquí.
 */
import { t as texto, tp } from '../../sidebar/i18n.js';
import { minutesLeft } from '../../sidebar/account/profile.js';
import { waiverAwaitsVerification, waiverNeedsSignature } from '../../sidebar/account/waiver.js';
import { diaDelPlazo, euros, horaCorta } from '../compra/vista.js';
import { hojaDelDia, tituloDe } from './reservas.js';

/** Los plegables del bloque, en el orden del diseño, con su icono. */
export const PLEGABLES = [
    { id: 'datos', icono: 'user-round' },
    { id: 'acceso', icono: 'key-round' },
    { id: 'privacidad', icono: 'shield-check' },
    { id: 'recibos', icono: 'receipt' },
];

/** Lo que edita «Tus datos», tal como llega de `GET /me`. */
export function datosDe(user) {
    return { nombre: String(user?.name ?? ''), telefono: String(user?.phone ?? ''), idioma: String(user?.locale ?? '') };
}

/** ¿Ha cambiado algo? «Guardar los cambios» solo sale entonces (el diseño: «un botón que solo sale cuando algo cambia»). */
export function hayCambios(d, user) {
    if (! user) return false;
    const g = datosDe(user);

    return d.nombre !== g.nombre || d.telefono !== g.telefono || d.idioma !== g.idioma;
}

/** Lo que falta se dice antes de preguntar: el nombre. El resto de las reglas (el teléfono, el idioma) son del servidor. */
export function revisarDatosCuenta(d, textos) {
    return d.nombre.trim() ? {} : { nombre: texto(textos, 'compra.datos.errores.nombre') };
}

/** Los idiomas de la instalación con su nombre nativo (`SiteLocales`: los del cajón), no los dos del mockup. */
export const idiomasDe = (locales) => (locales ?? []).map((l) => ({ value: String(l.value), label: String(l.label) }));

/**
 * La fila del correo y su cambio PENDIENTE: a dónde se mandó el enlace, con cuál se sigue entrando y cuánto le queda
 * (`pending_email` y su caducidad los resuelve el servidor; `ahora`, por parámetro, `#64`).
 *
 * @returns {{actual: string, pendiente: null|{correo: string, texto: string, caduca: string}}}
 */
export function correoDe(user, { textos, ahora }) {
    const actual = String(user?.email ?? '');
    const nuevo = user?.pending_email ? String(user.pending_email) : '';

    if (! nuevo) return { actual, pendiente: null };

    const minutos = minutesLeft(user.pending_email_expires_at, ahora);

    return {
        actual,
        pendiente: {
            correo: nuevo,
            texto: tp(textos, 'mi_cuenta.correo.enviado', { nuevo, actual }),
            caduca: minutos > 0 ? tp(textos, 'mi_cuenta.correo.caduca', { minutos }) : texto(textos, 'mi_cuenta.correo.caducado'),
        },
    };
}

/** El correo nuevo, antes de preguntar: que tenga forma de correo y que no sea el de ahora. */
export function revisarCorreo({ correo, clave }, { actual, textos }) {
    const e = {};
    const v = correo.trim();

    if (! /^\S+@\S+\.\S+$/.test(v)) e.correo = texto(textos, 'compra.datos.errores.correo');
    else if (v.toLowerCase() === actual.toLowerCase()) e.correo = texto(textos, 'mi_cuenta.correo.mismo');
    if (! clave) e.clave = texto(textos, 'compra.datos.errores.clave');

    return e;
}

/**
 * La fila de Google en «Acceso»: vinculada (con qué correo) o, si la instalación lo ofrece, «Vincular». Sin la lista aún,
 * nada: decir «Vincular» a quien ya lo tiene sería mentirle (`UNIQUE(user_id, provider)`: una llave por proveedor).
 * Apple no sale: llega con la v2.0.0 en su tanda (`#683`), y un corchete sin hueco no se pinta (`#773`·c).
 *
 * @param {object[]|null} identidades  `GET /me/identities`
 * @returns {null|{vinculada: boolean, correo?: string}}
 */
export function googleDe(identidades, { puedeVincular }) {
    if (! Array.isArray(identidades)) return null;
    const g = identidades.find((i) => i?.provider === 'google');

    if (g) return { vinculada: true, correo: String(g.email_at_link ?? '') };

    return puedeVincular ? { vinculada: false } : null;
}

/**
 * La fila «Tu descargo firmado» de «Privacidad», según el estado que publica `GET /me/waiver` (la decisión es del
 * servidor y de `sidebar/account/waiver.js`; aquí, el texto y lo que se ofrece):
 *   · firmado y vigente: «Firmado el … · versión …» y su PDF;
 *   · sin firma o de una versión anterior: lo dice, y «Firmar» (su paso);
 *   · aceptado al darse de alta y sin confirmar el correo: se firmará al confirmarlo (el aviso de arriba lo pide);
 *   · lo gestiona el parque (`externo`): lo dice; desactivado o sin estado, la fila no sale.
 *
 * @param {object|null} estado  `GET /me/waiver`
 * @param {{textos: object, motor: object}} deps  `motor`: el grupo `account` (los textos del estado, los del cajón)
 * @returns {null|{texto: string, pdf: string, firmar: boolean}}
 */
export function descargoDe(estado, { textos, motor }) {
    if (! estado || estado.mode === 'desactivado' || typeof estado.mode !== 'string') return null;
    if (estado.mode === 'externo') return { texto: texto(motor, 'account.privacy.waiver.status_external'), pdf: '', firmar: false };
    if (waiverAwaitsVerification(estado)) return { texto: texto(motor, 'account.privacy.waiver.status_awaiting_verification'), pdf: '', firmar: false };

    const propia = (estado.signatures ?? []).find((f) => f?.subject === 'holder') ?? null;

    if (waiverNeedsSignature(estado)) {
        return {
            texto: texto(textos, estado.signed === true ? 'mi_cuenta.descargo.anterior' : 'mi_cuenta.descargo.sin_firma'),
            pdf: propia?.pdf_url ?? '',
            firmar: true,
        };
    }

    return {
        texto: tp(textos, 'mi_cuenta.ajustes.firmado_el', { fecha: estado.accepted_label ?? '', version: estado.version ?? '' }),
        pdf: propia?.pdf_url ?? '',
        firmar: false,
    };
}

/** Los tres interruptores de «Privacidad», de `GET /me`: encendido = lo recibe o lo vincula. */
export function interruptoresDe(user) {
    return { novedades: user?.marketing_opt_in === true, analitica: user?.analytics_opt_out !== true, encuestas: user?.surveys_opt_out !== true };
}

/** Los rótulos del SALDO, los del libro del cajón (`account/orders.js`): se liquida en el parque. */
const SALDO = {
    pay_at_park: 'journal.balance_pay_at_park',
    refund_at_park: 'journal.balance_refund_at_park',
    refund_pending: 'journal.balance_refund_pending',
};

const conSigno = (cents, locale) => `${Number(cents) < 0 ? '−' : ''}${euros(Math.abs(Number(cents) || 0), locale)}`;

/**
 * Un recibo por pedido en el que se ha movido dinero —uno a medio pagar o caducado no tiene recibo—: arriba, qué, cuándo
 * y su número («sáb 26 sep · Kids 1 hora · 2 niños · Nº R-7K2P4», la `selection` de `PriceSummary`); debajo, las líneas
 * de DINERO del libro con sus rótulos del servidor («Pagado online», «Devuelto a la tarjeta») y, si queda, el saldo que
 * se liquida en el parque; y abajo, el TOTAL del libro —lo que el pedido vale hoy— (el mockup lo dejaba vacío: la pieza
 * pinta la fila siempre). ⚠️ Si el libro no cierra (`is_consistent: false`), solo lo cobrado, el total y la frase del
 * servidor (`#132`: ninguna otra línea es cierta; el total y los cobros siguen siendo hechos).
 *
 * @param {object[]} pedidos  `GET /me/orders`
 * @param {{locale?: string, textos?: object, messages?: object}} deps
 * @returns {{code: string, selection: string, lines: {label: string, value: string, tone?: string}[], total: string, note: string}[]}
 */
export function recibosDe(pedidos, { locale = 'es', textos = {}, messages = {} } = {}) {
    return (pedidos ?? [])
        .filter((p) => (p?.ledger?.settlements ?? []).length > 0)
        .map((p) => {
            const l = p.ledger;
            const cierra = l.is_consistent !== false;
            const primera = (p.items ?? [])[0] ?? {};
            const dia = primera.date ? hojaDelDia(primera.date, locale) : null;
            const lines = l.settlements
                .filter((s) => cierra || s.kind === 'payment')
                .map((s) => ({
                    label: s.label,
                    value: conSigno(s.amount_cents, locale),
                    // Una devolución que ya volvió se lee en positivo (el mockup, «Devuelto»); una en curso o que falló, sin
                    // él: la dice su rótulo del servidor («Devolución en curso») y no cuenta en lo pagado (`PAY-09`).
                    tone: s.status === 'succeeded' && Number(s.amount_cents) < 0 ? 'positive' : undefined,
                }));
            const saldo = cierra ? SALDO[l.balance?.kind] : undefined;

            if (saldo) lines.push({ label: texto(messages, saldo), value: euros(Math.abs(Number(l.balance.cents) || 0), locale) });

            return {
                code: String(p.code ?? ''),
                selection: [dia ? `${dia.dow} ${dia.n} ${dia.month}` : '', tituloDe(primera), tp(textos, 'mi_cuenta.proxima.numero', { code: p.code ?? '' })].filter(Boolean).join(' · '),
                lines,
                total: euros(Number(l.total_cents) || 0, locale),
                note: cierra ? '' : String(l.note ?? ''),
            };
        });
}

/**
 * «Borrar tu cuenta» con una reserva por celebrar: el servidor la NIEGA (`409 account_has_upcoming_reservations`), así
 * que se dice cuál es y el botón no se ofrece. Sin ninguna, `''`. ⚠️ Es un aviso adelantado, no la regla: la regla es del
 * servidor (`hasUpcomingFor`), y su `409` se pinta igual si llega por otra reserva.
 *
 * @param {{reservation: object}|null} proxima  la primera reserva viva de un pedido pagado (`reservas.js`)
 */
export function reservaQueImpide(proxima, { locale = 'es', textos = {} } = {}) {
    const r = proxima?.reservation;

    if (! r?.date) return '';
    const hora = horaCorta(r.start_time) ?? horaCorta(String(r.time_window ?? '').split(/[–-]/)[0].trim()) ?? '';

    return tp(textos, 'mi_cuenta.borrar.reserva', { dia: diaDelPlazo(r.date, locale), hora });
}
