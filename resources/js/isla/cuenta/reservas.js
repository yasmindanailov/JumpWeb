/**
 * **LAS RESERVAS DE MI CUENTA, sin estado** (T5b de `docs/specs/isla-y-landing-nueva.md` §4.13, `DECISIONES #775`): de lo
 * que publica `GET /me/reservations/{upcoming,past}` a lo que pintan «Tu próxima reserva», «Otras reservas» con su
 * historial, «Tu reserva» y «Cambiar o cancelar» (`paginas/mi-cuenta/bloques.jsx` y `pantallas.jsx` del diseño).
 *
 * ⚠️⚠️ **Ninguna regla de negocio se decide aquí** (la doctrina de `sidebar/account/orders.js`): qué es la próxima
 * lo decide el pedido PAGADO (como `next_reservation`, `upcomingFor()`), el plazo y si sigue abierto los publica el
 * servidor (`cancellation`), si procede «señal · resto» también (`shows_deposit_note`), y el dinero sale del LIBRO de la
 * reserva (`ledger`). Aquí se formatea y se elige el texto. Módulo plano, con su `node --test`.
 */
import { tp, t as texto } from '../../sidebar/i18n.js';
import { diaDelPlazo, diaLargo, euros, horaCorta } from '../compra/vista.js';

const PAGADO = 'paid';

/** «sáb», «26», «sep»: la hoja del calendario de `BookingCard`, sin los puntos que pone el navegador. */
export function hojaDelDia(iso, locale = 'es') {
    const p = new Intl.DateTimeFormat(locale, { weekday: 'short', day: 'numeric', month: 'short' }).formatToParts(new Date(`${iso}T12:00:00`));
    const de = (tipo) => (p.find((x) => x.type === tipo)?.value ?? '').replace(/\.$/, '');

    return { dow: de('weekday').toLowerCase(), n: de('day'), month: de('month').toLowerCase().slice(0, 3) };
}

/**
 * La próxima, las otras y el historial. La PRÓXIMA es la primera reserva de un pedido PAGADO (la misma regla que el
 * contexto de cuenta): un pedido a medio pagar, aún en su retención, no es «tu próxima reserva» —vuelve por la
 * compra, con «Sigue con tu reserva»—.
 *
 * @param {{proximas: object[]|null, pasadas: object[]}} datos  las tarjetas tal cual (`{reservation, order}`)
 */
export function reservasDeCuenta({ proximas, pasadas = [] }) {
    const vivas = (proximas ?? []).filter((c) => c?.order?.status === PAGADO);

    return { proxima: vivas[0] ?? null, otras: vivas.slice(1), historial: pasadas };
}

/** «Kids 1 hora · 2 niños»: qué y cuántos, las dos cosas ya compuestas por el servidor. */
export const tituloDe = (r) => [r?.product_name, r?.quantity_label].filter(Boolean).join(' · ');

/** La hora de inicio («17:00»). */
const horaDe = (r) => horaCorta(r?.start_time) ?? horaCorta(String(r?.time_window ?? '').split(/[–-]/)[0].trim()) ?? '';

/**
 * Lo que `BookingCard` necesita de una tarjeta: la hoja del día, la hora, qué, el número y su nombre accesible.
 *
 * ⚠️ El nombre accesible lleva el día escrito entero (la hoja es `aria-hidden`) Y QUÉ: en la fila, que es un botón, ese
 * nombre SUSTITUYE al contenido, y el del diseño («Sábado 3 de octubre a las 11:30») dejaba al lector de pantalla sin
 * saber qué se reservó (y sin cumplir WCAG 2.5.3, el nombre contiene lo que se lee).
 *
 * @param {{reservation: object, order: object}} card
 */
export function tarjetaDe(card, { locale = 'es', textos = {} } = {}) {
    const r = card.reservation;
    const hora = horaDe(r);
    const fecha = r.date ? tp(textos, 'mi_cuenta.proxima.aria', { dia: diaLargo(r.date, locale), hora }) : '';

    return {
        day: r.date ? hojaDelDia(r.date, locale) : { dow: '', n: '', month: '' },
        time: hora,
        title: tituloDe(r),
        code: tp(textos, 'mi_cuenta.proxima.numero', { code: card.order?.code ?? '' }),
        aria: [fecha, tituloDe(r)].filter(Boolean).join(', '),
    };
}

/** «el viernes 25 a las 17:00», de un instante del servidor en la zona del parque. */
function plazoEscrito(until, locale) {
    return { dia: diaDelPlazo(until, locale), hora: String(until).slice(11, 16) };
}

/** Pasado el plazo: «Quedan menos de 24 h: ya no se puede…»; con un plazo de cero horas no hay tramo que nombrar. */
function fueraDePlazo(c, textos) {
    return c?.span ? tp(textos, 'mi_cuenta.proxima.fuera', { tramo: c.span }) : texto(textos, 'mi_cuenta.proxima.fuera_sin_tramo');
}

/**
 * Las líneas bajo la tarjeta, en el orden del diseño: la señal pagada y lo del día de la fiesta (si procede, lo dice
 * el servidor), lo que se compró con ella (el AVISO del complemento que escribió el panel o, sin él, su nombre y su
 * cantidad, `#775`) y el plazo: dentro, hasta cuándo; fuera, qué queda. Sin plazo publicado, nada del plazo.
 *
 * @returns {{icon: string, texto: string, fuerte?: boolean}[]}
 */
export function lineasDe(card, { locale = 'es', textos = {} } = {}) {
    const r = card.reservation;
    const l = r.ledger ?? {};
    const lineas = [];

    if (r.shows_deposit_note) {
        lineas.push({ icon: 'wallet', fuerte: true, texto: tp(textos, 'mi_cuenta.proxima.senal', { importe: euros(l.paid_cents ?? 0, locale) }) });
        lineas.push({ icon: 'store', texto: tp(textos, 'mi_cuenta.proxima.resto', { importe: euros(Math.abs(Number(l.balance?.cents ?? 0)), locale) }) });
    }

    for (const a of r.addons ?? []) {
        lineas.push({ icon: 'package', texto: a.note || tp(textos, 'mi_cuenta.proxima.complemento', { nombre: a.product_name, cantidad: a.quantity_label }) });
    }

    const c = r.cancellation;

    if (c && c.open && c.until) lineas.push({ icon: 'calendar-clock', texto: tp(textos, 'mi_cuenta.proxima.plazo', plazoEscrito(c.until, locale)) });
    else if (c && ! c.open) lineas.push({ icon: 'clock-alert', texto: fueraDePlazo(c, textos) });

    return lineas;
}

/**
 * «Ver el pago» (`PriceSummary`): las líneas de lo reservado con lo que se cobró por cada una, el TOTAL del libro y,
 * con señal, «Señal pagada» y «El día de la fiesta». ⚠️ Dos defensas de la doctrina del libro (`DECISIONES #132`):
 *   · si el libro NO CIERRA (`is_consistent: false`), ninguna línea es cierta: solo el total y la frase del servidor;
 *   · si las líneas no suman el total (una cortesía, un cambio de invitados), cuentan la historia los MOVIMIENTOS del
 *     libro, con sus rótulos del servidor: nunca un recibo cuyas cifras no cuadran.
 */
export function pagoDe(card, { locale = 'es', textos = {} } = {}) {
    const r = card.reservation;
    const l = r.ledger ?? {};
    const total = euros(l.total_cents ?? 0, locale);
    const base = { total, totalLabel: texto(textos, 'mi_cuenta.proxima.total'), now: null, later: null, note: '' };

    if (l.is_consistent === false) return { ...base, lines: [], note: l.note ?? '' };

    const filas = [{ label: tituloDe(r), cents: Number(r.charged_subtotal_cents ?? 0) }]
        .concat((r.addons ?? []).map((a) => ({ label: `${a.product_name} · ${a.quantity_label}`, cents: Number(a.charged_subtotal_cents ?? 0) })));
    const cuadran = filas.reduce((s, f) => s + f.cents, 0) === Number(l.total_cents ?? 0);
    const lines = cuadran
        ? filas.map((f) => ({ label: f.label, value: f.cents === 0 ? texto(textos, 'mi_cuenta.proxima.incluido') : euros(f.cents, locale) }))
        : (l.movements ?? []).map((m) => ({ label: m.label, value: (Number(m.amount_cents) < 0 ? '−' : '') + euros(Math.abs(Number(m.amount_cents)), locale) }));

    if (! r.shows_deposit_note) return { ...base, lines };

    return {
        ...base, lines,
        now: { label: texto(textos, 'mi_cuenta.proxima.senal_rotulo'), value: euros(l.paid_cents ?? 0, locale) },
        later: { label: texto(textos, 'mi_cuenta.proxima.resto_rotulo'), value: euros(Math.abs(Number(l.balance?.cents ?? 0)), locale) },
    };
}

/** El estado de una fila del historial: cancelada, devuelta, sin pagar o, si no, pasada. */
export function estadoDe(card) {
    const r = card.reservation ?? {};
    const pedido = card.order?.status;

    if (r.cancelled || pedido === 'cancelled') return 'cancelada';
    if (pedido === 'refunded') return 'devuelta';
    if (pedido === 'expired' || pedido === 'pending') return 'sin_pagar';

    return 'pasada';
}

/** Una fila del historial (`BookingCard` `row`), con su estado escrito (también en su nombre accesible: se tacha). */
export function filaHistorial(card, deps = {}) {
    const tarjeta = tarjetaDe(card, deps);
    const status = texto(deps.textos ?? {}, `mi_cuenta.otras.estado.${estadoDe(card)}`);

    return { ...tarjeta, status, aria: `${tarjeta.aria}, ${status}` };
}

/** Los dígitos de un teléfono internacional, para `wa.me` y `tel:` («+34 641 99 57 14» → «34641995714»). */
const digitos = (telefono) => String(telefono ?? '').replace(/\D/g, '');

/**
 * «Cambiar o cancelar» (`PmcCambiar`): lo que se puede hacer (dentro del plazo, hasta cuándo y si se devuelve la señal
 * —solo si el producto lo promete, `#775`—; fuera, qué queda), el MENSAJE ya escrito y enseñado, y a dónde se manda:
 * WhatsApp con el teléfono del parque (`/site`), y «Llamar». Sin teléfono, ni WhatsApp ni llamada.
 */
export function cambiarDe(card, { locale = 'es', textos = {}, telefono = '' } = {}) {
    const r = card.reservation;
    const c = r.cancellation;
    const hora = horaDe(r);
    const dia = r.date ? diaDelPlazo(r.date, locale) : '';
    const comun = { dia, hora, que: [r.product_name, r.quantity_label].filter(Boolean).join(', '), code: card.order?.code ?? '' };
    const plazo = c ? `${c.written}${c.deposit_refundable ? texto(textos, 'mi_cuenta.cambiar.devolvemos') : ''}` : '';
    const mensaje = tp(textos, 'mi_cuenta.cambiar.mensaje', { code: comun.code, dia, hora });
    const numero = digitos(telefono);

    return {
        fuera: Boolean(c && ! c.open),
        texto: c && c.open ? tp(textos, 'mi_cuenta.cambiar.texto', { ...comun, plazo })
            : c ? fueraDePlazo(c, textos)
                : tp(textos, 'mi_cuenta.cambiar.texto_sin_plazo', comun),
        mensaje,
        whatsapp: numero ? `https://wa.me/${numero}?text=${encodeURIComponent(mensaje)}` : '',
        llamar: numero ? { href: `tel:+${numero}`, texto: tp(textos, 'mi_cuenta.cambiar.llamar', { telefono: String(telefono).trim() }) } : null,
    };
}
