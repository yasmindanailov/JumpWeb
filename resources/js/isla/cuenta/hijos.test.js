import { test, describe } from 'node:test';
import assert from 'node:assert/strict';
import { edadDe, erroresDeFicha, fechaTecleada, fichasDe, formularioHijos, hijoDe, isoDeFecha, quienDe, relacionesDe, revisarHijos } from './hijos.js';

/**
 * T5d — los hijos en Mi cuenta (`hijos.js`), contra `PmcQuien` y `PmcHijos` del mockup y `#773`·a·b.
 */
const textos = {
    mi_cuenta: {
        quien: { anio: ':n año', anios: ':n años', firmado: 'firmado', falta_firma: 'falta su firma' },
        hijos: {
            hijo_n: 'Hijo :n',
            relaciones: { father: 'Padre', mother: 'Madre', legal_guardian: 'Tutor o tutora legal', grandparent: 'Abuelo o abuela', other: 'Otra relación' },
            errores: { nombre: 'Escribe su nombre.', fecha: 'Revisa la fecha: día, mes y año.', relacion: 'Elige qué eres suyo.', descargo: 'Marca la casilla para guardar.' },
        },
        hijo: {
            nacido: 'nacido el :fecha', relacion: 'Eres su :relacion', adulto: ':nombre ya tiene 18 años: se registra él.',
            firmada: 'Firmado el :fecha · versión :version', anterior: 'Firmó una versión anterior del descargo: vuelve a firmarlo.',
            sin_firma: 'Aún no has firmado el descargo por :nombre.', verificar: 'Para firmar por :nombre, antes verifica tu correo.',
        },
    },
};
const HOY = '2026-09-26';
const menor = (extra = {}) => ({
    id: 3, name: 'Vera', surname: null, full_name: 'Vera', relationship: 'mother', born_on: '2019-03-07', age: 7, is_minor: true,
    waiver: { mode: 'interno', signed: true, outdated: false, accepted_label: '14/09/2026', version: 3, pdf_url: '/api/v1/me/waiver/9/pdf' }, ...extra,
});

describe('la fecha de nacimiento', () => {
    test('se escribe con las barras solas', () => {
        assert.equal(fechaTecleada('07032019'), '07/03/2019');
        assert.equal(fechaTecleada('0703'), '07/03');
        assert.equal(fechaTecleada('07'), '07');
        assert.equal(fechaTecleada('07/03/2019 y más'), '07/03/2019');
    });

    test('solo vale un día que exista', () => {
        assert.equal(isoDeFecha('07/03/2019'), '2019-03-07');
        assert.equal(isoDeFecha('30/02/2019'), null, 'no se desborda a marzo');
        assert.equal(isoDeFecha('7/3/2019'), null);
        assert.equal(isoDeFecha(''), null);
    });

    test('la edad es una pista, con el cumpleaños contado el mismo día', () => {
        assert.equal(edadDe('26/09/2019', HOY), 7);
        assert.equal(edadDe('27/09/2019', HOY), 6);
        assert.equal(edadDe('27/09/2026', HOY), null, 'una fecha futura no tiene edad');
        assert.equal(edadDe('30/02/2019', HOY), null);
    });
});

describe('Añade a tus hijos', () => {
    test('nace con una ficha vacía y la casilla sin marcar; la relación, sin ninguna elegida (`#236`)', () => {
        const h = formularioHijos();

        assert.equal(h.lista.length, 1);
        assert.equal(h.lista[0].rel, '');
        assert.equal(h.descargo, false);
    });

    test('las cinco relaciones del catálogo (`#773`·b)', () => {
        assert.deepEqual(relacionesDe(textos).map((r) => r.value), ['father', 'mother', 'legal_guardian', 'grandparent', 'other']);
        assert.equal(relacionesDe(textos)[4].title, 'Otra relación');
    });

    test('antes de preguntar: nombre, una fecha que exista y no sea futura, qué eres suyo y, si hay texto, la casilla', () => {
        const h = { lista: [{ id: 1, nombre: ' ', fecha: '31/02/2019', rel: '' }, { id: 2, nombre: 'Pol', fecha: '01/05/2021', rel: 'father' }], descargo: false };

        assert.deepEqual(revisarHijos(h, { firma: true, textos, hoy: HOY }), {
            lista: [{ nombre: 'Escribe su nombre.', fecha: 'Revisa la fecha: día, mes y año.', rel: 'Elige qué eres suyo.' }, {}],
            descargo: 'Marca la casilla para guardar.',
        });
        assert.equal(revisarHijos({ lista: [h.lista[1]], descargo: true }, { firma: true, textos, hoy: HOY }), null);
        assert.equal(revisarHijos({ lista: [h.lista[1]], descargo: false }, { firma: false, textos, hoy: HOY }), null, 'sin texto que firmar, la casilla no se pide');
        assert.ok(revisarHijos({ lista: [{ id: 3, nombre: 'Leo', fecha: '01/01/2027', rel: 'other' }], descargo: true }, { firma: true, textos, hoy: HOY }).lista[0].fecha);
    });

    test('lo que dice el servidor de una ficha va a su campo; lo que no es de un campo, arriba', () => {
        assert.deepEqual(erroresDeFicha({ name: ['Demasiado largo.'], born_on: ['La fecha no es válida.'] }), { ficha: { nombre: 'Demasiado largo.', fecha: 'La fecha no es válida.' }, descargo: '', aviso: '' });
        assert.deepEqual(erroresDeFicha({ accept_waiver: ['Acepta el descargo.'] }), { ficha: {}, descargo: 'Acepta el descargo.', aviso: '' });
        assert.deepEqual(erroresDeFicha({}, 'Tiene 18 años o más.'), { ficha: {}, descargo: '', aviso: 'Tiene 18 años o más.' });
    });

    test('con más de una ficha, cada una lleva su cabeza (su nombre o «Hijo 2») y su edad como pista', () => {
        const f = fichasDe({ lista: [{ id: 1, nombre: 'Vera', fecha: '07/03/2019', rel: '' }, { id: 2, nombre: '', fecha: '', rel: '' }] }, { textos, hoy: HOY });

        assert.deepEqual(f.map((x) => [x.titulo, x.pista]), [['Vera', '7 años'], ['Hijo 2', '']]);
    });
});

describe('Quién viene contigo', () => {
    test('cada hijo con su inicial, su edad del SERVIDOR y «firmado»; o que falta su firma', () => {
        const q = quienDe([menor(), menor({ id: 4, name: 'pol', age: 1, waiver: { mode: 'interno', signed: false, outdated: false } })], { textos, emailVerified: true });

        assert.deepEqual(q.map((x) => [x.inicial, x.edad, x.firmado, x.pendiente]), [['V', '7 años', true, false], ['P', '1 año', false, true]]);
        assert.equal(q[1].aria, 'pol, 1 año, falta su firma');
    });

    test('fuera del modo interno no se dice nada de la firma', () => {
        const q = quienDe([menor({ waiver: { mode: 'externo', signed: false } })], { textos, emailVerified: true });

        assert.equal(q[0].firmado, false);
        assert.equal(q[0].pendiente, false);
    });
});

describe('la ficha de un hijo', () => {
    test('firmado: su fecha, su versión y el PDF; nada que firmar', () => {
        const h = hijoDe(menor(), { textos, emailVerified: true });

        assert.equal(h.datos, '7 años · nacido el 07/03/2019');
        assert.equal(h.relacion, 'Eres su madre', 'dentro de la frase, en minúscula');
        assert.deepEqual(h.firma, { estado: 'firmada', texto: 'Firmado el 14/09/2026 · versión 3', pdf: '/api/v1/me/waiver/9/pdf' });
        assert.equal(h.firmar, false);
    });

    test('sin firma, se ofrece firmar; con el correo sin verificar, primero verificarlo', () => {
        const sin = menor({ waiver: { mode: 'interno', signed: false, outdated: false } });

        assert.equal(hijoDe(sin, { textos, emailVerified: true }).firmar, true);
        assert.equal(hijoDe(sin, { textos, emailVerified: true }).firma.texto, 'Aún no has firmado el descargo por Vera.');
        assert.equal(hijoDe(sin, { textos, emailVerified: false }).firmar, false);
        assert.equal(hijoDe(sin, { textos, emailVerified: false }).verificar, 'Para firmar por Vera, antes verifica tu correo.');
    });

    test('una versión anterior se vuelve a firmar; con 18 años, ya no se firma por él', () => {
        assert.equal(hijoDe(menor({ waiver: { mode: 'interno', signed: true, outdated: true } }), { textos, emailVerified: true }).firma.estado, 'anterior');
        const mayor = hijoDe(menor({ is_minor: false, age: 18, waiver: { mode: 'interno', signed: false } }), { textos, emailVerified: true });

        assert.equal(mayor.adulto, 'Vera ya tiene 18 años: se registra él.');
        assert.equal(mayor.firmar, false);
    });

    test('sin apellidos (`#773`·a), su nombre completo es el que se declaró', () => {
        assert.equal(hijoDe(menor(), { textos }).completo, 'Vera');
        assert.equal(hijoDe(menor({ full_name: 'Vera Gil' }), { textos }).completo, 'Vera Gil');
    });
});
