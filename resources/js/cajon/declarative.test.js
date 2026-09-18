import { test, describe } from 'node:test';
import assert from 'node:assert/strict';
import { installDeclarativeOpeners } from './declarative.js';

/** F4 · T2 — abrir el cajón con atributos `data-jw-*`, sin una línea de JS en la landing. */

function montar({ cajon } = {}) {
    const llamadas = [];
    const falso = cajon === undefined ? {
        open: () => llamadas.push(['open']),
        openWith: (intencion) => llamadas.push(['openWith', intencion]),
        openAccount: (evento, zona) => { evento.preventDefault(); llamadas.push(['openAccount', zona]); },
    } : cajon;

    let oyente = null;
    const doc = { addEventListener: (tipo, fn) => { if (tipo === 'click') oyente = fn; } };

    installDeclarativeOpeners(() => falso, doc);

    /** Un clic sobre un elemento con ese `dataset` (o sobre nada, si es `null`). */
    const clic = (dataset, extra = {}) => {
        const evento = {
            defaultPrevented: false,
            button: 0,
            target: { closest: () => (dataset === null ? null : { dataset }) },
            preventDefault() { this.defaultPrevented = true; },
            ...extra,
        };

        oyente(evento);

        return evento;
    };

    return { llamadas, clic };
}

describe('abrir el cajón con atributos', () => {
    test('data-jw-open a secas abre el catálogo y se traga el clic', () => {
        const { llamadas, clic } = montar();

        const evento = clic({ jwOpen: '' });

        assert.deepEqual(llamadas, [['open']]);
        assert.equal(evento.defaultPrevented, true);
    });

    test('cada atributo declara SU intención', () => {
        const { llamadas, clic } = montar();

        clic({ jwOpen: 'packs' });
        clic({ jwOpenZone: 'kids' });
        clic({ jwOpenProduct: '100' });
        clic({ jwOpenAccount: 'orders' });

        assert.deepEqual(llamadas, [
            ['openWith', { type: 'packs' }],
            ['openWith', { type: 'zone', slug: 'kids' }],
            ['openWith', { type: 'product', id: 100 }],   // número: es lo que el motor compara con el catálogo
            ['openAccount', 'orders'],
        ]);
    });

    test('un clic fuera de un abridor no hace nada', () => {
        const { llamadas, clic } = montar();

        const evento = clic(null);

        assert.deepEqual(llamadas, []);
        assert.equal(evento.defaultPrevented, false);
    });

    /**
     * ⚠️ El `href` es el camino largo y tiene que seguir vivo (`DECISIONES #117`): un clic central o con
     * Ctrl/⌘ quiere una pestaña nueva con `/entradas` o `/login`, que siguen siendo puertas. Tragárselo
     * dejaría esos tres gestos sin hacer NADA.
     */
    test('un clic con modificador o con otro botón se deja pasar', () => {
        const { llamadas, clic } = montar();

        for (const extra of [{ ctrlKey: true }, { metaKey: true }, { shiftKey: true }, { button: 1 }]) {
            assert.equal(clic({ jwOpen: '' }, extra).defaultPrevented, false);
        }

        assert.deepEqual(llamadas, []);
    });

    test('sin cajón en la página, el enlace hace lo que dice su href', () => {
        const { clic } = montar({ cajon: null });

        assert.equal(clic({ jwOpen: '' }).defaultPrevented, false);
    });

    /**
     * ⚠️ El cajón se PIDE en cada clic, no se captura: con Alpine, `window.JumpWeb.cajon` pasa a ser el proxy
     * reactivo tras `alpine:init`, y quien guardara el primero abriría «por dentro» sin mover la carcasa.
     */
    test('pregunta por el cajón en cada clic', () => {
        const usados = [];
        let actual = { open: () => usados.push('crudo') };
        let oyente = null;

        installDeclarativeOpeners(() => actual, { addEventListener: (_, fn) => { oyente = fn; } });

        const evento = () => ({ button: 0, target: { closest: () => ({ dataset: { jwOpen: '' } }) }, preventDefault() {} });

        oyente(evento());
        actual = { open: () => usados.push('proxy') };
        oyente(evento());

        assert.deepEqual(usados, ['crudo', 'proxy']);
    });
});
