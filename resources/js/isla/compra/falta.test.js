import { test, describe } from 'node:test';
import assert from 'node:assert/strict';
import { PERDIDA, conFalta, faltaDeEntrada, faltaDePerdida, marcaDe, preguntaDeFalta, sigueLaMarca, textoDeFalta } from './falta.js';

/**
 * Lo que falta para continuar, dicho (M2 de `specs/isla-y-landing-nueva.md` §4.29, `#881`): su frase y la pregunta que se
 * marca, en la pantalla 0, en «Entra» y en la hora llena.
 */
const textos = {
    compra: {
        falta: {
            zona: 'Elige la zona para continuar',
            dia: 'Elige el día para continuar',
            hora: 'Elige la hora para continuar',
            edad: 'Elige cuántos años cumple para continuar',
            datos: 'Rellena los datos de la reserva para continuar',
            correo: 'Escribe tu correo para continuar',
            codigo: 'Escribe las :n cifras del código',
            hora_libre: 'Elige una de estas horas para continuar',
            otra: ':zona no se vende ese día: quítala o elige otro día',
        },
    },
};

describe('la pantalla 0', () => {
    test('cada pregunta, con su frase', () => {
        assert.deepEqual(
            ['pjc-q-zona', 'pjc-q-dia', 'pjc-q-hora', 'pjc-q-edad'].map((f) => textoDeFalta(f, textos)),
            ['Elige la zona para continuar', 'Elige el día para continuar', 'Elige la hora para continuar', 'Elige cuántos años cumple para continuar'],
        );
    });

    test('un dato de la reserva de un pack marca su BLOQUE (la caja va al campo)', () => {
        assert.equal(preguntaDeFalta('pjc-dato-centro'), 'pjc-q-datos');
        assert.deepEqual(marcaDe('pjc-dato-centro', textos), { id: 'pjc-q-datos', texto: 'Rellena los datos de la reserva para continuar' });
    });

    test('la otra zona que no se vende ese día (K2 de `otra-zona.md`): su tarjeta, nombrando la zona', () => {
        assert.deepEqual(marcaDe('pjc-q-otra', textos, { zona: 'JUMP' }), { id: 'pjc-q-otra', texto: 'JUMP no se vende ese día: quítala o elige otro día' });
    });

    test('lo que no se sabe decir no se dice: ni nota ni marca', () => {
        assert.equal(textoDeFalta(null, textos), '');
        assert.equal(textoDeFalta('pjc-q-otra-cosa', textos), '');
        assert.equal(marcaDe(null, textos), null);
        assert.equal(marcaDe('pjc-q-hora', {}), null, 'sin su texto (una instalación sin traducir), sin marca vacía');
    });

    test('la marca se va al contestar su pregunta, y la siguiente no se marca sola', () => {
        const marca = marcaDe('pjc-q-dia', textos);

        assert.equal(sigueLaMarca(marca, 'pjc-q-dia'), true, 'falta lo mismo: sigue');
        assert.equal(sigueLaMarca(marca, 'pjc-q-hora'), false, 'el día ya está: se va');
        assert.equal(sigueLaMarca(marca, null), false, 'todo listo: se va');
        assert.equal(sigueLaMarca(marcaDe('pjc-dato-centro', textos), 'pjc-dato-responsable'), true, 'otro dato del mismo bloque: sigue');
        assert.equal(sigueLaMarca(null, 'pjc-q-hora'), false);
    });
});

describe('«Entra»', () => {
    test('sin correo, escribirlo; con él, nada', () => {
        assert.deepEqual(faltaDeEntrada({ paso: 'id', valor: '  ' }, textos), { id: 'pjc-ent', texto: 'Escribe tu correo para continuar' });
        assert.equal(faltaDeEntrada({ paso: 'id', valor: 'ana@example.com' }, textos), null);
    });

    test('el código, hasta sus seis cifras (los espacios y guiones no cuentan)', () => {
        assert.deepEqual(faltaDeEntrada({ paso: 'codigo', codigo: '123 45' }, textos), { id: 'pjc-ent-codigo', texto: 'Escribe las 6 cifras del código' });
        assert.equal(faltaDeEntrada({ paso: 'codigo', codigo: '123-456' }, textos), null);
    });
});

describe('la hora llena', () => {
    test('sin hora nueva, elegir una de las cercanas', () => {
        assert.deepEqual(faltaDePerdida(null, textos), { id: PERDIDA, texto: 'Elige una de estas horas para continuar' });
        assert.equal(faltaDePerdida('18:00:00', textos), null);
    });
});

describe('el pie con lo que falta', () => {
    const accion = () => 'continuar';
    const paso = { key: 'cuando', note: null, action: { label: 'Continuar', onClick: accion, disabled: true } };

    test('dice qué falta encima del botón, tocarlo hace lo mismo que el botón y el botón NUNCA se apaga', () => {
        const c = conFalta(paso, marcaDe('pjc-q-hora', textos), accion);

        assert.deepEqual([c.note, c.noteIcon, c.onNote(), c.action.disabled], ['Elige la hora para continuar', 'circle-alert', 'continuar', false]);
        assert.equal(c.action.label, 'Continuar', 'el botón dice lo de siempre');
        assert.equal(paso.action.disabled, true, 'sin tocar el paso de quien llama');
    });

    test('sin nada que falte (o sin frase para ello), el paso tal cual', () => {
        assert.equal(conFalta(paso, null, accion), paso);
        assert.equal(conFalta(paso, marcaDe('pjc-q-hora', {}), accion), paso);
        assert.equal(conFalta({ key: 'x', action: null }, marcaDe('pjc-q-dia', textos), accion).action, null, 'sin acción, no se inventa una');
    });
});
