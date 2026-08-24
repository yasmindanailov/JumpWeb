import { test } from 'node:test';
import assert from 'node:assert/strict';
import {
    answersByReservation, buildConfirmation, confirmationLine, declinedReasonText,
    loadConfirmation, loadPaymentStatus, pollVerdict, runRetry,
} from './outcome.js';

/**
 * La red del DESENLACE (Fase 4 · paso 4.6).
 *
 * Lo que aquí se prueba es **la traducción, el emparejado y las DECISIONES**, que es lo único que este
 * módulo hace y lo único que un diff de árbol no puede ver: el componente recibe filas ya compuestas,
 * así que un emparejado cruzado —las respuestas de una reserva bajo otra— pinta un árbol idéntico con
 * los datos de otro niño; y qué hace el cajón con un `failed` mientras sondea, o con un reintento
 * denegado, no deja rastro ninguno en el marcado.
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
    online_amount_cents: 3000,
    // El LEDGER, que desde la tanda B es el único sitio donde vive el desglose (`DECISIONES #127`).
    ledger: {
        value: {
            total_cents: 30000, paid_online_cents: 3000, pending_online_cents: 0,
            paid_at_gate_cents: 0, pending_at_gate_cents: 27000, compensated_cents: 0,
        },
        cash: { charged_online_cents: 3000, refunded_cents: 0, held_cents: 3000, pending_refund_cents: 0 },
        invoiced_cents: 30000, gate_lines: [], has_deposit: true, note: null,
    },
    refund: { refunded_at: null, fully_refunded: false },
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
    const built = buildConfirmation(order({
        online_amount_cents: 3000,
        ledger: {
            value: { total_cents: 30000, paid_online_cents: 5000, pending_online_cents: 0,
                paid_at_gate_cents: 0, pending_at_gate_cents: 25000, compensated_cents: 0 },
            cash: { charged_online_cents: 5000, refunded_cents: 0, held_cents: 5000, pending_refund_cents: 0 },
            invoiced_cents: 30000, gate_lines: [], has_deposit: true, note: null,
        },
    }));

    assert.equal(built.park_cents, 25000);
});

test('el estado del pedido viaja tal cual, y es lo que separa «pagado» de «pendiente»', () => {
    // ⚠️ Este caso nace en 4.7·2b·2·C (`DECISIONES #88`), de un HUECO medido: clavar `status` a
    // `'pending'` dejaba este fichero entero en verde. Lo cazaban dos casos PHP —el diff de árbol y
    // la paridad del desenlace—, pero no el test del propio módulo, que es quien debe fijar su mapeo.
    // No es cosmético: es la diferencia entre decirle «pago confirmado» o «pendiente de pago» a quien
    // acaba de pagar.
    assert.equal(buildConfirmation(order({ status: 'paid' })).status, 'paid');
    assert.equal(buildConfirmation(order({ status: 'pending' })).status, 'pending');
    assert.equal(buildConfirmation(order({ status: 'expired' })).status, 'expired');
    // Sin pedido no se inventa un estado: cadena vacía, que es lo que el resumen trata como «no sé».
    assert.equal(buildConfirmation(undefined).status, '');
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

// ── Los otros dos desenlaces: denegado (paso 10) y verificando (paso 11) ──────────────────────

const MESSAGES = {
    payment_failed: {
        reasons: {
            card_expired: 'Tu tarjeta está caducada.',
            bank_denied: 'Tu banco ha denegado el pago.',
            default: 'El pago no se autorizó.',
        },
    },
    errors: {
        retry_expired: 'Tu reserva ha caducado.',
        try_later: 'Inténtalo más tarde.',
        payment_unavailable: 'No hemos podido iniciar el pago.',
    },
};

test('el motivo del rechazo se lee del diccionario con el CÓDIGO como clave', () => {
    // `declined_reason` es exactamente lo que devuelve `RedsysResponseCode::reasonKey()`, que es la
    // clave bajo `tickets.payment_failed.reasons.*`. No hay tabla que mantener.
    assert.equal(declinedReasonText(MESSAGES, 'card_expired'), 'Tu tarjeta está caducada.');
    assert.equal(declinedReasonText(MESSAGES, 'bank_denied'), 'Tu banco ha denegado el pago.');
});

test('un motivo desconocido, nulo o vacío cae en el genérico y NUNCA pinta vacío', () => {
    // ⚠️ `i18n.js` devuelve cadena vacía cuando la clave no existe, así que sin esta caída un motivo
    // nuevo del servidor pintaría el rótulo «Motivo:» con nada detrás.
    assert.equal(declinedReasonText(MESSAGES, 'un_motivo_que_no_existe'), 'El pago no se autorizó.');
    assert.equal(declinedReasonText(MESSAGES, null), 'El pago no se autorizó.');
    assert.equal(declinedReasonText(MESSAGES, ''), 'El pago no se autorizó.');
});

test('el sondeo solo se mueve con `paid` y `expired`', () => {
    assert.equal(pollVerdict({ order_status: 'paid', payment_status: 'paid' }), 'confirmed');
    assert.equal(pollVerdict({ order_status: 'expired', payment_status: 'failed' }), 'expired');
});

test('lo demás sigue sondeando, incluido un intento FALLIDO', () => {
    // ⚠️ Es la acotación de `checkPaymentStatus()` y no una simplificación: un `failed` con la
    // notificación de la pasarela todavía en vuelo es justo el caso que esta pantalla existe para no
    // malinterpretar. Saltar al paso 10 aquí sería decirle al cliente que no ha pagado.
    assert.equal(pollVerdict({ order_status: 'pending', payment_status: 'failed' }), 'wait');
    assert.equal(pollVerdict({ order_status: 'pending', payment_status: 'pending' }), 'wait');
    assert.equal(pollVerdict({ order_status: 'cancelled', payment_status: 'none' }), 'wait');
});

test('no poder preguntar tampoco mueve el cajón', () => {
    // Espejo del `if (! $order) return;` de Livewire: un 401 pasajero, un 429 o un corte de red no son
    // un desenlace, y tratarlos como tal sacaría al cliente de la pantalla que dice la verdad.
    assert.equal(pollVerdict(null), 'wait');
    assert.equal(pollVerdict(undefined), 'wait');
    assert.equal(pollVerdict({}), 'wait');
});

test('loadPaymentStatus pregunta por el pedido y devuelve null si no puede', async () => {
    const asked = [];
    const ok = { get: async (p) => { asked.push(p); return { ok: true, data: { order_status: 'paid' } }; } };

    assert.deepEqual(await loadPaymentStatus({ orderCode: 'R-A B', api: ok }), { order_status: 'paid' });
    assert.deepEqual(asked, ['/orders/R-A%20B/payment-status']);

    const ko = { get: async () => ({ ok: false, status: 429 }) };
    assert.equal(await loadPaymentStatus({ orderCode: 'R-1', api: ko }), null);

    let calls = 0;
    assert.equal(await loadPaymentStatus({ orderCode: '', api: { get: async () => { calls++; } } }), null);
    assert.equal(calls, 0, 'sin código no se pregunta nada');
});

test('el reintento sale a la pasarela con el formulario del servidor', async () => {
    const api = {
        post: async (path, body) => {
            assert.equal(path, '/orders/R-ABC123/payment');
            assert.deepEqual(body, {}, 'el contrato dice que no hay cuerpo que enviar');

            return {
                ok: true,
                data: { payment: { url: 'https://sis.redsys.es', method: 'POST', fields: { Ds_Signature: 'x' } } },
            };
        },
    };

    const result = await runRetry({ orderCode: 'R-ABC123', api, messages: MESSAGES });

    assert.equal(result.ok, true);
    assert.equal(result.form.url, 'https://sis.redsys.es');
    // ⚠️ Los campos se emiten TAL CUAL: la firma cubre esos valores exactos.
    assert.deepEqual(result.form.fields, [{ name: 'Ds_Signature', value: 'x' }]);
    assert.equal(result.goTo, null);
});

test('solo `order_not_retryable` obliga a rehacer la reserva', async () => {
    // La pausa y el límite de frecuencia dejan al cliente donde está: su reserva SIGUE VIVA. Decirle
    // que ha caducado a quien pulsó dos veces seguidas es el error que el paso 2 encontró en la web.
    const cases = [
        ['order_not_retryable', 409, 'catalog', MESSAGES.errors.retry_expired],
        ['too_many_requests', 429, null, MESSAGES.errors.try_later],
        ['payment_unavailable', 502, null, MESSAGES.errors.payment_unavailable],
    ];

    for (const [code, status, goTo, error] of cases) {
        const api = { post: async () => ({ ok: false, status, error: { code } }) };
        const result = await runRetry({ orderCode: 'R-1', api, messages: MESSAGES });

        assert.equal(result.goTo, goTo, `${code} debería ir a ${goTo}`);
        assert.equal(result.error, error, `${code} debería avisar con su texto`);
        assert.equal(result.ok, false);
        assert.equal(result.rereadStatus, false);
    }
});

test('la pausa no compone mensaje: pide releer el estado', async () => {
    const api = { post: async () => ({ ok: false, status: 409, error: { code: 'reservations_paused' } }) };
    const result = await runRetry({ orderCode: 'R-1', api, messages: MESSAGES });

    assert.equal(result.error, '', 'el cartel de mantenimiento habla por él');
    assert.equal(result.rereadStatus, true);
    assert.equal(result.goTo, null, 'la reserva sigue viva: no se rehace nada');
});

test('un 401 lleva a identificarse, que es lo que hace Livewire con `$user` nulo', async () => {
    const api = { post: async () => ({ ok: false, status: 401, error: { code: 'unauthenticated' } }) };
    const result = await runRetry({ orderCode: 'R-1', api, messages: MESSAGES });

    assert.equal(result.goTo, 'identify');
    assert.equal(result.error, '', 'no se culpa al cliente de una sesión caducada');
});

test('sin código de pedido no se llama a la API y se vuelve al catálogo', async () => {
    let calls = 0;
    const api = { post: async () => { calls++; return { ok: true, data: {} }; } };

    assert.equal((await runRetry({ orderCode: '', api, messages: MESSAGES })).goTo, 'catalog');
    assert.equal(calls, 0);
});

test('un 200 con formulario a medias avisa sin fingir que no ha pasado nada', async () => {
    // El pedido sigue vivo con su hold recién extendido, así que se avisa y se queda donde está.
    const api = { post: async () => ({ ok: true, data: { payment: { url: '', method: 'POST', fields: {} } } }) };
    const result = await runRetry({ orderCode: 'R-1', api, messages: MESSAGES });

    assert.equal(result.ok, false);
    assert.equal(result.form, null);
    assert.equal(result.error, MESSAGES.errors.payment_unavailable);
    assert.equal(result.goTo, null, 'la reserva no se pierde por un formulario mal formado');
});

test('un código desconocido o un corte de red no dejan el reintento mudo', async () => {
    for (const response of [
        { ok: false, status: 409, error: { code: 'algo_nuevo' } },
        { ok: false, status: 500, error: null },
        { ok: false, status: 0, error: null },
    ]) {
        const result = await runRetry({ orderCode: 'R-1', api: { post: async () => response }, messages: MESSAGES });

        assert.equal(result.error, MESSAGES.errors.try_later);
        assert.equal(result.goTo, null, 'ante la duda, la reserva NO se da por perdida');
    }
});
