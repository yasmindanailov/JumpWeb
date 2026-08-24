import { test, describe } from 'node:test';
import assert from 'node:assert/strict';
import { cardRow, cardRows, depositNoteOf, financialsOf, guestFormOf, lineBadgeOf, orderRow, pageInfo } from './orders.js';

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
    // Los DOS bloques del desglose (`DECISIONES #127`). Claves reales del grupo `tickets`.
    ledger: {
        value_title: 'Qué vale este pedido',
        value_total: 'Valor del pedido',
        paid_online: 'Pagado por web',
        pending_online: 'Pendiente de pagar por web',
        paid_at_gate: 'Pagado en el parque',
        pending_at_gate: 'Pendiente de pagar en el parque',
        compensated: 'Compensación devuelta',
        cash_title: 'Tu dinero',
        charged_online: 'Cobrado por web',
        refunded: 'Ya devuelto',
        pending_refund: 'Pendiente de devolverte',
        invoiced: 'Importe al reservar',
        invoiced_hint: 'Es lo que se facturó al hacer la reserva.',
    },
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

/**
 * El ledger que publica el servidor (`openapi/v1.yaml` → `Ledger`). Fábrica ÚNICA: si cada caso
 * montara su forma a mano, el día que el contrato cambie estos tests seguirían verdes probando una
 * respuesta que ya no existe.
 */
const ledger = (over = {}) => ({
    value: {
        total_cents: 4500,
        paid_online_cents: 3000,
        pending_online_cents: 0,
        paid_at_gate_cents: 0,
        pending_at_gate_cents: 1500,
        compensated_cents: 0,
        ...(over.value ?? {}),
    },
    cash: {
        charged_online_cents: 3000,
        refunded_cents: 0,
        held_cents: 3000,
        pending_refund_cents: 0,
        ...(over.cash ?? {}),
    },
    invoiced_cents: over.invoiced_cents ?? 4500,
    gate_lines: over.gate_lines ?? [],
    has_deposit: over.has_deposit ?? false,
    note: over.note ?? null,
});

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
        assert.equal(row.totalLabel, '45,00 €');
        assert.equal(row.canRetry, false);
        assert.equal(row.refund, null);
    });

    test('⚠️ el reembolso es un eje INDEPENDIENTE del estado', () => {
        // Un pedido puede estar pagado y parcialmente reembolsado a la vez (`openapi/v1.yaml`).
        const row = orderRow(order({
            status: 'paid',
            refund: { refunded_at: '2026-06-05T09:00:00+02:00', refunded_label: '05/06/2026', fully_refunded: false },
            ledger: ledger({ cash: { charged_online_cents: 3000, refunded_cents: 1000, held_cents: 2000, pending_refund_cents: 0 } }),
        }), CTX);

        assert.equal(row.statusLabel, 'Pagado');
        // ⚠️ Aquí va CUÁNDO; el IMPORTE vive en el bloque de caja del ledger (`DECISIONES #127`):
        // un número, un sitio. Tenerlo en dos era la clase de duplicado del que nacen las divergencias.
        assert.deepEqual(row.refund, { label: '05/06/2026' });
        assert.equal(row.financials.cash.rows.at(-1).amountLabel, '10,00 €');
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
        // Es la mitad que protege de la divergencia: si esta función mirara el importe de puerta por
        // su cuenta, sería la quinta superficie recomponiendo la misma regla de tres condiciones.
        const note = depositNoteOf(item({
            shows_deposit_note: true,
            ledger: ledger({ value: { paid_online_cents: 3000, pending_at_gate_cents: 0 } }),
        }), MESSAGES);

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
     * ⚠️⚠️ **LA PROPIEDAD, no una foto de importes**: los cinco canales del eje del VALOR suman
     * siempre el valor. Es `PAY-16` visto desde la pantalla, y es lo que hasta la tanda B **no podía
     * cumplirse**: la API publicaba 2 de las 6 dimensiones, así que la columna del cliente no sumaba
     * en el 100 % de los pedidos que tenían algo cobrado en puerta.
     */
    test('⚠️ el eje del VALOR cierra, en todos los repartos', () => {
        const casos = {
            'todo pagado por web': { paid_online_cents: 4500, pending_at_gate_cents: 0 },
            'señal pagada, resto pendiente': { paid_online_cents: 3000, pending_at_gate_cents: 1500 },
            'ya disfrutado, resto cobrado': { paid_online_cents: 3000, paid_at_gate_cents: 1500, pending_at_gate_cents: 0 },
            'sin pagar todavía': { paid_online_cents: 0, pending_online_cents: 3000, pending_at_gate_cents: 1500 },
            'con compensación': { paid_online_cents: 2000, compensated_cents: 1000, pending_at_gate_cents: 1500 },
        };

        for (const [nombre, value] of Object.entries(casos)) {
            const v = { total_cents: 4500, paid_online_cents: 0, pending_online_cents: 0,
                paid_at_gate_cents: 0, pending_at_gate_cents: 0, compensated_cents: 0, ...value };
            const suma = v.paid_online_cents + v.pending_online_cents + v.paid_at_gate_cents
                + v.pending_at_gate_cents + v.compensated_cents;
            assert.equal(suma, v.total_cents, `el caso «${nombre}» no cierra: revisa el fixture`);

            const f = financialsOf(order({ ledger: ledger({ value: v }) }), MESSAGES);
            const pintado = [...f.value.rows, ...(f.value.gate ? [f.value.gate] : [])]
                .reduce((acc, r) => acc + Number(r.amountLabel.replace(/[^\d]/g, '')), 0);

            assert.equal(
                pintado, v.total_cents,
                `«${nombre}»: lo que se PINTA no suma el valor — es justo el defecto que la tanda B arregla`,
            );
        }
    });

    /**
     * ⚠️⚠️ **Los DOS bloques no se mezclan.** «Ya devuelto» y «Pendiente de devolverte» son de otro
     * eje: **no restan del valor**, y pintarlos dentro de su columna es lo que la hacía ilegible.
     */
    test('⚠️ el dinero devuelto va en su PROPIO bloque, no restando del valor', () => {
        const f = financialsOf(order({
            ledger: ledger({
                value: { total_cents: 2200, paid_online_cents: 2200, pending_at_gate_cents: 0 },
                cash: { charged_online_cents: 4500, refunded_cents: 0, held_cents: 4500, pending_refund_cents: 2300 },
            }),
        }), MESSAGES);

        assert.equal(f.value.total.amountLabel, '22,00 €');
        assert.deepEqual(f.value.rows.map((r) => r.label), ['Pagado por web'], 'el eje de caja se ha colado en el valor');
        assert.deepEqual(
            f.cash.rows.map((r) => [r.label, r.amountLabel]),
            [['Cobrado por web', '45,00 €'], ['Pendiente de devolverte', '23,00 €']],
        );
    });

    /** Un pedido sin nada que contar no pinta el bloque de caja: un bloque de ceros es ruido. */
    test('sin movimientos de dinero, el segundo bloque no existe', () => {
        const f = financialsOf(order(), MESSAGES);

        assert.equal(f.cash, null);
        assert.equal(f.invoiced, null, 'lo facturado coincide con el valor: no hay nada que trazar');
    });

    /**
     * ⚠️⚠️ **«Pagado por web» ya NO se oculta.** Se ocultaba cuando el producto no llevaba señal, así
     * que en un pedido con cualquier incidencia el cliente **no veía cuánto había pagado**. Medido:
     * pasaba en 7 de 50 pedidos reales.
     */
    test('⚠️ lo pagado por web se enseña SIEMPRE, lleve señal o no', () => {
        const f = financialsOf(order({ ledger: ledger({ has_deposit: false }) }), MESSAGES);

        assert.deepEqual(f.value.rows[0], { label: 'Pagado por web', amountLabel: '30,00 €' });
    });

    /**
     * ⚠️⚠️ **Un pedido SIN pagar dice «pendiente de pagar», no «pagado».** Publicar el importe
     * cobrable en un solo campo hacía que la pantalla lo leyera en pasado: «Pagado online 11,90 €»
     * en un pedido que nadie había pagado (`specs/desglose-dinero-cliente.md` §4.ter.2).
     */
    test('⚠️ un pedido sin pagar NO dice que se ha pagado', () => {
        const f = financialsOf(order({
            status: 'pending',
            ledger: ledger({ value: { paid_online_cents: 0, pending_online_cents: 3000, pending_at_gate_cents: 1500 } }),
        }), MESSAGES);

        assert.deepEqual(f.value.rows.map((r) => r.label), ['Pendiente de pagar por web']);
    });

    /** El desglose ↳ llega compuesto por el servidor: aquí solo se formatean los importes. */
    test('el desglose se pinta en el orden que llega, con las etiquetas del servidor', () => {
        const f = financialsOf(order({
            ledger: ledger({
                value: { total_cents: 4800, paid_online_cents: 0, pending_at_gate_cents: 4800 },
                gate_lines: [
                    { label: '+1 Entrada suelta', amount_cents: 1700 },
                    { label: 'Resto de la señal de Cumple Jump', amount_cents: 3100 },
                ],
            }),
        }), MESSAGES);

        assert.deepEqual(f.value.gate.lines, [
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
        const sin = financialsOf(order({ ledger: ledger({ has_deposit: false }) }), MESSAGES);
        const con = financialsOf(order({ ledger: ledger({ has_deposit: true }) }), MESSAGES);

        assert.equal(sin.value.gate.caption, MESSAGES.at_gate_caption);
        assert.equal(con.value.gate.caption, MESSAGES.at_gate_caption_deposit);
    });

    /**
     * ⚠️⚠️ **«Importe al reservar» SALE de la columna.** Era el «Subtotal», y apilaba dos bases
     * distintas sin decirlo: lo facturado arriba y el valor actual abajo. Ahora va al pie, con su
     * explicación, y **solo cuando difiere** — si coincide, repetirlo sería ruido.
     */
    test('⚠️ lo facturado al reservar solo aparece si ya no es lo que vale', () => {
        const igual = financialsOf(order({ ledger: ledger({ invoiced_cents: 4500 }) }), MESSAGES);
        const distinto = financialsOf(order({ ledger: ledger({ invoiced_cents: 13300 }) }), MESSAGES);

        assert.equal(igual.invoiced, null);
        assert.equal(distinto.invoiced.amountLabel, '133,00 €');
        assert.equal(distinto.invoiced.label, 'Importe al reservar');
    });

    /**
     * ⚠️ **La FRASE la compone el servidor y aquí solo se transporta.** Decidir qué caso es —se le
     * debe dinero, se canceló, caducó sin cobro— es regla de dominio, no presentación.
     */
    test('la frase de estado llega del servidor tal cual', () => {
        const f = financialsOf(order({ ledger: ledger({ note: 'Tenemos pendiente devolverte 23,00 €.' }) }), MESSAGES);

        assert.equal(f.note, 'Tenemos pendiente devolverte 23,00 €.');
        assert.equal(financialsOf(order(), MESSAGES).note, null, 'sin nada que explicar no se inventa una frase');
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
