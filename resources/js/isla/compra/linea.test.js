import { test, describe } from 'node:test';
import assert from 'node:assert/strict';
import { complementosDe, conLaCesta, esHoraLlena, lineaDe, lineasDe, meterLinea, meterLineas, pedidoDe, resolverOtras, resolverOtrasConOferta } from './linea.js';

/**
 * La línea de la compra de la isla (T3e·3 de `specs/isla-y-landing-nueva.md` §4.10): la cesta de la isla es SU
 * pedido, y meterlo la SUSTITUYE. Desde la L2 (`specs/otra-zona.md`, `#878`), con las líneas de la otra zona: cada una
 * validada con las anteriores de contexto, todo o nada. Una cesta de mentira que apunta lo que se le pide.
 */
const calcetin = { id: 110, price_cents: 200, max_quantity: 40, name: 'Calcetines' };
const borrador = { zona: 'kids', fila: 100, dia: '2026-09-26', hora: '17:00:00', n: 2, cal: 1 };

/** `veredicto`: el mismo para todas, o una función `(línea, contexto) => veredicto` (cada una el suyo). */
function cestaDeMentira({ veredicto = { valid: true, quantity: 2, merges_with_index: null }, ok = true, previas = [] } = {}) {
    const llamadas = [];

    return {
        llamadas,
        lines: previas,
        setLines(lineas) { this.lines = lineas; llamadas.push(['setLines', lineas.length]); },
        async validateLine({ line }) {
            llamadas.push(['validateLine', this.lines.length, line.product_id]);

            return { ok, data: typeof veredicto === 'function' ? veredicto(line, this.lines) : veredicto };
        },
        persist() { llamadas.push(['persist', this.lines.length]); },
        async refreshQuote() { llamadas.push(['refreshQuote']); },
    };
}

describe('el pedido de la pantalla 0', () => {
    test('recuerda lo que los datos decían al salir: mínimo, lo que cabe, los calcetines y el justificante', () => {
        const p = pedidoDe(borrador, { minimo: 1, maximo: 12, calcetin, guardian: 'required' });

        assert.deepEqual(p, {
            fiesta: false, fila: 100, dia: '2026-09-26', hora: '17:00:00', n: 2, cal: 1, minimo: 1, maximo: 12,
            calcetin: { id: 110, price_cents: 200, max_quantity: 40 }, guardian: true, evento: {}, elecciones: [], extras: [], otras: [],
        });
        // Si es una FIESTA, del borrador (T6c·3): el recibo llama «niños» a sus invitados y «personas» a los de una excursión.
        assert.equal(pedidoDe({ ...borrador, fiesta: true }).fiesta, true);
    });

    test('de una FIESTA con hora extra (T6b·3, `#836`): se recuerda y se PIDE, así «Pagar» no la pierde al rehacer la línea', () => {
        const p = pedidoDe({ ...borrador, fila: 105, n: 10, cal: 0 }, { evento: { age: 5 }, extras: [{ product_id: 317, quantity: 1, sobra: true }] });

        assert.deepEqual(p.extras, [{ product_id: 317, quantity: 1 }]);
        assert.deepEqual(complementosDe(p), [{ product_id: 317, quantity: 1 }]);
        assert.deepEqual(complementosDe({ ...pedidoDe(borrador, { calcetin }), extras: [{ product_id: 317, quantity: 1 }] }), [{ product_id: 110, quantity: 1 }, { product_id: 317, quantity: 1 }]);
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
        assert.deepEqual(cesta.llamadas, [['setLines', 0], ['validateLine', 0, 100], ['setLines', 1], ['persist', 1], ['refreshQuote']]);
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

        assert.deepEqual(r, { ok: false, aviso: 'La cesta está llena.', horaLlena: false, fila: 100 });
        assert.deepEqual(cesta.lines, [vieja]);
        assert.equal(cesta.llamadas.some(([q]) => q === 'persist' || q === 'refreshQuote'), false, 'ni se guarda ni se presupuesta');
    });

    test('un fallo de red no inventa un motivo: el genérico', async () => {
        const cesta = cestaDeMentira({ ok: false });
        const r = await meterLinea({ api: {}, pedido, resueltos: [], cartStore: cesta, messages: { errors: { choose_one: 'Elige una opción.' } } });

        assert.deepEqual(r, { ok: false, aviso: 'Elige una opción.', horaLlena: false, fila: 100 });
    });

    test('la HORA llena al continuar (`#822`): la compra lo sabe, para proponer las cercanas', async () => {
        for (const reason of ['sold_out', 'time_unavailable']) {
            const cesta = cestaDeMentira({ veredicto: { valid: false, problems: [{ reason }] } });
            const r = await meterLinea({ api: {}, pedido, resueltos: [], cartStore: cesta, messages: { errors: { choose_one: 'Elige una opción.' } } });

            assert.equal(r.ok, false, reason);
            assert.equal(r.horaLlena, true, reason);
        }
        assert.equal(esHoraLlena([{ reason: 'event_field_required' }, { reason: 'sold_out' }]), true, 'entre otros problemas, también');
        assert.equal(esHoraLlena([{ reason: 'cart_full' }]), false);
        assert.equal(esHoraLlena(undefined), false);
    });
});

describe('la OTRA ZONA en el pedido (L2, `otra-zona.md` §4.1)', () => {
    const jump = { fila: 103, n: 1 };

    test('las líneas de la otra zona: sin la fila del pedido (se funde en ella) ni repetidas, y sin las que no son nada', () => {
        const p = pedidoDe(borrador, { otras: [jump, { fila: 103, n: 2, guardian: 'required' }, { fila: 100, n: 3 }, { fila: 104, n: 0 }, { fila: 'x', n: 1 }] });

        assert.deepEqual(p.otras, [{ fila: 103, n: 3, guardian: false, minimo: 1, maximo: null, extras: [], elecciones: [] }], 'la misma fila, una línea con su gente sumada');
        assert.equal(p.n, 5, 'la misma fila del pedido no es otra línea: es más gente en la suya');
        assert.equal(pedidoDe(borrador, { otras: [{ fila: 103, n: 1, guardian: 'required' }] }).otras[0].guardian, true, 'el justificante obligatorio viaja');
        assert.equal(pedidoDe(borrador).n, 2, 'sin otras, la gente de siempre');
    });

    test('`#882`: cada una lleva lo elegido en SU tarjeta —sus complementos y sus grupos—; al fundirse, los de la primera', () => {
        const hora = { product_id: 140, quantity: 1, sobra: 'x' };
        const menu = { group: 'menu', product_id: 7, sobra: 'y' };
        const p = pedidoDe(borrador, { otras: [{ fila: 104, n: 1, extras: [hora], elecciones: [menu] }, { fila: 104, n: 2, extras: [{ product_id: 9, quantity: 3 }] }] });

        assert.deepEqual(p.otras, [{ fila: 104, n: 3, guardian: false, minimo: 1, maximo: null, extras: [{ product_id: 140, quantity: 1 }], elecciones: [{ group: 'menu', product_id: 7 }] }]);
    });

    test('las líneas candidatas: la suya primero y las otras con el MISMO día y hora (D1-A) y lo que el servidor resolvió de cada una', () => {
        const p = pedidoDe(borrador, { calcetin, otras: [jump, { fila: 104, n: 2, guardian: 'required' }] });
        const lineas = lineasDe(p, [{ product_id: 110, quantity: 1 }], { 103: [{ product_id: 150, quantity: 1 }] });

        assert.deepEqual(lineas.map((l) => [l.product_id, l.date, l.time, l.quantity]), [[100, '2026-09-26', '17:00:00', 2], [103, '2026-09-26', '17:00:00', 1], [104, '2026-09-26', '17:00:00', 2]]);
        assert.deepEqual(lineas[0].addons, [{ product_id: 110, quantity: 1 }], 'los calcetines, en la primera');
        assert.deepEqual(lineas[1].addons, [{ product_id: 150, quantity: 1 }]);
        assert.deepEqual(lineas[2].addons, [], 'sin nada resuelto, ninguno');
        assert.deepEqual(lineas.map((l) => l.guardian_authorization), [false, false, true]);
    });

    test('se meten EN ORDEN y cada una con las ANTERIORES de contexto (`AFORO-02`; dentro, competiría consigo misma)', async () => {
        const cesta = cestaDeMentira({ veredicto: (linea) => ({ valid: true, quantity: linea.quantity, merges_with_index: null }) });
        const p = pedidoDe(borrador, { otras: [jump] });
        const r = await meterLinea({ api: {}, pedido: p, resueltos: [], cartStore: cesta });

        assert.deepEqual(r, { ok: true, aviso: '' });
        assert.deepEqual(cesta.llamadas, [
            ['setLines', 0], ['validateLine', 0, 100],
            ['setLines', 1], ['validateLine', 1, 103],
            ['setLines', 2], ['persist', 2], ['refreshQuote'],
        ]);
        assert.deepEqual(cesta.lines.map((l) => [l.product_id, l.quantity]), [[100, 2], [103, 1]]);
    });

    test('TODO O NADA: si la segunda no cabe, la cesta vuelve a lo que era, nada se guarda y se dice cuál', async () => {
        const vieja = { product_id: 104, date: '2026-09-20', time: '11:00:00', quantity: 4 };
        const cesta = cestaDeMentira({
            previas: [vieja],
            veredicto: (linea) => (linea.product_id === 103 ? { valid: false, problems: [{ reason: 'sold_out' }] } : { valid: true, quantity: 2, merges_with_index: null }),
        });
        const r = await meterLineas({ api: {}, lineas: lineasDe(pedidoDe(borrador, { otras: [jump] }), []), cartStore: cesta, messages: { errors: { choose_one: 'Elige una opción.' } } });

        assert.deepEqual(r, { ok: false, aviso: 'Elige una opción.', horaLlena: true, fila: 103 });
        assert.deepEqual(cesta.lines, [vieja], 'ni la primera se queda');
        assert.equal(cesta.llamadas.some(([q]) => q === 'persist' || q === 'refreshQuote'), false);
    });

    test('si el servidor FUNDE una con otra (`merges_with_index`), la cesta suma su gente', async () => {
        const cesta = cestaDeMentira({
            veredicto: (linea, contexto) => ({ valid: true, quantity: linea.quantity, merges_with_index: contexto.length ? 0 : null }),
        });

        await meterLineas({ api: {}, lineas: [{ product_id: 100, quantity: 2 }, { product_id: 100, quantity: 1 }], cartStore: cesta });
        assert.deepEqual(cesta.lines.map((l) => [l.product_id, l.quantity]), [[100, 3]]);
    });

    test('lo que el servidor resuelve de cada una: en paralelo, con su día, su hora y su gente; sin otras, nada que pedir', async () => {
        const llamadas = [];
        const api = { post: async (ruta, cuerpo) => { llamadas.push([ruta, cuerpo]); return { ok: true, data: { selection: [{ product_id: 150, quantity: cuerpo.quantity }] } }; } };
        const p = pedidoDe(borrador, { otras: [{ fila: 103, n: 2 }] });

        assert.deepEqual(await resolverOtras({ api, pedido: p }), { 103: [{ product_id: 150, quantity: 2 }] });
        assert.deepEqual(llamadas, [['/catalog/products/103/addons', { quantity: 2, date: '2026-09-26', time: '17:00:00', addons: [], choices: [] }]]);
        assert.deepEqual(await resolverOtras({ api, pedido: pedidoDe(borrador) }), {});
        assert.equal(llamadas.length, 1, 'sin otras, ninguna petición');
        assert.equal(await resolverOtras({ api: { post: async () => ({ ok: false }) }, pedido: p }), null, 'una que no contesta: no se mete a medias');
    });

    test('`#882`: con lo elegido en su tarjeta, y de vuelta también lo que su tarjeta pinta a esa hora (`ofertas`)', async () => {
        const llamadas = [];
        const singles = [{ product_id: 140, available: true, note: '8,00 €' }];
        const groups = [{ key: 'menu', options: [] }];
        const api = { post: async (ruta, cuerpo) => { llamadas.push([ruta, cuerpo]); return { ok: true, data: { selection: [{ product_id: 140, quantity: 1 }], singles, groups } }; } };
        const p = pedidoDe(borrador, { otras: [{ fila: 104, n: 2, extras: [{ product_id: 140, quantity: 1 }], elecciones: [{ group: 'menu', product_id: 7 }] }] });

        assert.deepEqual(await resolverOtrasConOferta({ api, pedido: p }), { resueltos: { 104: [{ product_id: 140, quantity: 1 }] }, ofertas: { 104: { singles, groups } } });
        assert.deepEqual(llamadas, [['/catalog/products/104/addons', {
            quantity: 2, date: '2026-09-26', time: '17:00:00', addons: [{ product_id: 140, quantity: 1 }], choices: [{ group: 'menu', product_id: 7 }],
        }]]);
        assert.deepEqual(await resolverOtrasConOferta({ api, pedido: pedidoDe(borrador) }), { resueltos: {}, ofertas: {} });
        assert.equal(await resolverOtrasConOferta({ api: { post: async () => ({ ok: false }) }, pedido: p }), null);
    });

    test('las cantidades del pedido, las de la CESTA, leídas por PRODUCTO (con varias líneas, `lines[0]` ya no es la suya)', () => {
        const p = pedidoDe(borrador, { otras: [jump] });
        const conCesta = conLaCesta(p, [{ product_id: 103, quantity: 1 }, { product_id: 100, quantity: 4 }]);

        assert.deepEqual([conCesta.n, conCesta.otras[0].n], [4, 1]);
        assert.deepEqual([conLaCesta(p, []).n, conLaCesta(p, []).otras[0].n], [2, 1], 'sin su línea en la cesta, la del pedido');
    });
});
