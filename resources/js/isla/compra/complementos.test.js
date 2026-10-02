import { test, describe } from 'node:test';
import assert from 'node:assert/strict';
import { cargarSinHora, complementosDe, conExtra, deLaFicha, eleccionesDelBorrador, elegido, formaDe, gruposComoFilas, quedanEn } from './complementos.js';

/**
 * Los complementos de la pantalla 0 de la isla (M1 de `specs/isla-y-landing-nueva.md` §4.29, `#880`): todos los que se
 * venden al reservar, con la forma de sus datos y lo que el servidor dice de ellos. Los datos, los de la BD local medidos
 * el 02-10: la ficha del pack 105 (menús, calcetines y la hora extra de sala) y la de la entrada 101.
 */
const textos = {
    compra: {
        cuando: {
            extra_sin_hora: 'Elige la hora para saber si cabe.',
            extra_no: 'Ese día, a las :hora, no se puede añadir.',
            extra_requiere: 'Requiere :nombre.',
        },
    },
};

const menu1 = { id: 107, name: 'Menú 1', included: true, per_guest: true, allow_extra: false, choice_group: 'menu', max_quantity: null, mandatory: false };
const calcetines = { id: 110, name: 'Calcetines antideslizantes', included: false, per_guest: false, allow_extra: true, choice_group: null, max_quantity: null, mandatory: false, features: ['Imprescindibles para saltar'] };
const horaSala = { id: 317, name: 'Hora extra de cumpleaños (Kids)', included: false, per_guest: false, allow_extra: true, choice_group: null, max_quantity: 1, mandatory: false, features: ['La fiesta dura 3 horas en vez de 2'] };
const pack = { addons: [menu1, calcetines, horaSala] };

describe('qué filas y con qué forma', () => {
    test('sin los grupos de elección (el menú) ni lo que la pantalla ya pregunta (los calcetines)', () => {
        assert.deepEqual(deLaFicha(pack).map((a) => a.id), [110, 317]);
        assert.deepEqual(deLaFicha(pack, [110]).map((a) => a.id), [317]);
        assert.deepEqual(deLaFicha(null), []);
    });

    test('la forma sale de los datos: tope 1 o por invitado es un sí o un no; incluido que no se amplía, fijo', () => {
        assert.equal(formaDe(horaSala), 'si-no');
        assert.equal(formaDe({ ...calcetines, per_guest: true }), 'si-no');
        assert.equal(formaDe(calcetines), 'cantidad');
        assert.equal(formaDe({ ...calcetines, included: true, allow_extra: false }), 'fijo');
        assert.equal(formaDe({ ...calcetines, mandatory: true, allow_extra: false }), 'fijo');
        assert.equal(formaDe({ ...calcetines, included: true, allow_extra: true }), 'cantidad', 'incluido pero ampliable: se suma');
    });
});

describe('lo elegido, en el borrador', () => {
    test('se pone, se cambia en su sitio y con 0 se quita', () => {
        const uno = conExtra([], 317, 1);
        const dos = conExtra([{ product_id: 110, quantity: 2 }, ...uno], 110, 3);

        assert.deepEqual(uno, [{ product_id: 317, quantity: 1 }]);
        assert.deepEqual(dos, [{ product_id: 110, quantity: 3 }, { product_id: 317, quantity: 1 }], 'el orden no cambia');
        assert.deepEqual(conExtra(dos, 317, 0), [{ product_id: 110, quantity: 3 }]);
        assert.equal(elegido(dos, 110), 3);
        assert.equal(elegido(dos, 999), 0);
    });

    test('al cambiar de producto se queda lo que el nuevo también vende: la hora extra de otro pack, no (`#836`)', () => {
        const jump = { addons: [{ ...calcetines }, { ...horaSala, id: 316 }] };

        assert.deepEqual(quedanEn([{ product_id: 110, quantity: 2 }, { product_id: 317, quantity: 1 }], jump), [{ product_id: 110, quantity: 2 }]);
        assert.deepEqual(quedanEn(null, jump), []);
    });
});

describe('las filas', () => {
    const sinHora = [
        { product_id: 110, note: '2,00 € cada uno', available: true },
        { product_id: 317, note: '5,00 € cada uno', available: true },
    ];

    test('sin hora: la hora extra, apagada y diciendo por qué; lo que se suma, ya se elige; los precios, los sin hora', () => {
        const [cal, extra] = complementosDe({ ficha: pack, sinHora, conHora: null, extras: [{ product_id: 110, quantity: 2 }], textos });

        assert.deepEqual([cal.forma, cal.disponible, cal.n, cal.precio, cal.porQue], ['cantidad', true, 2, '2,00 € cada uno', '']);
        assert.deepEqual([extra.forma, extra.disponible, extra.marcada, extra.precio, extra.porQue], ['si-no', false, false, '5,00 € cada uno', 'Elige la hora para saber si cabe.']);
        assert.equal(extra.descripcion, 'La fiesta dura 3 horas en vez de 2.');
        assert.equal(extra.max, 1);
    });

    test('con hora y ofrecida: se marca lo elegido y el precio es el del día', () => {
        const conHora = [{ product_id: 110, note: '2,00 € cada uno', available: true }, { product_id: 317, note: '3,00 € cada uno', available: true }];
        const [, extra] = complementosDe({ ficha: pack, sinHora, conHora, hora: '17:00', extras: [{ product_id: 317, quantity: 1 }], textos });

        assert.deepEqual([extra.disponible, extra.marcada, extra.precio, extra.porQue], [true, true, '3,00 € cada uno', '']);
    });

    test('con hora y NO ofrecida (no cabe, o ese día no tiene precio): la fila sigue, apagada, sin marcar y diciendo por qué', () => {
        const conHora = [{ product_id: 110, note: '2,00 € cada uno', available: true }];
        const [cal, extra] = complementosDe({ ficha: pack, sinHora, conHora, hora: '19:00', extras: [{ product_id: 317, quantity: 1 }], textos });

        assert.equal(cal.disponible, true);
        assert.deepEqual([extra.disponible, extra.marcada, extra.n, extra.porQue], [false, false, 0, 'Ese día, a las 19:00, no se puede añadir.']);
        assert.equal(extra.precio, '5,00 € cada uno', 'el precio de sin hora, para que la fila no se quede muda');
    });

    test('uno que depende de otro: espera a que se elija aquél, y lo nombra', () => {
        const tarta2 = { ...calcetines, id: 120, name: 'Segunda tarta', requires_addon_id: 110 };
        const ficha = { addons: [calcetines, tarta2] };
        const sinElegir = complementosDe({ ficha, sinHora: [], conHora: null, extras: [], textos })[1];
        const conHoraSinRequisito = complementosDe({ ficha, sinHora: [], conHora: [{ product_id: 110 }, { product_id: 120, available: false, requires_name: 'Calcetines antideslizantes' }], hora: '17:00', extras: [], textos })[1];

        assert.deepEqual([sinElegir.disponible, sinElegir.porQue], [false, 'Requiere Calcetines antideslizantes.']);
        assert.equal(complementosDe({ ficha, sinHora: [], conHora: null, extras: [{ product_id: 110, quantity: 1 }], textos })[1].disponible, true);
        assert.deepEqual([conHoraSinRequisito.disponible, conHoraSinRequisito.porQue], [false, 'Requiere Calcetines antideslizantes.']);
    });

    test('lo incluido que no se amplía se ENSEÑA con su nota, sin control ni «por qué»', () => {
        const incluidos = { ...calcetines, included: true, allow_extra: false };
        const [fila] = complementosDe({ ficha: { addons: [incluidos] }, sinHora: [{ product_id: 110, note: 'Incluido' }], conHora: null, extras: [], textos });

        assert.deepEqual([fila.forma, fila.disponible, fila.precio, fila.porQue], ['fijo', false, 'Incluido', '']);
    });
});

describe('los grupos de elección, TODOS (`#881`)', () => {
    const grupos = [{ key: 'menu', options: [] }, { key: 'pulsera', options: [] }];

    test('en una fiesta, el menú en el primero y lo elegido en los demás', () => {
        assert.deepEqual(eleccionesDelBorrador(grupos, { menu: '108', elecciones: { pulsera: '201' }, conMenu: true }), [
            { group: 'menu', product_id: 108 }, { group: 'pulsera', product_id: 201 },
        ]);
    });

    test('como preguntas de la lista: sus opciones con la nota del servidor y la elegida —la suya o la que el servidor deja—', () => {
        const pulsera = {
            key: 'pulsera', label: 'Color de la pulsera',
            options: [
                { product_id: 201, product_name: 'Azul', note: 'Incluido', selected: true, features: ['La de siempre'] },
                { product_id: 202, product_name: 'Roja', note: '+1,00 €', selected: false, available: false },
            ],
        };
        const [fila] = gruposComoFilas([pulsera]);

        assert.deepEqual([fila.id, fila.forma, fila.grupo, fila.titulo, fila.valor], ['grupo-pulsera', 'grupo', 'pulsera', 'Color de la pulsera', '201']);
        assert.deepEqual(fila.items, [
            { value: '201', title: 'Azul', description: 'La de siempre.', price: 'Incluido', disabled: false },
            { value: '202', title: 'Roja', description: '', price: '+1,00 €', disabled: true },
        ]);
        assert.equal(gruposComoFilas([pulsera], { elecciones: { pulsera: 202 } })[0].valor, '202', 'la del borrador manda');
        assert.deepEqual(gruposComoFilas([{ key: 'menu', options: [{ product_id: 107 }] }, pulsera], { desde: 1 }).map((g) => g.grupo), ['pulsera'], 'en una fiesta, el menú tiene su pregunta');
        assert.deepEqual(gruposComoFilas([{ key: 'vacio', options: [] }]), [], 'sin opciones, no se pregunta');
    });

    test('en una entrada, todos por su clave; lo no elegido no viaja (el servidor deja el de por defecto)', () => {
        assert.deepEqual(eleccionesDelBorrador(grupos, { elecciones: { pulsera: 201 } }), [{ group: 'pulsera', product_id: 201 }]);
        assert.deepEqual(eleccionesDelBorrador(grupos, { menu: '108' }), [], 'sin `conMenu`, el menú del borrador no cuenta');
        assert.deepEqual(eleccionesDelBorrador(null), []);
    });
});

describe('lo que se pide sin hora', () => {
    test('sin día ni hora (el endpoint los rechaza en `null`), y vuelven los grupos y los sueltos; un fallo, nada', async () => {
        const llamadas = [];
        const api = {
            post: async (ruta, cuerpo) => {
                llamadas.push([ruta, cuerpo]);

                return { ok: true, data: { groups: [{ key: 'menu', options: [] }], singles: [{ product_id: 317, note: '5,00 € cada uno' }] } };
            },
        };

        const r = await cargarSinHora({ api, productId: 105, quantity: 8 });

        assert.deepEqual(llamadas, [['/catalog/products/105/addons', { quantity: 8, addons: [], choices: [] }]]);
        assert.deepEqual(r, { grupos: [{ key: 'menu', options: [] }], sueltos: [{ product_id: 317, note: '5,00 € cada uno' }] });
        assert.deepEqual(await cargarSinHora({ api: { post: async () => ({ ok: false }) }, productId: 105, quantity: 8 }), { grupos: [], sueltos: [] });
    });
});
