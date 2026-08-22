import { test, describe } from 'node:test';
import assert from 'node:assert/strict';
import { createPinia, setActivePinia } from 'pinia';
import { useTimeStore } from './time.js';

/**
 * La red del store de la HORA. Como en `date.test.js`, casi todo se lee **ANTES y DESPUÉS**: es la
 * lección de `DECISIONES #118` —un `computed` que nunca se evaluó no puede estar rancio—.
 */
const OFERTA = [
    { time: '10:00', available: 20, max_quantity: 20 },
    { time: '12:00', available: 12, max_quantity: 4 },   // un PACK: los dos números NO coinciden
];

function store() {
    setActivePinia(createPinia());

    return useTimeStore();
}

describe('el store de la hora', () => {
    test('arranca vacío', () => {
        const h = store();

        assert.deepEqual(h.offered, []);
        assert.equal(h.selected, null);
        assert.equal(h.current, null);
        assert.equal(h.maxQuantity, 0, 'sin hora elegida no se puede pedir nada');
    });

    test('el tope sale de `max_quantity` y NO de `available`', () => {
        const h = store();
        h.setOffer(OFERTA);

        h.select('10:00');
        assert.equal(h.maxQuantity, 20);

        h.select('12:00');
        assert.equal(
            h.maxQuantity, 4,
            'en un pack `available` (12) y `max_quantity` (4) NO coinciden: acotar por el primero '
            + 'dejaría pedir más plazas de las que el checkout admite',
        );
    });

    test('la hora elegida se REFRESCA, con sus dos números', () => {
        const h = store();
        h.setOffer(OFERTA);

        assert.equal(h.current, null);          // ← crea la caché
        h.select('10:00');
        assert.equal(h.current?.available, 20);

        h.select('12:00');
        assert.equal(h.current?.available, 12, 'cambiar de hora tiene que traer la otra fila');

        h.select('23:00');
        assert.equal(h.current, null, 'una hora que no está en la oferta no existe');
    });

    test('una oferta que no es lista no rompe nada', () => {
        const h = store();

        for (const basura of [null, undefined, 'nope', 7, {}]) {
            h.setOffer(basura);
            assert.deepEqual(h.offered, []);
        }
    });

    test('olvidar la hora CONSERVA la oferta; reiniciar se lleva las dos', () => {
        const h = store();
        h.setOffer(OFERTA);
        h.select('10:00');

        assert.equal(h.maxQuantity, 20);        // ← la caché

        h.clearSelection();
        assert.equal(h.selected, null);
        assert.equal(h.maxQuantity, 0);
        assert.equal(h.offered.length, 2, 'volver del paso 4 al 3 no vuelve a pedir las horas');

        h.reset();
        assert.deepEqual(h.offered, [], 'pero cambiar de día sí: la oferta era de ESE día');
    });
});
