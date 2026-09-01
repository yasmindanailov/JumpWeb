import { test, describe } from 'node:test';
import assert from 'node:assert/strict';
import {
    WAIVER_NOTICE_SIGN,
    WAIVER_NOTICE_VERIFY,
    waiverAwaitsVerification,
    waiverNeedsSignature,
    waiverNoticeFrom,
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

describe('el aviso del índice, desde el contexto de cuenta', () => {
    test('pide FIRMAR cuando el servidor dice que hace falta o que está anticuado', () => {
        assert.equal(waiverNoticeFrom({ waiver: { mode: 'interno', required: true, outdated: false } }), WAIVER_NOTICE_SIGN);
        assert.equal(waiverNoticeFrom({ waiver: { mode: 'interno', required: false, outdated: true } }), WAIVER_NOTICE_SIGN);
    });

    /**
     * `#327` — con la aceptación retenida el aviso cambia de contenido Y de botón: lo que falta es
     * verificar el correo, no firmar. Es el mismo estado del servidor (`required: true`) leído con el
     * dato que lo explica.
     */
    test('pide VERIFICAR cuando la aceptación está retenida', () => {
        assert.equal(
            waiverNoticeFrom({ waiver: { mode: 'interno', required: true, pending: true } }),
            WAIVER_NOTICE_VERIFY,
        );
    });

    /** ⚠️ Una re-firma (versión anterior) NO es verificación aunque quede una aceptación colgada. */
    test('con firma anterior se pide firmar, no verificar', () => {
        assert.equal(
            waiverNoticeFrom({ waiver: { mode: 'interno', required: false, outdated: true, pending: true } }),
            WAIVER_NOTICE_SIGN,
        );
    });

    test('y calla en el resto de casos, incluido un contexto sin waiver', () => {
        assert.equal(waiverNoticeFrom({ waiver: { mode: 'interno', required: false, outdated: false } }), null);
        assert.equal(waiverNoticeFrom({ waiver: { mode: 'externo', required: true } }), null);
        assert.equal(waiverNoticeFrom({}), null);
        assert.equal(waiverNoticeFrom(null), null);
    });
});
