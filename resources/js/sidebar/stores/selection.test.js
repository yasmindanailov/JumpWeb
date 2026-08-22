import { test, describe } from 'node:test';
import assert from 'node:assert/strict';
import { createPinia, setActivePinia } from 'pinia';
import { useSelectionStore } from './selection.js';

function store() {
    setActivePinia(createPinia());

    return useSelectionStore();
}

describe('el store de la línea en construcción', () => {
    test('arranca en blanco', () => {
        const s = store();

        assert.equal(s.quantity, 0);
        assert.deepEqual(s.addons, { groups: [], singles: [] });
        assert.deepEqual(s.eventData, {});
        assert.equal(s.line, null);
    });

    test('contestar un campo conserva los anteriores', () => {
        const s = store();
        s.answer('nombre', 'Ana');
        s.answer('edad', '8');
        s.answer('nombre', 'Ana María');

        assert.deepEqual(s.eventData, { nombre: 'Ana María', edad: '8' });
    });

    test('vaciar deja TODO en blanco: es la línea que ya viajó a la cesta', () => {
        const s = store();
        s.setQuantity(4);
        s.setAddons({ groups: [{ id: 1 }], singles: [] });
        s.setChoices([2]);
        s.setQuantities([1]);
        s.answer('nombre', 'Ana');
        s.setLine({ id: 7 });
        s.setResolved([{ id: 7 }]);

        s.clear();

        assert.equal(s.quantity, 0);
        assert.deepEqual(s.addons, { groups: [], singles: [] });
        assert.deepEqual(s.choices, []);
        assert.deepEqual(s.quantities, []);
        assert.deepEqual(s.eventData, {}, 'las respuestas del evento no se quedan de una línea a otra');
        assert.equal(s.line, null);
        assert.deepEqual(s.resolved, []);
    });

    test('lo que no es lista se ignora sin romper', () => {
        const s = store();

        for (const basura of [null, undefined, 'x', 3, {}]) {
            s.setChoices(basura);
            s.setQuantities(basura);
            s.setResolved(basura);
            assert.deepEqual(s.choices, []);
            assert.deepEqual(s.quantities, []);
            assert.deepEqual(s.resolved, []);
        }
    });
});
