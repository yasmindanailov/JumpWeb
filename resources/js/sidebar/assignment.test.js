import { test, describe } from 'node:test';
import assert from 'node:assert/strict';
import { REASON_ADULT, REASON_OUTDATED, REASON_UNSIGNED, applyRejections, assignableIds, assignableOptions, assignmentRejections, dependentsById, guardianIsBlocked, needsAssignment, reconcileAssignments, toggleDependent, trimToQuantity, whoSummaryKey } from './assignment.js';

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
    /**
     * ⚠️ **Nombre y edad viajan POR SEPARADO desde el 2026-08-28** (§9.11 D·2): antes salían fundidos
     * en un `label` («Lucas · 9 años») y el selector no podía darles peso distinto, que es lo que hizo
     * que el owner leyera el motivo de una fila apagada como parte del nombre. La forma ENTERA se
     * asevera con `deepEqual` a propósito: un campo nuevo que se cuele sin decidirse pone esto rojo.
     *
     * ⚠️ **Re-apuntado, no reescrito, al retirar `statusKey`** (`#242`): su sujeto es LA FORMA de la
     * opción, que sigue viva. El campo se fue; el `deepEqual` se queda, que es lo que lo hace útil.
     */
    test('un menor con la exención vigente se puede marcar, con su nombre y su edad', () => {
        const [option] = assignableOptions([minor()], MESSAGES);

        assert.deepEqual(option, {
            id: 12, name: 'Lucas', age: '9 años', assignable: true, reasonKey: null,
        });
    });

    /**
     * ⚠️ **Re-apuntado al retirar el estado positivo** (`#242`). El caso decía «fuera del modo interno
     * la fila no lleva estado»; de sus dos mitades, la del rótulo se fue con él y **la de CONDUCTA se
     * queda, que es la que importa**: sin modo interno no hay firma que comprobar, así que un menor se
     * puede marcar aunque no tenga nada firmado. Perder eso sería dejar de vender en las instalaciones
     * que no gestionan la exención aquí.
     */
    test('fuera del modo interno se puede marcar aunque no haya nada firmado', () => {
        for (const mode of ['externo', 'desactivado']) {
            const [option] = assignableOptions([minor({ waiver: { mode, signed: false, outdated: false } })], MESSAGES);

            assert.equal(option.assignable, true, mode);
            assert.equal(option.reasonKey, null, mode);
        }
    });

    /** Y quien no se puede marcar lleva su MOTIVO, que es lo único que no es obvio de una fila apagada. */
    test('quien no se puede marcar lleva motivo', () => {
        const [option] = assignableOptions([minor({ waiver: { mode: 'interno', signed: false, outdated: false } })], MESSAGES);

        assert.equal(option.assignable, false);
        assert.equal(option.reasonKey, REASON_UNSIGNED);
    });

    /** La edad se traduce con el grupo del EMBUDO; sin diccionario no rompe, sale vacía. */
    test('la edad sale ya traducida, y sin diccionario no revienta', () => {
        assert.equal(assignableOptions([minor()], MESSAGES)[0].age, '9 años');
        assert.equal(assignableOptions([minor()])[0].age, '');
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
        'items.1.dependent_ids.1': ['Falta su descargo firmado.'],
        'items.3.dependent_ids': ['Has elegido más menores que entradas.'],
        'items.0.quantity': ['otro campo que no es de aquí'],
    };

    test('los rechazos se agrupan por línea y se ignora lo que no es de la asignación', () => {
        assert.deepEqual(assignmentRejections(fields), {
            1: ['Ese menor no está en tu cuenta.', 'Falta su descargo firmado.'],
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

/**
 * **El rótulo del bloque plegado y la casilla que no se puede marcar** (`#401`).
 *
 * Las dos reglas salieron del componente porque `SidebarComponentBudgetTest` lo pidió, y aquí es
 * donde ganan la red que un árbol no puede darles: un árbol dice qué se pintó, no qué rama se eligió.
 */
test('el rótulo del bloque dice lo que hay dentro, en sus cinco casos', () => {
    assert.equal(whoSummaryKey({ dependents: 0, guardian: false }), 'who_block.none');
    assert.equal(whoSummaryKey({ dependents: 2, guardian: false }), 'who_block.some');
    assert.equal(whoSummaryKey({ dependents: 0, guardian: true }), 'who_block.guardian');
    assert.equal(whoSummaryKey({ dependents: 2, guardian: true }), 'who_block.both');
    // `#567` — el bloque trae SOLO el justificante (no hay menores que ofrecer) y no hay nada marcado:
    // decir «menores» ahí sería falso.
    assert.equal(whoSummaryKey({ dependents: 0, guardian: false, offers: false }), 'who_block.guardian_only');
    // …pero marcado o exigido, manda el justificante, ofrezca menores o no.
    assert.equal(whoSummaryKey({ dependents: 0, guardian: true, offers: false }), 'who_block.guardian');
    // Sin argumentos: el caso que se pinta antes de elegir nada.
    assert.equal(whoSummaryKey(), 'who_block.none');
});

test('el justificante no se puede marcar sin plazas libres, y sí se puede DESmarcar', () => {
    // ❗ El caso del owner: una entrada, asignada a su hija. No queda plaza para un invitado.
    assert.equal(guardianIsBlocked({ quantity: 1, dependents: 1, checked: false }), true);
    // Con una plaza libre, se puede.
    assert.equal(guardianIsBlocked({ quantity: 2, dependents: 1, checked: false }), false);
    // ⚠️ Ya marcado NO se bloquea aunque no queden plazas: si no, quien se equivoca se queda
    // atrapado con una casilla que no puede apagar.
    assert.equal(guardianIsBlocked({ quantity: 1, dependents: 1, checked: true }), false);
    // Sin menores asignados nunca estorba.
    assert.equal(guardianIsBlocked({ quantity: 1, dependents: 0, checked: false }), false);
});
