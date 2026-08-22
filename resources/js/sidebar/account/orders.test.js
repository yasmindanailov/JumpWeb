import { test, describe } from 'node:test';
import assert from 'node:assert/strict';
import { depositNoteOf, guestFormOf, lineBadgeOf, orderRow, orderRows, pageInfo } from './orders.js';

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
