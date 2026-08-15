import test from 'node:test';
import assert from 'node:assert/strict';

import { sectionsFrom, toItem, totalItems, searchIsEnabled } from './catalog.js';

/**
 * El catálogo del paso 1 (Fase 4 · paso 4.7·2b·2·B).
 *
 * ⚠️ **Estas funciones no tenían NINGÚN test hasta que salieron de `Sidebar.vue`**, y no por descuido
 * de nadie: dentro de un componente no las alcanza `node --test`, y el diff de árbol tampoco las
 * ejecutaba porque a Vue se le pasaba el view-model del servidor ya traducido. O sea, la traducción
 * que de verdad corre en el navegador era la única pieza del paso 1 sin red.
 */

const ENTRY = {
    id: 7,
    type: 'entry',
    name: 'Jump 1 hora',
    badge: 'Popular',
    features: ['Calcetines incluidos', 'Todas las zonas'],
    from_price_cents: 990,
    period_label: 'por persona',
    deposit_label: null,
    featured: false,
};

const PACK = {
    id: 12,
    type: 'pack',
    name: 'Cumpleaños',
    badge: null,
    features: [],
    from_price_cents: 18000,
    period_label: null,
    deposit_label: 'Señal de 30,00 €',
    featured: true,
};

test('reparte el catálogo plano en las dos secciones del cajón', () => {
    const sections = sectionsFrom([ENTRY, PACK]);

    assert.deepEqual(sections.map((s) => s.key), ['entries', 'services']);
    assert.deepEqual(sections[0].items.map((i) => i.id), [7]);
    assert.deepEqual(sections[1].items.map((i) => i.id), [12]);
});

/** Las dos secciones se emiten SIEMPRE: `CatalogStep` decide qué pinta, no este módulo. */
test('un catálogo sin packs sigue emitiendo las DOS secciones, la segunda vacía', () => {
    const sections = sectionsFrom([ENTRY]);

    assert.equal(sections.length, 2);
    assert.deepEqual(sections[1], { key: 'services', items: [] });
});

test('un catálogo vacío, nulo o corrupto no revienta y da dos secciones vacías', () => {
    for (const input of [[], null, undefined, 'no soy una lista']) {
        assert.deepEqual(sectionsFrom(input).map((s) => s.items.length), [0, 0], String(input));
    }
});

/**
 * ⚠️ El orden es el de LLEGADA, que es el `position` del panel. La API ya lo ordena
 * (`CatalogReader` hace `orderBy('position')`); reordenar aquí pisaría una decisión del panel.
 */
test('conserva el orden de llegada dentro de cada sección', () => {
    const sections = sectionsFrom([
        { ...ENTRY, id: 3, name: 'Tercera' },
        { ...ENTRY, id: 1, name: 'Primera' },
        { ...ENTRY, id: 2, name: 'Segunda' },
    ]);

    assert.deepEqual(sections[0].items.map((i) => i.id), [3, 1, 2]);
});

/** Un tipo que el cajón no pinta (un complemento suelto) no se cuela en ninguna sección. */
test('descarta los tipos que el paso 1 no enseña', () => {
    const sections = sectionsFrom([ENTRY, { ...ENTRY, id: 99, type: 'addon' }]);

    assert.deepEqual(sections[0].items.map((i) => i.id), [7]);
    assert.deepEqual(sections[1].items, []);
});

test('traduce el producto de la API a la fila que pinta el catálogo', () => {
    assert.deepEqual(toItem(ENTRY), {
        id: 7,
        name: 'Jump 1 hora',
        is_pack: false,
        featured: false,
        badge: 'Popular',
        features: 'Calcetines incluidos · Todas las zonas',
        from: 990,
        period_label: 'por persona',
        deposit_label: '',
        search: 'jump 1 hora calcetines incluidos todas las zonas',
    });
});

/**
 * ⚠️ Los nulos del contrato tienen que salir como CADENA VACÍA, no como «null».
 *
 * `badge`, `period_label` y `deposit_label` son nulables en `openapi/v1.yaml`, y el paso 1 los
 * interpola en el marcado: dejarlos pasar pintaría la palabra «null» en la tarjeta. `from` sí es nulo
 * legítimo —«este producto no se vende ese día»— y el componente decide qué hacer con él.
 */
test('los nulables del contrato salen vacíos, salvo el precio', () => {
    const item = toItem(PACK);

    assert.equal(item.badge, '');
    assert.equal(item.period_label, '');
    assert.equal(item.deposit_label, 'Señal de 30,00 €');
    assert.equal(item.is_pack, true);
    assert.equal(item.featured, true);

    assert.equal(toItem({ ...PACK, from_price_cents: null }).from, null);
});

test('un producto sin ventajas no compone una cadena con separadores sueltos', () => {
    assert.equal(toItem({ ...ENTRY, features: [] }).features, '');
    assert.equal(toItem({ ...ENTRY, features: undefined }).features, '');
});

test('el índice del buscador es minúsculas y junta nombre y ventajas', () => {
    assert.equal(
        toItem({ ...ENTRY, name: 'JUMP', features: ['Sócks'] }).search,
        'jump sócks'
    );
});

test('cuenta los productos de las dos secciones', () => {
    assert.equal(totalItems(sectionsFrom([ENTRY, PACK, { ...ENTRY, id: 8 }])), 3);
    assert.equal(totalItems([]), 0);
    assert.equal(totalItems(null), 0);
});

/**
 * ⚠️ **El umbral es estrictamente mayor**, y este es el caso que lo fija.
 *
 * Lo declara el contrato («total > catalog_search_min_items») y lo aplica el servidor con el mismo
 * operador. Con `>=`, un catálogo de exactamente el tamaño del umbral enseñaría el buscador en un
 * motor y no en el otro — y el diff de árbol solo lo vería si algún caso cayera justo en ese número.
 */
test('el buscador aparece por ENCIMA del umbral, no al alcanzarlo', () => {
    const dos = sectionsFrom([ENTRY, PACK]);

    assert.equal(searchIsEnabled(dos, 1), true, '2 > 1 → se enseña');
    assert.equal(searchIsEnabled(dos, 2), false, '2 > 2 es falso → NO se enseña');
    assert.equal(searchIsEnabled(dos, 3), false);
});

/** Sin umbral configurado no hay buscador: es lo que hace el servidor con el ajuste vacío. */
test('sin umbral numérico no se enseña el buscador', () => {
    const dos = sectionsFrom([ENTRY, PACK]);

    for (const threshold of [null, undefined, '1', NaN]) {
        assert.equal(searchIsEnabled(dos, threshold), false, String(threshold));
    }
});
