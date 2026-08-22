import { test, describe } from 'node:test';
import assert from 'node:assert/strict';
import { createPinia, setActivePinia } from 'pinia';
import { useBookingStore } from './booking.js';

function store() {
    setActivePinia(createPinia());

    return useBookingStore();
}

describe('el store del estado de reservas', () => {
    test('arranca sin saber nada, que no es lo mismo que «no hay pausa»', () => {
        const b = store();

        assert.equal(b.status, null);
        assert.equal(b.isPaused, false);
    });

    test('la pausa se REFRESCA al releer', async () => {
        const b = store();

        assert.equal(b.isPaused, false);   // ← crea la caché del getter

        await b.refresh({ api: { get: async () => ({ ok: true, data: { reservations_paused: true } }) } });

        assert.equal(b.isPaused, true, 'la dueña acciona el interruptor con clientes navegando');
    });

    /**
     * ⚠️ Devolver si se PUDO releer no es cosmético: quien pasa al pago lo necesita para no dejar un
     * clic mudo cuando el veredicto dice «pausa» y el cartel que lo explicaría no llega.
     */
    test('si no se puede releer, se dice, y el estado anterior NO se pisa', async () => {
        const b = store();
        await b.refresh({ api: { get: async () => ({ ok: true, data: { reservations_paused: true } }) } });

        const pudo = await b.refresh({ api: { get: async () => ({ ok: false, status: 500 }) } });

        assert.equal(pudo, false);
        assert.equal(b.isPaused, true, 'un fallo de red no significa que las reservas se hayan reabierto');
    });
});
