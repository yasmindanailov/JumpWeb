import { test, describe } from 'node:test';
import assert from 'node:assert/strict';
import { cardImageUrl, cardIsDrawable, tokenGroups } from './card.js';

/**
 * Lo que el cajón decide sobre el carné (Fase 6 · A, §9.6 B·2): cómo se DICTA el token y cómo se pide
 * la imagen. Ninguna regla de negocio: la forma del token y su validez las decide el servidor.
 */

const card = (overrides = {}) => ({
    token: 'JW0X3K9MABCDEFGH1234',
    issued_at: '2026-08-28T07:30:00+02:00',
    png_url: 'http://localhost:8081/api/v1/me/card/png',
    ...overrides,
});

describe('dictar el token', () => {
    test('en grupos de cuatro, en mayúsculas, sin nada que no sea letra o número', () => {
        assert.equal(tokenGroups('JW0X3K9MABCDEFGH1234'), 'JW0X 3K9M ABCD EFGH 1234');
        assert.equal(tokenGroups('jw0x 3k9m-abcd'), 'JW0X 3K9M ABCD', 'agrupar no cambia lo que vale: la puerta normaliza igual');
    });

    test('sin token no hay nada que dictar', () => {
        assert.equal(tokenGroups(null), '');
        assert.equal(tokenGroups(''), '');
        assert.equal(tokenGroups(undefined), '');
    });
});

describe('¿se puede dibujar?', () => {
    test('con token y URL, sí', () => {
        assert.equal(cardIsDrawable(card()), true);
    });

    test('con la clave del servidor rotada (token y URL nulos), no — y la pantalla ofrece renovar', () => {
        assert.equal(cardIsDrawable(card({ token: null, png_url: null })), false);
        assert.equal(cardIsDrawable(card({ token: '' })), false);
        assert.equal(cardIsDrawable(null), false);
    });
});

describe('la URL de la imagen', () => {
    test('lleva la VERSIÓN del carné, para que renovar haga que el navegador pida la imagen nueva', () => {
        assert.equal(cardImageUrl(card()), 'http://localhost:8081/api/v1/me/card/png?v=2026-08-28T07%3A30%3A00%2B02%3A00');
    });

    test('dos carnés distintos del mismo titular dan URLs distintas aunque `png_url` sea la misma', () => {
        const before = cardImageUrl(card());
        const after = cardImageUrl(card({ issued_at: '2026-08-28T08:00:00+02:00' }));

        assert.notEqual(before, after);
    });

    test('respeta una URL que ya lleve query', () => {
        assert.equal(cardImageUrl(card({ png_url: 'https://x.test/png?a=1', issued_at: '1' })), 'https://x.test/png?a=1&v=1');
    });

    test('sin nada que dibujar, cadena vacía: el `<img>` no se pinta', () => {
        assert.equal(cardImageUrl(card({ token: null, png_url: null })), '');
        assert.equal(cardImageUrl(undefined), '');
    });
});
