import { test, describe } from 'node:test';
import assert from 'node:assert/strict';
import { cambioDe, lineaListo, reciboDe, resumenDe, resumenDelPedido } from './recibo.js';

/**
 * El recibo y las líneas de la compra de la isla (T3e·3 de `specs/isla-y-landing-nueva.md` §4.10). El presupuesto y
 * el pedido tienen la FORMA real de la API (`Quote`/`QuoteLine` de `openapi/v1.yaml`, `outcome.js::buildConfirmation`).
 * Aquí no se calcula dinero (`PAY-12`): cada importe sale del servidor y solo se escribe.
 */
const textos = {
    compra: {
        cuando: {
            entrada: 'entrada', entradas: 'entradas', par: 'par', pares: 'pares',
            pregunta_calcetines: '¿Calcetines antideslizantes?', pista_calcetines: ':precio el par. Si ya los tenéis, traedlos.',
        },
        pagar: { precio_por: ':precio por :unidad', precio_el: ':precio el :unidad' },
        listo: { pedido: 'Nº de pedido :codigo' },
    },
};
const nb = (s) => s.replace(/\s/g, ' ');
const calcetin = { id: 110, price_cents: 200, max_quantity: 40 };
const pedido = { fila: 100, dia: '2026-09-26', hora: '17:00:00', n: 2, cal: 0, minimo: 1, maximo: 12, calcetin, guardian: false };
const linea = (addons = []) => ({
    index: 0, product_id: 100, product_name: 'Kids · 1 hora', is_pack: false, date: '2026-09-26', time: '17:00:00', quantity: 2,
    unit_price_cents: 800, subtotal_cents: 1600, has_deposit: false, deposit_cents: 1600, gate_remainder_cents: 0, addons, icon: 'ticket',
});
const quote = (addons, total = 1600) => ({ lines: [linea(addons)], total_cents: total, online_amount_cents: total });

describe('el recibo de «Pagar»', () => {
    test('la línea con su precio publicado, su importe y la gente cambiable entre el mínimo y lo que cabe', () => {
        const r = reciboDe({ quote: quote([]), pedido, textos });

        assert.equal(r.lineas.length, 1);
        assert.deepEqual({ ...r.lineas[0], sub: nb(r.lineas[0].sub), value: nb(r.lineas[0].value) }, {
            id: 'l0', label: 'Kids · 1 hora', sub: '8 € por entrada', value: '16 €',
            control: { n: 2, min: 1, max: 12, uno: 'entrada', varios: 'entradas' },
        });
        assert.equal(nb(r.total), '16 €');
    });

    test('sin calcetines, la línea que los ofrece con su precio; con ellos, su fila con sus pares', () => {
        const sin = reciboDe({ quote: quote([]), pedido, textos });

        assert.equal(nb(sin.calcetines.texto), '¿Calcetines antideslizantes? 2 € el par. Si ya los tenéis, traedlos.');

        const con = reciboDe({
            quote: quote([{ product_id: 110, product_name: 'Calcetines antideslizantes', quantity: 2, free_quantity: 0, subtotal_cents: 400 }], 2000),
            pedido: { ...pedido, cal: 2 },
            textos,
        });

        assert.equal(con.calcetines, null);
        assert.deepEqual({ ...con.lineas[1], sub: nb(con.lineas[1].sub), value: nb(con.lineas[1].value) }, {
            id: 'a0-110', label: 'Calcetines antideslizantes', sub: '2 € el par', value: '4 €', control: { n: 2, min: 0, max: 40, uno: 'par', varios: 'pares' },
        });
        assert.equal(nb(con.total), '20 €');
    });

    test('la cantidad que se ve es la del PEDIDO (al pulsar) y el importe, el del presupuesto (al llegar)', () => {
        const r = reciboDe({ quote: quote([]), pedido: { ...pedido, n: 3 }, textos });

        assert.equal(r.lineas[0].control.n, 3);
        assert.equal(nb(r.lineas[0].value), '16 €', 'el dinero no se adelanta: lo dice el servidor');
    });

    test('un complemento que el servidor inyectó sale sin control; una línea que no es la del pedido, también', () => {
        const r = reciboDe({ quote: quote([{ product_id: 120, product_name: 'Seguro', quantity: 2, free_quantity: 0, subtotal_cents: 100 }]), pedido: { ...pedido, fila: 999 }, textos });

        assert.equal(r.lineas[0].control, null);
        assert.equal(r.lineas[1].control, null);
        assert.equal(r.lineas[1].sub, '');
    });

    test('lo que cabe nunca queda por debajo de lo que ya se tiene', () => {
        assert.equal(reciboDe({ quote: quote([]), pedido: { ...pedido, n: 5, maximo: 3 }, textos }).lineas[0].control.max, 5);
    });

    test('cada fila dice qué cambia', () => {
        assert.deepEqual(cambioDe('l0', 3), { n: 3 });
        assert.deepEqual(cambioDe('a0-110', 1), { cal: 1 });
    });
});

describe('las líneas de la isla', () => {
    test('la de debajo: qué, cuándo y cuántos', () => {
        assert.equal(resumenDe([linea()], { textos }), 'Kids · 1 hora · sáb 26, 17:00 · 2 entradas');
        assert.equal(resumenDe([], { textos }), null);
    });

    test('la de «Listo», del pedido pagado, y el resumen de un pedido que ya existe', () => {
        const confirmacion = { code: 'R-7K2P4', total_cents: 1600, lines: [{ product_name: 'Kids · 1 hora', quantity: 1, date: '2026-09-26', time: '17:00:00' }] };

        assert.equal(lineaListo(confirmacion, { textos }), 'Sábado 26 de septiembre · 17:00 · Kids · 1 hora · 1 entrada · Nº de pedido R-7K2P4');
        assert.deepEqual({ ...resumenDelPedido(confirmacion, { textos }), total: nb(resumenDelPedido(confirmacion, { textos }).total) }, {
            summary: 'Kids · 1 hora · sáb 26, 17:00 · 1 entrada', total: '16 €',
        });
        assert.deepEqual(resumenDelPedido(null, { textos }), { summary: null, total: null });
    });
});
