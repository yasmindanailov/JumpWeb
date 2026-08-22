import { test, describe } from 'node:test';
import assert from 'node:assert/strict';
import { forgotErrors, runForgot } from './forgot.js';

/**
 * La red de RECUPERAR CONTRASEÑA en el cajón (`specs/auth-en-cajon.md` §4.2, criterio CE-6).
 *
 * ⚠️ Lo que más se prueba aquí no es un desenlace: es **la AUSENCIA de desenlaces**. La propiedad de
 * `SEC-06` que este formulario tiene que conservar es que **una cuenta que existe y una que no
 * producen exactamente lo mismo**, y una propiedad de indistinguibilidad no se comprueba mirando una
 * rama —sería un mutante equivalente por diseño (`CONVENCIONES §3.quater`, trampa 4)— sino
 * **cruzando las dos**.
 */

const MESSAGES = { errors: { try_later: 'Demasiados intentos seguidos. Espera un minuto antes de volver a intentarlo.' } };

const AUTH = { throttle: 'Demasiados intentos. Inténtalo de nuevo en :seconds segundos.' };

/** El endpoint responde **202**, no 200: «aceptado, ya veremos», que es lo que de verdad ocurre. */
const accepted = () => ({ ok: true, status: 202, data: null, error: null, offline: false });

const fail = (status, error) => ({ ok: false, status, data: { error }, error, offline: false });

const offline = () => ({ ok: false, status: 0, data: null, error: null, offline: true });

const errorsOf = (response) => forgotErrors(response, { messages: MESSAGES, auth: AUTH });

/** Un `api` de mentira que registra lo que se le pidió y responde lo que le digan. */
function fakeApi(response) {
    const calls = [];

    return {
        calls,
        post: async (path, body) => {
            calls.push({ path, body });

            return response;
        },
    };
}

describe('el reparto de los avisos', () => {
    test('el 202 no deja ningún aviso', () => {
        assert.deepEqual(errorsOf(accepted()), { global: '', fields: {} });
    });

    /**
     * ⚠️ **Bajo el campo, no en el banner.** Es donde lo pone `Auth\ForgotPassword::sendLink()`
     * —clave `email`—, al revés que el login, que lo manda a `_global`. Los dos son deliberados y se
     * transcriben como están: este trabajo mueve DÓNDE vive la pantalla, no qué dice.
     */
    test('límite de intentos → bajo el campo email, con los segundos del sobre', () => {
        const errors = errorsOf(fail(429, { code: 'too_many_requests', message: 'x', params: { retry_after: 47 } }));

        assert.equal(errors.fields.email, 'Demasiados intentos. Inténtalo de nuevo en 47 segundos.');
        assert.equal(errors.global, '', 'el banner es del login, no de esta pantalla');
    });

    test('sin `retry_after` el aviso no pinta «undefined»', () => {
        const errors = errorsOf(fail(429, { code: 'too_many_requests', message: 'x' }));

        assert.equal(errors.fields.email, 'Demasiados intentos. Inténtalo de nuevo en 0 segundos.');
    });

    test('un correo mal escrito se pinta con el texto del servidor, tal cual', () => {
        const message = 'El campo email debe ser una dirección de correo válida.';
        const errors = errorsOf(fail(422, { code: 'validation_failed', message: 'x', fields: { email: [message] } }));

        assert.equal(errors.fields.email, message);
        assert.equal(errors.global, '');
    });

    test('una validación sin mensajes no inventa un campo vacío', () => {
        const errors = errorsOf(fail(422, { code: 'validation_failed', message: 'x', fields: {} }));

        assert.deepEqual(errors.fields, {}, 'un `email: ""` pintaría un hueco de error sin texto');
    });

    test('sin red → el genérico del cajón, en el banner', () => {
        assert.equal(errorsOf(offline()).global, MESSAGES.errors.try_later);
    });

    test('un código desconocido degrada al genérico en vez de quedarse mudo', () => {
        assert.equal(errorsOf(fail(500, { code: 'server_exploded', message: 'x' })).global, MESSAGES.errors.try_later);
    });
});

describe('la NO-ENUMERACIÓN, que es la razón de ser de esta pantalla', () => {
    /**
     * ⚠️⚠️ **La propiedad, cruzando las dos respuestas.** El servidor devuelve 202 exista o no la
     * cuenta; si este módulo produjera algo distinto para una y otra, habría reconstruido en el
     * cliente el oráculo que el servidor se cuida de no dar.
     *
     * Se comparan los resultados COMPLETOS, no un campo: una diferencia en cualquier sitio —el aviso,
     * el `sent`, el `ok`— vale igual para un atacante.
     */
    test('una cuenta que existe y una que no producen EXACTAMENTE lo mismo', async () => {
        const existe = await runForgot({
            email: 'cliente@jumpweb.test', api: fakeApi(accepted()), messages: MESSAGES, auth: AUTH,
        });

        const noExiste = await runForgot({
            email: 'fantasma@jumpweb.test', api: fakeApi(accepted()), messages: MESSAGES, auth: AUTH,
        });

        assert.deepEqual(
            { ok: existe.ok, sent: existe.sent, errors: existe.errors },
            { ok: noExiste.ok, sent: noExiste.sent, errors: noExiste.errors },
            'el cajón distingue dos casos que el servidor se cuida de no distinguir'
        );
    });

    /**
     * Y el 429 **sí** se distingue, que es lo correcto: no dice nada sobre si la cuenta existe —dice
     * que quien pregunta lo ha hecho demasiadas veces—, y esconderlo dejaría al cliente esperando un
     * correo que no va a llegar.
     */
    test('el límite de intentos SÍ se distingue, y no enseña «revisa tu correo»', async () => {
        const result = await runForgot({
            email: 'cliente@jumpweb.test',
            api: fakeApi(fail(429, { code: 'too_many_requests', message: 'x', params: { retry_after: 60 } })),
            messages: MESSAGES,
            auth: AUTH,
        });

        assert.equal(result.sent, false, 'un 429 no puede enseñar la pantalla de «enviado»');
        assert.equal(result.ok, false);
        assert.notEqual(result.errors.fields.email, undefined);
    });
});

describe('la petición', () => {
    test('va al endpoint del contrato y solo lleva el correo', async () => {
        const api = fakeApi(accepted());

        await runForgot({ email: 'cliente@jumpweb.test', api, messages: MESSAGES, auth: AUTH });

        assert.equal(api.calls.length, 1, 'una sola petición: no hay nada que preguntar después');
        assert.equal(api.calls[0].path, '/auth/password/forgot');
        assert.deepEqual(api.calls[0].body, { email: 'cliente@jumpweb.test' });
    });

    /**
     * ⚠️ Cadena vacía, NUNCA `undefined`: el contrato declara `email` como `string` requerido, y un
     * campo ausente haría fallar por ESQUEMA en vez de por la validación que el cliente espera pintar
     * bajo el input. Es la misma trampa que `register.js` documenta con `turnstile_token`.
     */
    test('sin correo manda cadena vacía y deja que el servidor valide', async () => {
        const api = fakeApi(fail(422, { code: 'validation_failed', message: 'x', fields: { email: ['El campo email es obligatorio.'] } }));

        const result = await runForgot({ email: undefined, api, messages: MESSAGES, auth: AUTH });

        assert.deepEqual(api.calls[0].body, { email: '' });
        assert.equal(result.errors.fields.email, 'El campo email es obligatorio.');
    });

    test('`sent` es `ok` y nada más: un desenlace derivado sería un sitio donde meter una fuga', async () => {
        const enviado = await runForgot({ email: 'a@b.test', api: fakeApi(accepted()), messages: MESSAGES, auth: AUTH });
        const roto = await runForgot({ email: 'a@b.test', api: fakeApi(offline()), messages: MESSAGES, auth: AUTH });

        assert.equal(enviado.sent, enviado.ok);
        assert.equal(roto.sent, roto.ok);
    });
});
