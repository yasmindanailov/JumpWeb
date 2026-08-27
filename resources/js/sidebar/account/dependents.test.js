import { test, describe } from 'node:test';
import assert from 'node:assert/strict';
import { bornOnLabel, coverageKey, dependentForm, dependentNeedsSignature, dependentWaiverKey, replaceDependent } from './dependents.js';

/**
 * Lo que el cliente SÍ decide sobre un menor a cargo: qué frase, si se ofrece firmar y cómo se lee su
 * fecha. Lo que NO decide —edad, minoría, estado de la exención— lo publica el servidor y aquí se
 * fija que solo se TRADUCE (`CE-4`).
 */
const minor = (waiver = {}) => ({ id: 1, name: 'Lucas', born_on: '2017-03-12', age: 9, is_minor: true, waiver: { mode: 'interno', signed: false, outdated: false, ...waiver } });

describe('el formulario de alta', () => {
    test('nace vacío, con los dos únicos campos que el servidor acepta', () => {
        assert.deepEqual(dependentForm(), { name: '', born_on: '' });
    });
});

describe('la fecha de nacimiento', () => {
    test('se reordena a dd/mm/aaaa sin pasar por Date', () => {
        assert.equal(bornOnLabel('2017-03-12'), '12/03/2017');
    });

    test('lo que no tenga esa forma sale tal cual, nunca vacío', () => {
        assert.equal(bornOnLabel('12/03/2017'), '12/03/2017');
        assert.equal(bornOnLabel(null), '');
    });
});

describe('la frase de la exención', () => {
    test('fuera del modo interno no hay nada que decir', () => {
        assert.equal(dependentWaiverKey(minor({ mode: 'externo' })), '');
        assert.equal(dependentWaiverKey(minor({ mode: 'desactivado' })), '');
        assert.equal(dependentWaiverKey(null), '');
    });

    test('en interno: sin firmar, anterior o firmada, según lo que dijo el servidor', () => {
        assert.equal(dependentWaiverKey(minor()), 'account.dependents.waiver_unsigned');
        assert.equal(dependentWaiverKey(minor({ signed: true, outdated: true })), 'account.dependents.waiver_outdated');
        assert.equal(dependentWaiverKey(minor({ signed: true })), 'account.dependents.waiver_current');
    });
});

describe('cuándo se ofrece firmar en su nombre', () => {
    test('en interno, siendo menor, sin firma o con una anterior', () => {
        assert.equal(dependentNeedsSignature(minor()), true);
        assert.equal(dependentNeedsSignature(minor({ signed: true, outdated: true })), true);
        assert.equal(dependentNeedsSignature(minor({ signed: true })), false);
    });

    test('⚠️ nunca fuera del modo interno ni para quien ya tiene 18: el servidor lo rechazaría', () => {
        assert.equal(dependentNeedsSignature(minor({ mode: 'externo' })), false);
        assert.equal(dependentNeedsSignature({ ...minor(), is_minor: false }), false);
        assert.equal(dependentNeedsSignature(null), false);
    });
});

describe('la cobertura', () => {
    test('se marca a quien ya tiene 18; a un menor no se le dice nada', () => {
        assert.equal(coverageKey({ ...minor(), is_minor: false }), 'account.dependents.adult');
        assert.equal(coverageKey(minor()), '');
        assert.equal(coverageKey(null), '');
    });
});

describe('colocar la respuesta del servidor en la lista', () => {
    test('sustituye por id sin cambiar el orden, y añade si no estaba', () => {
        const list = [minor(), { ...minor(), id: 2, name: 'Vera' }];
        const signed = minor({ signed: true });

        assert.deepEqual(replaceDependent(list, signed).map((d) => [d.id, d.waiver.signed]), [[1, true], [2, false]]);
        assert.equal(replaceDependent(list, { ...minor(), id: 3 }).length, 3);
        assert.deepEqual(replaceDependent(null, minor()), [minor()]);
    });
});
