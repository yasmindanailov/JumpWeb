/**
 * **EL RECIBO Y LAS LÍNEAS de la compra de la isla, sin estado** (T3e·3 de `docs/specs/isla-y-landing-nueva.md`
 * §4.10, `DECISIONES #692`; las fiestas, T3e·5, `#696`).
 *
 * ⚠️⚠️ **Aquí no se calcula dinero** (`PAY-12`): cada importe es del SERVIDOR —el presupuesto de la cesta
 * (`POST /orders/quote`) mientras se compra, y el pedido (`GET /orders/{code}`) después— y aquí solo se formatea.
 * Los precios unitarios de las pistas («8 € por entrada», «2 € el par») son los publicados, no un cociente. Y lo que
 * queda por pagar en el parque es el que publica cada línea (`gate_remainder_cents`), no una resta del total.
 */
import { t as texto, tp as textoCon } from '../../sidebar/i18n.js';
import { diaCorto, diaLargo, euros, horaCorta } from './vista.js';

/** La unidad de una línea: invitados de un PACK («niños») o ENTRADAS. `is_pack` lo dice el servidor. */
const unidad = (textos, esPack = false) => (esPack
    ? { uno: texto(textos, 'compra.cuando.nino'), varios: texto(textos, 'compra.cuando.ninos') }
    : { uno: texto(textos, 'compra.cuando.entrada'), varios: texto(textos, 'compra.cuando.entradas') });
const pares = (textos) => ({ uno: texto(textos, 'compra.cuando.par'), varios: texto(textos, 'compra.cuando.pares') });
const cuantos = (n, u) => `${n} ${n === 1 ? u.uno : u.varios}`;

/**
 * La línea de la isla (`D.linea` del diseño): «Kids 1 hora · sáb 26, 17:00 · 2 entradas» · «Pack Kids · sáb 26,
 * 17:00 · 10 niños». De líneas con `product_name`, `date`, `time`, `quantity` e `is_pack`, que es la forma del
 * presupuesto y la del resumen del pedido.
 */
export function resumenDe(lineas, { textos = {}, locale = 'es' } = {}) {
    const primera = Array.isArray(lineas) ? lineas[0] : null;

    if (! primera) return null;

    return [
        lineas.map((l) => l.product_name).join(' + '),
        `${diaCorto(primera.date, locale)}${primera.time ? `, ${horaCorta(primera.time)}` : ''}`,
        lineas.map((l) => cuantos(l.quantity, unidad(textos, l.is_pack === true))).join(' + '),
    ].join(' · ');
}

/** «Hoy pagas 50 €» cuando la pasarela cobra MENOS que el total (una señal); si no, nada. */
export function hoyPagas(online, total, { textos = {}, locale = 'es' } = {}) {
    return Number.isInteger(online) && Number.isInteger(total) && online < total
        ? textoCon(textos, 'compra.pagar.hoy_pagas', { importe: euros(online, locale) })
        : null;
}

/** Lo de debajo mientras se compra: la línea, el total y la señal de la cesta presupuestada. */
export function resumenDeLaCesta(quote, { textos = {}, locale = 'es' } = {}) {
    if (! quote?.lines?.length) return { summary: null, total: null, today: null };

    return {
        summary: resumenDe(quote.lines, { textos, locale }),
        total: euros(quote.total_cents, locale),
        today: hoyPagas(quote.online_amount_cents, quote.total_cents, { textos, locale }),
    };
}

/**
 * El RECIBO de «Pagar» (`PjcPagar`): cada línea con su cantidad cambiable, el complemento por cantidad (los
 * calcetines) con la suya, el resto de complementos que el servidor resolvió, y el total. Sin calcetines, la línea
 * que los ofrece. El `id` de cada fila dice qué se cambia: `l{index}` la gente, `a{index}-{producto}` los pares.
 *
 * De una FIESTA (T3e·5): el menú elegido va en el rótulo del pack («Pack Kids · Menú 1») si no cuesta nada, y en su
 * propia fila si cuesta (su importe es del servidor: sumarlo al precio por niño sería componer dinero); debajo, la
 * señal y lo que queda para el parque.
 *
 * ⚠️ Las cantidades que se ven son las del PEDIDO (cambian al pulsar, como en la pantalla 0) y los importes, los del
 * presupuesto (llegan cuando el servidor responde). Solo la línea del pedido se cambia aquí: es la única de la cesta
 * de la isla (`linea.js`), y cualquier otra se pintaría sin control.
 *
 * @param {{quote: object|null, pedido: object|null, textos?: object, locale?: string}} e
 */
export function reciboDe({ quote, pedido, textos = {}, locale = 'es' }) {
    const p = pares(textos);
    const calcetin = pedido?.calcetin ?? null;
    const elegidos = new Set((pedido?.elecciones ?? []).map((x) => Number(x.product_id)));
    const lineas = [];
    let conCalcetines = false;

    for (const l of quote?.lines ?? []) {
        const delPedido = pedido !== null && pedido !== undefined && l.product_id === pedido.fila;
        const u = unidad(textos, l.is_pack === true);
        const menu = (l.addons ?? []).find((a) => elegidos.has(Number(a.product_id))) ?? null;
        const menuEnElRotulo = menu !== null && Number(menu.subtotal_cents) === 0;

        lineas.push({
            id: `l${l.index}`,
            label: menuEnElRotulo ? `${l.product_name} · ${menu.product_name}` : l.product_name,
            sub: l.unit_price_cents === null ? '' : textoCon(textos, 'compra.pagar.precio_por', { precio: euros(l.unit_price_cents, locale), unidad: u.uno }),
            value: euros(l.subtotal_cents, locale),
            control: delPedido ? { n: pedido.n, min: pedido.minimo ?? 1, max: Math.max(pedido.maximo ?? pedido.n, pedido.n), ...u } : null,
        });

        for (const a of l.addons ?? []) {
            if (Number(a.quantity) < 1 || (menuEnElRotulo && a === menu)) continue;
            const esCalcetin = delPedido && calcetin !== null && a.product_id === calcetin.id;

            conCalcetines ||= esCalcetin;
            lineas.push({
                id: `a${l.index}-${a.product_id}`,
                label: a.product_name,
                sub: esCalcetin ? textoCon(textos, 'compra.pagar.precio_el', { precio: euros(calcetin.price_cents, locale), unidad: p.uno }) : '',
                value: euros(a.subtotal_cents, locale),
                control: esCalcetin ? { n: pedido.cal, min: 0, max: calcetin.max_quantity ?? 40, ...p } : null,
            });
        }
    }

    const online = quote?.online_amount_cents;
    const resto = (quote?.lines ?? []).reduce((suma, l) => suma + Number(l.gate_remainder_cents ?? 0), 0);

    return {
        lineas,
        total: euros(quote?.total_cents ?? 0, locale),
        nota: hoyPagas(online, quote?.total_cents, { textos, locale })
            ? textoCon(textos, 'compra.pagar.senal', { senal: euros(online, locale), resto: euros(resto, locale) })
            : '',
        otraEntrada: false,
        calcetines: calcetin && ! conCalcetines ? {
            texto: `${texto(textos, 'compra.cuando.pregunta_calcetines')} ${textoCon(textos, 'compra.cuando.pista_calcetines', { precio: euros(calcetin.price_cents, locale) })}`,
            ...p,
        } : null,
    };
}

/** Qué cambia una fila del recibo: la gente (`{ n }`) o los pares (`{ cal }`). */
export function cambioDe(id, n) {
    return String(id).startsWith('l') ? { n } : { cal: n };
}

/**
 * La línea de «Listo» (`lineaListo` del diseño): «Sábado 26 de septiembre · 17:00 · Kids 1 hora · 2 entradas · Nº de
 * pedido R-7K2P4», del resumen del pedido pagado (`outcome.js::buildConfirmation`).
 */
export function lineaListo(confirmacion, { textos = {}, locale = 'es' } = {}) {
    const lineas = confirmacion?.lines ?? [];
    const primera = lineas[0] ?? null;

    return [
        primera?.date ? diaLargo(primera.date, locale) : '',
        primera?.time ? horaCorta(primera.time) : '',
        ...lineas.map((l) => `${l.product_name} · ${cuantos(l.quantity, unidad(textos, l.is_pack === true))}`),
        textoCon(textos, 'compra.listo.pedido', { codigo: confirmacion?.code ?? '' }),
    ].filter(Boolean).join(' · ');
}

/** Lo que la isla enseña siempre debajo (la línea, el total, la señal) de un pedido que ya existe: el banco y los desenlaces. */
export function resumenDelPedido(confirmacion, { textos = {}, locale = 'es' } = {}) {
    if (! confirmacion) return { summary: null, total: null, today: null };

    return {
        summary: resumenDe(confirmacion.lines, { textos, locale }),
        total: euros(confirmacion.total_cents, locale),
        today: hoyPagas(confirmacion.online_cents, confirmacion.total_cents, { textos, locale }),
    };
}
