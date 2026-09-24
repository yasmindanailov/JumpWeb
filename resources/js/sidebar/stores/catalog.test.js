import { test, describe } from 'node:test';
import assert from 'node:assert/strict';
import { createPinia, setActivePinia } from 'pinia';
import { useCatalogStore } from './catalog.js';

const SECCIONES = [
    { key: 'entries', items: [{ id: 1, name: 'Jump 1 h', is_pack: false }] },
    { key: 'services', items: [{ id: 9, name: 'Cumple Kids', is_pack: true }] },
];

function store() {
    setActivePinia(createPinia());

    return useCatalogStore();
}

describe('el store del catálogo', () => {
    test('elegir resuelve la FILA desde el listado ya descargado, sin esperar la ficha', () => {
        const c = store();
        c.setSections(SECCIONES);

        c.select(9);

        assert.equal(c.selectedId, 9);
        assert.equal(c.selectedRow?.name, 'Cumple Kids', 'la banda de progreso lo enseña de inmediato');
        assert.equal(c.product, null, 'la ficha todavía viaja');
    });

    /** La isla agrupa por ZONA (T3e·2): guarda el listado tal cual llegó, y lo que no es una lista no entra. */
    test('el listado se guarda tal cual, con su zona, y lo que no es una lista se queda vacío', () => {
        const c = store();

        c.setProducts([{ id: 100, type: 'entry', zone: { slug: 'kids' } }]);
        assert.equal(c.products[0].zone.slug, 'kids');

        c.setProducts(null);
        assert.deepEqual(c.products, []);
    });

    test('elegir un id que no está deja la fila en `null` sin romper', () => {
        const c = store();
        c.setSections(SECCIONES);

        c.select(404);

        assert.equal(c.selectedRow, null);
    });

    test('el suelo del selector se REFRESCA con la ficha, y solo un pack tiene mínimo', () => {
        const c = store();

        assert.equal(c.minQuantity, 1, 'sin ficha, uno');   // ← crea la caché
        c.setProduct({ type: 'entry' });
        assert.equal(c.minQuantity, 1);

        c.setProduct({ type: 'pack', min_quantity: 8 });
        assert.equal(c.minQuantity, 8, 'un cumpleaños de 8 niños no se vende de uno en uno');
        assert.equal(c.isPack, true);
    });

    /**
     * ⚠️ Las etiquetas del esquema NO se borran al cambiar de producto: la CESTA puede seguir
     * necesitándolas, porque el presupuesto no devuelve las respuestas del pack (RGPD) y sin ellas no
     * hay con qué emparejarlas.
     */
    test('las etiquetas de cada producto se acumulan y sobreviven al cambio de selección', () => {
        const c = store();
        c.rememberFields(1, [{ key: 'nombre' }]);
        c.rememberFields(9, [{ key: 'edad' }]);

        assert.deepEqual(Object.keys(c.fieldsByProduct), ['1', '9']);

        c.clearSelection();

        assert.deepEqual(Object.keys(c.fieldsByProduct), ['1', '9'], 'la cesta todavía las necesita');
        assert.equal(c.selectedId, null);
        assert.equal(c.product, null);
    });
});
