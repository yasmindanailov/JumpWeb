import { test, describe } from 'node:test';
import assert from 'node:assert/strict';
import { complementosDe, lineaDe, meterLinea, pedidoDe } from './linea.js';

/**
 * La línea de la compra de la isla (T3e·3 de `specs/isla-y-landing-nueva.md` §4.10): la cesta de la isla es SU
 * pedido, y meter la línea la SUSTITUYE. Una cesta de mentira que apunta lo que se le pide.
 */
const calcetin = { id: 110, price_cents: 200, max_quantity: 40, name: 'Calcetines' };
const borrador = { zona: 'kids', fila: 100, dia: '2026-09-26', hora: '17:00:00', n: 2, cal: 1 };

function cestaDeMentira({ veredicto = { valid: true, quantity: 2, merges_with_index: null }, ok = true, previas = [] } = {}) {
    const llamadas = [];

    return {
        llamadas,
        lines: previas,
        setLines(lineas) { this.lines = lineas; llamadas.push(['setLines', lineas.length]); },
        async validateLine({ line }) { llamadas.push(['validateLine', this.lines.length, line.quantity]); return { ok, data: veredicto }; },
        persist() { llamadas.push(['persist', this.lines.length]); },
        async refreshQuote() { llamadas.push(['refreshQuote']); },
    };
}

describe('el pedido de la pantalla 0', () => {
    test('recuerda lo que los datos decían al salir: mínimo, lo que cabe, los calcetines y el justificante', () => {
        const p = pedidoDe(borrador, { minimo: 1, maximo: 12, calcetin, guardian: 'required' });

        assert.deepEqual(p, {
            fila: 100, dia: '2026-09-26', hora: '17:00:00', n: 2, cal: 1, minimo: 1, maximo: 12,
            calcetin: { id: 110, price_cents: 200, max_quantity: 40 }, guardian: true, evento: {}, elecciones: [],
        });
    });

    test('de una FIESTA (T3e·5): la edad de quien cumple viaja en la línea, y el menú se recuerda para rehacerla', () => {
        const p = pedidoDe({ ...borrador, fila: 105, n: 10, cal: 0 }, { evento: { age: 5 }, elecciones: [{ group: 'menu', product_id: 108 }] });

        assert.deepEqual(lineaDe(p, [{ product_id: 108, quantity: 10 }]).event_data, { age: 5 });
        assert.deepEqual(p.elecciones, [{ group: 'menu', product_id: 108 }]);
        assert.equal(p.calcetin, null, 'una fiesta no ofrece calcetines antes de pagar (`#692`·4)');
    });

    test('sin complemento por cantidad, sin pares; y el justificante OPCIONAL no viaja (la isla no lo pregunta)', () => {
        const p = pedidoDe(borrador, { guardian: 'optional' });

        assert.equal(p.cal, 0);
        assert.equal(p.guardian, false);
        assert.deepEqual(complementosDe(p), []);
    });

    test('la candidata lleva los complementos RESUELTOS, sin respuestas ni menores', () => {
        const p = pedidoDe(borrador, { calcetin });
        const resueltos = [{ product_id: 110, quantity: 1 }];

        assert.deepEqual(complementosDe(p), [{ product_id: 110, quantity: 1 }]);
        assert.deepEqual(lineaDe(p, resueltos), {
            product_id: 100, date: '2026-09-26', time: '17:00:00', quantity: 2, event_data: {}, addons: resueltos, dependent_ids: [], guardian_authorization: false,
        });
    });
});

describe('meterLinea', () => {
    const pedido = pedidoDe(borrador, { calcetin });

    test('SUSTITUYE: valida con la cesta vacía como contexto y deja solo la suya, guardada y presupuestada', async () => {
        const vieja = { product_id: 103, date: '2026-09-20', time: '11:00:00', quantity: 4 };
        const cesta = cestaDeMentira({ previas: [vieja] });
        const r = await meterLinea({ api: {}, pedido, resueltos: [], cartStore: cesta });

        assert.deepEqual(r, { ok: true, aviso: '' });
        assert.deepEqual(cesta.llamadas, [['setLines', 0], ['validateLine', 0, 2], ['setLines', 1], ['persist', 1], ['refreshQuote']]);
        assert.equal(cesta.lines[0].product_id, 100, 'la línea de otra visita no se compra con ésta');
    });

    test('la cantidad es la que el servidor admite, no la pedida', async () => {
        const cesta = cestaDeMentira({ veredicto: { valid: true, quantity: 1, merges_with_index: null } });

        await meterLinea({ api: {}, pedido, resueltos: [], cartStore: cesta });
        assert.equal(cesta.lines[0].quantity, 1);
    });

    test('si no cabe, la cesta vuelve a lo que era y llega el aviso del motor', async () => {
        const vieja = { product_id: 100, quantity: 2 };
        const cesta = cestaDeMentira({ previas: [vieja], veredicto: { valid: false, problems: [{ reason: 'cart_full' }] } });
        const r = await meterLinea({ api: {}, pedido, resueltos: [], cartStore: cesta, messages: { errors: { cart_too_large: 'La cesta está llena.', choose_one: 'Elige una opción.' } } });

        assert.deepEqual(r, { ok: false, aviso: 'La cesta está llena.' });
        assert.deepEqual(cesta.lines, [vieja]);
        assert.equal(cesta.llamadas.some(([q]) => q === 'persist' || q === 'refreshQuote'), false, 'ni se guarda ni se presupuesta');
    });

    test('un fallo de red no inventa un motivo: el genérico', async () => {
        const cesta = cestaDeMentira({ ok: false });
        const r = await meterLinea({ api: {}, pedido, resueltos: [], cartStore: cesta, messages: { errors: { choose_one: 'Elige una opción.' } } });

        assert.deepEqual(r, { ok: false, aviso: 'Elige una opción.' });
    });
});
