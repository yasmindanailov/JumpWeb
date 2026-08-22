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

    /**
     * ⚠️⚠️ **`AFORO-02`: la consulta de horas LLEVA LA CESTA, y no es opcional.** `offerableTimes()`
     * descuenta los ocupantes que la propia cesta ya retiene; preguntar sin ella ofrece horas y topes
     * que el checkout luego RECHAZARÍA. El caso mira el CUERPO de la petición, no el resultado.
     */
    test('pedir las horas manda la cesta en el cuerpo', async () => {
        const h = store();
        const enviados = [];
        const api = { post: async (url, body) => { enviados.push({ url, body }); return { ok: true, data: { data: OFERTA } }; } };

        await h.loadOffer({
            api,
            productId: 7,
            date: '2026-09-28',
            cartLines: [{ product_id: 3, date: '2026-09-28', time: '10:00', quantity: 2, addons: [] }],
        });

        assert.equal(enviados[0].url, '/availability/7/times');
        assert.equal(enviados[0].body.date, '2026-09-28');
        assert.equal(enviados[0].body.items.length, 1, 'sin la cesta, el servidor ofrecería plazas que ya están retenidas');
        assert.equal(h.offered.length, 2, 'y la oferta se guarda');
    });

    test('si la petición falla, la oferta se queda vacía y no con la anterior', async () => {
        const h = store();
        h.setOffer(OFERTA);
        h.select('10:00');

        await h.loadOffer({ api: { post: async () => ({ ok: false, status: 500 }) }, productId: 7, date: 'x' });

        assert.deepEqual(h.offered, [], 'enseñar horas de otro día sería peor que no enseñar ninguna');
        assert.equal(h.maxQuantity, 0);
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
