import { test, describe } from 'node:test';
import assert from 'node:assert/strict';
import { minutesLeft, pendingNotice, profileForm } from './profile.js';
import { tp } from '../i18n.js';

const ACCOUNT = {
    account: {
        profile: {
            pending_email_msg: 'Enlace enviado a :email. Caduca en :minutes min. Sigues usando :current.',
        },
    },
};

const AHORA = Date.parse('2026-08-22T10:00:00Z');

describe('el formulario', () => {
    test('sale de lo que publica `GET /me`, sin inventar campos', () => {
        assert.deepEqual(
            profileForm({ name: 'Ana', email: 'a@x.test', phone: '600', locale: 'es', id: 7 }),
            { name: 'Ana', email: 'a@x.test', phone: '600', locale: 'es' },
        );
    });

    test('un perfil incompleto no produce `undefined` en un input', () => {
        assert.deepEqual(profileForm(null), { name: '', email: '', phone: '', locale: '' });
    });
});

describe('los minutos que le quedan al enlace', () => {
    test('⚠️ el «ahora» entra por parámetro: el caso no depende del reloj', () => {
        assert.equal(minutesLeft('2026-08-22T10:45:00Z', AHORA), 45);
    });

    test('⚠️ se redondea hacia ARRIBA: quedan 30 s → «1 min», no «0»', () => {
        // Decir «0 min» a falta de medio minuto suena a caducado cuando el enlace todavía sirve.
        assert.equal(minutesLeft('2026-08-22T10:00:30Z', AHORA), 1);
    });

    test('ya caducado no da negativos', () => {
        assert.equal(minutesLeft('2026-08-22T09:00:00Z', AHORA), 0);
    });

    test('sin fecha, o con una que no se entiende, da 0 y no revienta', () => {
        assert.equal(minutesLeft(null, AHORA), 0);
        assert.equal(minutesLeft(undefined, AHORA), 0);
        assert.equal(minutesLeft('no-es-una-fecha', AHORA), 0);
    });
});

describe('el aviso del cambio pendiente', () => {
    const user = {
        email: 'viejo@ejemplo.test',
        pending_email: 'nuevo@ejemplo.test',
        pending_email_expires_at: '2026-08-22T10:45:00Z',
    };

    test('lleva los TRES datos: a dónde se envió, cuánto queda y cuál sigue valiendo', () => {
        const notice = pendingNotice(user, ACCOUNT, AHORA, tp);

        assert.equal(notice.email, 'nuevo@ejemplo.test');
        assert.equal(notice.minutes, 45);
        assert.equal(
            notice.message,
            'Enlace enviado a nuevo@ejemplo.test. Caduca en 45 min. Sigues usando viejo@ejemplo.test.',
        );
    });

    test('⚠️ dice cuál sigue valiendo, que es lo que evita el susto', () => {
        // Sin `:current`, el cliente puede creer que su correo ya cambió y que ha perdido el acceso.
        assert.ok(pendingNotice(user, ACCOUNT, AHORA, tp).message.includes('viejo@ejemplo.test'));
    });

    test('sin cambio pendiente NO hay bloque', () => {
        assert.equal(pendingNotice({ email: 'a@x.test' }, ACCOUNT, AHORA, tp), null);
        assert.equal(pendingNotice(null, ACCOUNT, AHORA, tp), null);
    });
});
