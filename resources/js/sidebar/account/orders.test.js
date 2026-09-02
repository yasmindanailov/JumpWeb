import { test, describe } from 'node:test';
import assert from 'node:assert/strict';
import { cardRow, cardRows, financialsOf, guestFormOf, lineBadgeOf, orderRow, pageInfo, purchaseRows } from './orders.js';

/**
 * La red de «Mis reservas» (`docs/specs/area-cliente.md` §4.4).
 *
 * ⚠️ Los diccionarios se doblan con el CAMINO real de `lang/` (`orders.guest_form_pending`), no con
 * claves planas: `i18n.js` lee por camino, y una forma inventada aquí probaría algo que en producción
 * no ocurre.
 */

const MESSAGES = {
    statuses: { paid: 'Pagado', pending: 'Pendiente', cancelled: 'Cancelado', expired: 'Caducado', refunded: 'Reembolsado' },
    // ⚠️ La nota de señal de la CESTA sigue existiendo (`deposit_card_note`), pero la de la tarjeta
    // de una reserva ya comprada MURIÓ con ella el 2026-08-24 (`DECISIONES #130`): esa pantalla ya no
    // enseña dinero. `depositNoteOf()` y sus dos claves se retiraron con su sujeto.
    // EL LIBRO (`DECISIONES #305`, T3·1): los títulos y los rótulos del saldo son claves REALES del
    // grupo `tickets.journal`, que viaja entero en el montaje. Las etiquetas de cada línea NO están
    // aquí a propósito: las compone el servidor y llegan en la respuesta.
    journal: {
        movements_title: 'Movimientos',
        settlements_title: 'Pagos y devoluciones',
        total: 'Total',
        paid: 'Pagado',
        balance_pay_at_park: 'A pagar en el parque',
        balance_refund_at_park: 'A devolver en el parque',
        balance_refund_pending: 'Pendiente de devolución',
        balance_pay_online: 'Pendiente de pagar por web',
        balance_rest_at_park: 'y :amount en el parque',
    },
};

const ACCOUNT = {
    orders: {
        item_finished: 'Disfrutada',
        item_cancelled: 'Cancelada',
        // ⚠️ **SIN `:product` desde `#345`**: el rótulo dejó de llevar el nombre del producto porque
        // `.btn` es `white-space: nowrap` y este botón es de ancho completo — con un nombre largo el
        // texto **se salía del botón** (medido: 418 px pedidos en 308 disponibles). El nombre está
        // tres líneas más arriba en la tarjeta.
        guest_form_pending: 'Rellenar datos de la reserva',
        guest_form_done: 'Ver datos de la reserva',
        guest_form_past: 'Datos de la reserva',
        guest_form_cancelled: 'Datos de la reserva · cancelada',
        pagination: { label: 'Paginación', prev: 'Anteriores', next: 'Siguientes', page: 'Página :current de :last' },
    },
    // ⚠️ «Mis pedidos» tiene su PROPIO grupo de rótulos: una barra que dijera «Paginación de
    // reservas» sobre una lista de pedidos sería un texto falso que ninguna composición ve.
    purchases: {
        pagination: { label: 'Paginación de pedidos', prev: 'Anteriores', next: 'Siguientes', page: 'Página :current de :last' },
    },
};

const CTX = { messages: MESSAGES, account: ACCOUNT };

/**
 * El libro que publica el servidor (`openapi/v1.yaml` → `Ledger`). Fábrica ÚNICA: si cada caso
 * montara su forma a mano, el día que el contrato cambie estos tests seguirían verdes probando una
 * respuesta que ya no existe.
 *
 * Por defecto: un pedido de 45,00 nacido así, con 30,00 cobrados por web y 15,00 a pagar en el parque.
 */
const movement = (over = {}) => ({
    kind: 'booking', label: 'Reserva realizada', amount_cents: 4500,
    occurred_at: '2026-06-01T08:00:00+00:00', occurred_label: '01/06/2026', reservation_id: null,
    ...over,
});

const settlement = (over = {}) => ({
    kind: 'payment', label: 'Pagado online', amount_cents: 3000,
    occurred_at: '2026-06-01T08:05:00+00:00', occurred_label: '01/06/2026', status: 'succeeded', method: 'web',
    ...over,
});

const ledger = (over = {}) => ({
    total_cents: 4500,
    paid_cents: 3000,
    // ⚠️ La CLASE del saldo la publica el servidor; el doble la fija a lo que ese pedido es. Un caso
    // que sobrescriba `cents` sin tocar `kind` prueba justamente que la zona OBEDECE a la clase.
    balance: { kind: 'pay_at_park', cents: 1500, rest_at_park_cents: 0, ...(over.balance ?? {}) },
    movements: over.movements ?? [movement()],
    settlements: over.settlements ?? [settlement()],
    has_deposit: over.has_deposit ?? false,
    // ⚠️ El servidor publica si el libro CIERRA (`DECISIONES #132`); el doble lo respeta y por
    // defecto dice que sí, que es el caso de todo pedido sano.
    is_consistent: over.is_consistent ?? true,
    note: over.note ?? null,
    ...Object.fromEntries(Object.entries(over).filter(([k]) => ['total_cents', 'paid_cents'].includes(k))),
});

const item = (over = {}) => ({
    id: 1,
    product_name: 'Cumple Jump',
    date: '2026-06-10',
    date_label: 'Mié. 10 jun.',
    time_window: '10:00–11:00',
    quantity: 2,
    quantity_label: '2 entradas',
    charged_subtotal_cents: 4500,
    status: 'active',
    cancelled: false,
    guest_form_status: null,
    needs_guest_form: false,
    addons: [],
    is_pack: false,
    start_time: '10:00:00',
    ledger: ledger(),
    shows_deposit_note: false,
    guest_form_url: null,
    ...over,
});

const order = (over = {}) => ({
    code: 'JW-0001',
    status: 'paid',
    currency: 'EUR',
    online_amount_cents: 3000,
    ledger: ledger(),
    refund: { refunded_at: null, refunded_label: '', fully_refunded: false },
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
        assert.equal(row.totalLabel, '45,00 €', 'lo que el pedido VALE, del libro');
        assert.equal(row.canRetry, false);
        assert.equal(row.refund, null);
    });

    test('⚠️ el reembolso es un eje INDEPENDIENTE del estado', () => {
        // Un pedido puede estar pagado y parcialmente reembolsado a la vez (`openapi/v1.yaml`).
        const row = orderRow(order({
            status: 'paid',
            refund: { refunded_at: '2026-06-05T09:00:00+02:00', refunded_label: '05/06/2026', fully_refunded: false },
            ledger: ledger({
                paid_cents: 2000,
                settlements: [settlement(), settlement({ kind: 'refund', label: 'Devuelto a la tarjeta', amount_cents: -1000, occurred_label: '05/06/2026', method: 'card' })],
                balance: { cents: 2500 },
            }),
        }), CTX);

        assert.equal(row.statusLabel, 'Pagado');
        // ⚠️ Aquí va CUÁNDO; el IMPORTE vive en las liquidaciones del libro: un número, un sitio.
        // Tenerlo en dos era la clase de duplicado del que nacen las divergencias.
        assert.deepEqual(row.refund, { label: '05/06/2026' });
        assert.equal(row.financials.settlements.at(-1).amountLabel, '−10,00 €');
    });

    test('`can_be_retried` se OBEDECE, no se deduce del estado', () => {
        // La regla es del dominio: un `pending` con el hold vencido NO se puede reintentar, y deducirlo
        // aquí a partir del estado ofrecería un botón que el servidor rechazaría.
        assert.equal(orderRow(order({ status: 'pending', can_be_retried: false }), CTX).canRetry, false);
        assert.equal(orderRow(order({ status: 'pending', can_be_retried: true }), CTX).canRetry, true);
    });

});

/**
 * **La TARJETA por reserva** (`specs/mis-reservas-por-reserva.md` §4.4).
 *
 * ⚠️ Lo que se comprueba aquí no es que «pinta los campos», sino que **reutiliza la composición de
 * la línea** en vez de escribir una segunda: el nombre, la ventana, el distintivo, el aviso de señal
 * y el post-form salen de `lineRow()`, y una tarjeta que los recompusiera daría lo mismo hoy y
 * divergiría al primer arreglo que se hiciera en un solo sitio.
 */
describe('la tarjeta de una reserva', () => {
    // ⚠️ La reserva lleva post-form a propósito: es lo único de la línea cuya composición depende
    // del estado del PEDIDO, así que sin él el caso de abajo no mediría nada.
    const conFormulario = { guest_form_status: 'pending', needs_guest_form: true, guest_form_url: '/reserva/1/invitados' };

    const card = (over = {}) => ({
        reservation: item(conFormulario),
        order: { code: 'JJ-9', status: 'paid', created_label: '10/06/2026 12:00', can_be_retried: false },
        ...over,
    });

    test('lleva la reserva compuesta igual que dentro de su pedido', () => {
        const fila = cardRow(card(), CTX);
        const dentro = orderRow(order({ items: [item(conFormulario)] }), CTX).lines[0];

        assert.equal(fila.name, dentro.name);
        assert.equal(fila.whenLabel, dentro.whenLabel);
        assert.equal(fila.priceLabel, dentro.priceLabel);
        assert.deepEqual(fila.badge, dentro.badge);
        assert.deepEqual(fila.guestForm, dentro.guestForm);
    });

    test('y añade la referencia de su pedido, con el estado ya traducido', () => {
        const fila = cardRow(card(), CTX);

        assert.equal(fila.orderCode, 'JJ-9');
        assert.equal(fila.orderStatus, 'paid');
        assert.equal(fila.orderStatusLabel, 'Pagado');
        assert.equal(fila.orderCreatedLabel, '10/06/2026 12:00');
    });

    /**
     * ⚠️⚠️ **El post-form depende del estado del PEDIDO, no del de la reserva**, y por eso la tarjeta
     * tiene que pasárselo: `guestFormOf()` no ofrece enlace si el pedido no está pagado. Con un
     * pedido vacío la tarjeta enseñaría un botón que da 404 — lo destapó escribir este caso.
     */
    test('el post-form se decide con el estado del pedido que trae la tarjeta', () => {
        assert.notEqual(cardRow(card(), CTX).guestForm, null);
        assert.equal(
            cardRow(card({ order: { code: 'JJ-9', status: 'pending', can_be_retried: true } }), CTX).guestForm,
            null,
            'un pedido a medio pagar ofrece post-form: daría 404'
        );
    });

    test('el reintento sale del pedido, no se deduce del estado', () => {
        assert.equal(cardRow(card(), CTX).canRetry, false);
        assert.equal(cardRow(card({ order: { code: 'JJ-9', status: 'pending', can_be_retried: true } }), CTX).canRetry, true);
    });

    test('una respuesta vacía da una lista vacía, no revienta', () => {
        assert.deepEqual(cardRows({ data: [] }, CTX), []);
        assert.deepEqual(cardRows({}, CTX), []);
        assert.deepEqual(cardRows(null, CTX), []);
    });

    /** Y una tarjeta a medio llegar no tumba la pantalla: `t()` ya devuelve '' cuando falta la clave. */
    test('una tarjeta sin pedido no revienta', () => {
        const fila = cardRow({ reservation: item(conFormulario) }, CTX);

        assert.equal(fila.orderCode, undefined);
        assert.equal(fila.canRetry, false);
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
            items: [item({ addons: [{ id: 9, product_name: 'Calcetines', quantity: 2, quantity_label: '2 unidades', charged_subtotal_cents: 400, cancelled: false, status: 'active' }] })],
        }), CTX);

        assert.equal(row.lines[0].addons.length, 1);
        assert.equal(row.lines[0].addons[0].priceLabel, '4,00 €');
    });

    /**
     * ⚠️⚠️ **`L2` — la cantidad va con su SUSTANTIVO, y lo compone el servidor**
     * (`DECISIONES #128`, `specs/desglose-dinero-cliente.md` §17.1).
     *
     * La tarjeta pintaba el número pelado seguido del importe de la línea —`8×216,00 €`—, que se lee
     * como «8 unidades a 216 € cada una» = 1.728 € **cuando son 8 invitados y 216 € en total**. Un
     * número sin sustantivo no distingue cantidad de importe, y en dinero eso no es cosmético.
     */
    test('⚠️ `L2` la cantidad llega con su nombre y NO se recompone aquí', () => {
        const row = orderRow(order({
            items: [item({
                quantity: 8,
                quantity_label: '8 invitados',
                charged_subtotal_cents: 21600,
                addons: [{ id: 9, product_name: 'Calcetines', quantity: 2, quantity_label: '2 unidades', charged_subtotal_cents: 400, cancelled: false, status: 'active' }],
            })],
        }), CTX);

        assert.equal(row.lines[0].quantityLabel, '8 invitados');
        assert.equal(row.lines[0].addons[0].quantityLabel, '2 unidades', 'el complemento sufría el MISMO defecto');
        assert.equal(row.lines[0].quantity, undefined, 'el número pelado ya no viaja: era la mitad de la ambigüedad');
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
            label: 'Rellenar datos de la reserva',
            url: '/reserva/1/datos-invitados',
        });
    });

    /**
     * ❗ **El rótulo NO puede llevar el nombre del producto** (`#345`, `[DECIDIDO owner]`).
     *
     * `.btn` es `white-space: nowrap` y este botón es `width: 100%` dentro de una tarjeta de 308 px:
     * medido en navegador, «Completa el formulario de Cumpleaños Jump» cabía con **0 px de margen** y
     * uno más largo pedía **418**. Y no se trunca, se quita: *un rótulo que interpola un nombre que
     * escribe el panel no puede tener regla de longitud* (`#303`).
     */
    test('⚠️ ningún estado interpola el nombre del producto', () => {
        const estados = [
            paid({ needs_guest_form: true }),
            paid({ needs_guest_form: false, guest_form_status: 'ok' }),
            paid({ status: 'finished' }),
            paid({ cancelled: true }),
        ];

        for (const bloque of estados) {
            assert.ok(! bloque.label.includes('Cumple Jump'), `«${bloque.label}» lleva el nombre del producto`);
            // Y el control de que el rótulo se resolvió de verdad: un `:product` sin sustituir sería
            // el defecto contrario, y `t()` no interpola nada.
            assert.ok(! bloque.label.includes(':product'), `«${bloque.label}» dejó el marcador sin resolver`);
        }
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

/**
 * **EL LIBRO** (`DECISIONES #305`, `specs/desglose-libro.md` §4.3 · T3·1): cada gestión una línea
 * con su signo y su fecha, el Total es la suma y el saldo se liquida en el parque.
 *
 * ⚠️ Lo que aquí se prueba es que la zona TRANSPORTA lo que el servidor compone —etiquetas, fechas,
 * la clase del saldo— y le pone el signo y el formato. Ninguna regla se re-deriva: ni la clase del
 * saldo del signo, ni el Total de la suma, ni el aviso de cierre de los importes.
 */
describe('el libro', () => {
    const cents = (label) => Number(String(label).replace(/[^\d]/g, '')) * (String(label).startsWith('−') ? -1 : 1);

    test('cada movimiento lleva su etiqueta del servidor, su fecha y su SIGNO', () => {
        const f = financialsOf(order({
            ledger: ledger({
                total_cents: 1500,
                movements: [
                    movement({ amount_cents: 4500 }),
                    movement({ kind: 'edit', label: 'Cantidad: 2 → 1', amount_cents: -3000, occurred_label: '03/06/2026', reservation_id: 1 }),
                ],
            }),
        }), MESSAGES);

        assert.equal(f.movementsTitle, 'Movimientos');
        assert.deepEqual(
            f.movements.map((m) => [m.label, m.dateLabel, m.amountLabel, m.negative]),
            [['Reserva realizada', '01/06/2026', '+45,00 €', false], ['Cantidad: 2 → 1', '03/06/2026', '−30,00 €', true]],
            'la etiqueta llega compuesta y el signo va delante del importe',
        );
        assert.equal(f.total.label, 'Total');
        assert.equal(f.total.amountLabel, '15,00 €', 'el Total lo publica el servidor: no se suma aquí');
    });

    test('⚠️ el Total NO se recompone sumando: es el que llega', () => {
        // Un libro cuyo Total no fuera la suma sería un libro «en revisión» (I3), y eso lo dice el
        // servidor con `is_consistent`. Sumar aquí sería inventar una segunda identidad.
        const f = financialsOf(order({ ledger: ledger({ total_cents: 9999, movements: [movement({ amount_cents: 4500 })] }) }), MESSAGES);

        assert.equal(f.total.amountLabel, '99,99 €');
    });

    test('los pagos y devoluciones llevan su fecha, su signo y si cuentan', () => {
        const f = financialsOf(order({
            ledger: ledger({
                paid_cents: 3000,
                settlements: [
                    settlement(),
                    settlement({ kind: 'refund', label: 'Devolución en curso', amount_cents: -1000, status: 'pending', method: 'card', occurred_label: '05/06/2026' }),
                    settlement({ kind: 'gate', label: 'Liquidado en el parque', amount_cents: 1500, method: null, occurred_label: '10/06/2026' }),
                ],
            }),
        }), MESSAGES);

        assert.equal(f.settlementsTitle, 'Pagos y devoluciones');
        assert.deepEqual(
            f.settlements.map((s) => [s.label, s.dateLabel, s.amountLabel, s.negative, s.effective]),
            [
                ['Pagado online', '01/06/2026', '+30,00 €', false, true],
                ['Devolución en curso', '05/06/2026', '−10,00 €', true, false],
                ['Liquidado en el parque', '10/06/2026', '+15,00 €', false, true],
            ],
        );
        assert.equal(f.paid.label, 'Pagado');
        assert.equal(f.paid.amountLabel, '30,00 €', 'lo pagado lo publica el servidor: una devolución en curso no lo baja');
    });

    /**
     * ⚠️⚠️ **La CLASE del saldo la decide el servidor**, y aquí solo se elige el rótulo. «A devolver
     * en el parque» y «pendiente de devolución» son el mismo número con distinta salida —según haya
     * visita— y eso no está en el signo.
     */
    test('⚠️ el saldo se rotula por su CLASE, no por su signo', () => {
        const casos = {
            pay_at_park: ['A pagar en el parque', 1500, '15,00 €'],
            refund_at_park: ['A devolver en el parque', -2500, '25,00 €'],
            refund_pending: ['Pendiente de devolución', -2500, '25,00 €'],
            pay_online: ['Pendiente de pagar por web', 3000, '30,00 €'],
        };

        for (const [kind, [label, amount, amountLabel]] of Object.entries(casos)) {
            const f = financialsOf(order({ ledger: ledger({ balance: { kind, cents: amount } }) }), MESSAGES);

            assert.equal(f.balance.kind, kind);
            assert.equal(f.balance.label, label, `«${kind}» no lleva su rótulo`);
            assert.equal(f.balance.amountLabel, amountLabel, 'la magnitud, sin signo: el rótulo ya dice el sentido');
        }

        // La guarda de la guarda: la clase manda sobre el signo. Con importe y clase «saldado», no
        // hay línea — si la zona dedujera la clase del signo, aquí pintaría «a pagar 15,00».
        assert.equal(financialsOf(order({ ledger: ledger({ balance: { kind: 'settled', cents: 1500 } }) }), MESSAGES).balance, null);
        assert.equal(financialsOf(order({ ledger: ledger({ balance: { kind: 'expired', cents: 0 } }) }), MESSAGES).balance, null);
    });

    test('con señal y sin cobrar, el saldo dice lo que falta por web y lo que además irá al parque', () => {
        const f = financialsOf(order({
            status: 'pending',
            ledger: ledger({ paid_cents: 0, settlements: [], has_deposit: true, balance: { kind: 'pay_online', cents: 3000, rest_at_park_cents: 1500 } }),
        }), MESSAGES);

        assert.equal(f.balance.label, 'Pendiente de pagar por web');
        assert.equal(f.balance.amountLabel, '30,00 €');
        assert.equal(f.balance.rest, 'y 15,00 € en el parque', 'el resto llega PUBLICADO: no se resta del total aquí');
        assert.equal(financialsOf(order({ ledger: ledger({ balance: { kind: 'pay_online', cents: 3000, rest_at_park_cents: 0 } }) }), MESSAGES).balance.rest, undefined);
    });

    /**
     * ⚠️⚠️ **UN LIBRO QUE NO CIERRA NO SE PINTA** (`DECISIONES #132`). Si las identidades del dominio
     * fallan, ninguna línea es cierta: pintarlas es poner delante del cliente dos importes que se
     * contradicen sin decirle nada. Lo que sigue siendo un hecho es lo que vale el pedido y lo que
     * se le COBRÓ, y eso se queda; la frase explica el resto. La condición la decide el SERVIDOR.
     */
    test('⚠️ si el libro NO CIERRA, quedan el Total, los cobros y la frase', () => {
        const f = financialsOf(order({
            ledger: ledger({
                is_consistent: false,
                note: 'Estamos revisando el detalle de este pedido.',
                balance: { kind: 'under_review', cents: 0 },
                movements: [movement(), movement({ kind: 'edit', label: 'Cantidad: 1 → 2', amount_cents: 999 })],
                settlements: [settlement(), settlement({ kind: 'refund', label: 'Devuelto a la tarjeta', amount_cents: -500, method: 'card' })],
            }),
        }), MESSAGES);

        assert.deepEqual(f.movements, [], 'se siguen pintando líneas que no son ciertas');
        assert.equal(f.total.amountLabel, '45,00 €', 'lo que VALE el pedido sí es un hecho: se queda');
        assert.deepEqual(f.settlements.map((s) => s.label), ['Pagado online'], 'lo COBRADO también es un hecho: se queda; lo demás no');
        assert.equal(f.paid, null, 'un «pagado» que no cuadra no se afirma');
        assert.equal(f.balance, null, 'ningún saldo es cierto');
        assert.equal(f.note, 'Estamos revisando el detalle de este pedido.');
    });

    /** Sin ningún cobro no hay liquidaciones: la lista va vacía y lo pagado es 0. */
    test('sin ningún cobro, la lista de pagos va vacía', () => {
        const f = financialsOf(order({
            status: 'pending',
            ledger: ledger({ paid_cents: 0, settlements: [], balance: { kind: 'pay_online', cents: 4500 } }),
        }), MESSAGES);

        assert.deepEqual(f.settlements, []);
        assert.equal(f.paid.amountLabel, '0,00 €');
    });

    /** La FRASE la compone el servidor y aquí solo se transporta. */
    test('la frase de estado llega del servidor tal cual', () => {
        const f = financialsOf(order({ ledger: ledger({ note: 'Todavía no se ha completado el pago de 30,00 €.' }) }), MESSAGES);

        assert.equal(f.note, 'Todavía no se ha completado el pago de 30,00 €.');
        assert.equal(financialsOf(order(), MESSAGES).note, null, 'sin nada que explicar no se inventa una frase');
    });

    /**
     * ⚠️ El signo se pone sobre la MAGNITUD con el menos tipográfico, y `money()` no lo decide: un
     * importe negativo formateado a pelo llevaría el guion ASCII y el panel usa «−». Dos signos
     * distintos para el mismo concepto es la clase de detalle que resta credibilidad a una cuenta.
     */
    test('el signo va delante de la magnitud, con el menos tipográfico', () => {
        const f = financialsOf(order({
            ledger: ledger({ movements: [movement({ amount_cents: -123456 }), movement({ amount_cents: 0 })] }),
        }), MESSAGES);

        assert.equal(f.movements[0].amountLabel, '−1.234,56 €');
        assert.equal(cents(f.movements[0].amountLabel), -123456);
        assert.equal(f.movements[1].amountLabel, '+0,00 €');
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

/**
 * **«Mis pedidos»: una fila por PEDIDO** (`specs/desglose-dinero-cliente.md` §19).
 *
 * ⚠️⚠️ Lo que se protege aquí es que **NO nazca una segunda composición del dinero**. El pedido de
 * esta lista y el que se desplegaba desde una reserva son el mismo, servidos por el mismo
 * `OrderResource`; componerlos distinto sería la novena superficie.
 */
describe('la lista de pedidos', () => {
    test('⚠️ cada fila es `orderRow()` EXACTA, no una composición nueva', () => {
        const pedido = order({ code: 'R-1' });
        const filas = purchaseRows({ data: [pedido] }, CTX);

        assert.deepEqual(filas, [orderRow(pedido, CTX)], 'la lista compone el dinero por su cuenta');
    });

    test('una respuesta vacía no inventa filas', () => {
        assert.deepEqual(purchaseRows({ data: [] }, CTX), []);
        assert.deepEqual(purchaseRows(null, CTX), []);
    });

    /**
     * ⚠️⚠️ **LO QUE SE LISTA TIENE QUE SUMAR LO QUE DICE EL TOTAL.** Es la única promesa de esta
     * pantalla, y se rompió el primer día: `PurchaseCard` pintaba los principales y **no sus
     * complementos**, así que en `R-UPFQAB` las reservas ponían 120,00 € y el Total 124,00 € — con
     * 4,00 € de calcetines que la API publicaba y la lista se comía. Un desglose al que le falta una
     * línea cuadra por dentro y **no cuadra para quien lo lee**.
     *
     * ⚠️ Se suman solo las líneas VIVAS: una cancelada se pinta con su distintivo pero ya no vale.
     */
    test('⚠️ las líneas VIVAS que se listan suman el Total del pedido, complementos incluidos', () => {
        const conAddon = order({
            items: [item({
                charged_subtotal_cents: 12000,
                addons: [{ id: 9, product_name: 'Calcetines', quantity: 2, quantity_label: '2 unidades', charged_subtotal_cents: 400, cancelled: false, status: 'active' }],
            })],
            ledger: ledger({ total_cents: 12400, paid_cents: 3000, balance: { cents: 9400 } }),
        });

        const [fila] = purchaseRows({ data: [conAddon] }, CTX);
        const centimos = (etiqueta) => Number(String(etiqueta).replace(/[^\d]/g, ''));
        const sumado = fila.lines
            .filter((l) => ! l.badge || l.badge.key !== 'cancelled')
            .reduce((acc, l) => acc + centimos(l.priceLabel) + l.addons.reduce((a, x) => a + centimos(x.priceLabel), 0), 0);

        assert.equal(
            sumado, 12400,
            'lo que la pantalla lista no suma el total: falta una línea, y el cliente ve el hueco sin explicación',
        );
    });

    test('lleva el libro entero de cada pedido', () => {
        const [fila] = purchaseRows({ data: [order()] }, CTX);

        assert.equal(fila.financials.total.amountLabel, '45,00 €');
        assert.equal(fila.financials.movements[0].label, 'Reserva realizada');
        assert.equal(fila.financials.settlements[0].dateLabel, '01/06/2026', 'la fila no lleva la fecha del cobro');
        assert.equal(fila.financials.balance.label, 'A pagar en el parque');
    });

    /**
     * ⚠️ **La barra habla de PEDIDOS y no de reservas.** Los dos grupos de rótulos existen y el que
     * se usa es un parámetro: sin él, esta pantalla anunciaría «Paginación de reservas» sobre una
     * lista de pedidos, y eso no lo ve ningún test de composición ni el diff de árbol.
     */
    test('⚠️ la paginación usa los rótulos de SU grupo', () => {
        const payload = { meta: { current_page: 1, last_page: 3 } };

        assert.equal(pageInfo(payload, ACCOUNT, 'purchases').label, 'Paginación de pedidos');
        assert.equal(pageInfo(payload, ACCOUNT).label, 'Paginación', 'el grupo por defecto ha cambiado');
    });
});
