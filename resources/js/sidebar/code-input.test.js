import { test, describe } from 'node:test';
import assert from 'node:assert/strict';
import { CODE_LENGTH, codeDigits, formatWait, isNewlyComplete } from './code-input.js';

/**
 * La red del `CodeInput` del cajón (A4a de `docs/specs/acceso-con-codigo.md` §4.11): lo que se queda de lo escrito, cuándo
 * avisa de que está completo y cómo dice la espera. La pieza que lo pinta no decide nada de esto.
 */
describe('lo escrito o pegado', () => {
    test('pegar el código como lo trae el correo —con espacio o con guion— vale igual', () => {
        assert.equal(codeDigits('482 913'), '482913');
        assert.equal(codeDigits('482-913'), '482913');
        assert.equal(codeDigits(' 4 8 2 - 9 1 3 '), '482913');
    });

    test('solo cifras, y como mucho seis', () => {
        assert.equal(codeDigits('48a2'), '482');
        assert.equal(codeDigits('4829131'), '482913');
        assert.equal(CODE_LENGTH, 6);
    });

    test('nada escrito es cadena vacía, nunca `undefined`', () => {
        assert.equal(codeDigits(undefined), '');
        assert.equal(codeDigits(null), '');
        assert.equal(codeDigits(482913), '482913');
    });
});

describe('avisar de que está completo', () => {
    test('con la sexta cifra, sí', () => {
        assert.equal(isNewlyComplete('482913', ''), true);
    });

    test('con cinco, no: el servidor gastaría un intento en un código a medias', () => {
        assert.equal(isNewlyComplete('48291', ''), false);
    });

    test('el MISMO código completo no vuelve a avisar; otro distinto, sí', () => {
        assert.equal(isNewlyComplete('482913', '482913'), false, 'cada aviso de más gasta uno de los cinco intentos');
        assert.equal(isNewlyComplete('482914', '482913'), true);
    });
});

describe('la espera de «Reenviar el código»', () => {
    test('en minutos y segundos, como el diseño', () => {
        assert.equal(formatWait(58), '0:58');
        assert.equal(formatWait(60), '1:00');
        assert.equal(formatWait(5), '0:05');
    });

    test('sin espera, o con una rara, cero', () => {
        assert.equal(formatWait(0), '0:00');
        assert.equal(formatWait(-3), '0:00');
        assert.equal(formatWait(undefined), '0:00');
    });
});
