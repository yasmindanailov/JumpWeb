import test from 'node:test';
import assert from 'node:assert/strict';

import { createMissingReporter } from './missing.js';

/**
 * La demanda sin hueco (`#758`, la T2 de la analítica para decidir): qué sale al tracker y cuántas veces.
 */
const SEPT = new Date(2026, 8, 27);

test('un producto sin días este mes deja UN evento con el producto y el mes', () => {
    const sent = [];
    const report = createMissingReporter((name, props) => sent.push([name, props]));

    report(12, [{ date: '2026-10-03' }], SEPT);

    assert.deepEqual(sent, [['availability_missing', { product: '12', month: '2026-09' }]]);
});

test('volver a abrir el mismo producto no lo multiplica; otro producto sí cuenta', () => {
    const sent = [];
    const report = createMissingReporter((name, props) => sent.push(props));

    report(12, [], SEPT);
    report(12, [], SEPT);
    report(13, [], SEPT);

    assert.deepEqual(sent, [{ product: '12', month: '2026-09' }, { product: '13', month: '2026-09' }]);
});

test('con días este mes no sale nada', () => {
    const sent = [];
    const report = createMissingReporter((name, props) => sent.push(props));

    assert.deepEqual(report(12, [{ date: '2026-09-28' }], SEPT), []);
    assert.deepEqual(sent, []);
});

test('sin tracker no rompe: la analítica que no llega no es un fallo del cajón', () => {
    const report = createMissingReporter(undefined);

    assert.deepEqual(report(12, [], SEPT), ['2026-09']);
});
