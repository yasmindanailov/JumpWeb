import { test, describe } from 'node:test';
import assert from 'node:assert/strict';
import { credentialOutcome, fieldError } from './credentials.js';

/**
 * La red de las gestiones de credenciales en el cliente.
 *
 * ⚠️ Lo que aquí se prueba es **la traducción de la respuesta**, no las reglas: si la contraseña es
 * correcta, si la nueva cumple la política y cuántos intentos quedan lo decide el SERVIDOR (`CE-4`).
 * Los casos frontera se eligen por el MECANISMO del fallo: los tres modos que **no** son «datos que
 * corregir» —red caída, sesión perdida y límite alcanzado— tienen salida distinta y caso propio.
 */

const MESSAGES = { errors: { try_later: 'Espera un minuto antes de volver a intentarlo.' } };
const AUTH = { throttle: 'Demasiados intentos. Inténtalo de nuevo en :seconds segundos.' };
const CTX = { messages: MESSAGES, auth: AUTH };

const ok = () => ({ ok: true, status: 204, data: null, error: null, offline: false });
const fail = (status, error) => ({ ok: false, status, data: { error }, error, offline: false });
const offline = () => ({ ok: false, status: 0, data: null, error: null, offline: true });

describe('cuando sale bien', () => {
    test('no hay nada que enseñar', () => {
        assert.deepEqual(credentialOutcome(ok(), CTX), { ok: true, fields: {}, notice: '', expired: false });
    });
});

describe('cuando el servidor rechaza los datos', () => {
    test('el error va POR CAMPO, tal cual lo mandó el servidor', () => {
        const r = credentialOutcome(fail(422, {
            code: 'validation_failed',
            message: 'Revisa los datos que has enviado.',
            fields: { current_password: ['La contraseña actual no es correcta.'] },
        }), CTX);

        assert.equal(r.ok, false);
        assert.equal(r.notice, '', 'un error de campo no se duplica también como aviso general');
        assert.equal(fieldError(r.fields, 'current_password'), 'La contraseña actual no es correcta.');
    });

    test('varios mensajes de un campo: se pinta el PRIMERO', () => {
        // El servidor puede mandar «mínimo 8 caracteres» y «aparece en filtraciones» juntos; pegarlos
        // bajo un input produce un párrafo que nadie lee.
        const r = credentialOutcome(fail(422, {
            code: 'validation_failed',
            message: '',
            fields: { password: ['Mínimo 8 caracteres.', 'Aparece en filtraciones.'] },
        }), CTX);

        assert.equal(fieldError(r.fields, 'password'), 'Mínimo 8 caracteres.');
    });

    test('un campo sin errores no inventa mensaje', () => {
        assert.equal(fieldError({}, 'current_password'), '');
        assert.equal(fieldError(undefined, 'current_password'), '');
        assert.equal(fieldError({ current_password: [] }, 'current_password'), '');
    });
});

describe('los tres modos que NO son «datos que corregir»', () => {
    test('⚠️ la red caída se dice como tal, no como «revisa los datos»', () => {
        const r = credentialOutcome(offline(), CTX);

        assert.equal(r.notice, MESSAGES.errors.try_later);
        assert.deepEqual(r.fields, {}, 'un corte de red no señala ningún campo');
        assert.equal(r.expired, false);
    });

    test('⚠️ la sesión perdida NO es un aviso: es volver a entrar', () => {
        const r = credentialOutcome(fail(401, { code: 'unauthenticated', message: 'No autenticado.' }), CTX);

        assert.equal(r.expired, true);
        assert.equal(r.notice, '', 'un 401 con aviso genérico haría que el cliente reintentara en vano');
    });

    test('⚠️ el límite dice CUÁNTO falta, con el texto que ya usa el login', () => {
        const r = credentialOutcome(fail(429, {
            code: 'too_many_requests',
            message: 'Demasiadas peticiones.',
            params: { retry_after: 42 },
        }), CTX);

        assert.equal(r.notice, 'Demasiados intentos. Inténtalo de nuevo en 42 segundos.');
        assert.deepEqual(r.fields, {}, 'el límite no es culpa de un campo concreto');
    });

    test('un 429 sin `retry_after` no pinta «undefined»', () => {
        const r = credentialOutcome(fail(429, { code: 'too_many_requests', message: '' }), CTX);

        assert.ok(r.notice.includes('0'), `el aviso quedó como «${r.notice}»`);
        assert.equal(r.notice.includes('undefined'), false);
    });
});

describe('lo que no encaja en ninguna categoría', () => {
    test('se enseña lo que el servidor dijo, no una traducción inventada', () => {
        const r = credentialOutcome(fail(500, { code: 'server_error', message: 'Algo ha ido mal.' }), CTX);

        assert.equal(r.notice, 'Algo ha ido mal.');
    });

    test('y si no dijo nada, no se pinta «undefined»', () => {
        const r = credentialOutcome(fail(503, null), CTX);

        assert.equal(r.notice, '');
    });
});
