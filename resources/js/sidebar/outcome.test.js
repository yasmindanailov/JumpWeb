import { test } from 'node:test';
import assert from 'node:assert/strict';
import { answersByReservation, buildConfirmation, confirmationLine, loadConfirmation } from './outcome.js';

/**
 * La red del DESENLACE (Fase 4 · paso 4.6·1).
 *
 * Lo que aquí se prueba es **la traducción y el emparejado**, que es lo único que este módulo hace y
 * lo único que un diff de árbol no puede ver: el componente recibe filas ya compuestas, así que un
 * emparejado cruzado —las respuestas de una reserva bajo otra— pinta un árbol idéntico con los datos
 * de otro niño.
 */

const item = (over = {}) => ({
    id: 11,
    product_name: 'Cumpleaños',
    date: '2026-08-15',
    time_window: '10:00–11:00',
    start_time: '10:00:00',
    quantity: 6,
    charged_subtotal_cents: 30000,
    status: 'active',
    cancelled: false,
    guest_form_status: null,
    needs_guest_form: false,
    addons: [],
    is_pack: true,
    paid_online_cents: 3000,
    gate_remainder_cents: 27000,
    shows_deposit_note: true,
    ...over,
});

const order = (over = {}) => ({
    code: 'R-ABC123',
    status: 'paid',
    currency: 'EUR',
    total_cents: 30000,
    online_amount_cents: 3000,
    pending_at_gate_cents: 27000,
    refund: { refunded_at: null, amount_cents: 0, fully_refunded: false },
    can_be_retried: false,
    created_at: null,
    paid_at: null,
    expires_at: null,
    items: [item()],
    guest_form_pending: false,
    ...over,
});

test('empareja las respuestas por reservation_id, no por posición', () => {
    const eventData = {
        order_code: 'R-ABC123',
        reservations: [
            { reservation_id: 22, answers: [{ key: 'celebrant', label: 'Homenajeado', value: 'Nil' }] },
            { reservation_id: 11, answers: [{ key: 'celebrant', label: 'Homenajeado', value: 'Mara' }] },
        ],
    };

    const built = buildConfirmation(order({ items: [item({ id: 11 }), item({ id: 22 })] }), eventData);

    // ⚠️ El endpoint NO garantiza el orden de las líneas, y recorrer las dos listas en paralelo
    // pondría el nombre de un niño bajo la reserva de otro sin que ningún árbol lo notara.
    assert.equal(built.lines[0].event[0].value, 'Mara');
    assert.equal(built.lines[1].event[0].value, 'Nil');
});

test('una reserva sin respuestas queda con su lista vacía, no sin campo', () => {
    const built = buildConfirmation(order(), { reservations: [{ reservation_id: 11, answers: [] }] });

    assert.deepEqual(built.lines[0].event, []);
});

test('una línea que no viene en las respuestas tampoco rompe la fila', () => {
    const built = buildConfirmation(order(), { reservations: [] });

    assert.deepEqual(built.lines[0].event, []);
});

test('answersByReservation tolera un sobre sin reservas', () => {
    assert.deepEqual(answersByReservation({}), {});
    assert.deepEqual(answersByReservation(null), {});
    assert.deepEqual(answersByReservation({ reservations: 'no' }), {});
});

test('traduce la línea del pedido a la forma del presupuesto', () => {
    const line = confirmationLine(item({
        addons: [{ id: 5, product_name: 'Tarta', quantity: 2, charged_subtotal_cents: 1000, free_quantity: 1 }],
    }), [{ key: 'celebrant', label: 'Homenajeado', value: 'Mara' }]);

    assert.deepEqual(line, {
        product_name: 'Cumpleaños',
        is_pack: true,
        quantity: 6,
        date: '2026-08-15',
        // ⚠️ `start_time` y no `time_window`: el segundo es un texto ya compuesto para MOSTRAR.
        time: '10:00:00',
        subtotal_cents: 30000,
        has_deposit: true,
        deposit_cents: 3000,
        gate_remainder_cents: 27000,
        addons: [{ product_name: 'Tarta', quantity: 2, free_quantity: 1, subtotal_cents: 1000 }],
        event: [{ key: 'celebrant', label: 'Homenajeado', value: 'Mara' }],
    });
});

test('la nota de señal sale del campo COMPUESTO por el servidor, no de los importes', () => {
    // Son tres condiciones (`ReservationFinancials::showsDepositNote`) y recomponerlas en el cliente
    // es como divergen las cuatro superficies que pintan este bloque.
    const line = confirmationLine(item({ shows_deposit_note: false, gate_remainder_cents: 27000 }));

    assert.equal(line.has_deposit, false);
});

test('el pendiente en puerta se PINTA, no se resta del total', () => {
    // `total − online` no es `pending_at_gate`: hay ajustes que no viven en ninguno de los dos.
    const built = buildConfirmation(order({ total_cents: 30000, online_amount_cents: 3000, pending_at_gate_cents: 25000 }));

    assert.equal(built.park_cents, 25000);
});

test('has_guest_form sale del pedido, no del any() de las líneas', () => {
    // El servidor descarta antes las líneas CANCELADAS; agregarlas aquí prometería un formulario que
    // nadie va a pedir. Por eso los dos campos se llaman distinto en el contrato.
    const built = buildConfirmation(order({
        guest_form_pending: false,
        items: [item({ needs_guest_form: true, cancelled: true })],
    }));

    assert.equal(built.has_guest_form, false);
});

test('loadConfirmation pide las dos cosas y devuelve el resumen', async () => {
    const asked = [];
    const api = {
        get: async (path) => {
            asked.push(path);

            return path.endsWith('/event-data')
                ? { ok: true, data: { reservations: [{ reservation_id: 11, answers: [] }] } }
                : { ok: true, data: order() };
        },
    };

    const built = await loadConfirmation({ orderCode: 'R-ABC123', api });

    assert.deepEqual(asked.sort(), ['/orders/R-ABC123', '/orders/R-ABC123/event-data']);
    assert.equal(built.code, 'R-ABC123');
    assert.equal(built.lines.length, 1);
});

test('el código del pedido viaja escapado en la ruta', async () => {
    const asked = [];
    const api = { get: async (path) => { asked.push(path); return { ok: false }; } };

    await loadConfirmation({ orderCode: 'R-A/B?c', api });

    assert.deepEqual(asked, ['/orders/R-A%2FB%3Fc', '/orders/R-A%2FB%3Fc/event-data']);
});

test('sin código no se pregunta nada', async () => {
    let calls = 0;
    const api = { get: async () => { calls++; return { ok: true, data: {} }; } };

    assert.equal(await loadConfirmation({ orderCode: '', api }), null);
    assert.equal(await loadConfirmation({ orderCode: null, api }), null);
    assert.equal(calls, 0);
});

test('si el pedido no se puede traer, el resumen es null y la pantalla sigue en pie', async () => {
    const api = { get: async (path) => (path.endsWith('/event-data') ? { ok: true, data: {} } : { ok: false, status: 404 }) };

    assert.equal(await loadConfirmation({ orderCode: 'R-ABC123', api }), null);
});

test('si fallan SOLO las respuestas del pack, el resumen se pinta igual', async () => {
    // Son un bloque bajo cada línea: su ausencia es un bloque menos, no una pantalla rota — y este
    // endpoint es el que va con `no-store` y puede caer por su propio limitador.
    const api = { get: async (path) => (path.endsWith('/event-data') ? { ok: false, status: 429 } : { ok: true, data: order() }) };

    const built = await loadConfirmation({ orderCode: 'R-ABC123', api });

    assert.equal(built.code, 'R-ABC123');
    assert.deepEqual(built.lines[0].event, []);
});
