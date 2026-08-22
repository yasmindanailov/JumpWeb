import { test, describe, beforeEach } from 'node:test';
import assert from 'node:assert/strict';
import { createPinia, setActivePinia } from 'pinia';
import { useCartStore } from './cart.js';
import { STORAGE_KEY } from '../cart.js';

/**
 * La red del store de la CESTA.
 *
 * ⚠️ **El almacén se dobla, `cart.js` NO.** El saneado, la propiedad y la caducidad ya tienen sus
 * casos y su paridad; aquí se prueba el estado y —lo que más importa— **que las respuestas del evento
 * no lleguen NUNCA al almacén**, que es una obligación de RGPD y no una preferencia (`#38(d)`: son el
 * nombre de un menor, su edad y sus alergias, art. 9).
 */
function fakeStorage() {
    const datos = new Map();

    return {
        volcado: datos,
        getItem: (k) => (datos.has(k) ? datos.get(k) : null),
        setItem: (k, v) => datos.set(k, String(v)),
        removeItem: (k) => datos.delete(k),
    };
}

function store() {
    setActivePinia(createPinia());

    return useCartStore();
}

const LINEA = (extra = {}) => ({
    product_id: 3, date: '2026-09-28', time: '10:00', quantity: 2, addons: [], event_data: {}, ...extra,
});

describe('el store de la cesta', () => {
    beforeEach(() => {
        globalThis.window = { localStorage: fakeStorage() };
    });

    test('arranca vacía, de invitado y sin presupuesto', () => {
        const c = store();

        assert.deepEqual(c.lines, []);
        assert.equal(c.owner, null);
        assert.equal(c.quote, null);
        assert.equal(c.count, 0);
        assert.equal(c.isEmpty, true);
    });

    test('el contador sale del PRESUPUESTO, no de las líneas', () => {
        const c = store();
        c.setLines([LINEA(), LINEA()]);

        assert.equal(c.count, 0, 'sin presupuesto no hay nada tarificado que contar');

        c.setQuote({ lines: [{ total_cents: 100 }] });
        assert.equal(c.count, 1, 'la verdad la dice el servidor, no lo que haya en memoria');
    });

    test('persistir y volver a leer devuelve la cesta', () => {
        const c = store();
        c.setLines([LINEA()]);
        c.persist();

        const otra = store();
        const { lines } = otra.restore('2026-09-01');

        assert.equal(lines.length, 1);
        assert.equal(lines[0].product_id, 3);
    });

    /**
     * ⚠️⚠️ EL CASO QUE NO PUEDE FALTAR. Las respuestas del evento son datos del art. 9 y **no salen
     * de memoria**. Se busca un centinela en el volcado ENTERO del almacén, no en el campo esperado:
     * si algún día se persistieran por otra vía, el caso muerde igual.
     */
    test('las respuestas del evento NO llegan al almacén', () => {
        const c = store();
        c.setLines([LINEA()]);
        c.updateField(0, 'nombre', 'CENTINELA-MENOR');
        c.updateField(0, 'alergias', 'CENTINELA-SALUD');
        c.persist();

        assert.equal(c.lines[0].event_data.nombre, 'CENTINELA-MENOR', 'en memoria sí están');

        const volcado = [...globalThis.window.localStorage.volcado.entries()].map(([k, v]) => k + v).join('|');

        assert.ok(volcado.includes(STORAGE_KEY) || volcado.length > 0, 'algo se guardó');
        assert.ok(! volcado.includes('CENTINELA-MENOR'), 'el nombre de un menor no puede quedar en el navegador');
        assert.ok(! volcado.includes('CENTINELA-SALUD'), 'ni un dato de salud (art. 9)');
    });

    test('contestar un campo BORRA el aviso y no toca el resto', () => {
        const c = store();
        c.setLines([LINEA()]);
        c.setError('Falta algo');

        c.updateField(0, 'nombre', 'Ana');

        assert.equal(c.error, '');
        assert.equal(c.lines[0].event_data.nombre, 'Ana');

        c.updateField(99, 'nombre', 'X');
        assert.equal(c.lines.length, 1, 'un índice que no existe no crea líneas');
    });

    /** El store dice QUÉ pasó; no navega. Volver al catálogo es del embudo. */
    test('quitar la última línea avisa de que la cesta quedó vacía', () => {
        const c = store();
        c.setLines([LINEA(), LINEA({ product_id: 4 })]);
        c.setQuote({ lines: [{}, {}] });

        assert.equal(c.remove(0), false, 'todavía queda una');
        assert.equal(c.lines.length, 1);
        assert.notEqual(c.quote, null);

        assert.equal(c.remove(0), true, 'y esta sí la vacía');
        assert.equal(c.quote, null, 'sin líneas, el presupuesto anterior ya no significa nada');
    });

    test('el tope de líneas solo acepta números', () => {
        const c = store();

        c.setMaxLines('muchas');
        assert.equal(c.maxLines, 50, 'lo que no es número se ignora: el tope lo publica el servidor');
        c.setMaxLines(8);
        assert.equal(c.maxLines, 8);
    });
});
