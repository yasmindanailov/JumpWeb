import { test, describe } from 'node:test';
import assert from 'node:assert/strict';
import { formState, resetForm, runForm } from './form-run.js';

/**
 * La red del guardián común de los formularios del área de cliente.
 *
 * ⚠️ **Lo que de verdad hay que sostener aquí es el ORDEN**, no la asignación de campos: que se limpie
 * **antes** de llamar, y no después. Una versión que limpiara al final dejaría el error del intento
 * anterior en pantalla mientras la petición nueva está en vuelo, y el cliente lo leería como el
 * resultado del intento nuevo — que es el verde falso que ya se pagó en el navegador
 * (`DECISIONES #120(p)`). Por eso el primer caso mira el estado **desde dentro de la llamada**.
 */

const CTX = { messages: {}, auth: { throttle: 'Espera :seconds s.' } };

const ok = (data = null) => ({ ok: true, status: 200, data, error: null, offline: false });
const rejected = (fields) => ({
    ok: false, status: 422, data: null, offline: false,
    error: { code: 'validation_failed', message: '', fields },
});

const store = () => ({ ...formState() });

describe('el estado inicial', () => {
    test('nace limpio y cada store tiene el SUYO', () => {
        const a = store();
        const b = store();

        assert.deepEqual(a, { busy: false, fields: {}, notice: '', done: false, expired: false });

        a.fields = { email: ['x'] };

        assert.deepEqual(b.fields, {}, 'dos stores comparten el mismo objeto de estado');
    });

    test('`resetForm` lo devuelve a como estaba', () => {
        const s = store();

        Object.assign(s, { busy: true, fields: { a: ['x'] }, notice: 'algo', done: true, expired: true });
        resetForm(s);

        assert.deepEqual(s, { busy: false, fields: {}, notice: '', done: false, expired: false });
    });
});

describe('mientras la petición está en vuelo', () => {
    test('el veredicto ANTERIOR ya no está en pantalla', async () => {
        const s = store();

        Object.assign(s, { fields: { current_password: ['La contraseña actual no es correcta.'] }, notice: 'Espera 30 s.' });

        let seen = null;

        await runForm(s, async () => {
            // Lo que la pantalla enseñaría en este instante.
            seen = { busy: s.busy, fields: { ...s.fields }, notice: s.notice };

            return ok();
        }, CTX);

        assert.deepEqual(seen, { busy: true, fields: {}, notice: '' });
    });
});

describe('cuando termina', () => {
    test('sale bien: `done` y nada que corregir', async () => {
        const s = store();

        const result = await runForm(s, async () => ok(), CTX);

        assert.equal(result, true);
        assert.equal(s.done, true);
        assert.equal(s.busy, false);
    });

    test('el servidor rechaza: el error va por campo y `done` sigue en falso', async () => {
        const s = store();

        const result = await runForm(s, async () => rejected({ current_password: ['No es correcta.'] }), CTX);

        assert.equal(result, false);
        assert.equal(s.done, false);
        assert.deepEqual(s.fields, { current_password: ['No es correcta.'] });
    });

    /** `busy` tiene que soltarse pase lo que pase, o la pantalla se queda con el botón deshabilitado. */
    test('si la llamada REVIENTA, deja de estar en vuelo', async () => {
        const s = store();

        await assert.rejects(() => runForm(s, async () => { throw new Error('boom'); }, CTX));

        assert.equal(s.busy, false);
    });
});

describe('el gancho posterior', () => {
    test('corre solo si salió bien, y con la respuesta delante', async () => {
        const s = store();
        const seen = [];

        await runForm(s, async () => ok({ id: 7 }), CTX, (response) => seen.push(response.data));
        await runForm(s, async () => rejected({ a: ['x'] }), CTX, (response) => seen.push(response.data));

        assert.deepEqual(seen, [{ id: 7 }], 'el gancho ha corrido sobre una respuesta rechazada');
    });

    test('se ESPERA: un gancho asíncrono termina antes de soltar `busy`', async () => {
        const s = store();
        let busyDuringHook = null;

        await runForm(s, async () => ok(), CTX, async () => {
            await Promise.resolve();
            busyDuringHook = s.busy;
        });

        assert.equal(busyDuringHook, true, 'el gancho corrió cuando la pantalla ya se creía libre');
        assert.equal(s.busy, false);
    });
});
