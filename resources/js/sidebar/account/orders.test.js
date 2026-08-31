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
        // T5 · D9: la voz real es «Liquidado» (el fixture MIENTE en silencio si no sigue a `lang/`).
        paid_at_gate: 'Liquidado en el parque',
        pending_at_gate: 'Pendiente de pagar en el parque',
        compensated: 'Compensación devuelta',
        paid_desk: 'Pagado en recepción',
        cash_title: 'Tu dinero',
        cash_caption: 'Es el dinero que ya te hemos cobrado. Puedes cotejarlo con tu extracto.',
        charged_online: 'Cobrado por web',
        charged_desk: 'Cobrado en recepción',
        refunded: 'Ya devuelto',
        pending_refund: 'Pendiente de devolverte',
        // ⚠️ `invoiced_hint` NO está aquí a propósito: desde `L6` la compone el DOMINIO y llega en la
        // respuesta (`DECISIONES #133`). Dejarla en el diccionario mantendría viva la cadena fija
        // que este trabajo vino a retirar, y una recaída pasaría inadvertida.
        invoiced: 'Importe al reservar',
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
    // ⚠️ «Mis pedidos» tiene su PROPIO grupo de rótulos: una barra que dijera «Paginación de
    // reservas» sobre una lista de pedidos sería un texto falso que ninguna composición ve.
    purchases: {
        pagination: { label: 'Paginación de pedidos', prev: 'Anteriores', next: 'Siguientes', page: 'Página :current de :last' },
    },
};

const CTX = { messages: MESSAGES, account: ACCOUNT };

/**
 * El ledger que publica el servidor (`openapi/v1.yaml` → `Ledger`). Fábrica ÚNICA: si cada caso
 * montara su forma a mano, el día que el contrato cambie estos tests seguirían verdes probando una
 * respuesta que ya no existe.
 */
const ledger = (over = {}) => {
    const cash = {
        charged_online_cents: 3000,
        refunded_cents: 0,
        held_cents: 3000,
        pending_refund_cents: 0,
        charged_method: 'web',
        charged_at_label: '01/06/2026',
        ...(over.cash ?? {}),
    };

    const value = {
        total_cents: 4500,
        paid_online_cents: 3000,
        pending_online_cents: 0,
        paid_at_gate_cents: 0,
        pending_at_gate_cents: 1500,
        compensated_cents: 0,
        ...(over.value ?? {}),
    };

    const invoiced = over.invoiced_cents ?? 4500;

    return {
        value,
        // ⚠️ `has_cash` se DERIVA aquí con la MISMA regla que `OrderLedger::hasCash()` en vez de
        // fijarse a un valor: el doble tiene que seguir siendo fiel cuando un caso sobrescribe los
        // importes. Un `true` fijo dejaría verdes casos que en producción no se pintan.
        // ⚠️ La regla es «¿dice algo que el eje del valor no diga ya?» (`DECISIONES #130`): hubo
        // devolución, se debe una, o **lo cobrado no coincide con lo pagado** —que en un pedido sano
        // no puede pasar (`PAY-17`) y en uno con el dato roto sí—.
        // ⚠️ Y un caso puede forzarlo: es lo que permite probar que la zona **obedece** al servidor
        // en vez de re-derivar la condición, que fue el origen de `L1`.
        cash: {
            ...cash,
            has_cash: over.cash?.has_cash
                ?? (cash.refunded_cents > 0 || cash.pending_refund_cents > 0
                    || cash.charged_online_cents !== value.paid_online_cents),
        },
        // ⚠️ El servidor publica si el desglose CIERRA (`DECISIONES #132`); el doble lo respeta y por
        // defecto dice que sí, que es el caso de todo pedido sano.
        is_consistent: over.is_consistent ?? true,
        invoiced_cents: invoiced,
        // ⚠️ La FRASE de «Importe al reservar» también la publica el servidor, y **su nulidad es la
        // condición de enseñar la línea** (`L6`, `DECISIONES #133`). El doble la deriva con la misma
        // regla que `OrderLedger::invoicedNoteFor()` —hay frase solo si lo facturado difiere del
        // valor— para seguir siendo fiel cuando un caso sobrescribe los importes.
        // ⚠️ `in` y no `??`: un caso tiene que poder pedir `null` EXPLÍCITAMENTE —importes distintos
        // y sin frase— para probar que la zona obedece al servidor en vez de re-derivar.
        invoiced_hint: 'invoiced_hint' in over
            ? over.invoiced_hint
            : (invoiced !== value.total_cents
                ? 'Al reservar se facturaron '+(invoiced / 100).toFixed(2).replace('.', ',')+' €. El pedido cambió después.'
                : null),
        gate_lines: over.gate_lines ?? [],
        // El «a tu favor» de fiesta mixta (T4): frase compuesta por el servidor; `null` ES la
        // condición de enseñarla (el patrón de `invoiced_hint`).
        in_favour_hint: over.in_favour_hint ?? null,
        has_deposit: over.has_deposit ?? false,
        note: over.note ?? null,
    };
};

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
        // ⚠️ Aquí la fecha NO va arriba: con una devolución de por medio, `paid_online` ya no es lo
        // que se cobró ese día, y pegarle la fecha convertía la línea en una afirmación falsa
        // (`DECISIONES #131`). La fecha viaja abajo, con el importe que sí se cobró.
        assert.deepEqual(f.value.rows.map((r) => r.label), ['Pagado por web'], 'la fecha se ha pegado a un importe que no se cobró ese día');
        assert.deepEqual(
            f.cash.rows.map((r) => [r.label, r.amountLabel]),
            [['Cobrado por web · 01/06/2026', '45,00 €'], ['Pendiente de devolverte', '23,00 €']],
        );
    });

    /**
     * ⚠️⚠️ **En un pedido CORRIENTE el eje de caja no aparece, y lo verificable va en su sitio**
     * (`DECISIONES #130`).
     *
     * `#128` lo enseñaba siempre que hubiera habido un cobro, y el owner leyó la pantalla y no la
     * entendió: el mismo importe salía dos veces, como «Pagado por web 30,00 €» arriba y «Cobrado por
     * web 30,00 €» abajo. Son la misma frase con las palabras en otro orden, y un bloque que repite
     * lo de arriba enseña a saltarse el bloque que sí importa.
     *
     * ▶ Lo que hacía falta conservar —que el importe se pueda **cotejar con el banco**— no era el
     * bloque: era la FECHA. Y ahora va pegada a la línea del canal, como el panel ya hacía.
     */
    /**
     * ⚠️⚠️ **LA FECHA VA DONDE EL IMPORTE ES EL QUE SE COBRÓ, Y EN NINGÚN OTRO SITIO**
     * (`DECISIONES #131`).
     *
     * `#130` la pegó a la línea del canal para hacer el importe conciliable con el banco. Pero
     * `paid_online` es el canal del VALOR y, en cuanto hay una devolución, **deja de ser lo que se
     * cobró ese día**. Medido sobre los 58 pedidos: en **7** la pantalla afirmaba «Pagado por web ·
     * 24/08/2026 — 9,90 €» cuando ese día se cobraron 19,80 €, y **6 de los 7 eran pedidos SANOS**.
     * Justo lo contrario de conciliable: el cliente miraría su extracto y no encontraría ese importe.
     */
    test('⚠️ con una devolución de por medio, la fecha NO se pega al importe del valor', () => {
        const f = financialsOf(order({
            ledger: ledger({
                value: { total_cents: 2200, paid_online_cents: 2200, pending_at_gate_cents: 0 },
                cash: { charged_online_cents: 4500, refunded_cents: 2300, held_cents: 2200, pending_refund_cents: 0 },
            }),
        }), MESSAGES);

        assert.equal(f.value.rows[0].label, 'Pagado por web', 'la fecha vuelve a pegarse a un importe que no se cobró ese día');
        assert.equal(f.cash.rows[0].label, 'Cobrado por web · 01/06/2026', 'la fecha tiene que ir con el importe que SÍ se cobró');
    });

    test('⚠️ un pedido corriente NO repite el importe: la fecha va en la línea del canal', () => {
        const f = financialsOf(order(), MESSAGES);

        assert.equal(f.value.rows[0].label, 'Pagado por web · 01/06/2026', 'sin fecha, el importe no se busca en un extracto');
        assert.equal(f.cash, null, 'el eje de caja repite un número que el de arriba ya dice');
    });

    /**
     * ⚠️⚠️ **Y LO QUE `#128` VINO A ARREGLAR SIGUE ENTERO**: cuando lo cobrado NO coincide con lo
     * pagado, el bloque aparece y la contradicción se ve.
     *
     * En un pedido sano los dos importes coinciden **por construcción** (`PAY-17`), así que solo
     * difieren cuando el dato está roto — que es exactamente el caso `R-L6UTIA`: 30,00 € cobrados
     * de verdad contra 114,00 € que el desglose llama «pagados». Sin este término, esa pantalla
     * volvería a leerse como si no pasara nada.
     */
    test('⚠️ si lo COBRADO no cuadra con lo pagado, el eje de caja aparece y lo enseña', () => {
        const f = financialsOf(order({
            ledger: ledger({
                value: { total_cents: 21600, paid_online_cents: 11400, pending_at_gate_cents: 10200 },
                cash: { charged_online_cents: 3000, held_cents: 3000, refunded_cents: 0, pending_refund_cents: 0 },
            }),
        }), MESSAGES);

        assert.notEqual(f.cash, null, 'el dato roto vuelve a pasar desapercibido');
        assert.deepEqual(
            f.cash.rows.map((r) => [r.label, r.amountLabel]),
            [['Cobrado por web · 01/06/2026', '30,00 €']],
        );
        assert.equal(f.value.rows[0].amountLabel, '114,00 €', 'los dos importes tienen que verse a la vez para que salte');
    });

    /**
     * ⚠️ Y el MÉTODO manda en el rótulo: el eje de caja suma todos los pagos cobrados sin mirar el
     * `provider`, así que un pedido de taquilla que dijera «por web» mentiría sobre dinero entregado
     * en mano. El panel ya lo distinguía desde `P1/P10` y el cliente no.
     */
    test('⚠️ un pedido cobrado en taquilla no dice «web» en ninguno de los dos ejes', () => {
        const conDevolucion = financialsOf(order({
            ledger: ledger({
                value: { total_cents: 2200, paid_online_cents: 2200, pending_at_gate_cents: 0 },
                cash: { charged_online_cents: 4500, refunded_cents: 2300, held_cents: 2200, pending_refund_cents: 0, charged_method: 'desk' },
            }),
        }), MESSAGES);

        assert.equal(conDevolucion.value.rows[0].label, 'Pagado en recepción', 'con devolución, la fecha no va arriba');
        assert.equal(conDevolucion.cash.rows[0].label, 'Cobrado en recepción · 01/06/2026');
    });

    /**
     * ⚠️ La condición **la decide el servidor** (`cash.has_cash`). Derivarla aquí fue exactamente el
     * origen de `L1`: la interfaz se quedó con media regla y nadie lo vio hasta medir el panel.
     */
    test('⚠️ `L1` la condición del bloque de caja NO se re-deriva: llega publicada', () => {
        const f = financialsOf(order({
            ledger: ledger({ cash: { charged_online_cents: 3000, held_cents: 3000, has_cash: false } }),
        }), MESSAGES);

        assert.equal(f.cash, null, 'la zona ha vuelto a decidir por su cuenta cuándo se pinta el eje de caja');
    });

    /**
     * ⚠️⚠️ **UN DESGLOSE QUE NO CIERRA NO SE DESCOMPONE** (`DECISIONES #132`).
     *
     * Si las identidades del dominio fallan, ninguna línea por canal es cierta: pintarlas es poner
     * delante del cliente dos importes que se contradicen sin decirle nada. Lo que sigue siendo un
     * hecho es lo que vale el pedido y lo que se le cobró, y eso se queda.
     */
    test('⚠️ si el desglose NO CIERRA, no se pinta la descomposición por canales', () => {
        const f = financialsOf(order({
            ledger: ledger({ is_consistent: false, note: 'Estamos revisando el detalle de este pedido.' }),
        }), MESSAGES);

        assert.deepEqual(f.value.rows, [], 'se siguen pintando canales que no son ciertos');
        assert.equal(f.value.gate, null, 'se sigue pintando el desglose de puerta');
        assert.equal(f.value.total.amountLabel, '45,00 €', 'lo que VALE el pedido sí es un hecho: se queda');
        assert.equal(f.cash.rows[0].amountLabel, '30,00 €', 'lo COBRADO también es un hecho: se queda');
        assert.equal(f.note, 'Estamos revisando el detalle de este pedido.');
        assert.equal(f.invoiced, null, 'la trazabilidad sobra cuando el desglose no se puede leer');
    });

    /** Sin ningún cobro no hay ancla que enseñar: un bloque de ceros enseña a ignorar los que importan. */
    test('sin ningún cobro, el segundo bloque no existe', () => {
        const f = financialsOf(order({
            ledger: ledger({
                value: { total_cents: 4500, paid_online_cents: 0, pending_online_cents: 4500, pending_at_gate_cents: 0 },
                cash: { charged_online_cents: 0, held_cents: 0 },
            }),
        }), MESSAGES);

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

        assert.deepEqual(f.value.rows[0], { label: 'Pagado por web · 01/06/2026', amountLabel: '30,00 €' });
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
     * El «a tu favor» de fiesta mixta (T4, `specs/cumple-mixto.md` §24.4): la frase llega COMPUESTA
     * del servidor y su nulidad ES la condición de enseñarla — la zona no re-deriva nada (la
     * lección de `L1`/`L6`). Mutación: re-derivar aquí la condición dejaría este caso ciego.
     */
    test('el «a tu favor» de fiesta mixta se pinta tal cual llega, y solo si llega', () => {
        const sin = financialsOf(order(), MESSAGES);
        const con = financialsOf(order({ ledger: ledger({
            in_favour_hint: '14,00 € a tu favor — se te devuelven en el parque el día de la fiesta.',
        }) }), MESSAGES);

        assert.equal(sin.inFavour, null);
        assert.equal(con.inFavour, '14,00 € a tu favor — se te devuelven en el parque el día de la fiesta.');
    });

    /**
     * ⚠️⚠️ **LA FRASE DE «Importe al reservar» LA COMPONE EL SERVIDOR** (`L6`, `DECISIONES #133`).
     *
     * Era una cadena FIJA del diccionario —«…es porque el pedido cambió después»— y decía *que* el
     * pedido había cambiado sin decir **en qué dirección ni cuánto**. Elegir entre «vale X más» y
     * «vale X menos» es decidir qué caso es: regla de dominio, igual que la frase de estado.
     */
    test('⚠️ la frase de lo facturado llega del servidor, no del diccionario', () => {
        const f = financialsOf(order({
            ledger: ledger({
                invoiced_cents: 18000,
                invoiced_hint: 'Al reservar se facturaron 180,00 €. El pedido cambió después y ahora vale 135,00 € menos.',
            }),
        }), MESSAGES);

        assert.equal(
            f.invoiced.hint,
            'Al reservar se facturaron 180,00 €. El pedido cambió después y ahora vale 135,00 € menos.',
            'la zona ha vuelto a componer la frase por su cuenta en vez de transportar la del servidor',
        );
    });

    /**
     * ⚠️⚠️ **Y la CONDICIÓN de enseñar la línea también es del servidor.**
     *
     * `invoiced_hint` vale `null` exactamente cuando no hay diferencia que explicar. Si la zona
     * volviera a comparar `invoiced_cents` con `total_cents` por su cuenta, este caso —dos importes
     * distintos, sin frase— pintaría un pie con un número y sin explicación. Es la misma forma de
     * divergencia que dejó al cliente sin el ancla de caja (`L1`).
     */
    test('⚠️ sin frase del servidor no hay línea, aunque los importes difieran', () => {
        const f = financialsOf(order({
            ledger: ledger({ invoiced_cents: 13300, invoiced_hint: null }),
        }), MESSAGES);

        assert.equal(f.invoiced, null, 'la zona re-deriva la condición en vez de obedecer al servidor');
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
     * complementos**, así que en `R-UPFQAB` las reservas ponían 120,00 € y «Valor del pedido»
     * 124,00 € — con 4,00 € de calcetines que la API publicaba y la lista se comía. Un desglose al
     * que le falta una línea cuadra por dentro y **no cuadra para quien lo lee**.
     *
     * ⚠️ Se suman solo las líneas VIVAS: una cancelada se pinta con su distintivo pero ya no vale.
     */
    test('⚠️ las líneas VIVAS que se listan suman el valor del pedido, complementos incluidos', () => {
        const conAddon = order({
            items: [item({
                charged_subtotal_cents: 12000,
                addons: [{ id: 9, product_name: 'Calcetines', quantity: 2, quantity_label: '2 unidades', charged_subtotal_cents: 400, cancelled: false, status: 'active' }],
            })],
            ledger: ledger({ value: { total_cents: 12400, paid_online_cents: 3000, pending_at_gate_cents: 9400 } }),
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

    test('lleva el desglose entero de cada pedido', () => {
        const [fila] = purchaseRows({ data: [order()] }, CTX);

        assert.equal(fila.financials.value.total.amountLabel, '45,00 €');
        assert.equal(fila.financials.value.rows[0].label, 'Pagado por web · 01/06/2026', 'la fila no lleva la fecha del cobro');
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
