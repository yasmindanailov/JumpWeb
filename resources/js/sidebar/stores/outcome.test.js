import { test, describe } from 'node:test';
import assert from 'node:assert/strict';
import { createPinia, setActivePinia } from 'pinia';
import { useOutcomeStore } from './outcome.js';

/**
 * La red del store del DESENLACE. Se dobla solo la frontera HTTP: `outcome.js` corre de verdad, con
 * sus casos y su paridad contra el servidor ya puestos.
 */
function store() {
    setActivePinia(createPinia());

    return useOutcomeStore();
}

const api = (respuestas) => ({
    get: async (url) => respuestas[url] ?? { ok: false, status: 404, data: null },
    post: async (url) => respuestas[url] ?? { ok: false, status: 404, data: null },
});

describe('el store del desenlace', () => {
    test('sin código de pedido no hay nada de lo que hablar', () => {
        const o = store();

        assert.equal(o.orderCode, '');
        assert.equal(o.hasOrder, false);

        o.setOrderCode('JW-123');
        assert.equal(o.hasOrder, true);

        o.setOrderCode(null);
        assert.equal(o.hasOrder, false, 'nulo y vacío son lo mismo');
    });

    /**
     * ⚠️ El caso que protege a quien ACABA de pagar: sin resumen la pantalla se pinta igual, con su
     * código y el aviso del correo. Un `null` aquí no es un fallo que haya que gritar.
     */
    test('no poder traer el resumen deja `null`, no un error', async () => {
        const o = store();
        o.setOrderCode('JW-123');

        const r = await o.loadConfirmation({ api: api({}) });

        assert.equal(r, null);
        assert.equal(o.confirmation, null);
        assert.equal(o.orderCode, 'JW-123', 'y el código sigue ahí: la pantalla lo necesita');
    });

    /**
     * ⚠️ Barrer el contexto del pedido anterior NO se ve en el paso 1, y por eso se olvida. Dejarlo
     * latente hace que una segunda compra arrastre el desenlace de la primera.
     */
    test('barrer deja el contexto del pedido anterior en blanco', () => {
        const o = store();
        o.setOrderCode('JW-123');
        o.setGateway({ action: 'https://sis-t.redsys.es', fields: {} });
        o.confirmation = { code: 'JW-123' };
        o.setDeclinedReason('Tarjeta caducada');
        o.confirming = true;
        o.retrying = true;

        assert.equal(o.hasOrder, true);   // ← crea la caché del getter

        o.clear();

        assert.equal(o.orderCode, '');
        assert.equal(o.hasOrder, false, 'el getter tiene que refrescarse, no quedarse cacheado');
        assert.equal(o.gateway, null);
        assert.equal(o.confirmation, null);
        assert.equal(o.declinedReason, '');
        assert.equal(o.confirming, false);
        assert.equal(o.retrying, false);
    });

    /**
     * ⚠️ El bloque de «registro del parque» NO se borra al barrer: viene de `GET /config`, es de la
     * instalación y no del pedido. Volver a pedirlo sería una petición por compra.
     */
    test('el registro del parque sobrevive al barrido: es de la instalación, no del pedido', () => {
        const o = store();
        o.setRegistration({ url: 'https://parque.test/registro' });
        o.setOrderCode('JW-9');

        o.clear();

        assert.deepEqual(o.registration, { url: 'https://parque.test/registro' });
    });

    test('vacío en el motivo del rechazo significa «no se pudo preguntar»', () => {
        const o = store();

        assert.equal(o.declinedReason, '');
        o.setDeclinedReason('Tarjeta caducada');
        assert.equal(o.declinedReason, 'Tarjeta caducada');
        o.setDeclinedReason(null);
        assert.equal(o.declinedReason, '');
    });
});
