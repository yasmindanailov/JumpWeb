/**
 * **LO QUE EL COMPRADOR DEBE ANTES DE PAGAR** (`specs/auth-con-google.md` §21.4.2, `#349`).
 *
 * Dos cosas pueden faltarle a quien va a contratar: la **aceptación de las condiciones** en su versión
 * vigente y el **teléfono**. Aquí vive la decisión de qué se le pide y qué se le dice; la pantalla
 * solo pinta.
 *
 * ⚠️ **Módulo PLANO, sin Vue** (`CE-6`), y no por gusto: nació porque
 * `SidebarComponentBudgetTest` puso la sección en rojo al meterle esta lógica dentro. Ese techo
 * existe justo para provocar esta pregunta — y la respuesta buena nunca es subirlo.
 *
 * ⚠️⚠️ **Aquí NO se decide si hacen falta: eso lo dice el SERVIDOR.** Este módulo solo junta sus dos
 * voces, que son distintas y las dos necesarias:
 *  1. la **pista** del contexto de cuenta, sembrada al pintar la página — es lo que evita una
 *     petición más en el paso de pagar;
 *  2. el **«no» del servidor** al crear el pedido, que es la autoridad.
 * La pista se sembró al CARGAR: si entre medias se publicó una versión nueva de las condiciones,
 * decía `false`. Sin la segunda voz, el cliente recibiría un error sobre un campo **que no está
 * pintado** — un «no» mudo, que es el defecto que `#400` documenta en otro subsistema.
 */

/** El estado de la pantalla: lo tecleado y lo que el servidor respondió. */
export function emptyBuyerDue() {
    return { acceptTerms: false, phone: '', errors: {} };
}

/**
 * Qué hay que pedirle y con qué palabras.
 *
 * @param {{terms_pending?: boolean, terms_updated?: boolean, phone_missing?: boolean}|null} context
 *        el contexto de cuenta tal cual lo publica el servidor
 * @param {Record<string, string>} errors  los «no» por campo del último intento
 * @returns {{terms: boolean, phone: boolean, termsUpdated: boolean}}
 */
export function buyerNeeds(context, errors = {}) {
    const said = errors ?? {};

    return {
        terms: context?.terms_pending === true || 'accept_terms' in said,
        phone: context?.phone_missing === true || 'phone' in said,
        // ⚠️ **Solo lo dice la PISTA, nunca el error.** El 422 sabe que faltan, no si es porque han
        // cambiado — y decirle «las hemos actualizado» a quien nunca las aceptó es una mentira que el
        // cliente no puede desmentir. Ante la duda, el texto neutro.
        termsUpdated: context?.terms_updated === true,
    };
}
