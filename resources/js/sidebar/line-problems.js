import { t, tp } from './i18n.js';

/**
 * El «no» de `POST /cart/validate-line` traducido a lo que el paso 3 enseña.
 *
 * Vivía dentro de `PurchaseSection.vue` (`showLineProblems`) y bajó aquí en la tanda 4 de menores a
 * cargo por `CE-6` y por el presupuesto del orquestador (`SidebarComponentBudgetTest`: solo encoge):
 * es una regla de PRESENTACIÓN —qué campo se resalta, qué aviso se compone— y una regla sin
 * `node --test` es una regla sin red. El componente solo aplica lo que esto devuelve.
 *
 * Los tres motivos de SELECCIÓN —producto no elegible, franja no ofrecida, sin sitio— comparten aviso
 * a propósito: es el que el cajón ha enseñado siempre, y son el mismo callejón para quien mira el
 * paso 3. Los campos que faltan se resaltan uno a uno **y** se nombran en un resumen: sin las dos
 * cosas, un pack con cuatro campos deja al cliente adivinando cuál falla.
 *
 * ⚠️ Los `problems` vienen SIN contexto a propósito: el mínimo, el tope y las etiquetas ya los
 * publican `catalog/products/{id}` y `config`, y republicarlos sería un segundo sitio del que leer el
 * mismo valor.
 *
 * @param {Array<{reason: string, field?: string|null}>} problems  del veredicto del servidor
 * @param {Array<{key: string, label: string}>} eventFields  el esquema del producto, para nombrar lo que falta
 * @param {object} messages  el grupo `tickets`
 * @returns {{fieldErrors: Record<string, string>, error: string}}
 */
export function lineProblems(problems, eventFields = [], messages = {}) {
    const fieldErrors = {};
    const missing = [];
    let error = '';

    for (const problem of Array.isArray(problems) ? problems : []) {
        if (problem?.reason === 'event_field_required' && problem.field) {
            fieldErrors[problem.field] = t(messages, 'errors.field_required');

            const label = (Array.isArray(eventFields) ? eventFields : []).find((f) => f?.key === problem.field)?.label;
            if (label) missing.push(label);

            continue;
        }

        error = problem?.reason === 'cart_full'
            ? t(messages, 'errors.cart_too_large')
            : t(messages, 'errors.choose_one');
    }

    if (missing.length > 0) {
        error = tp(messages, 'errors.fields_missing', { fields: missing.join(', ') });
    }

    return { fieldErrors, error };
}
