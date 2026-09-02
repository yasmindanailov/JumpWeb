import assert from 'node:assert/strict';
import { describe, test } from 'node:test';
import { buyerNeeds, emptyBuyerDue } from './buyer-due.js';

/**
 * **Lo que el comprador debe antes de pagar** (`#349`).
 *
 * ⚠️ Lo que aquí se vigila no es que sume dos booleanos: es que **cada campo se encienda por sus DOS
 * caminos**. La pista del servidor se sembró al CARGAR la página; si entre medias se publicó una
 * versión nueva de las condiciones, decía `false` — y sin la segunda voz el cliente recibiría un «no»
 * sobre un campo **que no está pintado**.
 */
describe('lo que el comprador debe', () => {
    test('sin contexto no se le debe nada — el anónimo no es un caso especial', () => {
        assert.deepEqual(buyerNeeds(null), { terms: false, phone: false, termsUpdated: false });
        assert.deepEqual(buyerNeeds(undefined, undefined), { terms: false, phone: false, termsUpdated: false });
    });

    test('la PISTA del servidor enciende cada campo por separado', () => {
        assert.deepEqual(
            buyerNeeds({ terms_pending: true, phone_missing: false }),
            { terms: true, phone: false, termsUpdated: false },
        );
        assert.deepEqual(
            buyerNeeds({ terms_pending: false, phone_missing: true }),
            { terms: false, phone: true, termsUpdated: false },
        );
    });

    /**
     * ⚠️⚠️ **LA MITAD QUE HACE ROBUSTO ESTO.** La pista se sembró al cargar la página. Si mientras el
     * cliente llenaba el carrito alguien publicó una versión nueva de las condiciones, el contexto
     * sigue diciendo `false` y el servidor responde 422: sin esta rama, el error apuntaría a una
     * casilla que no está en pantalla — un «no» mudo.
     */
    test('el «no» del servidor enciende el campo aunque la pista dijera que no', () => {
        const need = buyerNeeds(
            { terms_pending: false, phone_missing: false },
            { accept_terms: 'Tienes que aceptar las condiciones.' },
        );

        assert.equal(need.terms, true);
        assert.equal(need.phone, false);
    });

    test('y lo mismo con el teléfono', () => {
        const need = buyerNeeds({ phone_missing: false }, { phone: 'El teléfono es obligatorio.' });

        assert.equal(need.phone, true);
    });

    /**
     * ⚠️ **«Se han actualizado» lo dice SOLO la pista, nunca el error.** El 422 sabe que faltan, no si
     * es porque han cambiado — y decírselo a quien nunca las aceptó es una mentira que el cliente no
     * puede desmentir. Ante la duda, el texto neutro.
     */
    test('«se han actualizado» no se deduce del error', () => {
        assert.equal(buyerNeeds({ terms_pending: true, terms_updated: true }).termsUpdated, true);
        assert.equal(buyerNeeds({}, { accept_terms: 'x' }).termsUpdated, false);
    });

    /** Un valor que no sea exactamente `true` no enciende nada: la pista viene de la red. */
    test('solo un `true` de verdad cuenta como pista', () => {
        assert.equal(buyerNeeds({ terms_pending: 'sí', phone_missing: 1 }).terms, false);
        assert.equal(buyerNeeds({ terms_pending: 'sí', phone_missing: 1 }).phone, false);
    });

    test('el estado nace vacío y sin errores', () => {
        assert.deepEqual(emptyBuyerDue(), { acceptTerms: false, phone: '', errors: {} });
    });
});
