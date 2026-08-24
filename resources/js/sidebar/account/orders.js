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
 * El pie financiero de una reserva con señal, o `null`.
 *
 * ⚠️ **La condición la decide el SERVIDOR** (`shows_deposit_note`), que la compone de tres —pedido
 * pagado, producto con señal y algo pendiente en puerta—. Aquí solo se pintan los dos importes.
 *
 * ⚠️ El primer importe es **lo pagado por web de ESTA reserva**, no «la señal»: en un pack con
 * complementos cobrados íntegros, los dos números no coinciden y llamarlo señal engaña
 * (`specs/desglose-dinero-cliente.md` §4.4). El rótulo lo dice desde la tanda B.
 */
export function depositNoteOf(item, messages) {
    if (! item.shows_deposit_note) return null;

    return tp(messages, 'deposit_card_note', {
        deposit: money(item.ledger.value.paid_online_cents),
        rest: money(item.ledger.value.pending_at_gate_cents),
    });
}

/**
 * **El desglose de un pedido, en DOS BLOQUES** (`DECISIONES #127`, spec §10.3).
 *
 * ⚠️⚠️ **Ni un solo importe se calcula aquí, y ahora tampoco se DECIDE cuál es cuál.** El servidor
 * publica el ledger entero en `order.ledger`, compuesto por `Booking\Services\OrderLedger` — la
 * MISMA composición que leen el panel, la sub-card, el PDF y los correos. Aquí solo se elige qué
 * líneas tienen algo que enseñar.
 *
 * ⚠️⚠️ **Los dos bloques NO se mezclan, y ésa es la corrección de fondo.** Arriba, el EJE VALOR:
 * cinco canales que suman el valor, siempre. Abajo, el EJE CAJA: qué ha pasado con su dinero. Hasta
 * ahora «Devuelto» y «Pendiente de devolución» se pintaban como restas dentro de la columna del
 * valor —de la que **no restan**— y por eso la columna dejaba de leerse: medido, 18 de 23 gestiones
 * del panel la dejaban ilegible.
 *
 * ⚠️ **«Importe al reservar» sale de la columna** y va al pie, solo si difiere: era el «Subtotal»,
 * que apilaba dos bases distintas sin decirlo.
 * ⚠️ Y **«Pagado por web» ya no se oculta** cuando el producto no lleva señal — se ocultaba, y el
 * cliente no veía cuánto había pagado.
 */
export function financialsOf(order, messages) {
    const l = order.ledger;
    const v = l.value;
    const c = l.cash;
    const line = (key, cents) => (cents > 0 ? { label: t(messages, 'ledger.' + key), amountLabel: money(cents) } : null);

    const gate = v.pending_at_gate_cents > 0
        ? {
            label: t(messages, 'ledger.pending_at_gate'),
            amountLabel: money(v.pending_at_gate_cents),
            showLabel: t(messages, 'show_breakdown'),
            hideLabel: t(messages, 'hide_breakdown'),
            caption: t(messages, l.has_deposit ? 'at_gate_caption_deposit' : 'at_gate_caption'),
            // Las etiquetas llegan compuestas por el dominio, en el orden que la página usa.
            lines: (l.gate_lines ?? []).map((gl) => ({ label: gl.label, amountLabel: money(gl.amount_cents) })),
        }
        : null;

    return {
        value: {
            title: t(messages, 'ledger.value_title'),
            rows: [
                line('paid_online', v.paid_online_cents),
                line('pending_online', v.pending_online_cents),
                line('paid_at_gate', v.paid_at_gate_cents),
                line('compensated', v.compensated_cents),
            ].filter(Boolean),
            gate,
            total: { label: t(messages, 'ledger.value_total'), amountLabel: money(v.total_cents) },
        },
        // El eje de caja solo aparece cuando tiene algo que contar. Un bloque de ceros enseña a
        // ignorar el que sí importa.
        cash: (c.refunded_cents > 0 || c.pending_refund_cents > 0)
            ? {
                title: t(messages, 'ledger.cash_title'),
                rows: [
                    line('charged_online', c.charged_online_cents),
                    line('refunded', c.refunded_cents),
                    line('pending_refund', c.pending_refund_cents),
                ].filter(Boolean),
            }
            : null,
        // La FRASE que explica el estado. La compone el servidor: decidir qué caso es, es regla.
        note: l.note ?? null,
        // Trazabilidad: solo si lo facturado ya no es lo que vale.
        invoiced: l.invoiced_cents !== v.total_cents
            ? {
                label: t(messages, 'ledger.invoiced'),
                amountLabel: money(l.invoiced_cents),
                hint: t(messages, 'ledger.invoiced_hint'),
            }
            : null,
    };
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
        totalLabel: money(order.ledger.value.total_cents),
        canRetry: order.can_be_retried === true,
        // El reembolso es un eje INDEPENDIENTE del estado: un pedido puede estar pagado y
        // parcialmente reembolsado a la vez (`openapi/v1.yaml`).
        // ⚠️ CUÁNDO se devolvió. **El importe vive en el bloque de caja del ledger**: tenerlo en dos
        // sitios era la clase de duplicado del que nacen las divergencias.
        refund: order.refund?.refunded_at ? { label: order.refund.refunded_label } : null,
        guestFormPending: order.guest_form_pending === true,
        // ⚠️ Si alguna línea es un pack, el pedido PUEDE tener respuestas del evento — y solo
        // entonces se ofrece el despliegue. Cuáles son no se sabe hasta pedirlas: no viajan en la
        // lista a propósito (art. 9), que es justo lo que hace que haya que preguntar.
        hasPack: (order.items ?? []).some((item) => item.is_pack === true),
        // El ledger entero (tanda 3 · paso 10): qué líneas se enseñan y con qué rótulo.
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
 * creado dos sitios donde arreglar el mismo fallo — y este fichero ya lleva escrito, para el ledger,
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
