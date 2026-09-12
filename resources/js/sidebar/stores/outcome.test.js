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
        o.declinedReason = 'Tarjeta caducada';
        o.holdUntil = '18:42';
        o.confirming = true;
        o.retrying = true;

        assert.equal(o.hasOrder, true);   // ← crea la caché del getter

        o.clear();

        assert.equal(o.orderCode, '');
        assert.equal(o.hasOrder, false, 'el getter tiene que refrescarse, no quedarse cacheado');
        assert.equal(o.gateway, null);
        assert.equal(o.confirmation, null);
        assert.equal(o.declinedReason, '');
        assert.equal(o.holdUntil, '', 'la hora de caducidad es del pedido anterior: se barre con él');
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

    /**
     * ⚠️ **Sin respuesta no se pinta el bloque; CON respuesta se pinta siempre.** El servidor cae a
     * `default` cuando no conoce el código, así que «hay respuesta» y «hay motivo conocido» no son lo
     * mismo — y la diferencia se ve en pantalla.
     *
     * ⚠️ **Y la hora de caducidad sigue la misma regla** (`#563`): sin respuesta no se promete un
     * plazo, porque no se conoce. Un desenlace de dinero no puede inventar una hora.
     */
    test('sin respuesta no hay ni motivo ni hora que prometer', () => {
        const o = store();
        const messages = { payment_failed: { reasons: { cvv_wrong: 'El CVV no es correcto.', default: 'No se pudo completar.' } } };

        o.applyDeclinedReason(messages, { declined_reason: 'cvv_wrong', expires_at: '2026-09-13T18:42:00+02:00' }, 'es');
        assert.equal(o.declinedReason, 'El CVV no es correcto.');
        // ⚠️ **Se asevera que está FORMATEADA, no solo que no esté vacía**: lo que va a pantalla es
        // «te guardamos la plaza hasta las …», y ahí un ISO crudo sería basura dentro de una promesa.
        // Con `assert.notEqual(…, '')` la guarda pasaba en verde poniendo el `expires_at` tal cual.
        assert.match(o.holdUntil, /^\d{1,2}[:.]\d{2}$/, 'la hora va FORMATEADA, no el ISO del contrato');

        o.applyDeclinedReason(messages, null, 'es');
        assert.equal(o.declinedReason, '');
        assert.equal(o.holdUntil, '');
    });

    /**
     * ⚠️ **Un pedido SIN caducidad no deja la promesa a medias**: `expires_at` es anulable en el
     * contrato, y ahí la pantalla tiene que volver a su frase de siempre en vez de pintar una hora
     * vacía dentro de una frase que la anuncia.
     */
    test('con motivo pero sin caducidad se dice el motivo y no la hora', () => {
        const o = store();
        const messages = { payment_failed: { reasons: { default: 'No se pudo completar.' } } };

        o.applyDeclinedReason(messages, { declined_reason: null, expires_at: null }, 'es');

        assert.equal(o.declinedReason, 'No se pudo completar.');
        assert.equal(o.holdUntil, '');
    });
});
