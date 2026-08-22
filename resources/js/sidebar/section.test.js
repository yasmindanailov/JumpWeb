import { test, describe } from 'node:test';
import assert from 'node:assert/strict';
import { DEFAULT_SECTION, SECTIONS, isSection, publishedIdentifying, publishedMode } from './section.js';
import { FUNNEL_STEPS, modeOf } from './machine.js';

/**
 * La red del nivel SECCIÓN (`docs/specs/area-cliente.md` §4.5).
 *
 * ⚠️ **Se prueba aquí y no en el componente por lo de siempre**: las señales que el cajón publica
 * viven FUERA de su marcado —`layout.blade.php` y `account-context`—, así que **ningún diff de árbol
 * puede verlas**. Ya se cobraron dos regresiones silenciosas antes de que existiera esta capa
 * (`DECISIONES #118`: el modo llevaba muerto desde 4.1 y nadie lo notó).
 */

describe('el catálogo de secciones', () => {
    test('el cajón abre en la COMPRA, que es a lo que se viene desde la landing', () => {
        assert.equal(DEFAULT_SECTION, SECTIONS.PURCHASE);
    });

    test('reconoce las secciones que existen y rechaza las que no', () => {
        assert.equal(isSection(SECTIONS.PURCHASE), true);
        assert.equal(isSection(SECTIONS.ACCOUNT), true);
        assert.equal(isSection('ajustes'), false);
        assert.equal(isSection(undefined), false);
        assert.equal(isSection(null), false);
    });
});

describe('el modo que el cajón publica hacia fuera', () => {
    test('en la compra es el del EMBUDO, paso a paso y sin tocarlo', () => {
        for (const step of FUNNEL_STEPS) {
            assert.equal(
                publishedMode(SECTIONS.PURCHASE, modeOf(step)),
                modeOf(step),
                `el paso ${step} deja de publicar su modo del embudo`,
            );
        }
    });

    test('en la cuenta publica `account`, y NO reutiliza `cart`', () => {
        assert.equal(publishedMode(SECTIONS.ACCOUNT, 'catalog'), 'account');

        // ⚠️ La mitad que de verdad protege: `cart` también colapsaría el bloque de cuenta, así que
        // el fallo sería INVISIBLE en pantalla. Lo que se rompe al reutilizarlo es la verdad de la
        // señal (`#115`), y eso solo lo puede sostener un test.
        assert.notEqual(publishedMode(SECTIONS.ACCOUNT, 'cart'), 'cart');
    });

    test('el modo de la cuenta NO pisa al del embudo cuando se vuelve a la compra', () => {
        assert.equal(publishedMode(SECTIONS.PURCHASE, 'cart'), 'cart');
    });

    test('una sección desconocida degrada al modo del embudo, no a la nada', () => {
        assert.equal(publishedMode('inventada', 'booking'), 'booking');
    });
});

describe('la señal que bloquea los botones de login de FUERA', () => {
    test('en la compra la publica tal cual: es el embudo quien pide identificarse', () => {
        assert.equal(publishedIdentifying(SECTIONS.PURCHASE, true), true);
        assert.equal(publishedIdentifying(SECTIONS.PURCHASE, false), false);
    });

    test('⚠️ en la cuenta es SIEMPRE false, aunque el embudo se quedara en el paso 5', () => {
        // El caso real: el cliente está identificándose para pagar y salta a ver sus reservas. Si la
        // señal se quedara pegada, los botones de fuera quedarían muertos sin nada que lo explicara.
        assert.equal(publishedIdentifying(SECTIONS.ACCOUNT, true), false);
    });
});
