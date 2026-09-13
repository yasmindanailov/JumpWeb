import test from 'node:test';
import assert from 'node:assert/strict';

// ⚠️ El módulo vive en `public/js/` porque la página lo sirve SIN compilar (ver su cabecera); el caso vive
// aquí porque `npm run test:js` solo recoge `resources/js/**/*.test.js`.
import { choice, cleanName, ficheState, parseNames, planPaste } from '../../../public/js/guest-form/logic.js';

/**
 * El pegado de la lista de nombres y el estado de una ficha (`docs/specs/celebracion-e-invitacion.md` T2).
 */

test('una lista copiada pierde viñetas, numeraciones y espacios', () => {
    assert.equal(cleanName('  1. Lucía   Serrano '), 'Lucía Serrano');
    assert.equal(cleanName('3) Mateo'), 'Mateo');
    assert.equal(cleanName('- Hugo'), 'Hugo');
    assert.equal(cleanName('• Vega'), 'Vega');
    assert.equal(cleanName('12 Bruno'), 'Bruno');
    assert.equal(cleanName('Ana María'), 'Ana María');
});

test('una numeración pegada a una palabra no es una lista', () => {
    assert.equal(cleanName('1.º Lucía'), '1.º Lucía');
});

test('uno por línea, en su orden, sin las líneas vacías', () => {
    assert.deepEqual(parseNames('Lucía\n\n  Mateo \r\nHugo\n'), ['Lucía', 'Mateo', 'Hugo']);
});

test('una sola línea con comas es una lista, no un nombre', () => {
    assert.deepEqual(parseNames('Lucía, Mateo; Hugo'), ['Lucía', 'Mateo', 'Hugo']);
});

test('varias líneas NO se parten por las comas', () => {
    // Control del caso anterior: con dos líneas, la coma es parte del texto que escribió alguien.
    assert.deepEqual(parseNames('Serrano, Lucía\nRuiz, Mateo'), ['Serrano, Lucía', 'Ruiz, Mateo']);
});

test('nada que leer da cero nombres', () => {
    assert.deepEqual(parseNames(''), []);
    assert.deepEqual(parseNames(' \n  \n'), []);
    assert.deepEqual(parseNames(undefined), []);
});

test('el pegado va solo a las fichas vacías y nunca pisa una con datos', () => {
    const fiches = [
        { index: 0, empty: false },
        { index: 1, empty: true },
        { index: 2, empty: false },
        { index: 3, empty: true },
    ];

    assert.deepEqual(planPaste(['Mateo', 'Hugo'], fiches), {
        assignments: [{ index: 1, name: 'Mateo' }, { index: 3, name: 'Hugo' }],
        placed: 2,
        overflow: 0,
        kept: 2,
    });
});

test('los nombres que no caben se cuentan, no se pierden en silencio', () => {
    const plan = planPaste(['A', 'B', 'C'], [{ index: 5, empty: true }, { index: 6, empty: false }]);

    assert.deepEqual(plan.assignments, [{ index: 5, name: 'A' }]);
    assert.equal(plan.overflow, 2);
    assert.equal(plan.kept, 1);
});

test('sin fichas vacías no se coloca ninguno', () => {
    const plan = planPaste(['A'], [{ index: 0, empty: false }]);

    assert.equal(plan.placed, 0);
    assert.equal(plan.overflow, 1);
});

test('una ficha con datos y la edad vacía dice qué le falta', () => {
    const state = ficheState([
        { required: true, filled: true, label: 'Nombre' },
        { required: true, filled: false, label: 'Edad' },
        { required: false, filled: false, label: 'Alergia' },
    ]);

    assert.deepEqual(state, { complete: false, hasData: true, missing: 'Edad' });
});

test('una ficha en blanco no dice qué le falta: sería ruido', () => {
    assert.deepEqual(
        ficheState([{ required: true, filled: false, label: 'Nombre' }]),
        { complete: false, hasData: false, missing: null },
    );
});

test('una columna opcional vacía no deja la ficha a medias', () => {
    assert.equal(ficheState([
        { required: true, filled: true, label: 'Nombre' },
        { required: false, filled: false, label: 'Alergia' },
    ]).complete, true);
});

test('una edad sin producto nunca da la ficha por lista', () => {
    assert.equal(ficheState([{ required: true, filled: true, label: 'Nombre' }], true).complete, false);
});

test('el plural sale del formato de Laravel, uno|varios', () => {
    assert.equal(choice('Hemos leído :count nombre|Hemos leído :count nombres', 1), 'Hemos leído 1 nombre');
    assert.equal(choice('Hemos leído :count nombre|Hemos leído :count nombres', 17), 'Hemos leído 17 nombres');
    assert.equal(choice('Hemos leído :count nombre|Hemos leído :count nombres', 0), 'Hemos leído 0 nombres');
});

test('y de los intervalos {0}, {1} y [2,*]', () => {
    const template = '{0} Ninguno elegido|{1} :count elegido|[2,*] :count elegidos';

    assert.equal(choice(template, 0), 'Ninguno elegido');
    assert.equal(choice(template, 1), '1 elegido');
    assert.equal(choice(template, 4), '4 elegidos');
});

test('sustituye el resto de marcadores sin confundir uno con el prefijo de otro', () => {
    assert.equal(choice(':count de :countTotal', 3, { countTotal: 20 }), '3 de 20');
});
