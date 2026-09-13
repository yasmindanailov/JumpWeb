import { test, describe } from 'node:test';
import assert from 'node:assert/strict';
import { lineProblems } from './line-problems.js';

/**
 * La red de `lineProblems()`: el «no» del servidor al añadir una línea, traducido a qué campo se
 * resalta y qué aviso se compone. Antes vivía en el orquestador sin ningún caso.
 */
const MESSAGES = {
    errors: {
        field_required: 'Obligatorio.',
        cart_too_large: 'La cesta tiene demasiadas líneas.',
        choose_one: 'Elige producto, día y hora.',
        fields_missing: 'Faltan: :fields.',
        celebrant_age_between: 'Es para cumpleaños de :min a :max años.',
        celebrant_age_from: 'Es para cumpleaños desde los :min años.',
        celebrant_age_up_to: 'Es para cumpleaños de hasta :max años.',
        celebrant_age_try: 'Para esa edad, elige «:product».',
        celebrant_age_generic: 'La edad no encaja.',
    },
};

const FIELDS = [{ key: 'celebrant', label: 'Homenajeado' }, { key: 'age', label: 'Edad' }];

describe('el «no» al añadir una línea', () => {
    test('los campos obligatorios que faltan se resaltan uno a uno Y se nombran en el aviso', () => {
        const outcome = lineProblems([
            { reason: 'event_field_required', field: 'celebrant' },
            { reason: 'event_field_required', field: 'age' },
        ], FIELDS, MESSAGES);

        assert.deepEqual(outcome.fieldErrors, { celebrant: 'Obligatorio.', age: 'Obligatorio.' });
        assert.equal(outcome.error, 'Faltan: Homenajeado, Edad.');
    });

    test('un campo sin etiqueta en el esquema se resalta pero no se nombra', () => {
        const outcome = lineProblems([{ reason: 'event_field_required', field: 'other' }], FIELDS, MESSAGES);

        assert.deepEqual(outcome.fieldErrors, { other: 'Obligatorio.' });
        assert.equal(outcome.error, '', 'sin etiqueta no hay resumen que componer');
    });

    test('la cesta llena tiene su aviso; los tres motivos de selección comparten el de siempre', () => {
        assert.equal(lineProblems([{ reason: 'cart_full' }], [], MESSAGES).error, 'La cesta tiene demasiadas líneas.');

        for (const reason of ['product_unavailable', 'time_not_offered', 'sold_out']) {
            assert.equal(lineProblems([{ reason }], [], MESSAGES).error, 'Elige producto, día y hora.', reason);
        }
    });

    test('los campos que faltan MANDAN sobre el aviso de selección', () => {
        const outcome = lineProblems([{ reason: 'sold_out' }, { reason: 'event_field_required', field: 'age' }], FIELDS, MESSAGES);

        assert.equal(outcome.error, 'Faltan: Edad.');
    });

    test('la edad del cumpleañero fuera de tramo dice el tramo del pack y recomienda el que la admite (#588)', () => {
        const fields = [{ key: 'age', label: 'Edad', min: 4, max: 7 }];
        const outcome = lineProblems([
            { reason: 'celebrant_age_out_of_range', field: 'age', suggestion: { product_id: 9, name: 'Pack Jump' } },
        ], fields, MESSAGES);

        assert.deepEqual(outcome.fieldErrors, { age: 'Es para cumpleaños de 4 a 7 años. Para esa edad, elige «Pack Jump».' });
        assert.equal(outcome.error, '', 'el aviso va en su campo, no en el resumen');
    });

    test('sin recomendación se dice solo el tramo, y un tramo abierto se escribe por su lado', () => {
        const desde = lineProblems([{ reason: 'celebrant_age_out_of_range', field: 'age', suggestion: null }],
            [{ key: 'age', min: 8, max: null }], MESSAGES);
        assert.equal(desde.fieldErrors.age, 'Es para cumpleaños desde los 8 años.');

        const hasta = lineProblems([{ reason: 'celebrant_age_out_of_range', field: 'age' }],
            [{ key: 'age', min: null, max: 7 }], MESSAGES);
        assert.equal(hasta.fieldErrors.age, 'Es para cumpleaños de hasta 7 años.');

        const sinTramo = lineProblems([{ reason: 'celebrant_age_out_of_range', field: 'age' }], [], MESSAGES);
        assert.equal(sinTramo.fieldErrors.age, 'La edad no encaja.');
    });

    test('sin problemas no hay nada que enseñar, y una entrada rara no revienta', () => {
        assert.deepEqual(lineProblems([], FIELDS, MESSAGES), { fieldErrors: {}, error: '' });
        assert.deepEqual(lineProblems(undefined, undefined, MESSAGES), { fieldErrors: {}, error: '' });
        assert.equal(lineProblems([null], FIELDS, MESSAGES).error, 'Elige producto, día y hora.');
    });
});
