import { test, describe } from 'node:test';
import assert from 'node:assert/strict';
import {
    emptyGoogleScreen, loadGooglePending, loadGoogleScreen, runGoogleSignup, submitGoogleScreen, WAIVER_STALE,
} from './google.js';

/**
 * La red de la pantalla que completa un alta con Google (`specs/auth-con-google.md` §7).
 *
 * ⚠️ **El caso que más importa es el que NO manda el correo.** Que el `sub` y la dirección no salgan
 * de aquí es la defensa entera de esta pantalla: si viajaran, cualquiera crearía una cuenta con la
 * identidad verificada de otro. Un test que solo mirase el desenlace lo dejaría pasar, así que se
 * asevera el CUERPO enviado.
 */

const okResponse = { ok: true, status: 201, data: null, error: null };

function apiThatCaptures(response = okResponse) {
    const sent = [];

    return {
        sent,
        post: async (path, body) => {
            sent.push({ path, body });

            return response;
        },
    };
}

describe('el perfil que espera', () => {
    test('llega con nombre y correo', async () => {
        const api = { get: async () => ({ ok: true, status: 200, data: { name: 'Ana Pérez', email: 'ana@gmail.test' } }) };

        assert.deepEqual(await loadGooglePending({ api }), { name: 'Ana Pérez', email: 'ana@gmail.test' });
    });

    /** Google no siempre da nombre: la pantalla se pinta igual y el campo se teclea. */
    test('sin nombre, el campo llega vacío y el correo manda', async () => {
        const api = { get: async () => ({ ok: true, status: 200, data: { name: null, email: 'ana@gmail.test' } }) };

        assert.deepEqual(await loadGooglePending({ api }), { name: '', email: 'ana@gmail.test' });
    });

    test('un 404 es «no hay nada que completar», no un error', async () => {
        const api = { get: async () => ({ ok: false, status: 404, data: null, error: { code: 'not_found' } }) };

        assert.equal(await loadGooglePending({ api }), null);
    });

    /**
     * ⚠️ Sin correo no hay pantalla: es el único dato que no se puede teclear. Un cuerpo a medias se
     * trata como «no hay nada» en vez de pintar un formulario que crearía una cuenta sin identidad.
     */
    test('un cuerpo sin correo se trata como si no hubiera nada', async () => {
        const api = { get: async () => ({ ok: true, status: 200, data: { name: 'Ana' } }) };

        assert.equal(await loadGooglePending({ api }), null);
    });
});

describe('el envío', () => {
    /**
     * ⚠️⚠️ **LA guarda de esta pantalla.** Lo que se manda es solo lo que Google no sabe; el correo y
     * el `sub` los pone el servidor desde la sesión. Si algún día alguien «completa» este cuerpo con
     * el correo «para que el servidor no tenga que buscarlo», este caso se pone rojo.
     */
    test('NUNCA manda el correo ni el identificador de Google', async () => {
        const api = apiThatCaptures();

        await runGoogleSignup({
            form: { name: 'Ana Pérez', phone: '600111222', accept_terms: true, accept_waiver: true },
            api,
            waiver: { id: 7 },
        });

        assert.deepEqual(Object.keys(api.sent[0].body).sort(), [
            'accept_terms', 'accept_waiver', 'name', 'phone', 'waiver_document_id',
        ]);
        assert.equal(api.sent[0].path, '/auth/google/complete');
    });

    test('la casilla del descargo viaja con el id del texto que se sirvió', async () => {
        const api = apiThatCaptures();

        await runGoogleSignup({
            form: { name: 'Ana', phone: '600', accept_terms: true, accept_waiver: true },
            api,
            waiver: { id: 12 },
        });

        assert.equal(api.sent[0].body.accept_waiver, true);
        assert.equal(api.sent[0].body.waiver_document_id, 12);
    });

    /**
     * ⚠️ **Marcada SIN texto en memoria no se manda aceptada**, y no es una cortesía: aceptar sin decir
     * qué se leyó no prueba nada. Es la misma regla que `register.js` aplica al alta con contraseña.
     */
    test('sin documento en memoria, la casilla se manda desmarcada y sin id', async () => {
        const api = apiThatCaptures();

        await runGoogleSignup({
            form: { name: 'Ana', phone: '600', accept_terms: true, accept_waiver: true },
            api,
            waiver: null,
        });

        assert.equal(api.sent[0].body.accept_waiver, false);
        assert.equal(api.sent[0].body.waiver_document_id, null);
    });

    test('un 201 es un alta hecha', async () => {
        const outcome = await runGoogleSignup({ form: {}, api: apiThatCaptures() });

        assert.deepEqual(outcome, { ok: true, expired: false, stale: false, errors: { summary: [], fields: {} } });
    });

    /** Un 404 al enviar es «esto ya no existe»: la pantalla ofrece empezar otra vez, no un aviso. */
    test('un 404 se distingue de un fallo', async () => {
        const api = apiThatCaptures({ ok: false, status: 404, data: null, error: { code: 'not_found' } });

        const outcome = await runGoogleSignup({ form: {}, api });

        assert.equal(outcome.expired, true);
        assert.equal(outcome.stale, false);
        assert.deepEqual(outcome.errors, { summary: [], fields: {} });
    });

    /** El 409 del texto republicado tiene su propio desenlace: hay que RELEER y desmarcar. */
    test('el texto caducado se señala aparte', async () => {
        const api = apiThatCaptures({ ok: false, status: 409, data: null, error: { code: WAIVER_STALE } });

        const outcome = await runGoogleSignup({ form: {}, api });

        assert.equal(outcome.stale, true);
        assert.equal(outcome.expired, false);
        assert.equal(outcome.ok, false);
    });

    test('un 422 se traduce a avisos por campo, como el alta con contraseña', async () => {
        const api = apiThatCaptures({
            ok: false,
            status: 422,
            data: null,
            error: { code: 'validation_failed', fields: { phone: ['El teléfono es obligatorio.'] } },
        });

        const outcome = await runGoogleSignup({ form: {}, api });

        assert.equal(outcome.errors.fields.phone, 'El teléfono es obligatorio.');
        assert.deepEqual(outcome.errors.summary, ['El teléfono es obligatorio.']);
        assert.equal(outcome.expired, false);
        assert.equal(outcome.stale, false);
    });
});

describe('el estado de la pantalla', () => {
    test('sin perfil esperando, la pantalla nace en «ya no hay nada que completar»', async () => {
        const api = { get: async () => ({ ok: false, status: 404, data: null, error: { code: 'not_found' } }) };

        const state = await loadGoogleScreen({ api });

        assert.equal(state.expired, true);
        assert.equal(state.pending, null);
        assert.equal(state.busy, false);
    });

    test('con perfil, nace pintando el formulario', async () => {
        const api = { get: async () => ({ ok: true, status: 200, data: { name: 'Ana', email: 'ana@gmail.test' } }) };

        const state = await loadGoogleScreen({ api });

        assert.equal(state.expired, false);
        assert.deepEqual(state.pending, { name: 'Ana', email: 'ana@gmail.test' });
    });

    /**
     * ⚠️ **`busy` vuelve a `false` también cuando el servidor dice que no.** Es el modo de fallo más
     * silencioso de una pantalla: el botón se queda deshabilitado y quien mira no tiene forma de
     * saber que ya puede volver a intentarlo.
     */
    test('tras un «no» del servidor, el botón vuelve a estar vivo', async () => {
        const api = apiThatCaptures({
            ok: false, status: 422, data: null,
            error: { code: 'validation_failed', fields: { phone: ['Falta el teléfono.'] } },
        });

        const { state } = await submitGoogleScreen({
            state: { ...emptyGoogleScreen(), pending: { name: 'Ana', email: 'a@b.test' }, busy: true },
            form: {}, api,
        });

        assert.equal(state.busy, false);
        assert.equal(state.errors.fields.phone, 'Falta el teléfono.');
        assert.deepEqual(state.pending, { name: 'Ana', email: 'a@b.test' }, 'el perfil sigue ahí: el formulario tiene que poder corregirse');
    });

    /** Y con un 404 al enviar, la pantalla cambia de cara: ya no hay nada que corregir. */
    test('un 404 al enviar deja la pantalla en «empieza otra vez»', async () => {
        const api = apiThatCaptures({ ok: false, status: 404, data: null, error: { code: 'not_found' } });

        const { state, result } = await submitGoogleScreen({
            state: { ...emptyGoogleScreen(), pending: { name: 'Ana', email: 'a@b.test' }, busy: true },
            form: {}, api,
        });

        assert.equal(state.expired, true);
        assert.equal(state.pending, null);
        assert.equal(result.ok, false);
    });

    test('un alta buena devuelve el desenlace con el que la pantalla navega', async () => {
        const { state, result } = await submitGoogleScreen({
            state: { ...emptyGoogleScreen(), busy: true }, form: {}, api: apiThatCaptures(),
        });

        assert.equal(result.ok, true);
        assert.equal(state.busy, false);
        assert.equal(state.expired, false);
    });
});
