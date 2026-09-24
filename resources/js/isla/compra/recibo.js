/**
 * **EL RECIBO Y LAS LÍNEAS de la compra de la isla, sin estado** (T3e·3 de `docs/specs/isla-y-landing-nueva.md`
 * §4.10, `DECISIONES #692`).
 *
 * ⚠️⚠️ **Aquí no se calcula dinero** (`PAY-12`): cada importe es del SERVIDOR —el presupuesto de la cesta
 * (`POST /orders/quote`) mientras se compra, y el pedido (`GET /orders/{code}`) después— y aquí solo se formatea.
 * Los precios unitarios de las pistas («8 € por entrada», «2 € el par») son los publicados, no un cociente.
 */
import { t as texto, tp as textoCon } from '../../sidebar/i18n.js';
import { diaCorto, diaLargo, euros, horaCorta } from './vista.js';

const unidad = (textos) => ({ uno: texto(textos, 'compra.cuando.entrada'), varios: texto(textos, 'compra.cuando.entradas') });
const pares = (textos) => ({ uno: texto(textos, 'compra.cuando.par'), varios: texto(textos, 'compra.cuando.pares') });
const cuantos = (n, u) => `${n} ${n === 1 ? u.uno : u.varios}`;

/**
 * La línea de la isla (`D.linea` del diseño): «Kids 1 hora · sáb 26, 17:00 · 2 entradas». De líneas con
 * `product_name`, `date`, `time` y `quantity`, que es la forma del presupuesto y la del resumen del pedido.
 */
export function resumenDe(lineas, { textos = {}, locale = 'es' } = {}) {
    const primera = Array.isArray(lineas) ? lineas[0] : null;

    if (! primera) return null;

    const u = unidad(textos);

    return [
        lineas.map((l) => l.product_name).join(' + '),
        `${diaCorto(primera.date, locale)}${primera.time ? `, ${horaCorta(primera.time)}` : ''}`,
        lineas.map((l) => cuantos(l.quantity, u)).join(' + '),
    ].join(' · ');
}

/**
 * El RECIBO de «Pagar» (`PjcPagar`): cada línea con su cantidad cambiable, el complemento por cantidad (los
 * calcetines) con la suya, el resto de complementos que el servidor resolvió, y el total. Sin calcetines, la línea
 * que los ofrece. El `id` de cada fila dice qué se cambia: `l{index}` la gente, `a{index}-{producto}` los pares.
 *
 * ⚠️ Las cantidades que se ven son las del PEDIDO (cambian al pulsar, como en la pantalla 0) y los importes, los del
 * presupuesto (llegan cuando el servidor responde). Solo la línea del pedido se cambia aquí: es la única de la cesta
 * de la isla (`linea.js`), y cualquier otra se pintaría sin control.
 *
 * @param {{quote: object|null, pedido: object|null, textos?: object, locale?: string}} e
 */
export function reciboDe({ quote, pedido, textos = {}, locale = 'es' }) {
    const u = unidad(textos);
    const p = pares(textos);
    const calcetin = pedido?.calcetin ?? null;
    const lineas = [];
    let conCalcetines = false;

    for (const l of quote?.lines ?? []) {
        const delPedido = pedido !== null && pedido !== undefined && l.product_id === pedido.fila;

        lineas.push({
            id: `l${l.index}`,
            label: l.product_name,
            sub: l.unit_price_cents === null ? '' : textoCon(textos, 'compra.pagar.precio_por', { precio: euros(l.unit_price_cents, locale), unidad: u.uno }),
            value: euros(l.subtotal_cents, locale),
            control: delPedido ? { n: pedido.n, min: pedido.minimo ?? 1, max: Math.max(pedido.maximo ?? pedido.n, pedido.n), ...u } : null,
        });

        for (const a of l.addons ?? []) {
            if (Number(a.quantity) < 1) continue;
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

    return {
        lineas,
        total: euros(quote?.total_cents ?? 0, locale),
        nota: '',
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
    const u = unidad(textos);

    return [
        primera?.date ? diaLargo(primera.date, locale) : '',
        primera?.time ? horaCorta(primera.time) : '',
        ...lineas.map((l) => `${l.product_name} · ${cuantos(l.quantity, u)}`),
        textoCon(textos, 'compra.listo.pedido', { codigo: confirmacion?.code ?? '' }),
    ].filter(Boolean).join(' · ');
}

/** Lo que la isla enseña siempre debajo (la línea, el total) de un pedido que ya existe: el banco y los desenlaces. */
export function resumenDelPedido(confirmacion, { textos = {}, locale = 'es' } = {}) {
    if (! confirmacion) return { summary: null, total: null };

    return { summary: resumenDe(confirmacion.lines, { textos, locale }), total: euros(confirmacion.total_cents, locale) };
}
