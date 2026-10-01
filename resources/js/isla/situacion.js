/**
 * QUÉ DICE LA ISLA — la tabla de prioridades del diseño, portada regla a regla (`isla-y-landing-nueva.md` §4.9).
 *
 * Es la función `resolveSituation` de `ParkIsland.jsx` (la referencia, `instancias/playjump/diseno/`), con UNA
 * diferencia: los textos no van escritos aquí, llegan del grupo `isla` de `lang/` (`t()` / `tp()`), porque la
 * isla es del producto y habla tres idiomas. En español dicen lo mismo, letra a letra: la isla se juzga contra
 * el diseño píxel a píxel (`scripts/pixel.mjs`).
 *
 * Se lee de arriba abajo y gana la primera fila con datos: añadir una situación es añadir una fila, nunca tocar
 * el pintado. Si una fila no habla de la acción, hereda la de la página. La isla lleva siempre frase y acción
 * (Z6a, zip (6), «tres huecos» del 28-09): ninguna fila la quita.
 */
import { t, tp } from '../sidebar/i18n.js';

/** «Hoy» (situación 3): la frase y, si quedan huecos, «Reservar para hoy». */
function leerHoy(p, act, m) {
    const hoy = p.today;
    // Situación 15 · las páginas que no venden (`kind: 'apoyo'`: Visítanos, Normas): el horario de hoy, entero, y
    // la acción de la página. «Quedan huecos» y «Reservar para hoy» son de la situación 3: aquí el selector ya
    // ofrece «Para hoy» si hay huecos. Cerrado hoy, «Hoy abrimos…» no sería verdad y va la frase de cerrado.
    if (p.page.kind === 'apoyo') {
        if (hoy.state === 'cerrado') return { line: tp(m, 'hoy.cerrado', { hora: hoy.opensAt }), tone: 'neutral', action: act };
        return { line: tp(m, 'hoy.apoyo', { abre: hoy.opensAt, cierra: hoy.closesAt }),
            tone: hoy.slots && hoy.state !== 'completo' ? 'live' : 'neutral', action: act };
    }
    const antes = hoy.state === 'antes';
    const abierto = hoy.state === 'abierto';
    // [Hoy] vive en la isla desde la llegada (Z6a): las cabeceras que venden ya no lo dicen, así que antes de abrir, y
    // con la hora de cierre, la isla dice la frase entera que decía la cabecera.
    const line = antes
        ? (hoy.closesAt
            ? tp(m, hoy.slots ? 'hoy.antes_de_a_con_huecos' : 'hoy.antes_de_a', { abre: hoy.opensAt, cierra: hoy.closesAt })
            : tp(m, hoy.slots ? 'hoy.antes_con_huecos' : 'hoy.antes', { hora: hoy.opensAt }))
        : abierto
            ? tp(m, hoy.slots ? 'hoy.abierto_con_huecos' : 'hoy.abierto', { hora: hoy.closesAt })
            : hoy.state === 'completo'
                ? t(m, 'hoy.completo')
                : tp(m, 'hoy.cerrado', { hora: hoy.opensAt });
    const paraHoy = Boolean(hoy.slots) && (antes || abierto);

    return { line, tone: paraHoy ? 'live' : 'neutral', action: paraHoy ? { ...act, label: t(m, 'accion.reservar_hoy') } : act };
}

/*
 * Las situaciones 13 (tarea) y 14 (reserva hoy) no son filas desde la Z6a: no mandan en la barra. Ponen el punto en la
 * cuenta (naranja y lima, `useIsla.js`) y viven en Mi QR. En la acción solo mandan la compra y lo que la resuelve (11).
 */
const REGLAS = [
    { id: 'compra', when: (p) => p.checkout,
        read: (p) => ({ line: p.checkout.summary, action: p.checkout.action, tone: 'focus', locked: true }) },
    // Un proceso que sigue con la capa cerrada (Z6b·3, `waiting`): «Confirmando tu pago», y después «¡Reservado!». Es un
    // banner en el sitio de la acción (`bn`), sin acción: se toca y vuelve a abrir lo suyo.
    { id: 'espera', when: (p) => p.waiting,
        read: (p) => ({ line: null, bn: { type: 'espera', ...p.waiting }, locked: true, action: null }) },
    { id: 'pago-fallido', when: (p) => p.payment === 'failed',
        read: (p, act, m) => ({ line: p.paymentText || t(m, 'pago.no_cobrado'), tone: 'alert', locked: true,
            action: { label: t(m, 'accion.pagar_bizum'), onClick: p.onPayBizum },
            extra: { secondary: { label: t(m, 'accion.reintentar_tarjeta'), onClick: p.onRetry },
                manual: p.onManual ? { label: t(m, 'accion.manual'), onClick: p.onManual } : null,
                onDismiss: p.onDismiss || null } }) },
    // La reserva a medias urge (`urgent`): resuelve algo que ya pasó y no baja a secundaria con el botón de la página.
    { id: 'a-medias', when: (p) => p.resume,
        read: (p, act, m) => ({ line: p.resume.text, tone: 'alert', urgent: true, action: { label: t(m, 'accion.seguir'), onClick: p.resume.onClick } }) },
    // Con el botón del widget a la vista, en secundaria (`calm`): el brief pide no repetirlo, y la isla no se queda
    // nunca sin acción.
    { id: 'elegido', when: (p) => p.chosen,
        read: (p, act, m) => ({ line: p.chosen.text, note: p.filling ? p.filling.text : null, noteTone: 'live',
            calm: Boolean(p.chosen.widgetVisible || p.ctaVisible),
            action: { label: p.chosen.label || t(m, 'accion.pagar_senal'), onClick: p.chosen.onClick } }) },
    // Un cálculo con aviso (`quote.alert`) se dice en alerta y no abre el resumen.
    { id: 'calculado', when: (p) => p.quote,
        read: (p, act) => ({ line: p.quote.text, opens: p.quote.alert ? null : 'resumen', tone: p.quote.alert ? 'alert' : 'neutral', action: act }) },
    { id: 'hoy', when: (p) => p.today, read: leerHoy },
    { id: 'oferta', when: (p) => p.offer, read: (p) => ({ line: p.offer }) },
    // La frase que quita el miedo de la pieza que se lee (situación 5, `#866`): la página la declara por zona y llega aquí
    // ya REPARTIDA (`razon.js`: una por visita salvo las de decisión).
    { id: 'miedo', when: (p) => p.reassurance, read: (p) => ({ line: p.reassurance }) },
];

/**
 * **La razón en lugar del botón repetido** (Z6b, opción C «Da la razón»): con un botón de la página a la vista (`calm`),
 * la isla no lo repite en secundaria; pone un banner con el dato que quita la duda de esa pieza (`bn`). Lo bloqueado (la
 * compra, el pago fallido) y lo que ya tiene banner no la llevan; sin razón, secundaria como antes.
 */
function conRazon(s, p) {
    if (s.calm && p.reason && ! s.locked && ! s.bn) s.bn = { type: 'razon', ...p.reason };

    return s;
}

/**
 * La situación que manda. `entrada` son las props de la isla; `m`, el grupo `isla` de `lang/`.
 *
 * La acción no se va nunca (Z6a). Con un botón de la página en pantalla (`ctaVisible`), la de la isla baja a
 * secundaria (`calm`): mismo texto, mismo sitio, y un solo naranja por pantalla; y si la página da la razón de la pieza
 * (`reason`, Z6b), el banner de la razón ocupa su sitio. La compra y el pago fallido (`locked`) y la reserva a medias
 * (`urgent`) no bajan: resuelven algo que ya pasó.
 */
export function resolverSituacion(entrada, m) {
    const p = { ...entrada, page: entrada.page || {} };
    const act = p.page.action || { label: t(m, 'accion.reservar') };
    for (const regla of REGLAS) {
        if (!regla.when(p)) continue;
        const s = { id: regla.id, tone: 'neutral', action: act, ...(regla.read(p, act, m) || {}) };
        if (s.calm === undefined) s.calm = !s.locked && !s.urgent && Boolean(p.ctaVisible);
        return conRazon(s, p);
    }
    return conRazon({ id: 'desde', tone: 'neutral', line: p.page.from || '', action: act, calm: Boolean(p.ctaVisible) }, p);
}

/**
 * Cómo se reparte la situación en la isla, sacado aquí para probarlo sin pintar. La frase va SIEMPRE (Z6a): en móvil,
 * en su renglón encima de la fila (nunca dentro del botón); arriba, en la fila. Con el menú abierto, fuera: quien abre
 * el menú está navegando, y la frase de la sección de detrás ya no le habla. Ya no hay compacta ni isla cedida.
 * **Una sola voz** (Z6b): con el banner a la vista (la isla cerrada), no hay frase, tampoco arriba.
 */
export function reparto({ s, top, menuOpen, inCheckout, isOpen = false }) {
    const hasLine = Boolean(s.line) && !inCheckout && !menuOpen && !(s.bn && !isOpen);

    return { hasLine, row: top };
}
