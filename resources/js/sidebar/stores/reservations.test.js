import { test, describe, beforeEach } from 'node:test';
import assert from 'node:assert/strict';
import { createPinia, setActivePinia } from 'pinia';
import { useReservationsStore } from './reservations.js';

function fakeApi(respuestas) {
    const llamadas = [];

    return {
        llamadas,
        get: async (url) => {
            llamadas.push(url);

            return respuestas[url] ?? { ok: false, status: 500, data: null };
        },
    };
}

describe('las próximas reservas', () => {
    beforeEach(() => setActivePinia(createPinia()));

    test('publica la más próxima y cuántas quedan', async () => {
        const store = useReservationsStore();
        const api = fakeApi({
            '/me/reservations': {
                ok: true,
                status: 200,
                data: {
                    data: [{ date: '2026-06-10', date_label: 'Mié. 10 jun.', time_window: '10:00–11:00', product_name: 'Cumple' }],
                    meta: { total: 3 },
                },
            },
        });

        await store.ensure({ api });

        assert.equal(store.next.date_label, 'Mié. 10 jun.', 'la etiqueta llega compuesta del servidor');
        assert.equal(store.upcoming, 3, 'el contador sale de `meta.total`, no de contar la lista');
    });

    test('⚠️ el contador NO se deduce contando: la lista es corta, `meta.total` es la verdad', async () => {
        const store = useReservationsStore();
        const api = fakeApi({ '/me/reservations': { ok: true, status: 200, data: { data: [{ date_label: 'x' }], meta: { total: 7 } } } });

        await store.ensure({ api });

        assert.equal(store.upcoming, 7);
    });

    test('sin reservas, no hay próxima y el contador es 0', async () => {
        const store = useReservationsStore();

        await store.ensure({ api: fakeApi({ '/me/reservations': { ok: true, status: 200, data: { data: [], meta: { total: 0 } } } }) });

        assert.equal(store.next, null);
        assert.equal(store.upcoming, 0);
    });

    test('no repite la petición', async () => {
        const store = useReservationsStore();
        const api = fakeApi({ '/me/reservations': { ok: true, status: 200, data: { data: [], meta: { total: 0 } } } });

        await store.ensure({ api });
        await store.ensure({ api });

        assert.equal(api.llamadas.length, 1);
    });

    test('⚠️ un fallo NO rompe el índice ni deja el velo girando: es contexto de cortesía', async () => {
        const store = useReservationsStore();

        await store.ensure({ api: fakeApi({}) });

        assert.equal(store.next, null);
        assert.equal(store.loading, false);
        assert.equal(store.loaded, false, 'y podrá reintentarse en la próxima entrada');
    });
});
