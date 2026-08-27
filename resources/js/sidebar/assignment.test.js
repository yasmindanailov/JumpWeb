import { test, describe } from 'node:test';
import assert from 'node:assert/strict';
import {
    REASON_ADULT, REASON_OUTDATED, REASON_UNSIGNED,
    applyRejections, assignableIds, assignableOptions, assignmentRejections, dependentsById,
    needsAssignment, reconcileAssignments, toggleDependent, trimToQuantity,
} from './assignment.js';

/**
 * La red de `assignment.js` (Fase 6 · tanda 4, `docs/specs/menores-a-cargo.md` §9.9.3 D8/D9): a quién
 * se ofrece y por qué no, el conjunto acotado por la cantidad, la reconciliación con la lista viva, la
 * puerta 2 y el 422 del checkout aplicado a la cesta.
 */
const MESSAGES = { dependents: { age: ':age años' } };

const minor = (over = {}) => ({
    id: 12, name: 'Lucas', age: 9, is_minor: true,
    waiver: { mode: 'interno', signed: true, outdated: false },
    ...over,
});

describe('a quién se ofrece', () => {
    test('un menor con la exención vigente se puede marcar, con su etiqueta', () => {
        const [option] = assignableOptions([minor()], MESSAGES);

        assert.deepEqual(option, { id: 12, name: 'Lucas', label: 'Lucas · 9 años', assignable: true, reasonKey: null });
    });

    /** `[DECIDIDO owner]` #202·2: sin exención firmada no se asigna — se enseña, deshabilitado, con el porqué. */
    test('en modo interno, sin firma o con una anterior, se enseña pero NO se puede marcar', () => {
        assert.equal(assignableOptions([minor({ waiver: { mode: 'interno', signed: false, outdated: false } })])[0].reasonKey, REASON_UNSIGNED);
        assert.equal(assignableOptions([minor({ waiver: { mode: 'interno', signed: true, outdated: true } })])[0].reasonKey, REASON_OUTDATED);
        assert.equal(assignableOptions([minor({ waiver: { mode: 'interno', signed: false } })])[0].assignable, false);
    });

    test('fuera del modo interno no hay firma que comprobar', () => {
        for (const mode of ['externo', 'desactivado']) {
            assert.equal(assignableOptions([minor({ waiver: { mode, signed: false, outdated: false } })])[0].assignable, true, mode);
        }
    });

    test('quien ya tiene 18 no se puede marcar, aunque esté firmado', () => {
        const [option] = assignableOptions([minor({ is_minor: false, age: 18 })]);

        assert.equal(option.assignable, false);
        assert.equal(option.reasonKey, REASON_ADULT);
    });

    test('los ids asignables son solo los marcables, y el mapa por id lleva solo id y nombre', () => {
        const list = [minor(), minor({ id: 15, name: 'Vera', waiver: { mode: 'interno', signed: false } })];

        assert.deepEqual(assignableIds(list), [12]);
        assert.deepEqual(dependentsById(list), { 12: { id: 12, name: 'Lucas' }, 15: { id: 15, name: 'Vera' } });
        assert.deepEqual(assignableOptions(null), []);
    });
});

describe('el conjunto acotado por la cantidad', () => {
    test('marcar añade, volver a marcar quita', () => {
        assert.deepEqual(toggleDependent([], 12, 2), [12]);
        assert.deepEqual(toggleDependent([12], 15, 2), [12, 15]);
        assert.deepEqual(toggleDependent([12, 15], 12, 2), [15]);
    });

    test('con la línea llena no entra ninguno más', () => {
        assert.deepEqual(toggleDependent([12, 15], 18, 2), [12, 15]);
        assert.deepEqual(toggleDependent([12], 15, 1), [12]);
    });

    test('al bajar la cantidad se quitan los ÚLTIMOS marcados', () => {
        assert.deepEqual(trimToQuantity([12, 15, 18], 2), [12, 15]);
        assert.deepEqual(trimToQuantity([12], 0), []);
        assert.deepEqual(trimToQuantity(undefined, 3), []);
    });
});

describe('la reconciliación con la lista viva (§4.8·2)', () => {
    test('un id que ya no se puede asignar se quita de su línea y la línea sigue', () => {
        const lines = [
            { product_id: 1, quantity: 2, dependent_ids: [12, 15] },
            { product_id: 2, quantity: 1, dependent_ids: [12] },
        ];

        const { lines: next, changed } = reconcileAssignments(lines, [12]);

        assert.equal(changed, true);
        assert.deepEqual(next[0].dependent_ids, [12]);
        assert.deepEqual(next[1].dependent_ids, [12]);
        assert.equal(next[1], lines[1], 'una línea sin cambios es el MISMO objeto');
    });

    test('sin nada que quitar no se toca nada', () => {
        const lines = [{ product_id: 1, quantity: 2, dependent_ids: [] }];

        assert.deepEqual(reconcileAssignments(lines, []), { lines, changed: false });
    });
});

describe('la puerta 2: volver al carrito tras identificarse', () => {
    const row = (over = {}) => ({ is_pack: false, quantity: 2, dependent_ids: [], ...over });

    test('con menores asignables y una entrada con unidades sin asignar → hace falta', () => {
        assert.equal(needsAssignment([row()], 1), true);
        assert.equal(needsAssignment([row({ dependent_ids: [12] })], 1), true, 'a medias también');
    });

    test('sin menores asignables nunca hace falta, aunque haya entradas sueltas', () => {
        assert.equal(needsAssignment([row()], 0), false);
    });

    test('con todo asignado, o solo packs, no hace falta', () => {
        assert.equal(needsAssignment([row({ dependent_ids: [12, 15] })], 2), false);
        assert.equal(needsAssignment([row({ is_pack: true })], 2), false);
        assert.equal(needsAssignment([], 2), false);
    });
});

describe('el 422 del checkout aplicado a la cesta', () => {
    const fields = {
        'items.1.dependent_ids.0': ['Ese menor no está en tu cuenta.'],
        'items.1.dependent_ids.1': ['Falta su exención firmada.'],
        'items.3.dependent_ids': ['Has elegido más menores que entradas.'],
        'items.0.quantity': ['otro campo que no es de aquí'],
    };

    test('los rechazos se agrupan por línea y se ignora lo que no es de la asignación', () => {
        assert.deepEqual(assignmentRejections(fields), {
            1: ['Ese menor no está en tu cuenta.', 'Falta su exención firmada.'],
            3: ['Has elegido más menores que entradas.'],
        });
        assert.deepEqual(assignmentRejections(undefined), {});
    });

    test('las líneas rechazadas se quedan SIN asignar y se devuelve el primer aviso', () => {
        const lines = [
            { dependent_ids: [12] }, { dependent_ids: [12, 15] }, { dependent_ids: [] }, { dependent_ids: [18] },
        ];

        const { lines: next, changed, message } = applyRejections(lines, fields);

        assert.equal(changed, true);
        assert.deepEqual(next.map((l) => l.dependent_ids), [[12], [], [], []]);
        assert.equal(message, 'Ese menor no está en tu cuenta.');
        assert.deepEqual(applyRejections(lines, { 'items.0.quantity': ['x'] }), { lines, changed: false, message: '' });
    });
});
