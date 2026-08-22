import { test, describe } from 'node:test';
import assert from 'node:assert/strict';
import { depositNoteOf, financialsOf, guestFormOf, lineBadgeOf, orderRow, orderRows, pageInfo } from './orders.js';

/**
 * La red de «Mis reservas» (`docs/specs/area-cliente.md` §4.4).
 *
 * ⚠️ Los diccionarios se doblan con el CAMINO real de `lang/` (`orders.guest_form_pending`), no con
 * claves planas: `i18n.js` lee por camino, y una forma inventada aquí probaría algo que en producción
 * no ocurre.
 */

const MESSAGES = {
    statuses: { paid: 'Pagado', pending: 'Pendiente', cancelled: 'Cancelado', expired: 'Caducado', refunded: 'Reembolsado' },
    deposit_card_note: 'Señal :deposit · :rest en el parque',
    // El ledger financiero (tanda 3 · paso 10). Claves REALES del grupo `tickets`, que viaja entero
    // en el montaje: doblarlas con nombres inventados probaría algo que en producción no ocurre.
    subtotal: 'Subtotal',
    total: 'Total',
    deposit_paid_online: 'Pagado online',
    at_gate: 'A cobrar en el parque',
    show_breakdown: 'Ver desglose',
    hide_breakdown: 'Ocultar desglose',
    at_gate_caption: 'Diferencia por cambios en el pedido.',
    at_gate_caption_deposit: 'Resto a pagar en recepción. La señal ya quedó pagada online.',
    pendiente_devolucion: 'Pendiente de devolución',
    pendiente_devolucion_caption: 'Pagaste de más por un cambio en el pedido.',
};

const ACCOUNT = {
    orders: {
        item_finished: 'Disfrutada',
        item_cancelled: 'Cancelada',
        guest_form_pending: 'Rellenar datos de :product',
        guest_form_done: 'Ver datos de :product',
        guest_form_past: 'Datos de :product',
        guest_form_cancelled: ':product cancelada',
        pagination: { label: 'Paginación', prev: 'Anteriores', next: 'Siguientes', page: 'Página :current de :last' },
    },
};

const CTX = { messages: MESSAGES, account: ACCOUNT };

const item = (over = {}) => ({
    id: 1,
    product_name: 'Cumple Jump',
    date: '2026-06-10',
    date_label: 'Mié. 10 jun.',
    time_window: '10:00–11:00',
    quantity: 2,
    charged_subtotal_cents: 4500,
    status: 'active',
    cancelled: false,
    guest_form_status: null,
    needs_guest_form: false,
    addons: [],
    is_pack: false,
    start_time: '10:00:00',
    paid_online_cents: 3000,
    gate_remainder_cents: 1500,
    shows_deposit_note: false,
    guest_form_url: null,
    ...over,
});

const order = (over = {}) => ({
    code: 'JW-0001',
    status: 'paid',
    currency: 'EUR',
    total_cents: 4500,
    online_amount_cents: 3000,
    pending_at_gate_cents: 1500,
    refund: { refunded_at: null, refunded_label: '', amount_cents: 0, fully_refunded: false },
    can_be_retried: false,
    created_at: '2026-06-01T10:00:00+02:00',
    created_label: '01/06/2026 10:00',
    paid_at: null,
    expires_at: null,
    items: [item()],
    guest_form_pending: false,
    has_deposit: false,
    pending_at_gate_lines: [],
    pending_refund_cents: 0,
    total_final_cents: 4500,
    ...over,
});

describe('el pedido', () => {
    test('se pinta con lo que el servidor ya resolvió, sin recomponer nada', () => {
        const row = orderRow(order(), CTX);

        assert.equal(row.code, 'JW-0001');
        assert.equal(row.statusLabel, 'Pagado', 'el rótulo sale del MISMO diccionario que la página');
        assert.equal(row.createdLabel, '01/06/2026 10:00', 'la fecha llega compuesta, no se formatea aquí');
        assert.equal(row.totalLabel, '45,00 €');
        assert.equal(row.canRetry, false);
        assert.equal(row.refund, null);
    });

    test('⚠️ el reembolso es un eje INDEPENDIENTE del estado', () => {
        // Un pedido puede estar pagado y parcialmente reembolsado a la vez (`openapi/v1.yaml`).
        const row = orderRow(order({
            status: 'paid',
            refund: { refunded_at: '2026-06-05T09:00:00+02:00', refunded_label: '05/06/2026', amount_cents: 1000, fully_refunded: false },
        }), CTX);

        assert.equal(row.statusLabel, 'Pagado');
        assert.deepEqual(row.refund, { label: '05/06/2026', amountLabel: '10,00 €' });
    });

    test('`can_be_retried` se OBEDECE, no se deduce del estado', () => {
        // La regla es del dominio: un `pending` con el hold vencido NO se puede reintentar, y deducirlo
        // aquí a partir del estado ofrecería un botón que el servidor rechazaría.
        assert.equal(orderRow(order({ status: 'pending', can_be_retried: false }), CTX).canRetry, false);
        assert.equal(orderRow(order({ status: 'pending', can_be_retried: true }), CTX).canRetry, true);
    });

    test('una respuesta vacía da una lista vacía, no revienta', () => {
        assert.deepEqual(orderRows({ data: [] }, CTX), []);
        assert.deepEqual(orderRows({}, CTX), []);
        assert.deepEqual(orderRows(null, CTX), []);
    });
});

describe('la línea', () => {
    test('junta el día y la ventana horaria, los dos ya compuestos por el servidor', () => {
        const row = orderRow(order(), CTX);

        assert.equal(row.lines[0].whenLabel, 'Mié. 10 jun. · 10:00–11:00');
    });

    test('una reserva SIN franja no deja el separador colgando', () => {
        const row = orderRow(order({ items: [item({ date_label: '', time_window: null })] }), CTX);

        assert.equal(row.lines[0].whenLabel, '');
    });

    test('los complementos van ANIDADOS, con su propio importe', () => {
        const row = orderRow(order({
            items: [item({ addons: [{ id: 9, product_name: 'Calcetines', quantity: 2, charged_subtotal_cents: 400, cancelled: false, status: 'active' }] })],
        }), CTX);

        assert.equal(row.lines[0].addons.length, 1);
        assert.equal(row.lines[0].addons[0].priceLabel, '4,00 €');
    });
});

describe('el distintivo de la línea', () => {
    test('sin nada que decir, no se pinta', () => {
        assert.equal(lineBadgeOf(item(), ACCOUNT), null);
    });

    test('disfrutada cuando su franja ya pasó', () => {
        assert.equal(lineBadgeOf(item({ status: 'finished' }), ACCOUNT).key, 'finished');
    });

    test('⚠️ CANCELADA gana a disfrutada, y no es cosmético', () => {
        // Una reserva cancelada cuya franja ya pasó cumple las dos condiciones. Anunciarla como
        // «disfrutada» le diría al cliente algo falso sobre algo que además pudo costarle dinero.
        const badge = lineBadgeOf(item({ cancelled: true, status: 'finished' }), ACCOUNT);

        assert.equal(badge.key, 'cancelled');
    });
});

describe('el aviso de señal', () => {
    test('lo decide el SERVIDOR, no se recompone de las tres condiciones', () => {
        assert.equal(depositNoteOf(item({ shows_deposit_note: false }), MESSAGES), null);

        assert.equal(
            depositNoteOf(item({ shows_deposit_note: true }), MESSAGES),
            'Señal 30,00 € · 15,00 € en el parque',
        );
    });

    test('⚠️ con `shows_deposit_note` en true se pinta aunque el resto parezca decir otra cosa', () => {
        // Es la mitad que protege de la divergencia: si esta función mirara `gate_remainder_cents` por
        // su cuenta, sería la quinta superficie recomponiendo la misma regla de tres condiciones.
        const note = depositNoteOf(item({ shows_deposit_note: true, gate_remainder_cents: 0 }), MESSAGES);

        assert.equal(note, 'Señal 30,00 € · 0,00 € en el parque');
    });
});

describe('el bloque del post-form', () => {
    const paid = (over) => guestFormOf(order(), item({ guest_form_status: 'pending', guest_form_url: '/reserva/1/datos-invitados', ...over }), ACCOUNT);

    test('una reserva que no pide datos por invitado no lo lleva', () => {
        assert.equal(guestFormOf(order(), item({ guest_form_status: null }), ACCOUNT), null);
    });

    test('un pedido PENDIENTE todavía no lo enseña', () => {
        assert.equal(
            guestFormOf(order({ status: 'pending' }), item({ guest_form_status: 'pending' }), ACCOUNT),
            null,
        );
    });

    test('pendiente de rellenar: enlace con su URL del servidor', () => {
        assert.deepEqual(paid({ needs_guest_form: true }), {
            state: 'pending',
            label: 'Rellenar datos de Cumple Jump',
            url: '/reserva/1/datos-invitados',
        });
    });

    test('ya relleno: se puede ver', () => {
        assert.equal(paid({ needs_guest_form: false, guest_form_status: 'ok' }).state, 'done');
    });

    test('la franja ya pasó: enlace de solo lectura', () => {
        assert.equal(paid({ status: 'finished' }).state, 'past');
    });

    test('⚠️ CANCELADA va primero, y SIN url', () => {
        // El orden entre los cuatro estados es contrato: una reserva cancelada cuya franja pasó cumple
        // dos, y el botón queda deshabilitado —al pulsarlo daría 404— pero VISIBLE, para no hacer
        // desaparecer el contexto de la reserva (decisión de la clienta, 2026-06-15).
        const block = paid({ cancelled: true, status: 'finished' });

        assert.equal(block.state, 'cancelled');
        assert.equal(block.url, null, 'un enlace aquí llevaría a un 404');
    });

    test('⚠️ un pedido TERMINADO cancela el bloque entero, aunque la reserva no esté marcada', () => {
        // Medido contra la vista: el item de un pack puede no estar marcado uno a uno cuando se
        // cancela el pedido, así que la condición mira el PEDIDO además de la línea.
        for (const status of ['cancelled', 'refunded']) {
            const block = guestFormOf(order({ status }), item({ guest_form_status: 'ok', guest_form_url: '/x' }), ACCOUNT);

            assert.equal(block.state, 'cancelled', `el pedido «${status}» debería cancelar el bloque`);
            assert.equal(block.url, null);
        }
    });
});

describe('la paginación', () => {
    test('con una sola página NO se pinta', () => {
        assert.equal(pageInfo({ meta: { current_page: 1, last_page: 1 } }, ACCOUNT), null);
        assert.equal(pageInfo({}, ACCOUNT), null, 'sin meta tampoco');
    });

    test('en la primera de tres, solo se puede avanzar', () => {
        const info = pageInfo({ meta: { current_page: 1, last_page: 3 } }, ACCOUNT);

        assert.equal(info.canPrev, false);
        assert.equal(info.canNext, true);
        assert.equal(info.pageLabel, 'Página 1 de 3');
    });

    test('en la última, solo se puede retroceder', () => {
        const info = pageInfo({ meta: { current_page: 3, last_page: 3 } }, ACCOUNT);

        assert.equal(info.canPrev, true);
        assert.equal(info.canNext, false);
    });
});

describe('el bloque financiero', () => {
    /**
     * ⚠️ **El caso más común es el que más fácil se estropea**: un pedido sin cambios ni señal debe
     * enseñar UN solo total. Si la primera línea dijera «Subtotal» y debajo apareciera un «Total» con
     * el mismo número, el cliente leería dos cobros.
     */
    test('un pedido normal enseña UN solo total y nada más', () => {
        // ⚠️ `pending_at_gate_cents: 0` EXPLÍCITO: el fixture base lleva 1.500 pendientes en puerta
        // —es un pedido con señal— y heredarlo aquí probaría el caso contrario al que dice el título.
        const f = financialsOf(order({ pending_at_gate_cents: 0 }), MESSAGES);

        assert.equal(f.firstLabel, 'Total');
        assert.equal(f.final, null, 'se pinta el total dos veces');
        assert.equal(f.online, null, 'un pedido sin señal no anuncia «Pagado online»');
        assert.equal(f.gate, null);
        assert.equal(f.pendingRefund, null);
    });

    test('con algo pendiente en puerta, la primera línea pasa a ser el SUBTOTAL', () => {
        const f = financialsOf(order({ pending_at_gate_cents: 3100, total_final_cents: 4500 }), MESSAGES);

        assert.equal(f.firstLabel, 'Subtotal');
        assert.equal(f.gate.amountLabel, '31,00 €');
        assert.deepEqual(f.final, { label: 'Total', amountLabel: '45,00 €' });
    });

    /** El desglose llega compuesto por el servidor: aquí solo se formatean los importes. */
    test('el desglose se pinta en el orden que llega, con las etiquetas del servidor', () => {
        const f = financialsOf(order({
            pending_at_gate_cents: 4800,
            pending_at_gate_lines: [
                { label: '+1 Entrada suelta', amount_cents: 1700 },
                { label: 'Resto de la señal de Cumple Jump', amount_cents: 3100 },
            ],
        }), MESSAGES);

        assert.deepEqual(f.gate.lines, [
            { label: '+1 Entrada suelta', amountLabel: '17,00 €' },
            { label: 'Resto de la señal de Cumple Jump', amountLabel: '31,00 €' },
        ]);
    });

    /**
     * ⚠️⚠️ **La leyenda cambia con la señal, y decirlo mal engaña sobre lo ya pagado.** «Diferencia
     * por cambios» en un pedido con señal le diría al cliente que le han cambiado el pedido cuando lo
     * que hay es el resto de lo que él mismo dejó a deber.
     */
    test('la leyenda del bloque de puerta depende de que haya señal', () => {
        const sin = financialsOf(order({ pending_at_gate_cents: 100 }), MESSAGES);
        const con = financialsOf(order({ pending_at_gate_cents: 100, has_deposit: true }), MESSAGES);

        assert.equal(sin.gate.caption, MESSAGES.at_gate_caption);
        assert.equal(con.gate.caption, MESSAGES.at_gate_caption_deposit);
    });

    /**
     * ⚠️⚠️ **`has_deposit` NO se deduce de que quede algo pendiente**, y este caso es el que lo
     * distingue: un pedido con señal cuyo resto ya se cobró en recepción no tiene nada pendiente y
     * **sigue** teniendo que enseñar lo que se pagó online.
     */
    test('un pedido con señal ya saldada sigue anunciando lo pagado online', () => {
        const f = financialsOf(order({ has_deposit: true, pending_at_gate_cents: 0 }), MESSAGES);


        assert.deepEqual(f.online, { label: 'Pagado online', amountLabel: '30,00 €' });
        assert.equal(f.gate, null, 'no queda nada pendiente en puerta');
    });

    /**
     * ⚠️ **Lo pendiente de devolver y lo ya devuelto son cosas distintas**, y cruzarlas invierte el
     * mensaje: uno es dinero que el cliente va a recibir y el otro, dinero que ya recibió.
     */
    test('lo pendiente de devolver se enseña con su porqué', () => {
        const f = financialsOf(order({ pending_at_gate_cents: 0, pending_refund_cents: 2300, total_final_cents: 2200 }), MESSAGES);

        assert.equal(f.pendingRefund.amountLabel, '23,00 €');
        assert.equal(f.pendingRefund.caption, MESSAGES.pendiente_devolucion_caption);
        assert.equal(f.firstLabel, 'Subtotal', 'una devolución pendiente también abre desglose');
        assert.equal(f.final.amountLabel, '22,00 €');
    });

    /** Un reembolso ya hecho abre desglose igual — pero solo si de verdad se procesó (tiene fecha). */
    test('un reembolso sin fecha NO cuenta como desglose', () => {
        const conFecha = financialsOf(order({
            pending_at_gate_cents: 0,
            refund: { refunded_at: '2026-06-02T10:00:00+02:00', refunded_label: '02/06/2026', amount_cents: 500, fully_refunded: false },
        }), MESSAGES);
        const sinFecha = financialsOf(order({
            pending_at_gate_cents: 0,
            refund: { refunded_at: null, refunded_label: '', amount_cents: 500, fully_refunded: false },
        }), MESSAGES);

        assert.equal(conFecha.firstLabel, 'Subtotal');
        assert.equal(sinFecha.firstLabel, 'Total', 'un importe sin fecha de reembolso ha abierto el desglose');
    });

    /** Y el pedido compuesto lo lleva, para que la zona solo tenga que pintar. */
    test('el pedido compuesto trae su ledger', () => {
        const row = orderRow(order({ pending_at_gate_cents: 3100 }), CTX);

        assert.equal(row.financials.firstLabel, 'Subtotal');
        assert.equal(row.financials.gate.amountLabel, '31,00 €');
    });
});

describe('el despliegue de las respuestas del pack', () => {
    /**
     * ⚠️ Solo se OFRECE si hay un pack: es lo único que el cliente puede saber sin pedir los datos,
     * porque las respuestas no viajan en la lista (art. 9).
     */
    test('un pedido con pack lo ofrece; uno sin pack, no', () => {
        assert.equal(orderRow(order({ items: [item({ is_pack: true })] }), CTX).hasPack, true);
        assert.equal(orderRow(order({ items: [item({ is_pack: false })] }), CTX).hasPack, false);
    });

    test('con varias líneas basta una que lo sea', () => {
        const row = orderRow(order({ items: [item({ id: 1, is_pack: false }), item({ id: 2, is_pack: true })] }), CTX);

        assert.equal(row.hasPack, true);
    });

    test('un pedido sin líneas no lo ofrece', () => {
        assert.equal(orderRow(order({ items: [] }), CTX).hasPack, false);
    });
});
