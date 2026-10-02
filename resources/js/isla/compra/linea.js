/**
 * **LA LÍNEA de la compra de la isla** (T3e·3 de `docs/specs/isla-y-landing-nueva.md` §4.10, `DECISIONES #692`).
 *
 * ⚠️⚠️ **La cesta de la isla es SU pedido, y por eso meter la línea la SUSTITUYE en vez de añadirla.** La pantalla 0
 * dice una línea y «Pagar» la cambia sin salir (gente, calcetines); no hay pantalla de cesta. Si se añadiera, lo que
 * quedara de antes —una cesta guardada de otra visita, o la línea de un «Continuar» anterior tras volver atrás— se
 * compraría con ella sin que el cliente lo viera en ningún sitio: el recibo no tiene «quitar». Lo que decide si cabe
 * sigue siendo del servidor (`POST /cart/validate-line`, con la cesta vacía como contexto) y el precio, de su
 * presupuesto (`PAY-12`).
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
import { t } from '../../sidebar/i18n.js';

/**
 * Lo que la isla recuerda del pedido al salir de la pantalla 0: el borrador y lo que en ese momento decían los datos
 * (el mínimo, lo que cabe a esa hora, el complemento por cantidad y el justificante), que el motor olvida al añadir.
 * De una FIESTA (T3e·5), además, su respuesta de reserva —la edad de quien cumple, en la clave de su campo—, su
 * elección de grupo —el menú— y lo que la alarga (`extras`, la hora extra de la calculadora de la página, T6b·3): el
 * recibo los necesita para rehacer la línea sin perderlos.
 */
export function pedidoDe(borrador, { minimo = 1, maximo = null, calcetin = null, guardian = 'none', evento = {}, elecciones = [], extras = [] } = {}) {
    return {
        // Si es una FIESTA (sus invitados son «niños» y el resto se paga «el día de la fiesta»): un pack sin edad —una
        // excursión, T6c·3— no lo es, y el recibo lo dice con sus palabras (`recibo.js`).
        fiesta: borrador.fiesta === true,
        fila: borrador.fila,
        dia: borrador.dia,
        hora: borrador.hora,
        n: borrador.n,
        cal: calcetin ? borrador.cal : 0,
        minimo,
        maximo,
        calcetin: calcetin ? { id: calcetin.id, price_cents: calcetin.price_cents, max_quantity: calcetin.max_quantity ?? null } : null,
        guardian: guardian === 'required',
        evento: { ...evento },
        elecciones: [...elecciones],
        extras: extras.map((x) => ({ product_id: x.product_id, quantity: x.quantity })),
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

/** Si el «no» del servidor a una línea es la HORA: completa o que ya no se ofrece (`CartLineProblem`). */
export function esHoraLlena(problemas) {
    return (Array.isArray(problemas) ? problemas : []).some((p) => p?.reason === 'sold_out' || p?.reason === 'time_unavailable');
}

/**
 * Mete la línea del pedido como la ÚNICA de la cesta, la guarda y pide su presupuesto.
 *
 * Si el servidor dice que no, la cesta vuelve a lo que era y se devuelve el aviso ya traducido (el mismo que da el
 * cajón: `line-problems.js`). `horaLlena`: el «no» es la HORA —completa (`sold_out`) o que ya no se ofrece
 * (`time_unavailable`)—, y la compra puede proponer las cercanas (`#822`, §4.16).
 *
 * @param {{api: object, pedido: object, resueltos: Array, cartStore: object, messages?: object}} deps
 * @returns {Promise<{ok: boolean, aviso: string, horaLlena?: boolean}>}
 */
export async function meterLinea({ api, pedido, resueltos, cartStore, messages = {} }) {
    const linea = lineaDe(pedido, resueltos);
    const previas = cartStore.lines;

    // Con la cesta VACÍA como contexto: la candidata sustituye, así que no compite con lo que va a reemplazar.
    cartStore.setLines([]);
    const respuesta = await cartStore.validateLine({ api, line: linea });

    if (! respuesta?.ok || respuesta.data?.valid !== true) {
        cartStore.setLines(previas);
        const problemas = respuesta?.ok ? respuesta.data?.problems ?? [] : [];
        const aviso = respuesta?.ok ? lineProblems(problemas, [], messages).error : '';

        return { ok: false, aviso: aviso || t(messages, 'errors.choose_one'), horaLlena: esHoraLlena(problemas) };
    }

    cartStore.setLines(addLine([], linea, respuesta.data));
    cartStore.persist();
    await cartStore.refreshQuote({ api });

    return { ok: true, aviso: '' };
}
