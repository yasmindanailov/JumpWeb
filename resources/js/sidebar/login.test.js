import { test, describe } from 'node:test';
import assert from 'node:assert/strict';
import { loginErrors, runLogin } from './login.js';

/**
 * Fase 4 · paso 4.4a·2 — la red del login embebido (criterio CE-6).
 *
 * Lo que se fija aquí es **de dónde sale cada texto y dónde se pinta**. Que los literales coincidan
 * lo comparaba `SidebarLoginParityTest` contra `__()` real; ese fichero se retiró con el modal
 * (`DECISIONES #122`) y hoy los códigos y el sobre los fija `Api\V1\AuthSessionTest`.
 *
 * ▶ Esto cubre lo que aquella paridad no podía: los estados degradados y el REPARTO entre el banner y
 * el campo, que es el hallazgo L-02 de la auditoría del origen.
 */

const MESSAGES = { errors: { try_later: 'Demasiados intentos seguidos. Espera un minuto antes de volver a intentarlo.' } };

const AUTH = {
    failed: 'Estas credenciales no coinciden con nuestros registros.',
    throttle: 'Demasiados intentos. Inténtalo de nuevo en :seconds segundos.',
};

const ok = (data) => ({ ok: true, status: 200, data, error: null, offline: false });

const fail = (status, error) => ({ ok: false, status, data: { error }, error, offline: false });

const offline = () => ({ ok: false, status: 0, data: null, error: null, offline: true });

const errorsOf = (response) => loginErrors(response, { messages: MESSAGES, auth: AUTH });

describe('el reparto de los avisos', () => {
    test('entrar bien no deja ningún aviso', () => {
        assert.deepEqual(errorsOf(ok({ id: 7 })), { global: '', fields: {} });
    });

    /**
     * ⚠️ **Bajo el campo, no en el banner** (hallazgo L-02): el mensaje de credenciales es genérico a
     * propósito —no revela si el correo existe— y mezclarlo con el del limitador borraría esa
     * distinción, que es la que hace el mensaje seguro.
     */
    test('credenciales incorrectas → bajo el campo email, con el literal de la web', () => {
        const errors = errorsOf(fail(401, { code: 'invalid_credentials', message: 'El correo o la contraseña no son correctos.' }));

        assert.equal(errors.fields.email, AUTH.failed);
        assert.equal(errors.global, '');
    });

    /**
     * ⚠️ **El texto sale del DICCIONARIO, no del sobre.** El fixture usa a propósito un literal DISTINTO
     * del de la API (el que el diccionario tuvo hasta `#588`): con los dos iguales este caso no podría
     * distinguir qué se pinta. Pintar el sobre cambiaría la copia del cajón sin que el diff de árbol
     * pueda verlo, porque descarta los nodos de texto.
     */
    test('el `message` del sobre NO es lo que se pinta', () => {
        const message = 'El correo o la contraseña no son correctos.';
        const errors = errorsOf(fail(401, { code: 'invalid_credentials', message }));

        assert.notEqual(errors.fields.email, message);
    });

    test('límite de intentos → al banner, con los segundos del sobre', () => {
        const errors = errorsOf(fail(429, { code: 'too_many_requests', message: 'x', params: { retry_after: 59 } }));

        assert.equal(errors.global, 'Demasiados intentos. Inténtalo de nuevo en 59 segundos.');
        assert.deepEqual(errors.fields, {});
    });

    test('sin `retry_after` el aviso no pinta «undefined»', () => {
        const errors = errorsOf(fail(429, { code: 'too_many_requests', message: 'x' }));

        assert.equal(errors.global, 'Demasiados intentos. Inténtalo de nuevo en 0 segundos.');
    });
});

describe('la validación', () => {
    /**
     * Los textos de campo los escribe el servidor con las MISMAS reglas que el componente Livewire
     * (`required|string|email`), así que ya coinciden: reescribirlos aquí sería una segunda traducción
     * de las reglas de Laravel.
     */
    test('los mensajes por campo se pintan tal cual llegan', () => {
        const errors = errorsOf(fail(422, {
            code: 'validation_failed',
            message: 'Revisa los datos que has enviado.',
            fields: { email: ['El campo email es obligatorio.'], password: ['El campo contraseña es obligatorio.'] },
        }));

        assert.equal(errors.fields.email, 'El campo email es obligatorio.');
        assert.equal(errors.fields.password, 'El campo contraseña es obligatorio.');
        assert.equal(errors.global, '', 'la validación no llena el banner: cada aviso va bajo lo suyo');
    });

    test('solo un campo con problema deja el otro limpio', () => {
        const errors = errorsOf(fail(422, {
            code: 'validation_failed', message: 'x', fields: { email: ['email debe ser una dirección de correo válida.'] },
        }));

        assert.equal(errors.fields.email, 'email debe ser una dirección de correo válida.');
        assert.equal(errors.fields.password, undefined);
    });

    /** El Blade pinta `@error('email')`, que es el PRIMER mensaje. Con varios, se conserva ese. */
    test('con varios mensajes por campo se pinta el primero, como `@error`', () => {
        const errors = errorsOf(fail(422, {
            code: 'validation_failed', message: 'x', fields: { email: ['primero', 'segundo'] },
        }));

        assert.equal(errors.fields.email, 'primero');
    });

    test('un campo con una forma inesperada no rompe el formulario', () => {
        const errors = errorsOf(fail(422, { code: 'validation_failed', message: 'x', fields: { email: {}, password: null } }));

        assert.deepEqual(errors.fields, {});
    });

    test('un 422 sin `fields` no deja el formulario mudo ni lanza', () => {
        const errors = errorsOf(fail(422, { code: 'validation_failed', message: 'x' }));

        assert.deepEqual(errors, { global: '', fields: {} });
    });
});

describe('lo que Livewire no puede tener', () => {
    /** Un corte de red: allí tumbaría la petición entera, así que no hay paridad que respetar. */
    test('sin red se avisa con el genérico del cajón', () => {
        assert.equal(errorsOf(offline()).global, MESSAGES.errors.try_later);
    });

    test('un 500 también', () => {
        assert.equal(errorsOf(fail(500, { code: 'server_error', message: 'x' })).global, MESSAGES.errors.try_later);
    });

    /** Un código nuevo del contrato no puede dejar el formulario sin decir nada. */
    test('un código desconocido cae en el aviso genérico', () => {
        assert.equal(errorsOf(fail(409, { code: 'algo_nuevo', message: 'x' })).global, MESSAGES.errors.try_later);
    });

    test('un fallo sin sobre tampoco deja el formulario mudo', () => {
        assert.equal(errorsOf({ ok: false, status: 500, data: null, error: null }).global, MESSAGES.errors.try_later);
    });
});

describe('el envío', () => {
    function apiDouble(response) {
        const sent = [];

        return {
            sent,
            post: async (path, body) => {
                sent.push({ path, body });

                return response;
            },
        };
    }

    test('se manda a la puerta de la API con las tres claves del contrato', async () => {
        const api = apiDouble(ok({ id: 7 }));

        await runLogin({
            credentials: { email: 'a@b.test', password: 'secreta', remember: true },
            api, messages: MESSAGES, auth: AUTH,
        });

        assert.equal(api.sent.length, 1);
        assert.equal(api.sent[0].path, '/auth/login');
        assert.deepEqual(api.sent[0].body, { email: 'a@b.test', password: 'secreta', remember: true });
    });

    /** `remember` viaja SIEMPRE y como booleano: el contrato lo valida con `boolean`. */
    test('`remember` viaja como booleano aunque no se haya tocado', async () => {
        const api = apiDouble(ok({ id: 7 }));

        await runLogin({ credentials: { email: 'a@b.test', password: 'x' }, api, messages: MESSAGES, auth: AUTH });

        assert.equal(api.sent[0].body.remember, false);
    });

    /**
     * ⚠️ **La respuesta se devuelve ENTERA**, no solo un booleano: quien llama la pasa por la misma
     * tubería de identidad que `GET /me`, y eso es lo que evita una petición más justo después de
     * entrar. Si esto devolviera solo `ok`, el cajón tendría que volver a preguntar quién es.
     */
    test('devuelve la respuesta entera, que es de donde sale el titular', async () => {
        const response = ok({ id: 42, name: 'Mara' });
        const result = await runLogin({
            credentials: { email: 'a@b.test', password: 'x' }, api: apiDouble(response), messages: MESSAGES, auth: AUTH,
        });

        assert.equal(result.ok, true);
        assert.equal(result.response.data.id, 42);
        assert.deepEqual(result.errors, { global: '', fields: {} });
    });

    test('un rechazo vuelve con sus avisos ya traducidos', async () => {
        const api = apiDouble(fail(401, { code: 'invalid_credentials', message: 'x' }));

        const result = await runLogin({ credentials: { email: 'a@b.test', password: 'x' }, api, messages: MESSAGES, auth: AUTH });

        assert.equal(result.ok, false);
        assert.equal(result.errors.fields.email, AUTH.failed);
    });

    test('unas credenciales a medias se mandan igual: quien valida es el servidor', async () => {
        const api = apiDouble(fail(422, { code: 'validation_failed', message: 'x', fields: {} }));

        await runLogin({ credentials: {}, api, messages: MESSAGES, auth: AUTH });

        assert.deepEqual(api.sent[0].body, { email: '', password: '', remember: false });
    });
});
