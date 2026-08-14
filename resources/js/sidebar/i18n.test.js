import { test, describe } from 'node:test';
import assert from 'node:assert/strict';
import { t, tc, tp } from './i18n.js';

/**
 * Fase 4 · paso 4.3·1 — la red de los textos del cajón (`sidebar-spa.md` §4.5, criterio CE-6).
 *
 * Aquí se fija la CONDUCTA; que coincida con `__()`/`trans_choice()` lo comprueba
 * `SidebarTextParityTest` contra el traductor real. Cada caso nombra el fallo silencioso que evita:
 * los tres que motivan el módulo se descubrieron midiendo, no leyendo.
 */

const MESSAGES = {
    cart_title: 'Tu carrito',
    guests_count: ':count invitados',
    cart_items: ':count artículo|:count artículos',
    deposit_card_note: 'Señal :deposit · :rest en el parque',
    errors: {
        choose_one: 'Elige una opción para continuar.',
        fields_missing: 'Falta rellenar: :fields.',
    },
};

describe('lectura del diccionario', () => {
    test('una clave de primer nivel se lee tal cual', () => {
        assert.equal(t(MESSAGES, 'cart_title'), 'Tu carrito');
    });

    /**
     * ⚠️ El payload inyectado NO es plano: `errors`, `paused`, `statuses` y `payment_failed` son
     * subarrays. El helper anterior buscaba la clave literal `'errors.choose_one'`, no la encontraba
     * y devolvía `''`: el aviso de error se pintaba VACÍO y nada avisaba.
     */
    test('una clave anidada se lee por CAMINO, no por clave literal', () => {
        assert.equal(t(MESSAGES, 'errors.choose_one'), 'Elige una opción para continuar.');
    });

    test('una clave que no existe devuelve cadena vacía y no revienta', () => {
        assert.equal(t(MESSAGES, 'no_existe'), '');
        assert.equal(t(MESSAGES, 'errors.no_existe'), '');
        assert.equal(t(MESSAGES, 'cart_title.mas.abajo'), '');
    });

    /** Un subarray no es un texto: devolverlo pintaría «[object Object]» en el cajón. */
    test('una clave que apunta a un subarray no se pinta', () => {
        assert.equal(t(MESSAGES, 'errors'), '');
    });
});

describe('parámetros', () => {
    test('sustituye los marcadores como hace __()', () => {
        assert.equal(tp(MESSAGES, 'guests_count', { count: 8 }), '8 invitados');
    });

    /**
     * ⚠️ El aviso de señal del carrito lleva DOS marcadores, y el símbolo del euro va DENTRO del
     * valor sustituido, no en la plantilla. El precedente que había en el cajón encadenaba un solo
     * `.replace()`, que dejaría `:rest` sin sustituir.
     */
    test('sustituye los DOS marcadores del aviso de señal', () => {
        assert.equal(
            tp(MESSAGES, 'deposit_card_note', { deposit: '30,00 €', rest: '114,00 €' }),
            'Señal 30,00 € · 114,00 € en el parque'
        );
    });

    /** ⚠️ `String.replace` con patrón de texto sustituye solo la primera: aquí hay dos `:count`. */
    test('sustituye TODAS las apariciones del mismo marcador', () => {
        assert.equal(tp(MESSAGES, 'cart_items', { count: 2 }), '2 artículo|2 artículos');
    });

    /**
     * Laravel ordena las sustituciones de más larga a más corta para que `:name` no se coma el
     * principio de `:names`. Sin ese orden, el segundo marcador queda partido.
     */
    test('un marcador que es prefijo de otro no lo rompe', () => {
        assert.equal(
            tp({ x: ':fields y :field' }, 'x', { field: 'A', fields: 'B' }),
            'B y A'
        );
    });
});

describe('pluralización', () => {
    test('en español el singular es solo el uno', () => {
        assert.equal(tc(MESSAGES, 'cart_items', 1, 'es'), '1 artículo');
        assert.equal(tc(MESSAGES, 'cart_items', 2, 'es'), '2 artículos');
        assert.equal(tc(MESSAGES, 'cart_items', 0, 'es'), '0 artículos');
    });

    /**
     * ⚠️ **El caso que un `n === 1 ? sing : plur` falla.** En francés el CERO cae en el singular
     * (medido contra `MessageSelector::getPluralIndex('fr', 0)` → 0), y el cero es justo el número
     * que más se ve en una barra de carrito.
     */
    test('en francés el cero cae en el SINGULAR', () => {
        assert.equal(tc(MESSAGES, 'cart_items', 0, 'fr'), '0 artículo');
        assert.equal(tc(MESSAGES, 'cart_items', 1, 'fr'), '1 artículo');
        assert.equal(tc(MESSAGES, 'cart_items', 2, 'fr'), '2 artículos');
    });

    test('el idioma se normaliza: `es-ES` y `es_ES` son español', () => {
        assert.equal(tc(MESSAGES, 'cart_items', 2, 'es-ES'), '2 artículos');
        assert.equal(tc(MESSAGES, 'cart_items', 2, 'es_ES'), '2 artículos');
    });

    test('una clave sin barra se devuelve entera, no partida', () => {
        assert.equal(tc(MESSAGES, 'guests_count', 3, 'es'), '3 invitados');
    });
});
