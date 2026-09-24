import { test, describe } from 'node:test';
import assert from 'node:assert/strict';
import { needsConsentsToDecide, offersMarketingOptIn } from './marketing-offer.js';

/**
 * T4c de la analítica — el opt-in de comunicaciones al acabar de comprar (`docs/specs/analitica.md` §4.3, §4.6):
 * se ofrece solo con sesión, sin opt-in dado y sin retirada previa; sin la lista de consentimientos aún no se decide.
 */
const sinOptIn = { marketing_opt_in: false };
const conOptIn = { marketing_opt_in: true };

describe('¿se ofrece la casilla?', () => {
    test('con sesión, sin opt-in y sin retirada previa: sí', () => {
        assert.equal(offersMarketingOptIn({ hasSession: true, user: sinOptIn, consents: [] }), true);
        assert.equal(offersMarketingOptIn({ hasSession: true, user: sinOptIn, consents: [{ type: 'privacy', revoked_at: null }, { type: 'marketing', revoked_at: null }] }), true, 'una fila viva de marketing no es una retirada');
    });

    test('sin sesión no se pregunta (el enlace de verificación llega sin sesión)', () => {
        assert.equal(offersMarketingOptIn({ hasSession: false, user: sinOptIn, consents: [] }), false);
    });

    test('quien ya dio el opt-in no ve la pregunta', () => {
        assert.equal(offersMarketingOptIn({ hasSession: true, user: conOptIn, consents: [] }), false);
    });

    test('quien lo RETIRÓ alguna vez ya dijo que no: no se insiste', () => {
        assert.equal(offersMarketingOptIn({ hasSession: true, user: sinOptIn, consents: [{ type: 'marketing', revoked_at: '2026-09-01T10:00:00Z' }] }), false);
    });

    /** ⚠️ `GET /me/consents` llega con SOBRE (`{ data: [...] }`): la sonda encontró la casilla sin pintar por esto. */
    test('la lista llega con sobre {data} y se lee igual', () => {
        assert.equal(offersMarketingOptIn({ hasSession: true, user: sinOptIn, consents: { data: [] } }), true);
        assert.equal(offersMarketingOptIn({ hasSession: true, user: sinOptIn, consents: { data: [{ type: 'marketing', revoked_at: '2026-09-01T10:00:00Z' }] } }), false);
        assert.equal(needsConsentsToDecide({ hasSession: true, user: sinOptIn, consents: { data: [] } }), false, 'con el sobre cargado ya no hace falta pedir');
    });

    test('sin perfil o sin consentimientos cargados no se decide todavía', () => {
        assert.equal(offersMarketingOptIn({ hasSession: true, user: null, consents: [] }), false);
        assert.equal(offersMarketingOptIn({ hasSession: true, user: sinOptIn, consents: null }), false);
        assert.equal(offersMarketingOptIn({ hasSession: true, user: sinOptIn, consents: undefined }), false);
    });
});

describe('¿hace falta traer los consentimientos?', () => {
    test('solo con sesión y un perfil sin opt-in, y solo mientras no estén', () => {
        assert.equal(needsConsentsToDecide({ hasSession: true, user: sinOptIn, consents: null }), true);
        assert.equal(needsConsentsToDecide({ hasSession: true, user: sinOptIn, consents: [] }), false);
        assert.equal(needsConsentsToDecide({ hasSession: true, user: conOptIn, consents: null }), false, 'con opt-in la respuesta ya es no');
        assert.equal(needsConsentsToDecide({ hasSession: false, user: sinOptIn, consents: null }), false);
        assert.equal(needsConsentsToDecide({ hasSession: true, user: null, consents: null }), false, 'sin perfil todavía no se sabe');
    });
});
