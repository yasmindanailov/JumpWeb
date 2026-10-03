/**
 * **LA LÍNEA de la compra de la isla** (T3e·3 de `docs/specs/isla-y-landing-nueva.md` §4.10, `DECISIONES #692`) y, desde la
 * L2 (`docs/specs/otra-zona.md`, `#878`), **LAS LÍNEAS**: la del pedido y las de la otra zona, con el mismo día y hora.
 *
 * ⚠️⚠️ **La cesta de la isla es SU pedido, y por eso meterlo la SUSTITUYE en vez de añadir.** La pantalla 0 dice el
 * pedido y «Pagar» lo cambia sin salir (gente, calcetines); no hay pantalla de cesta. Si se añadiera, lo que quedara de
 * antes —una cesta guardada de otra visita, o el pedido de un «Continuar» anterior tras volver atrás— se compraría con él
 * sin que el cliente lo viera en ningún sitio. Lo que decide si cabe sigue siendo del servidor (`POST /cart/validate-line`,
 * cada línea con las anteriores de contexto) y el precio, de su presupuesto (`PAY-12`).
 *
 * ⚠️ Es el `addToCart()` del motor (`usePurchaseFlow`) sin lo que la isla no pregunta ANTES de pagar: las demás
 * respuestas del pack —van al formulario de invitados, `#692`—, menores asignados y el justificante opcional (el diseño
 * los deja para después: la pista de la casilla y «Listo», `compra.datos.pista_quien` y `compra.listo.firmas`). La EDAD
 * de quien cumple sí viaja (elige el pack), y el justificante
 * OBLIGATORIO, como en el cajón. Y no mueve la máquina: quien llama sabe si es la primera vez
 * («Continuar», `→ CART`) o un cambio en «Pagar», que se queda donde está.
 */
import { addLine } from '../../sidebar/cart.js';
import { lineProblems } from '../../sidebar/line-problems.js';
import { t, tp } from '../../sidebar/i18n.js';

/**
 * Las líneas de la OTRA ZONA del pedido (L2, `otra-zona.md` §4.1): sin la fila del pedido y sin repetir fila —la misma fila
 * no es otra línea, es más gente: se FUNDE, como hace el servidor (`merges_with_index`)— y con lo que cada una pide (su
 * gente, si su justificante es obligatorio, su mínimo, lo que cabía a esa hora y, desde la K2·b de `#882`, sus complementos
 * y lo elegido en sus grupos: al fundirse, los de la primera). Devuelve también lo que suma a la del pedido.
 */
function otrasDe(fila, otras) {
    const porFila = new Map();
    let aLaPrimera = 0;

    for (const o of Array.isArray(otras) ? otras : []) {
        const n = Number(o?.n) || 0;

        if (! Number.isInteger(o?.fila) || n < 1) continue;
        if (o.fila === fila) { aLaPrimera += n; continue; }
        const ya = porFila.get(o.fila);

        porFila.set(o.fila, ya
            ? { ...ya, n: ya.n + n }
            : {
                fila: o.fila, n, guardian: o.guardian === true || o.guardian === 'required', minimo: o.minimo ?? 1, maximo: o.maximo ?? null,
                extras: (Array.isArray(o.extras) ? o.extras : []).map((x) => ({ product_id: x.product_id, quantity: x.quantity })),
                elecciones: (Array.isArray(o.elecciones) ? o.elecciones : []).map((c) => ({ group: c.group, product_id: c.product_id })),
            });
    }

    return { otras: [...porFila.values()], aLaPrimera };
}

/**
 * Lo que la isla recuerda del pedido al salir de la pantalla 0: el borrador y lo que en ese momento decían los datos
 * (el mínimo, lo que cabe a esa hora, el complemento por cantidad y el justificante), que el motor olvida al añadir.
 * De una FIESTA (T3e·5), además, su respuesta de reserva —la edad de quien cumple, en la clave de su campo—, su
 * elección de grupo —el menú— y lo que la alarga (`extras`, la hora extra de la calculadora de la página, T6b·3): el
 * recibo los necesita para rehacer la línea sin perderlos.
 * ▶ Y las de la OTRA ZONA (`otras`, la L2 de `#876`, `otra-zona.md`): con el MISMO día y la MISMA hora que la suya (D1-A,
 * `#878`); los calcetines y lo demás que se pregunta, en la primera.
 */
export function pedidoDe(borrador, { minimo = 1, maximo = null, calcetin = null, guardian = 'none', evento = {}, elecciones = [], extras = [], otras = [] } = {}) {
    const deLaOtraZona = otrasDe(borrador.fila, otras);

    return {
        // Si es una FIESTA (sus invitados son «niños» y el resto se paga «el día de la fiesta»): un pack sin edad —una
        // excursión, T6c·3— no lo es, y el recibo lo dice con sus palabras (`recibo.js`).
        fiesta: borrador.fiesta === true,
        fila: borrador.fila,
        dia: borrador.dia,
        hora: borrador.hora,
        n: deLaOtraZona.aLaPrimera > 0 ? borrador.n + deLaOtraZona.aLaPrimera : borrador.n,
        cal: calcetin ? borrador.cal : 0,
        minimo,
        maximo,
        calcetin: calcetin ? { id: calcetin.id, price_cents: calcetin.price_cents, max_quantity: calcetin.max_quantity ?? null } : null,
        guardian: guardian === 'required',
        evento: { ...evento },
        elecciones: [...elecciones],
        extras: extras.map((x) => ({ product_id: x.product_id, quantity: x.quantity })),
        otras: deLaOtraZona.otras,
    };
}

/**
 * Los complementos que se PIDEN: el de por cantidad (los calcetines), con los pares del pedido, y los de la fiesta que la
 * alargan (`extras`). ⚠️ Sin ellos, cambiar los niños en «Pagar» rehacía la línea SIN la hora extra elegida.
 */
export const complementosDe = (p) => [
    ...(p.calcetin && p.cal > 0 ? [{ product_id: p.calcetin.id, quantity: p.cal }] : []),
    ...(Array.isArray(p.extras) ? p.extras : []),
];

/** La línea candidata, con los complementos que RESOLVIÓ el servidor (`selection`), no los pedidos. */
export function lineaDe(p, resueltos) {
    return {
        product_id: p.fila,
        date: p.dia,
        time: p.hora,
        quantity: p.n,
        event_data: { ...(p.evento ?? {}) },
        addons: Array.isArray(resueltos) ? resueltos : [],
        dependent_ids: [],
        guardian_authorization: p.guardian === true,
    };
}

/**
 * Las LÍNEAS candidatas del pedido, en su orden (L2, `otra-zona.md` §4.1): la suya, con los complementos que resolvió el
 * servidor, y detrás las de la otra zona, con el MISMO día y la MISMA hora (D1-A, `#878`) y los complementos que el servidor
 * resolvió para cada una (lo obligatorio o incluido y, desde `#882`, lo elegido en su tarjeta que quepa a esa hora).
 */
export function lineasDe(p, resueltos, resueltosOtras = {}) {
    return [
        lineaDe(p, resueltos),
        ...(Array.isArray(p.otras) ? p.otras : []).map((o) => ({
            product_id: o.fila,
            date: p.dia,
            time: p.hora,
            quantity: o.n,
            event_data: {},
            addons: Array.isArray(resueltosOtras?.[o.fila]) ? resueltosOtras[o.fila] : [],
            dependent_ids: [],
            guardian_authorization: o.guardian === true,
        })),
    ];
}

/**
 * Lo que el servidor RESUELVE de cada línea de la otra zona (`POST /catalog/products/{id}/addons`, con su día, su hora, su
 * gente y lo elegido en su tarjeta —sus complementos y sus grupos, `#882`—), EN PARALELO: lo que la línea lleva
 * (`resueltos`: su `selection`, lo obligatorio o incluido y lo pedido que cabe) y lo que su tarjeta pinta a esa hora
 * (`ofertas`: sus `singles` y sus `groups`, con su precio y si caben). Si alguna no contesta, `null`: no se mete un pedido a
 * medias. Sin otras, vacíos y ninguna petición.
 *
 * @returns {Promise<{resueltos: Record<number, Array<{product_id: number, quantity: number}>>, ofertas: Record<number, {singles: Array, groups: Array}>}|null>}
 */
export async function resolverOtrasConOferta({ api, pedido }) {
    const otras = Array.isArray(pedido?.otras) ? pedido.otras : [];
    const respuestas = await Promise.all(otras.map((o) => api.post(`/catalog/products/${o.fila}/addons`, {
        quantity: o.n, date: pedido.dia, time: pedido.hora, addons: o.extras ?? [], choices: o.elecciones ?? [],
    })));

    if (respuestas.some((r) => ! r?.ok)) return null;

    return {
        resueltos: Object.fromEntries(otras.map((o, i) => [o.fila, respuestas[i].data?.selection ?? []])),
        ofertas: Object.fromEntries(otras.map((o, i) => [o.fila, { singles: respuestas[i].data?.singles ?? [], groups: respuestas[i].data?.groups ?? [] }])),
    };
}

/**
 * Lo que LLEVA cada línea de la otra zona (`resolverOtrasConOferta` sin su oferta): `{ [fila]: selection }`, o `null`.
 *
 * @returns {Promise<Record<number, Array<{product_id: number, quantity: number}>>|null>}
 */
export async function resolverOtras({ api, pedido }) {
    const r = await resolverOtrasConOferta({ api, pedido });

    return r === null ? null : r.resueltos;
}

/** Si el «no» del servidor a una línea es la HORA: completa o que ya no se ofrece (`CartLineProblem`). */
export function esHoraLlena(problemas) {
    return (Array.isArray(problemas) ? problemas : []).some((p) => p?.reason === 'sold_out' || p?.reason === 'time_unavailable');
}

/**
 * Mete las líneas del pedido como la cesta ENTERA de la isla, en su orden, y la guarda y pide su presupuesto.
 *
 * ⚠️ Cada una se valida con las ANTERIORES de contexto (`items`) y ella FUERA (`cart.js::validateLine`): dentro competiría
 * consigo misma y el tope saldría menor (`otra-zona.md` §0, trampa 2). La primera, con la cesta vacía: sustituye lo que
 * hubiera, que no compite con lo que va a reemplazar.
 * ⚠️ TODO O NADA: si una dice que no, la cesta vuelve a lo que era y se devuelve el aviso ya traducido (el del cajón:
 * `line-problems.js`), con la fila que no cupo (`fila`), para nombrar su zona. `horaLlena`: el «no» es la HORA —completa
 * (`sold_out`) o que ya no se ofrece (`time_unavailable`)—, y la compra puede proponer las cercanas (`#822`, §4.16).
 *
 * @param {{api: object, lineas: Array<object>, cartStore: object, messages?: object}} deps
 * @returns {Promise<{ok: boolean, aviso: string, horaLlena?: boolean, fila?: number}>}
 */
export async function meterLineas({ api, lineas, cartStore, messages = {} }) {
    const previas = cartStore.lines;
    let cesta = [];

    for (const linea of lineas) {
        cartStore.setLines(cesta);
        const respuesta = await cartStore.validateLine({ api, line: linea });

        if (! respuesta?.ok || respuesta.data?.valid !== true) {
            cartStore.setLines(previas);
            const problemas = respuesta?.ok ? respuesta.data?.problems ?? [] : [];
            const aviso = respuesta?.ok ? lineProblems(problemas, [], messages).error : '';

            return { ok: false, aviso: aviso || t(messages, 'errors.choose_one'), horaLlena: esHoraLlena(problemas), fila: linea.product_id };
        }
        cesta = addLine(cesta, linea, respuesta.data);
    }

    cartStore.setLines(cesta);
    cartStore.persist();
    await cartStore.refreshQuote({ api });

    return { ok: true, aviso: '' };
}

/**
 * El PEDIDO como la cesta entera de la isla (`meterLineas`): la suya y, si las hay, las de la otra zona con lo que el
 * servidor resolvió para cada una (`resolverOtras`).
 *
 * @param {{api: object, pedido: object, resueltos: Array, resueltosOtras?: object, cartStore: object, messages?: object}} deps
 */
export function meterLinea({ api, pedido, resueltos, resueltosOtras = {}, cartStore, messages = {} }) {
    return meterLineas({ api, lineas: lineasDe(pedido, resueltos, resueltosOtras), cartStore, messages });
}

/**
 * El «no» del servidor a una línea del pedido (`meterLineas`): el de la suya, tal cual; el de una línea AÑADIDA, con el nombre
 * de su zona —«En JUMP ya no queda sitio a esa hora…» si es la hora, o el aviso del motor detrás de su zona— (K2 de
 * `otra-zona.md`; desde la K3 también al cambiarla en «Pagar» o al añadirla con «Añadir otra entrada»).
 */
export function avisoDeLinea(r, pedido, { productos = [], textos = {} } = {}) {
    if (! r?.fila || r.fila === pedido?.fila) return r?.aviso ?? '';
    const zona = (Array.isArray(productos) ? productos : []).find((p) => p.id === r.fila)?.zone?.name ?? '';

    return r.horaLlena ? tp(textos, 'compra.cuando.otra_llena', { zona }) : tp(textos, 'compra.cuando.aviso_otra', { zona, aviso: r.aviso });
}

/**
 * Las líneas añadidas del PEDIDO, como las lleva el BORRADOR de la pantalla 0 (K3 de `otra-zona.md` §4.3): tras cambiarlas en
 * «Pagar», volver atrás enseña sus tarjetas tal cual (su fila, su gente, sus complementos y, por grupo, lo elegido).
 */
export const borradorDeOtras = (otras) => (Array.isArray(otras) ? otras : []).map((o) => ({
    fila: o.fila,
    n: o.n,
    extras: (o.extras ?? []).map((x) => ({ product_id: x.product_id, quantity: x.quantity })),
    elecciones: Object.fromEntries((o.elecciones ?? []).map((c) => [c.group, String(c.product_id)])),
}));

/**
 * **«Añadir otra entrada»** (K3 de `otra-zona.md` §4.3; `continuarCuando` del mockup): el cambio del pedido con la línea
 * nueva. Su fila ya está —la del pedido o una añadida—: es más gente en ella (la misma fila no es otra línea, `otrasDe`);
 * si no, una línea más, al final, con su justificante y lo elegido en ella.
 *
 * @param {object} pedido  el de ahora
 * @param {{fila: number, n: number, extras?: Array, elecciones?: Array, guardian?: boolean|string}} nueva
 */
export function conNuevaLinea(pedido, nueva) {
    const otras = Array.isArray(pedido?.otras) ? pedido.otras : [];

    if (nueva.fila === pedido.fila) return { n: pedido.n + nueva.n };
    if (otras.some((o) => o.fila === nueva.fila)) return { otras: otras.map((o) => (o.fila === nueva.fila ? { ...o, n: o.n + nueva.n } : o)) };

    return { otras: [...otras, ...otrasDe(pedido.fila, [nueva]).otras] };
}

/**
 * El pedido con las cantidades que la CESTA tiene de verdad —las que admitió el servidor, que puede recortar—, leídas por
 * PRODUCTO y no por posición (`otra-zona.md` §4.1: con varias líneas, `lines[0]` ya no es «la del pedido»).
 */
export function conLaCesta(pedido, lines) {
    const cesta = Array.isArray(lines) ? lines : [];
    const cuantas = (fila, n) => cesta.find((l) => l.product_id === fila)?.quantity ?? n;

    return { ...pedido, n: cuantas(pedido.fila, pedido.n), otras: (pedido.otras ?? []).map((o) => ({ ...o, n: cuantas(o.fila, o.n) })) };
}
