/**
 * **De la respuesta de `GET /me/orders` a lo que la zona «Mis reservas» pinta**
 * (`docs/specs/area-cliente.md` §4.4).
 *
 * Módulo PLANO, sin Vue (`CE-6`): aquí vive la composición y por eso se puede probar con
 * `node --test` y comparar campo a campo contra la página que este cajón sustituye.
 *
 * ⚠️⚠️ **Ninguna regla de negocio vive aquí** (`CE-4`), y en esta zona la tentación es grande porque
 * la página de la que se copia SÍ las tiene: si un pedido se puede reintentar, cuánto queda por
 * cobrar en puerta, si procede el aviso de señal y si una reserva admite post-form lo decide el
 * SERVIDOR y llega resuelto (`can_be_retried`, `gate_remainder_cents`, `shows_deposit_note`,
 * `guest_form_url`). Recomponer cualquiera de esas cuatro aquí sería la quinta superficie que
 * diverge — que es exactamente lo que `openapi/v1.yaml` avisa para `shows_deposit_note`.
 *
 * ⚠️ Y **las fechas tampoco se componen**: llegan como `date_label` / `created_label` porque `Intl`
 * no reproduce lo que compone Carbon en español y porque la del pedido lleva la zona horaria de la
 * instalación, que el navegador no conoce (`DECISIONES #120(j)`).
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
 * El pie financiero de una reserva con señal («Señal X · Y en el parque»), o `null`.
 *
 * ⚠️ **La condición la decide el SERVIDOR** (`shows_deposit_note`), que la compone de tres —pedido
 * pagado, producto con señal y algo pendiente en puerta—. Aquí solo se pintan los dos importes.
 */
export function depositNoteOf(item, messages) {
    if (! item.shows_deposit_note) return null;

    return tp(messages, 'deposit_card_note', {
        deposit: money(item.paid_online_cents),
        rest: money(item.gate_remainder_cents),
    });
}

/** Un complemento anidado, tal como la página lo pinta bajo su línea. */
function addonRow(addon, account) {
    return {
        id: addon.id,
        name: addon.product_name,
        quantity: addon.quantity,
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
        quantity: item.quantity,
        isPack: item.is_pack,
        priceLabel: money(item.charged_subtotal_cents),
        badge: lineBadgeOf(item, account),
        depositNote: depositNoteOf(item, messages),
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
        totalLabel: money(order.total_cents),
        canRetry: order.can_be_retried === true,
        // El reembolso es un eje INDEPENDIENTE del estado: un pedido puede estar pagado y
        // parcialmente reembolsado a la vez (`openapi/v1.yaml`).
        refund: order.refund?.refunded_at
            ? { label: order.refund.refunded_label, amountLabel: money(order.refund.amount_cents) }
            : null,
        guestFormPending: order.guest_form_pending === true,
        lines: (order.items ?? []).map((item) => lineRow(order, item, ctx)),
    };
}

/**
 * La página de pedidos entera.
 *
 * @param {{data?: Array<object>}} payload  la respuesta de `GET /me/orders`
 * @param {{messages: object, account: object}} ctx  los diccionarios del montaje
 */
export function orderRows(payload, ctx) {
    return (payload?.data ?? []).map((order) => orderRow(order, ctx));
}

/**
 * La paginación, o `null` cuando cabe en una sola página.
 *
 * ⚠️ **`null` es un estado real y no un descuido**: la página tampoco pinta la barra con un único
 * resultado, y pintarla deshabilitada anunciaría un recorrido que no existe.
 */
export function pageInfo(payload, account) {
    const meta = payload?.meta ?? {};
    const current = Number(meta.current_page ?? 1);
    const last = Number(meta.last_page ?? 1);

    if (! Number.isFinite(current) || ! Number.isFinite(last) || last <= 1) return null;

    return {
        current,
        last,
        canPrev: current > 1,
        canNext: current < last,
        label: t(account, 'orders.pagination.label'),
        prevLabel: t(account, 'orders.pagination.prev'),
        nextLabel: t(account, 'orders.pagination.next'),
        pageLabel: tp(account, 'orders.pagination.page', { current, last }),
    };
}
