import test from 'node:test';
import assert from 'node:assert/strict';
import { anchorFor, applyIntent, resolveIntent } from './intent.js';

/**
 * La costura de intención (`DECISIONES #117`).
 *
 * ⚠️ Estos casos prueban la DECISIÓN. Que además exista alguien que la llame en producción —el fallo
 * real: los dos extremos probados y el medio sin cablear— lo vigila `SidebarIntentWiringTest`, y no
 * puede vigilarse desde aquí: un test que llama a la función es, precisamente, lo que no bastaba.
 */

test('el destino de cada intención', async (t) => {
    await t.test('«packs» va a la sección de servicios, y es paridad EXACTA con el motor retirado', () => {
        assert.deepEqual(resolveIntent({ type: 'packs' }), { section: 'services', zone: null, exact: true });
    });

    await t.test('«zone» va a entradas y se declara NO exacta: la SPA no sabe de zonas', () => {
        assert.deepEqual(resolveIntent({ type: 'zone', slug: 'jump' }), { section: 'entries', zone: 'jump', exact: false });
    });

    await t.test('una zona sin slug no es un destino', () => {
        assert.equal(resolveIntent({ type: 'zone' }), null);
        assert.equal(resolveIntent({ type: 'zone', slug: '   ' }), null);
    });

    await t.test('lo que no se reconoce no mueve nada', () => {
        for (const malo of [null, undefined, {}, { type: 'otra' }, 'packs', 42]) {
            assert.equal(resolveIntent(malo), null, `«${JSON.stringify(malo)}» no debería resolver`);
        }
    });
});

test('el ancla es el id que emite CatalogStep.vue', () => {
    assert.equal(anchorFor({ section: 'services' }), 'catalog-sec-services');
    assert.equal(anchorFor({ section: 'entries' }), 'catalog-sec-entries');
    assert.equal(anchorFor(null), null);
});

test('aplicar una intención', async (t) => {
    const espia = (anchorExiste = true) => {
        const hechos = { catalogo: 0, esperado: null, desplazado: null };

        return {
            hechos,
            deps: {
                goToCatalog: () => { hechos.catalogo += 1; },
                waitForAnchor: async (id) => { hechos.esperado = id; return anchorExiste ? { id } : null; },
                scrollTo: (el) => { hechos.desplazado = el; },
            },
        };
    };

    await t.test('«packs»: vuelve al catálogo y desplaza a servicios', async () => {
        const { hechos, deps } = espia();
        const r = await applyIntent({ type: 'packs' }, deps);

        assert.deepEqual(r, { applied: true, anchor: 'catalog-sec-services', exact: true, zone: null });
        assert.equal(hechos.catalogo, 1, 'el enlace profundo pide EMPEZAR en el catálogo');
        assert.equal(hechos.esperado, 'catalog-sec-services');
        assert.deepEqual(hechos.desplazado, { id: 'catalog-sec-services' });
    });

    await t.test('«zone»: aterriza en entradas, y lo devuelve marcado como NO exacto', async () => {
        const { deps } = espia();
        const r = await applyIntent({ type: 'zone', slug: 'kids' }, deps);

        assert.equal(r.applied, true);
        assert.equal(r.anchor, 'catalog-sec-entries');
        assert.equal(r.exact, false, 'no se finge paridad: el motor viejo iba a la zona y este no puede');
        assert.equal(r.zone, 'kids');
    });

    await t.test('sin intención NO se toca el paso: un cajón a mitad de embudo no se rebobina solo', async () => {
        const { hechos, deps } = espia();
        const r = await applyIntent(null, deps);

        assert.deepEqual(r, { applied: false, reason: 'no_intent' });
        assert.equal(hechos.catalogo, 0, 'ir al catálogo sin motivo tiraría la selección del cliente');
        assert.equal(hechos.esperado, null);
    });

    await t.test('si la sección no existe se dice, y el cajón se queda en el catálogo', async () => {
        // `CatalogStep.vue` no pinta las secciones vacías: un catálogo sin packs no tiene destino.
        const { hechos, deps } = espia(false);
        const r = await applyIntent({ type: 'packs' }, deps);

        assert.deepEqual(r, { applied: false, reason: 'anchor_missing', anchor: 'catalog-sec-services' });
        assert.equal(hechos.catalogo, 1, 'el paso 1 SÍ se aplicó: es la mitad que siempre se puede');
        assert.equal(hechos.desplazado, null);
    });
});
