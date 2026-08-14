import { test, describe } from 'node:test';
import assert from 'node:assert/strict';
import { addLine, cartRows, eventAnswers, removeLine, toApiItems } from './cart.js';

/**
 * Fase 4 · paso 4.3·2 — la red de la cesta (criterio CE-6).
 *
 * Aquí se fija la CONDUCTA del módulo; que su resultado coincida con el del servidor lo comprueba
 * `SidebarCartParityTest`. Hacen falta los dos: esa paridad no puede llegar a los estados que solo
 * existen en el cliente —una fusión que el servidor señala, una cesta que se queda vacía al quitar—.
 */

const line = (overrides = {}) => ({
    product_id: 1, date: '2026-09-05', time: '10:00:00', quantity: 2, event_data: {}, addons: [], ...overrides,
});

describe('añadir', () => {
    test('una línea nueva se añade al final con la cantidad EFECTIVA', () => {
        const cart = addLine([], line({ quantity: 5 }), { quantity: 3, merges_with_index: null });

        assert.equal(cart.length, 1);
        // ⚠️ Entra la cantidad del veredicto, no la pedida: el servidor recorta al cupo en silencio
        // desde siempre, y pintar la pedida enseña una reserva que no se tiene.
        assert.equal(cart[0].quantity, 3);
    });

    /**
     * ⚠️ La fusión la SEÑALA el servidor y solo ocurre entre entradas del mismo producto, día y hora
     * sin complementos. Reinventarla en el cliente crea una línea duplicada donde el servidor habría
     * sumado cantidades.
     */
    test('una línea que se funde SUMA su cantidad a la señalada, sin crecer la cesta', () => {
        const existing = [line({ quantity: 2 }), line({ product_id: 9, quantity: 1 })];
        const cart = addLine(existing, line({ quantity: 4 }), { quantity: 4, merges_with_index: 0 });

        assert.equal(cart.length, 2, 'la cesta no crece al fundir');
        assert.equal(cart[0].quantity, 6);
        assert.equal(cart[1].quantity, 1, 'las demás líneas no se tocan');
    });

    test('un índice de fusión que no existe no puede perder la línea', () => {
        const cart = addLine([line()], line({ product_id: 7 }), { quantity: 1, merges_with_index: 99 });

        assert.equal(cart.length, 2);
    });

    test('añadir no muta la cesta anterior', () => {
        const before = [line()];
        addLine(before, line({ product_id: 7 }), { quantity: 1, merges_with_index: null });

        assert.equal(before.length, 1);
    });
});

describe('quitar', () => {
    test('quita la posición pedida y conserva el resto en orden', () => {
        const cart = [line({ product_id: 1 }), line({ product_id: 2 }), line({ product_id: 3 })];

        assert.deepEqual(removeLine(cart, 1).map((l) => l.product_id), [1, 3]);
    });

    test('quitar la última deja la cesta vacía', () => {
        assert.deepEqual(removeLine([line()], 0), []);
    });
});

describe('lo que viaja a la API', () => {
    /**
     * ⚠️ La hora se guarda CANÓNICA (`HH:MM:SS`). El dominio compara franjas por cadena, así que una
     * hora sin segundos no casa con ninguna y falla en silencio: presupuesto sin líneas, no error.
     */
    test('la línea viaja con el vocabulario de la API y la hora canónica', () => {
        const [item] = toApiItems([line()]);

        assert.deepEqual(item, { product_id: 1, date: '2026-09-05', time: '10:00:00', quantity: 2 });
    });

    /** Las claves vacías no viajan: el contrato las declara opcionales y mandarlas vacías es ruido. */
    test('las respuestas del pack y los complementos solo viajan si los hay', () => {
        const [item] = toApiItems([line({
            event_data: { celebrant: 'Mara' },
            addons: [{ product_id: 4, quantity: 1 }],
        })]);

        assert.deepEqual(item.event_data, { celebrant: 'Mara' });
        assert.deepEqual(item.addons, [{ product_id: 4, quantity: 1 }]);
    });
});

describe('las filas que se pintan', () => {
    /**
     * ⚠️ **El emparejado va por `index`, no por posición.** Una línea cuyo producto ya no se vende no
     * se tarifica y desaparece del presupuesto; el hueco en la secuencia es la única señal de que
     * existió. Recorrer las dos listas en paralelo pinta las respuestas de una línea sobre otra.
     */
    test('cada fila toma las respuestas de SU línea aunque falten índices', () => {
        const cart = [
            line({ product_id: 1, event_data: { celebrant: 'Mara' } }),
            line({ product_id: 2 }),
            line({ product_id: 3, event_data: { celebrant: 'Leo' } }),
        ];
        const quoteLines = [
            { index: 0, product_id: 1 },
            { index: 2, product_id: 3 },
        ];
        const fields = { 1: [{ key: 'celebrant', label: 'Homenajeado' }], 3: [{ key: 'celebrant', label: 'Homenajeado' }] };

        const rows = cartRows(quoteLines, cart, fields);

        assert.equal(rows[0].event[0].value, 'Mara');
        assert.equal(rows[1].event[0].value, 'Leo', 'la segunda fila es el índice 2, no la posición 1');
    });
});

describe('respuestas del pack emparejadas con su etiqueta', () => {
    test('el orden lo pone el ESQUEMA, no las respuestas', () => {
        const fields = [{ key: 'a', label: 'A' }, { key: 'b', label: 'B' }];

        assert.deepEqual(
            eventAnswers(fields, { b: 'dos', a: 'uno' }),
            [{ key: 'a', label: 'A', value: 'uno' }, { key: 'b', label: 'B', value: 'dos' }]
        );
    });

    /** Una respuesta vacía no se pinta: es el mismo criterio que `TicketType::eventAnswers()`. */
    test('las respuestas vacías o ausentes no dan fila', () => {
        const fields = [{ key: 'a', label: 'A' }, { key: 'b', label: 'B' }, { key: 'c', label: 'C' }];

        assert.deepEqual(eventAnswers(fields, { a: '', c: null }), []);
        assert.deepEqual(eventAnswers(fields, {}), []);
    });

    /** Un cero SÍ es una respuesta: es `''` lo que significa «sin responder», no lo falso. */
    test('un cero es una respuesta', () => {
        assert.deepEqual(eventAnswers([{ key: 'age', label: 'Edad' }], { age: 0 }), [{ key: 'age', label: 'Edad', value: '0' }]);
    });

    /** Un valor que no es escalar tampoco: pintarlo daría «[object Object]». */
    test('un valor que no es escalar no se pinta', () => {
        assert.deepEqual(eventAnswers([{ key: 'a', label: 'A' }], { a: { x: 1 } }), []);
    });
});
