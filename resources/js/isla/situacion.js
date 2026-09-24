/**
 * QUÉ DICE LA ISLA — la tabla de prioridades del diseño, portada regla a regla (`isla-y-landing-nueva.md` §4.9).
 *
 * Es la función `resolveSituation` de `ParkIsland.jsx` (la referencia, `instancias/playjump/diseno/`), con UNA
 * diferencia: los textos no van escritos aquí, llegan del grupo `isla` de `lang/` (`t()` / `tp()`), porque la
 * isla es del producto y habla tres idiomas. En español dicen lo mismo, letra a letra: la isla se juzga contra
 * el diseño píxel a píxel (`scripts/pixel.mjs`).
 *
 * Se lee de arriba abajo y gana la primera fila con datos: añadir una situación es añadir una fila, nunca tocar
 * el pintado. `action: null` = esta situación no lleva botón; si una fila no habla de la acción, hereda la de
 * la página.
 */
import { t, tp } from '../sidebar/i18n.js';

/** «Hoy» (situación 3): la frase y, si quedan huecos, «Reservar para hoy». */
function leerHoy(p, act, m) {
    const hoy = p.today;
    const antes = hoy.state === 'antes';
    const abierto = hoy.state === 'abierto';
    const line = antes
        ? tp(m, hoy.slots ? 'hoy.antes_con_huecos' : 'hoy.antes', { hora: hoy.opensAt })
        : abierto
            ? tp(m, hoy.slots ? 'hoy.abierto_con_huecos' : 'hoy.abierto', { hora: hoy.closesAt })
            : hoy.state === 'completo'
                ? t(m, 'hoy.completo')
                : tp(m, 'hoy.cerrado', { hora: hoy.opensAt });
    const paraHoy = Boolean(hoy.slots) && (antes || abierto);

    // Con la acción cedida (act = null: hay un primario de la página a la vista), «hoy» no puede resucitarla.
    return { line, tone: paraHoy ? 'live' : 'neutral', action: paraHoy && act ? { ...act, label: t(m, 'accion.reservar_hoy') } : act };
}

/** Situación 13: la tarea sale en la portada y en la página del producto reservado. */
export const productoDe = (page) => page.product || (page.kind === 'cumpleanos' ? 'cumpleanos' : null);

const REGLAS = [
    { id: 'compra', when: (p) => p.checkout,
        read: (p) => ({ line: p.checkout.summary, action: p.checkout.action, tone: 'focus', locked: true }) },
    { id: 'reserva-hoy', when: (p) => p.bookingToday,
        read: (p, act, m) => ({ line: p.bookingToday.text, note: p.bookingToday.extra, tone: 'live',
            action: p.account && p.account.onQr
                ? { label: t(m, 'accion.ver_qr'), onClick: () => p.account.onQr({ from: 'isla' }) }
                : { label: t(m, 'accion.ver_qr'), panel: 'qr', onClick: p.bookingToday.onQr } }) },
    { id: 'pago-fallido', when: (p) => p.payment === 'failed',
        read: (p, act, m) => ({ line: p.paymentText || t(m, 'pago.no_cobrado'), tone: 'alert', locked: true,
            action: { label: t(m, 'accion.pagar_bizum'), onClick: p.onPayBizum },
            extra: { secondary: { label: t(m, 'accion.reintentar_tarjeta'), onClick: p.onRetry },
                manual: p.onManual ? { label: t(m, 'accion.manual'), onClick: p.onManual } : null,
                onDismiss: p.onDismiss || null } }) },
    { id: 'a-medias', when: (p) => p.resume,
        read: (p, act, m) => ({ line: p.resume.text, tone: 'alert', action: { label: t(m, 'accion.seguir'), onClick: p.resume.onClick } }) },
    { id: 'tarea', when: (p) => p.task && (p.page.kind === 'portada' || productoDe(p.page) === (p.task.product || 'cumpleanos')),
        read: (p) => ({ line: p.task.text, tone: 'alert', action: p.task.action }) },
    { id: 'elegido', when: (p) => p.chosen,
        read: (p, act, m) => ({ line: p.chosen.text, note: p.filling ? p.filling.text : null, noteTone: 'live',
            action: (p.chosen.widgetVisible || p.ctaVisible) ? null : { label: p.chosen.label || t(m, 'accion.pagar_senal'), onClick: p.chosen.onClick } }) },
    { id: 'calculado', when: (p) => p.quote,
        read: (p, act) => ({ line: p.quote.text, opens: 'resumen', action: act }) },
    { id: 'hoy', when: (p) => p.today, read: leerHoy },
    { id: 'oferta', when: (p) => p.offer, read: (p) => ({ line: p.offer }) },
    { id: 'miedo', when: (p) => p.reassurance, read: (p) => ({ line: p.reassurance }) },
];

/**
 * La situación que manda. `entrada` son las props de la isla; `m`, el grupo `isla` de `lang/`.
 *
 * Con un botón de la página en pantalla, la isla no repite la acción: cede. Solo cede la acción HEREDADA de la
 * página: las situaciones con acción propia y urgente (compra, pago fallido, reserva a medias, reserva de hoy,
 * tarea pendiente) mandan siempre, porque no compiten con vender: resuelven algo que ya pasó.
 */
export function resolverSituacion(entrada, m) {
    const p = { ...entrada, page: entrada.page || {} };
    const act = p.ctaVisible ? null : (p.page.action || { label: t(m, 'accion.reservar') });
    for (const regla of REGLAS) {
        if (!regla.when(p)) continue;
        return { id: regla.id, tone: 'neutral', action: act, ...(regla.read(p, act, m) || {}) };
    }
    return { id: 'desde', tone: 'neutral', line: p.page.from || '', action: act };
}

/**
 * Cómo se reparte la situación en la isla: dónde va la línea, si se encoge, si es fila o bloque. Son las
 * cuentas del render del diseño, sacadas aquí para que se puedan probar sin pintar.
 *
 * Dato duro (precio u hora): renglón propio a 14px, el brief exige que se lea a la primera. Contexto blando:
 * dentro del botón, una sola fila. Compacta = «línea en vez de botón», nunca «ni una ni otro».
 */
export function reparto({ s, top, compact, scrolledDown, isOpen, menuOpen, inCheckout, mobileContext, cookies, shownNotice }) {
    const hardData = /€|\d{1,2}:\d{2}/.test(s.line || '');
    const softFits = !hardData && (s.line || '').length <= 62 && !s.note;
    const inside = mobileContext === 'inside' || (mobileContext === 'auto' && softFits);
    const isCompact = (compact === true || (compact === 'auto' && scrolledDown)) && !isOpen && !s.locked && !cookies && !shownNotice && Boolean(s.action);
    const lineInButton = !top && !isCompact && mobileContext !== 'row' && inside && Boolean(s.line) && Boolean(s.action) && !menuOpen;
    const hasLine = Boolean(s.line) && !lineInButton && !isCompact && !inCheckout && !menuOpen;
    // Sin acción (la cede a un botón de la página a la vista) la isla se encoge a su contenido, también abajo.
    const yielded = !s.action && !isOpen && !cookies && !shownNotice && !inCheckout;
    const row = top || yielded;

    return { hardData, softFits, inside, isCompact, lineInButton, hasLine, yielded, row };
}
