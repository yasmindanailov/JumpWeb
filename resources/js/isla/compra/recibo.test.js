import { test, describe } from 'node:test';
import assert from 'node:assert/strict';
import { cambioDe, hoyPagas, lineaListo, marcasDe, reciboDe, resumenDe, resumenDeLaCesta, resumenDelPedido } from './recibo.js';

/**
 * El recibo y las líneas de la compra de la isla (T3e·3 de `specs/isla-y-landing-nueva.md` §4.10). El presupuesto y
 * el pedido tienen la FORMA real de la API (`Quote`/`QuoteLine` de `openapi/v1.yaml`, `outcome.js::buildConfirmation`).
 * Aquí no se calcula dinero (`PAY-12`): cada importe sale del servidor y solo se escribe.
 */
const textos = {
    compra: {
        cuando: {
            entrada: 'entrada', entradas: 'entradas', par: 'par', pares: 'pares', nino: 'niño', ninos: 'niños',
            pregunta_calcetines: '¿Calcetines antideslizantes?', pista_calcetines: ':precio el par. Si ya los tenéis, traedlos.',
        },
        pagar: {
            precio_por: ':precio por :unidad', precio_el: ':precio el :unidad', hoy_pagas: 'Hoy pagas :importe',
            senal: 'Hoy pagas :senal de señal; el resto, :resto, el día de la fiesta.',
        },
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
            summary: 'Kids · 1 hora · sáb 26, 17:00 · 1 entrada', total: '16 €', today: null,
        });
        assert.deepEqual(resumenDelPedido(null, { textos }), { summary: null, total: null, today: null });
    });
});

describe('el recibo de una FIESTA (T3e·5)', () => {
    const pedido = { fila: 105, dia: '2026-09-26', hora: '17:00:00', n: 10, cal: 0, minimo: 8, maximo: 20, calcetin: null, elecciones: [{ group: 'menu', product_id: 107 }] };
    const lineaPack = (menu) => ({
        index: 0, product_id: 105, product_name: 'Pack Kids', is_pack: true, date: '2026-09-26', time: '17:00:00', quantity: 10,
        unit_price_cents: 1495, subtotal_cents: 14950, has_deposit: true, deposit_cents: 5000, gate_remainder_cents: menu.resto,
        addons: [{ product_id: menu.id, product_name: menu.nombre, quantity: 10, free_quantity: menu.gratis, subtotal_cents: menu.importe }], icon: 'party',
    });
    const quote = (menu, total) => ({ lines: [lineaPack(menu)], total_cents: total, online_amount_cents: 5000 });

    test('el menú incluido va en el rótulo del pack; los niños, entre su mínimo y su máximo; debajo, la SEÑAL', () => {
        const r = reciboDe({ quote: quote({ id: 107, nombre: 'Menú 1', importe: 0, gratis: 10, resto: 9950 }, 14950), pedido, textos });

        assert.equal(r.lineas.length, 1, 'el menú sin coste no ocupa fila');
        assert.equal(r.lineas[0].label, 'Pack Kids · Menú 1');
        assert.equal(nb(r.lineas[0].sub), '14,95 € por niño');
        assert.deepEqual(r.lineas[0].control, { n: 10, min: 8, max: 20, uno: 'niño', varios: 'niños' });
        assert.equal(nb(r.nota), 'Hoy pagas 50 € de señal; el resto, 99,50 €, el día de la fiesta.');
        assert.equal(r.calcetines, null, 'una fiesta no ofrece calcetines aquí (`#692`·4)');
    });

    test('un menú que cuesta, en su propia fila con su importe del servidor (sin sumarlo al precio por niño)', () => {
        const r = reciboDe({ quote: quote({ id: 108, nombre: 'Menú 2', importe: 2000, gratis: 0, resto: 11950 }, 16950), pedido: { ...pedido, elecciones: [{ group: 'menu', product_id: 108 }] }, textos });

        assert.equal(r.lineas[0].label, 'Pack Kids');
        assert.deepEqual({ ...r.lineas[1], value: nb(r.lineas[1].value) }, { id: 'a0-108', label: 'Menú 2', sub: '', value: '20 €', control: null });
        assert.equal(nb(r.nota), 'Hoy pagas 50 € de señal; el resto, 119,50 €, el día de la fiesta.');
    });

    test('debajo, la línea en niños y «Hoy pagas» la señal; en «Listo», también en niños', () => {
        const q = quote({ id: 107, nombre: 'Menú 1', importe: 0, gratis: 10, resto: 9950 }, 14950);

        assert.deepEqual({ ...resumenDeLaCesta(q, { textos }), total: nb(resumenDeLaCesta(q, { textos }).total), today: nb(resumenDeLaCesta(q, { textos }).today) }, {
            summary: 'Pack Kids · sáb 26, 17:00 · 10 niños', total: '149,50 €', today: 'Hoy pagas 50 €',
        });
        assert.equal(hoyPagas(1600, 1600, { textos }), null, 'sin señal no hay «hoy pagas»');
        // Pagada, la fiesta trae su formulario de invitados (`guest_form_url`): es lo que la hace fiesta en «Listo».
        assert.equal(lineaListo({ code: 'R-1', lines: [{ product_name: 'Pack Kids', quantity: 10, is_pack: true, date: '2026-09-26', time: '17:00:00', guest_form_url: '/reserva/7/datos-invitados' }] }, { textos }),
            'Sábado 26 de septiembre · 17:00 · Pack Kids · 10 niños · Nº de pedido R-1');
    });
});

/**
 * **Un pack SIN lista de invitados** (una excursión, T6c·3): no es una fiesta. Su gente son «personas» y el resto se paga
 * «el día de la visita». Mientras se compra lo dice el pedido (`pedido.fiesta`, de `linea.js::pedidoDe`); después, la línea
 * del pedido, que no trae formulario de invitados. Visto en el navegador el 28-09: «60 niños» y «el día de la fiesta».
 */
describe('el recibo de un pack que no es una fiesta (T6c·3)', () => {
    const conPersonas = { ...textos, compra: { ...textos.compra, cuando: { ...textos.compra.cuando, persona: 'persona', personas: 'personas' }, pagar: { ...textos.compra.pagar, senal_visita: 'Hoy pagas :senal de señal; el resto, :resto, el día de la visita.' } } };
    const pedido = { fiesta: false, fila: 395, dia: '2026-10-05', hora: '10:00:00', n: 60, cal: 0, minimo: 30, maximo: 100, calcetin: null, elecciones: [] };
    const excursion = {
        index: 0, product_id: 395, product_name: 'Excursión escolar · 2 horas', is_pack: true, date: '2026-10-05', time: '10:00:00', quantity: 60,
        unit_price_cents: 1500, subtotal_cents: 90000, has_deposit: true, deposit_cents: 10000, gate_remainder_cents: 80000, addons: [], icon: 'bus',
    };
    const q = { lines: [excursion], total_cents: 90000, online_amount_cents: 10000 };

    test('en «Pagar», personas y «el día de la visita»', () => {
        const r = reciboDe({ quote: q, pedido, textos: conPersonas });

        assert.equal(nb(r.lineas[0].sub), '15 € por persona');
        assert.deepEqual(r.lineas[0].control, { n: 60, min: 30, max: 100, uno: 'persona', varios: 'personas' });
        assert.equal(nb(r.nota), 'Hoy pagas 100 € de señal; el resto, 800 €, el día de la visita.');
        assert.equal(resumenDeLaCesta(q, { textos: conPersonas, fiesta: false }).summary, 'Excursión escolar · 2 horas · lun 5, 10:00 · 60 personas');
    });

    test('después, sin formulario de invitados en su línea: personas en «Listo» y en el resumen del pedido', () => {
        const pagado = { code: 'R-2', total_cents: 90000, online_cents: 10000, lines: [{ ...excursion, guest_form_url: null }] };

        assert.equal(lineaListo(pagado, { textos: conPersonas }), 'Lunes 5 de octubre · 10:00 · Excursión escolar · 2 horas · 60 personas · Nº de pedido R-2');
        assert.equal(resumenDelPedido(pagado, { textos: conPersonas }).summary, 'Excursión escolar · 2 horas · lun 5, 10:00 · 60 personas');
    });
});

describe('las formas de pago del final del recibo (#784)', () => {
    test('salen de urls.mark_<id> en el orden del servidor, con la marca por nombre y su versión oscura; lo demás no', () => {
        const urls = {
            contact: '/contacto', mark_bizum: '/p/bizum.svg', mark_bizum_ink: '/p/bizum-tinta.svg', terms: '/c',
            mark_mastercard: '/p/mastercard.svg', mark_visa: '', mark_visa_ink: '/p/visa-tinta.svg',
        };

        assert.deepEqual(marcasDe(urls), [
            { id: 'bizum', nombre: 'Bizum', src: '/p/bizum.svg', srcTinta: '/p/bizum-tinta.svg' },
            { id: 'mastercard', nombre: 'Mastercard', src: '/p/mastercard.svg', srcTinta: '/p/mastercard.svg' },
        ], 'una sin ruta no se pinta rota: no se pinta; sin versión oscura, la misma (`#786`)');
        assert.deepEqual(marcasDe(undefined), [], 'sin arranque, ninguna');
    });
});
