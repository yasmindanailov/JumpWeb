import { test, describe } from 'node:test';
import assert from 'node:assert/strict';
import { euros } from '../compra/vista.js';
import { menuDeLaFiesta, menusDeLaFiesta, packDeLaEdad, vistaCalculadoraFiesta } from './vistaFiesta.js';

/**
 * La calculadora de la FIESTA de la página (T6b·3 de `specs/isla-y-landing-nueva.md` §4.18, `#836`): la edad elige el
 * pack, el dinero llega hecho del servidor, y lo que alarga la fiesta se ve si cabe a esa hora.
 */
const menus = [
    { id: 107, name: 'Menú 1', price_cents: 0, included: true, choice_group: 'menu', features: ['Sándwich mixto'], gifts: ['Cono de chuches'] },
    { id: 108, name: 'Menú 2', price_cents: 200, included: false, choice_group: 'menu', features: ['Pizza o perrito'], gifts: [] },
];
const kids = { id: 105, name: 'Pack Kids', guest_age_min: 4, guest_age_max: 7, min_quantity: 8, max_quantity: 20, from_price_cents: 1495, addons: menus };
const jump = { id: 106, name: 'Pack Jump', guest_age_min: 8, guest_age_max: null, min_quantity: 8, max_quantity: 20, from_price_cents: 1595, addons: menus };
const pagina = {
    zona: 'cumpleanos', nombre: 'Play Jump Park', url: 'https://ejemplo.test/cumpleanos',
    packs: [kids, jump],
    extensiones: { 105: { id: 317, descripcion: 'Una hora más de sala.', desde: 'desde 3 €' }, 106: { id: 316, descripcion: 'Una hora más de sala.', desde: 'desde 5 €' } },
    textos: {
        preguntas: ['¿Cuántos años cumple?', '¿Cuántos niños vienen?', '¿Qué día?', '¿A qué hora?', '¿Qué menú?'],
        persona: ['niño', 'niños'], ninos: { label: 'Niños en la fiesta', sub: 'Mínimo :n' },
        hora_extra: { titulo: 'Hora extra', nota: 'Hoy sigues pagando 50 € de señal.' },
        ahora: 'Hoy pagas la señal', luego: 'El resto, el día de la fiesta', senal: '50 €', nota: 'Reserva con 8 y ajusta.',
        boton: 'Reservar y pagar la señal', junto: 'Pago con tarjeta o Bizum.', compartir: 'Envíaselo por WhatsApp', mensaje: 'Mira el cumple: ',
    },
};
const textos = {
    calculadora: {
        falta_edad: 'Elige la edad', falta_dia: 'Elige el día', falta_hora: 'Elige la hora', espera_hora: 'Antes el día', por: ':precio por :persona',
        desde: 'desde :precio', a_las: ':dia a las :hora', linea_pack: ':pack · :n × :precio', menu_incluido: 'Incluido', menu_mas: '+:precio por :persona',
        extra_mas: '+:precio', extra_por: '+:precio por :persona', extra_total: '+:precio en total', extra_sin_hora: 'Elige la hora para saber si cabe.',
        extra_no_cabe: 'A las :hora no cabe.',
    },
    compra: { cuando: { pack_de_a: ':pack, de :min a :max años', pack_desde: ':pack, desde :min años' } },
};
const precios = { 105: [{ date: '2026-10-03', price_cents: 1695, rate_key: 'special' }], 106: [{ date: '2026-10-03', price_cents: 1995, rate_key: 'special' }] };
const horas = [{ time: '11:00:00', available: 60, max_quantity: 20, sellable: true }, { time: '19:00:00', available: 60, max_quantity: 20, sellable: true }];
const base = { pagina, textos, locale: 'es', hoy: '2026-09-28', precios, horas: [], grupos: null, sueltos: [], linea: null, pendiente: false, tocada: false, abriendo: false };
const borrador = { edad: null, n: 8, dia: null, hora: null, menu: null, horaExtra: false };

describe('la calculadora de la fiesta, sin nada elegido', () => {
    const v = vistaCalculadoraFiesta({ ...base, borrador });

    test('sin edad no hay pack ni total: lo que falta es la edad, y el primer pack presta su mínimo y su «desde»', () => {
        assert.deepEqual([v.edad.pack, v.resumen.falta, v.resumen.faltaHref, v.resumen.listo, v.resumen.total], ['', 'Elige la edad', '#p6-edad', false, '']);
        assert.deepEqual([v.ninos.min, v.ninos.max, v.ninos.sub, v.ninos.precio], [8, 20, 'Mínimo 8', `desde ${euros(1495)}`]);
    });

    test('las edades salen de los tramos de los packs (un tramo abierto, cinco más: de 4 a 13)', () => {
        assert.deepEqual(v.edad.edades.map((x) => x.time), ['4', '5', '6', '7', '8', '9', '10', '11', '12', '13']);
    });

    test('los menús de la ficha, con su precio publicado y sus platos; elegido, el incluido', () => {
        assert.deepEqual(v.menu.items.map((i) => [i.value, i.title, i.price, i.includes]), [
            ['107', 'Menú 1', 'Incluido', ['Sándwich mixto', 'Cono de chuches']],
            ['108', 'Menú 2', `+${euros(200)} por niño`, ['Pizza o perrito']],
        ]);
        assert.equal(v.menu.valor, '107');
    });

    test('la hora extra, antes de la hora: su «desde» y que aún no se sabe si cabe', () => {
        assert.deepEqual([v.horaExtra.precio, v.horaExtra.disponible, v.horaExtra.marcada, v.horaExtra.notaNo], ['desde 3 €', false, false, 'Elige la hora para saber si cabe.']);
    });

    test('sin tocarla, no le cuenta nada a la isla; «Hoy pagas» enseña la señal de la página', () => {
        assert.equal(v.isla, null);
        assert.deepEqual(v.resumen.ahora, { label: 'Hoy pagas la señal', value: '50 €' });
        assert.equal(v.resumen.luego, null);
    });
});

describe('con todo elegido', () => {
    const linea = {
        unit_price_cents: 1995, subtotal_cents: 19950, total_cents: 21950, has_deposit: true, deposit_cents: 5000, gate_remainder_cents: 16950,
        addons: [{ product_id: 107, quantity: 0, subtotal_cents: 0 }, { product_id: 108, product_name: 'Menú 2', quantity: 10, subtotal_cents: 2000 }, { product_id: 316, product_name: 'Hora extra', quantity: 1, subtotal_cents: 0 }],
    };
    const sueltos = [{ product_id: 316, price_cents: 800, per_guest: false }];
    const v = vistaCalculadoraFiesta({ ...base, borrador: { ...borrador, edad: 9, n: 10, dia: '2026-10-03', hora: '11:00:00', menu: '108', horaExtra: true }, horas, linea, sueltos, tocada: true });

    test('la edad elige el pack de su tramo (9 → Jump, abierto), y el precio por niño es el del día', () => {
        assert.deepEqual([v.edad.pack, v.ninos.precio], ['Pack Jump, desde 8 años', `${euros(1995)} por niño`]);
    });

    test('el TOTAL, la señal y el resto son los del servidor, no una suma (`PAY-12`)', () => {
        assert.deepEqual([v.resumen.total, v.resumen.ahora.value, v.resumen.luego.value, v.resumen.listo], [euros(21950), euros(5000), euros(16950), true]);
    });

    test('con algo cobrado aparte, el desglose: el pack y lo cobrado (lo que no cuesta nada, fuera)', () => {
        assert.deepEqual(v.resumen.lineas, [
            { label: `Pack Jump · 10 × ${euros(1995)}`, value: euros(19950) },
            { label: 'Menú 2', value: euros(2000) },
        ]);
    });

    test('la hora extra que cabe: su precio del día (por bloque, sin «por niño») y marcada', () => {
        assert.deepEqual([v.horaExtra.precio, v.horaExtra.disponible, v.horaExtra.marcada, v.horaExtra.notaNo, v.horaExtra.total], [`+${euros(800)}`, true, true, '', '']);
    });

    test('a la isla, lo elegido en corto con el total del servidor; y el mensaje de WhatsApp lo lleva', () => {
        assert.deepEqual([v.isla.falta, v.isla.elegido.endsWith(euros(21950)), v.isla.elegido.includes('11:00 · 10 niños')], ['', true, true]);
        assert.match(decodeURIComponent(v.compartir.items[0].href), /Pack Jump · 10 niños · sábado 3 a las 11:00/);
        assert.equal(v.compartir.items.length, 1, 'solo WhatsApp (`#833`)');
    });
});

describe('la hora extra que NO cabe y lo que falta', () => {
    test('si a esa hora el servidor no la ofrece, no cabe: ni marcada aunque el borrador la pidiera', () => {
        const v = vistaCalculadoraFiesta({ ...base, borrador: { ...borrador, edad: 5, n: 10, dia: '2026-10-03', hora: '19:00:00', horaExtra: true }, horas, sueltos: [] });

        assert.deepEqual([v.horaExtra.disponible, v.horaExtra.marcada, v.horaExtra.notaNo], [false, false, 'A las 19:00 no cabe.']);
    });

    test('lo que falta, en orden: la edad, el día, la hora', () => {
        const falta = (b) => vistaCalculadoraFiesta({ ...base, borrador: { ...borrador, ...b }, horas }).resumen.falta;

        assert.deepEqual([falta({}), falta({ edad: 5 }), falta({ edad: 5, dia: '2026-10-03' })], ['Elige la edad', 'Elige el día', 'Elige la hora']);
    });

    test('una edad que ningún pack cubre no elige pack', () => {
        assert.equal(packDeLaEdad([kids, jump], 2), null);
    });
});

describe('los menús', () => {
    test('los resueltos por el servidor mandan sobre los de la ficha; el del borrador se conserva si se ofrece', () => {
        const grupos = [{ key: 'menu', options: [{ product_id: 107, product_name: 'Menú 1', price_cents: 0, is_included: true, selected: true }, { product_id: 108, product_name: 'Menú 2', price_cents: 200, is_included: false, selected: false }] }];
        const items = menusDeLaFiesta(grupos, kids, { tp: (k) => k, locale: 'es', persona: 'niño' });

        assert.deepEqual([menuDeLaFiesta(items, grupos, kids, '108'), menuDeLaFiesta(items, grupos, kids, '999')], ['108', '107']);
    });
});
