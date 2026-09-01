import { test, describe } from 'node:test';
import assert from 'node:assert/strict';
import {
    WAIVER_NOTICE_SIGN,
    WAIVER_NOTICE_VERIFY,
    waiverAwaitsVerification,
    waiverNeedsSignature,
    accountNoticeFrom,
    waiverStatusKey,
} from './waiver.js';

/**
 * `account/waiver.js` — lo que el cajón DEDUCE del estado que publica el servidor, y nada más.
 * Fase 6 · waiver (`specs/waiver-probatorio.md` §9.9) y `#327`.
 */
describe('la frase de estado', () => {
    test('sin estado no hay frase: un fallo de lectura no se anuncia', () => {
        assert.equal(waiverStatusKey(null), '');
        assert.equal(waiverStatusKey({}), '');
        assert.equal(waiverStatusKey({ mode: 'desactivado' }), '');
    });

    test('en externo solo hay sello: lo gestiona el parque', () => {
        assert.equal(waiverStatusKey({ mode: 'externo', signed: true }), 'account.privacy.waiver.status_external');
        assert.equal(waiverStatusKey({ mode: 'externo', signed: false }), 'account.privacy.waiver.status_external');
    });

    test('en interno manda el registro: pendiente, anterior o vigente', () => {
        assert.equal(waiverStatusKey({ mode: 'interno', signed: false }), 'account.privacy.waiver.status_unsigned');
        assert.equal(waiverStatusKey({ mode: 'interno', signed: true, outdated: true }), 'account.privacy.waiver.status_outdated');
        assert.equal(waiverStatusKey({ mode: 'interno', signed: true, outdated: false }), 'account.privacy.waiver.status_current');
    });

    /**
     * `#327` — el caso que faltaba: la aceptó al registrarse y todavía no ha verificado el correo.
     * Decirle «todavía no la has firmado» es acusarle de no hacer algo que sí hizo.
     */
    test('aceptada y sin verificar tiene frase PROPIA, no la de «sin firmar»', () => {
        assert.equal(
            waiverStatusKey({ mode: 'interno', signed: false, pending: true }),
            'account.privacy.waiver.status_awaiting_verification',
        );
    });

    /** ⚠️ Y una firma vigente no cambia de frase por que haya quedado una aceptación colgada. */
    test('con firma, la aceptación en espera no cambia nada', () => {
        assert.equal(
            waiverStatusKey({ mode: 'interno', signed: true, outdated: false, pending: true }),
            'account.privacy.waiver.status_current',
        );
    });
});

describe('aceptada y esperando la verificación', () => {
    test('solo en interno, sin firma y con una aceptación retenida', () => {
        assert.equal(waiverAwaitsVerification({ mode: 'interno', signed: false, pending: true }), true);
        assert.equal(waiverAwaitsVerification({ mode: 'interno', signed: false, pending: false }), false);
        assert.equal(waiverAwaitsVerification({ mode: 'interno', signed: true, pending: true }), false);
        assert.equal(waiverAwaitsVerification({ mode: 'externo', pending: true }), false);
        assert.equal(waiverAwaitsVerification(null), false);
    });

    /** ⚠️ No se deduce de la ausencia de firma: `pending` lo dice el servidor o no se sabe. */
    test('sin el dato del servidor no se supone que haya aceptación', () => {
        assert.equal(waiverAwaitsVerification({ mode: 'interno', signed: false }), false);
    });
});

describe('cuándo se ofrece la firma', () => {
    test('solo en interno, y solo sin firma o con una anterior', () => {
        assert.equal(waiverNeedsSignature({ mode: 'interno', signed: false }), true);
        assert.equal(waiverNeedsSignature({ mode: 'interno', signed: true, outdated: true }), true);
        assert.equal(waiverNeedsSignature({ mode: 'interno', signed: true, outdated: false }), false);
        assert.equal(waiverNeedsSignature({ mode: 'externo', signed: false }), false);
        assert.equal(waiverNeedsSignature({ mode: 'desactivado' }), false);
        assert.equal(waiverNeedsSignature(null), false);
    });

    /**
     * ⚠️⚠️ **El caso que motivó `#327`.** Con la aceptación en espera el botón existía y llevaba a un
     * 409 (`waiver_email_unverified`): no se puede firmar sin el correo verificado. Un botón que solo
     * puede fallar es peor que ninguno.
     */
    test('NO se ofrece con la aceptación esperando verificación: ese botón solo puede dar error', () => {
        assert.equal(waiverNeedsSignature({ mode: 'interno', signed: false, pending: true }), false);
    });
});

describe('el aviso ÚNICO del índice, desde el contexto de cuenta', () => {
    /** `#330` — quien acaba de registrarse llega con sesión y sin verificar: ése es el aviso. */
    test('pide VERIFICAR el correo, y dice si la exención viaja con él', () => {
        assert.deepEqual(
            accountNoticeFrom({ email_verified: false, waiver: { mode: 'interno', required: true, pending: true } }),
            { kind: 'verify', withWaiver: true },
        );
        assert.deepEqual(
            accountNoticeFrom({ email_verified: false, waiver: { mode: 'interno', required: true, pending: false } }),
            { kind: 'verify', withWaiver: false },
        );
    });

    /** ⚠️ Sin waiver interno el aviso SIGUE saliendo: lo que falta es el correo, no la exención. */
    test('sin exención que firmar, el aviso de verificar se queda', () => {
        assert.deepEqual(
            accountNoticeFrom({ email_verified: false, waiver: { mode: 'externo' } }),
            { kind: 'verify', withWaiver: false },
        );
    });

    /**
     * ⚠️⚠️ **El ORDEN, y es la regla que `#328` dejó escrita**: con el correo sin verificar NO se pide
     * firmar, aunque el waiver «haga falta» — ese botón lleva a un 409.
     */
    test('con el correo sin verificar nunca se pide firmar', () => {
        const notice = accountNoticeFrom({ email_verified: false, waiver: { mode: 'interno', required: true, outdated: false, pending: true } });

        assert.equal(notice.kind, 'verify');
    });

    test('con el correo verificado, pide FIRMAR si falta o está anticuada', () => {
        assert.deepEqual(
            accountNoticeFrom({ email_verified: true, waiver: { mode: 'interno', required: true } }),
            { kind: 'sign', withWaiver: true },
        );
        assert.deepEqual(
            accountNoticeFrom({ email_verified: true, waiver: { mode: 'interno', required: false, outdated: true } }),
            { kind: 'sign', withWaiver: true },
        );
    });

    test('y calla en el resto de casos, incluido un contexto sin waiver', () => {
        assert.equal(accountNoticeFrom({ email_verified: true, waiver: { mode: 'interno', required: false, outdated: false } }), null);
        assert.equal(accountNoticeFrom({ email_verified: true, waiver: { mode: 'externo', required: true } }), null);
        assert.equal(accountNoticeFrom({ email_verified: true }), null);
        assert.equal(accountNoticeFrom(null), null);
    });

    /**
     * ⚠️ **Un contexto SIN el campo no inventa un aviso.** La semilla de una página cacheada puede no
     * traerlo todavía; `undefined` no es `false`.
     */
    test('sin el dato de verificación no se supone que falte', () => {
        assert.equal(accountNoticeFrom({ waiver: { mode: 'interno', required: false, outdated: false } }), null);
    });
});
