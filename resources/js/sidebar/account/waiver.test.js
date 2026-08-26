import { test, describe } from 'node:test';
import assert from 'node:assert/strict';
import { waiverNeedsSignature, waiverPendingFrom, waiverStatusKey } from './waiver.js';

/**
 * `account/waiver.js` — lo que el cajón DEDUCE del estado que publica el servidor, y nada más.
 * Fase 6 · waiver (`specs/waiver-probatorio.md` §9.9).
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
});

describe('el aviso del índice, desde el contexto de cuenta', () => {
    test('avisa cuando el servidor dice que hace falta o que está anticuado', () => {
        assert.equal(waiverPendingFrom({ waiver: { mode: 'interno', required: true, outdated: false } }), true);
        assert.equal(waiverPendingFrom({ waiver: { mode: 'interno', required: false, outdated: true } }), true);
    });

    test('y calla en el resto de casos, incluido un contexto sin waiver', () => {
        assert.equal(waiverPendingFrom({ waiver: { mode: 'interno', required: false, outdated: false } }), false);
        assert.equal(waiverPendingFrom({ waiver: { mode: 'externo', required: true } }), false);
        assert.equal(waiverPendingFrom({}), false);
        assert.equal(waiverPendingFrom(null), false);
    });
});
