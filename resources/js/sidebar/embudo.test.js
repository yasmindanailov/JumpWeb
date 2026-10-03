import { test } from 'node:test';
import assert from 'node:assert/strict';
import { createFunnelFacts } from './embudo.js';
import { STEPS } from './machine.js';

/** Un embudo con su cesta y lo que emite, en orden. */
function montar(lineas = []) {
    const emitidos = [];
    const embudo = createFunnelFacts({ track: (name, props) => emitidos.push({ name, props }), lineas: () => lineas });

    return { embudo, emitidos, nombres: () => emitidos.map((e) => e.name) };
}

test('al CARRITO, cada línea entera cuenta: su producto, su fecha, su hora y su cantidad', () => {
    const { embudo, emitidos } = montar([
        { product_id: 100, date: '2026-10-10', time: '17:00', quantity: 2 },
        { product_id: 103, date: '2026-10-10', time: '17:00', quantity: 1 },
    ]);

    embudo.paso(STEPS.CATALOG, STEPS.CART);

    assert.deepEqual(emitidos, [
        { name: 'product_chosen', props: { product: 100 } },
        { name: 'date_chosen', props: { product: 100, date: '2026-10-10' } },
        { name: 'time_chosen', props: { product: 100 } },
        { name: 'line_added', props: { product: 100, qty: 2 } },
        { name: 'product_chosen', props: { product: 103 } },
        { name: 'date_chosen', props: { product: 103, date: '2026-10-10' } },
        { name: 'time_chosen', props: { product: 103 } },
        { name: 'line_added', props: { product: 103, qty: 1 } },
    ]);
});

test('una línea sin producto no cuenta, y una sin hora no cuenta la hora (la cantidad, mínimo 1)', () => {
    const { embudo, emitidos } = montar([{ product_id: null }, { product_id: 7, date: '2026-10-11' }]);

    embudo.paso(STEPS.TIME, STEPS.CART);

    assert.deepEqual(emitidos.map((e) => [e.name, e.props]), [
        ['product_chosen', { product: 7 }],
        ['date_chosen', { product: 7, date: '2026-10-11' }],
        ['line_added', { product: 7, qty: 1 }],
    ]);
});

test('a PAGAR se llega identificado: «checkout» si acaba de entrar, «session» si ya lo estaba', () => {
    const { embudo, emitidos } = montar();

    embudo.paso(STEPS.CART, STEPS.IDENTIFY);
    embudo.paso(STEPS.IDENTIFY, STEPS.PAY);
    embudo.paso(STEPS.CART, STEPS.PAY);
    embudo.paso(STEPS.VERIFY_EMAIL, STEPS.PAY);

    assert.deepEqual(emitidos, [
        { name: 'identify_started', props: { method: 'checkout' } },
        { name: 'identified', props: { method: 'checkout' } },
        { name: 'identified', props: { method: 'session' } },
        { name: 'identified', props: { method: 'checkout' } },
    ]);
});

test('el correo por verificar cuenta al llegar a su paso', () => {
    const { embudo, nombres } = montar();

    embudo.paso(STEPS.IDENTIFY, STEPS.VERIFY_EMAIL);

    assert.deepEqual(nombres(), ['email_verification_pending']);
});

test('a la PASARELA, el inicio del pago con el ÚLTIMO total presupuestado, aunque la cesta ya se vaciara', () => {
    const { embudo, emitidos } = montar();

    embudo.precio(3200);
    embudo.precio(4800);
    // La cesta se vacía con su presupuesto justo antes de saltar: un total vacío no borra el anterior.
    embudo.precio(0);
    embudo.precio(null);
    embudo.paso(STEPS.PAY, STEPS.REDIRECTING);

    assert.deepEqual(emitidos, [{ name: 'pay_started', props: { amount_cents: 4800 } }]);
});

test('los demás pasos (fecha, hora, desenlaces) no emiten nada aquí: los cuentan quien los conoce', () => {
    const { embudo, nombres } = montar([{ product_id: 1, date: '2026-10-12', time: '18:00', quantity: 1 }]);

    for (const to of [STEPS.CATALOG, STEPS.DATE, STEPS.TIME, STEPS.CONFIRMED, STEPS.DECLINED, STEPS.VERIFYING]) {
        embudo.paso(STEPS.CART, to);
    }

    assert.deepEqual(nombres(), []);
});
