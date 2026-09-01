/**
 * **De la respuesta de `GET /me/orders` a lo que la zona «Mis reservas» pinta**
 * (`docs/specs/area-cliente.md` §4.4).
 *
 * Módulo PLANO, sin Vue (`CE-6`): aquí vive la composición y por eso se puede probar con
 * `node --test` y comparar campo a campo contra la página que este cajón sustituye.
 *
 * ⚠️⚠️ **Ninguna regla de negocio vive aquí** (`CE-4`), y en esta zona la tentación es grande porque
 * la página de la que se copia SÍ las tiene: si un pedido se puede reintentar, cuánto queda por
 * pagar en el parque, si procede el aviso de señal y si una reserva admite post-form lo decide el
 * SERVIDOR y llega resuelto (`can_be_retried`, `ledger.balance`, `shows_deposit_note`,
 * `guest_form_url`). Recomponer cualquiera de esas cuatro aquí sería la quinta superficie que
 * diverge — que es exactamente lo que `openapi/v1.yaml` avisa para `shows_deposit_note`.
 *
 * ⚠️ Y **las fechas tampoco se componen**: llegan como `date_label` / `created_label` / `occurred_label`
 * porque `Intl` no reproduce lo que compone Carbon en español y porque la del pedido lleva la zona
 * horaria de la instalación, que el navegador no conoce (`DECISIONES #120(j)`).
 */

import { t, tp } from '../i18n.js';
import { money } from '../money.js';

/** Los estados de pedido en los que la web enseña el bloque del post-form. Medido contra la vista. */
const TERMINATED = ['cancelled', 'refunded'];

/**
 * El bloque de post-form de una reserva, o `null` si no lleva ninguno.
 *
 * ⚠️ **Los cuatro estados están MEDIDOS contra `account/orders.blade.php`**, no inventados, y el
 * orden entre ellos es contrato: una reserva cancelada dentro de un pedido pagado enseña «cancelada»
 * aunque su franja ya haya pasado, y por eso el caso de cancelación va PRIMERO.
 *
 * ⚠️ El botón deshabilitado se conserva a propósito (decisión de la clienta, 2026-06-15): al pulsarlo
 * daría 404 —el controlador exige pedido pagado y no cancelado— pero quitarlo haría «desaparecer» el
 * contexto de la reserva.
 */
export function guestFormOf(order, item, account) {
    if (item.guest_form_status === null || item.guest_form_status === undefined) return null;

    const terminated = TERMINATED.includes(order.status);

    if (order.status !== 'paid' && ! terminated) return null;

    const product = { product: item.product_name };

    if (item.cancelled || terminated) {
        return { state: 'cancelled', label: tp(account, 'orders.guest_form_cancelled', product), url: null };
    }

    if (item.status === 'finished') {
        return { state: 'past', label: tp(account, 'orders.guest_form_past', product), url: item.guest_form_url };
    }

    return item.needs_guest_form
        ? { state: 'pending', label: tp(account, 'orders.guest_form_pending', product), url: item.guest_form_url }
        : { state: 'done', label: tp(account, 'orders.guest_form_done', product), url: item.guest_form_url };
}

/**
 * El distintivo de una línea: cancelada o ya disfrutada.
 *
 * ⚠️ **Cancelada gana**, y no es un detalle de estilo: una reserva cancelada cuya franja ya pasó
 * cumple las dos condiciones, y anunciarla como «disfrutada» diría algo falso al cliente.
 */
export function lineBadgeOf(item, account) {
    if (item.cancelled) return { key: 'cancelled', label: t(account, 'orders.item_cancelled') };
    if (item.status === 'finished') return { key: 'finished', label: t(account, 'orders.item_finished') };

    return null;
}

/**
 * Un importe CON su signo delante, como pide el libro (`DECISIONES #305`, D1: «"+" y "−" y un
 * total»). El menos es el tipográfico (U+2212), el mismo que el panel usa en «Devuelto»; `money()`
 * emite el guion ASCII para negativos y aquí el signo se pone sobre la magnitud.
 */
function signed(cents) {
    return (cents < 0 ? '−' : '+') + money(Math.abs(Number(cents) || 0));
}

/** Los rótulos del saldo, uno por clase (`balance.kind`). `settled`, `expired` y `under_review` no llevan línea. */
const BALANCE_LABELS = {
    pay_at_park: 'journal.balance_pay_at_park',
    refund_at_park: 'journal.balance_refund_at_park',
    refund_pending: 'journal.balance_refund_pending',
    pay_online: 'journal.balance_pay_online',
};

/**
 * La línea del SALDO, o `null` cuando no hay nada que saldar.
 *
 * ⚠️⚠️ **La CLASE la decide el servidor** (`balance.kind`) y aquí solo se elige el rótulo. Deducirla
 * del signo de `cents` sería re-derivar una regla del dominio —«¿habrá visita?» decide entre «a
 * devolver en el parque» y «pendiente de devolución», y eso no está en el número—, que es la forma
 * de divergencia que dejó al cliente sin ver el ancla de caja durante toda la vida del producto (`L1`).
 */
function balanceOf(balance, messages) {
    const key = BALANCE_LABELS[balance?.kind];

    if (! key) return null;

    const line = { kind: balance.kind, label: t(messages, key), amountLabel: money(Math.abs(Number(balance.cents) || 0)) };

    // Con señal, lo que falta por web va acompañado de lo que además se pagará en el parque: viene
    // PUBLICADO (`rest_at_park_cents`), no restado del total aquí.
    if (balance.kind === 'pay_online' && Number(balance.rest_at_park_cents) > 0) {
        line.rest = tp(messages, 'journal.balance_rest_at_park', { amount: money(balance.rest_at_park_cents) });
    }

    return line;
}

/**
 * **EL LIBRO de un pedido, tal como la tarjeta lo pinta** (`DECISIONES #305`,
 * `specs/desglose-libro.md` §4.3 · T3·1).
 *
 * ⚠️⚠️ **Ni un solo importe se calcula aquí, y tampoco se DECIDE qué línea es cuál.** El servidor
 * publica el libro entero en `order.ledger`, compuesto por `Booking\Services\OrderBook` — la MISMA
 * composición que leen el panel, la hoja de sala, la puerta y los correos (D1: cliente y operador ven
 * lo mismo). Aquí solo se formatean los importes con su signo y se eligen los rótulos del saldo.
 *
 * Tres bloques: los MOVIMIENTOS (cada gestión con su signo y su fecha, hasta el Total), los PAGOS Y
 * DEVOLUCIONES (lo que de verdad entró y salió, hasta lo Pagado) y el SALDO, que se liquida en el
 * parque — positivo se paga, negativo se devuelve.
 *
 * ⚠️⚠️ **Si el libro NO CIERRA, no se pinta** (`DECISIONES #132`): cuando las identidades del dominio
 * fallan, ninguna línea es cierta. Lo que sigue siendo un hecho es el Total y lo que se COBRÓ, así
 * que eso se queda — y la frase explica el resto. La condición la decide el SERVIDOR (`is_consistent`).
 */
export function financialsOf(order, messages) {
    const l = order.ledger ?? {};
    const movement = (m) => ({
        label: m.label,
        dateLabel: m.occurred_label ?? '',
        amountLabel: signed(m.amount_cents),
        negative: Number(m.amount_cents) < 0,
    });
    const settlement = (s) => ({
        label: s.label,
        dateLabel: s.occurred_label ?? '',
        amountLabel: signed(s.amount_cents),
        negative: Number(s.amount_cents) < 0,
        // Un reembolso en curso o fallido se LISTA y no cuenta: la tarjeta lo atenúa.
        effective: s.status === 'succeeded',
    });
    const settlements = Array.isArray(l.settlements) ? l.settlements : [];
    const total = { label: t(messages, 'journal.total'), amountLabel: money(l.total_cents ?? 0) };

    if (l.is_consistent === false) {
        return {
            movementsTitle: t(messages, 'journal.movements_title'),
            movements: [],
            total,
            settlementsTitle: t(messages, 'journal.settlements_title'),
            settlements: settlements.filter((s) => s.kind === 'payment').map(settlement),
            paid: null,
            balance: null,
            note: l.note ?? null,
        };
    }

    return {
        movementsTitle: t(messages, 'journal.movements_title'),
        movements: (Array.isArray(l.movements) ? l.movements : []).map(movement),
        total,
        settlementsTitle: t(messages, 'journal.settlements_title'),
        settlements: settlements.map(settlement),
        paid: { label: t(messages, 'journal.paid'), amountLabel: money(l.paid_cents ?? 0) },
        balance: balanceOf(l.balance, messages),
        // La FRASE que explica el estado —tres casos: en revisión, caducó, pendiente de pago—. La
        // compone el servidor: decidir qué caso es, es regla.
        note: l.note ?? null,
    };
}

/** Un complemento anidado, tal como la página lo pinta bajo su línea. */
function addonRow(addon, account) {
    return {
        id: addon.id,
        name: addon.product_name,
        // ⚠️ La etiqueta, no el número pelado: el complemento sufría el MISMO defecto `L2` que la
        // línea principal —`· 2×` pegado al importe de la línea— y arreglar solo el principal habría
        // dejado la ambigüedad viva una fila más abajo. La compone el servidor.
        quantityLabel: addon.quantity_label,
        priceLabel: money(addon.charged_subtotal_cents),
        badge: lineBadgeOf(addon, account),
    };
}

/** Una línea principal con sus complementos. */
function lineRow(order, item, { messages, account }) {
    return {
        id: item.id,
        name: item.product_name,
        // Ya compuestos por el servidor los dos: el día porque `Intl` no lo reproduce, la ventana
        // porque componerla —según la franja tenga fin o no— es regla del dominio.
        whenLabel: [item.date_label, item.time_window].filter(Boolean).join(' · '),
        // ⚠️⚠️ **La cantidad con su SUSTANTIVO, y ése era el defecto `L2`** (`DECISIONES #128`,
        // `specs/desglose-dinero-cliente.md` §17.1). La tarjeta pintaba `8×` seguido del importe de
        // la línea —`8×216,00 €`—, que se lee como «8 unidades a 216 € cada una» = 1.728 € cuando
        // son **8 invitados y 216 € en total**. La compone el servidor porque el sustantivo depende
        // del tipo de producto y del idioma (`OrderItem::displayQuantityLabel()`).
        quantityLabel: item.quantity_label,
        isPack: item.is_pack,
        priceLabel: money(item.charged_subtotal_cents),
        badge: lineBadgeOf(item, account),
        guestForm: guestFormOf(order, item, account),
        addons: (item.addons ?? []).map((addon) => addonRow(addon, account)),
    };
}

/**
 * Un pedido, tal como la zona lo pinta.
 *
 * ⚠️ **El estado se traduce con `tickets.statuses.*`, que es el MISMO diccionario que usa la página**
 * —y que ya viaja entero en el montaje—, no con una tabla propia. Un segundo juego de rótulos para
 * los mismos cinco estados es una divergencia esperando su turno.
 */
export function orderRow(order, ctx) {
    return {
        code: order.code,
        status: order.status,
        statusLabel: t(ctx.messages, 'statuses.' + order.status),
        createdLabel: order.created_label,
        // Lo que el pedido VALE hoy (`ledger.total_cents`), no lo facturado al nacer.
        totalLabel: money(order.ledger?.total_cents ?? 0),
        canRetry: order.can_be_retried === true,
        // El reembolso es un eje INDEPENDIENTE del estado: un pedido puede estar pagado y
        // parcialmente reembolsado a la vez (`openapi/v1.yaml`).
        // ⚠️ CUÁNDO se devolvió. **El importe vive en las liquidaciones del libro**: tenerlo en dos
        // sitios era la clase de duplicado del que nacen las divergencias.
        refund: order.refund?.refunded_at ? { label: order.refund.refunded_label } : null,
        guestFormPending: order.guest_form_pending === true,
        // ⚠️ Si alguna línea es un pack, el pedido PUEDE tener respuestas del evento — y solo
        // entonces se ofrece el despliegue. Cuáles son no se sabe hasta pedirlas: no viajan en la
        // lista a propósito (art. 9), que es justo lo que hace que haya que preguntar.
        hasPack: (order.items ?? []).some((item) => item.is_pack === true),
        // El libro entero: qué líneas se enseñan y con qué rótulo.
        financials: financialsOf(order, ctx.messages),
        lines: (order.items ?? []).map((item) => lineRow(order, item, ctx)),
    };
}

/**
 * **Una TARJETA DE RESERVA**: una línea principal con el contexto mínimo de su pedido
 * (`docs/specs/mis-reservas-por-reserva.md` §4.4).
 *
 * ⚠️⚠️ **Reutiliza `lineRow()` entera, y ahí está el punto.** Una reserva pintada suelta y una
 * reserva pintada dentro de su pedido son **la misma cosa**: el mismo nombre, la misma ventana
 * horaria, el mismo distintivo, el mismo aviso de señal, el mismo post-form y los mismos
 * complementos. Escribir una segunda composición «porque ahora la tarjeta es de la reserva» habría
 * creado dos sitios donde arreglar el mismo fallo — y este fichero ya lleva escrito, para el libro,
 * por qué eso no se hace.
 *
 * ⚠️ **El estado del PEDIDO llega resuelto** (`order.status` es el de HECHO, no la columna) y aquí
 * solo se traduce con `tickets.statuses.*`, el mismo diccionario de siempre.
 *
 * ⚠️ **Y no se decide si la tarjeta va atenuada.** Eso es propiedad de la PANTALLA —el historial
 * atenúa lo que pinta— y no de la fila: recomponerlo aquí sería una segunda definición del predicado
 * que reparte los dos ámbitos, que vive en SQL. El distintivo «cancelada» / «disfrutada», que sí es
 * de la reserva, lo pone `lineBadgeOf()` con los datos que sí viajan.
 */
export function cardRow(card, ctx) {
    const order = card.order ?? {};

    return {
        ...lineRow({ status: order.status }, card.reservation ?? {}, ctx),
        orderCode: order.code,
        orderStatus: order.status,
        orderStatusLabel: t(ctx.messages, 'statuses.' + order.status),
        orderCreatedLabel: order.created_label,
        canRetry: order.can_be_retried === true,
    };
}

/**
 * La página de tarjetas entera.
 *
 * @param {{data?: Array<object>}} payload  la respuesta de `GET /me/reservations/{scope}`
 * @param {{messages: object, account: object}} ctx  los diccionarios del montaje
 */
export function cardRows(payload, ctx) {
    return (payload?.data ?? []).map((card) => cardRow(card, ctx));
}

/**
 * **La página de «Mis pedidos»**: una fila por PEDIDO (`specs/desglose-dinero-cliente.md` §19).
 *
 * ⚠️⚠️ **Es `orderRow()` sin una línea propia, y ahí está el punto.** El pedido que se despliega
 * desde una reserva y el pedido de esta lista **son el mismo pedido**, servidos por el MISMO
 * `OrderResource` —`GET /orders/{code}` y `GET /me/orders` lo comparten—, así que componerlo aquí
 * otra vez habría creado la novena superficie de dinero. Lo que cambia es la pantalla que lo pinta.
 *
 * @param {{data?: Array<object>}} payload  la respuesta de `GET /me/orders`
 * @param {{messages: object, account: object}} ctx  los diccionarios del montaje
 */
export function purchaseRows(payload, ctx) {
    return (payload?.data ?? []).map((order) => orderRow(order, ctx));
}

/**
 * La paginación, o `null` cuando cabe en una sola página.
 *
 * ⚠️ **`null` es un estado real y no un descuido**: la página tampoco pinta la barra con un único
 * resultado, y pintarla deshabilitada anunciaría un recorrido que no existe.
 *
 * ⚠️ **El GRUPO de rótulos es un parámetro** desde que hay dos listas paginadas: «Mis reservas» habla
 * de reservas y «Mis pedidos» de pedidos, y una barra que dijera «Paginación de reservas» sobre una
 * lista de pedidos sería un texto falso que ningún test de composición vería.
 */
export function pageInfo(payload, account, group = 'orders') {
    const meta = payload?.meta ?? {};
    const current = Number(meta.current_page ?? 1);
    const last = Number(meta.last_page ?? 1);

    if (! Number.isFinite(current) || ! Number.isFinite(last) || last <= 1) return null;

    return {
        current,
        last,
        canPrev: current > 1,
        canNext: current < last,
        label: t(account, group + '.pagination.label'),
        prevLabel: t(account, group + '.pagination.prev'),
        nextLabel: t(account, group + '.pagination.next'),
        pageLabel: tp(account, group + '.pagination.page', { current, last }),
    };
}
