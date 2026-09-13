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
 * mismo valor. La excepción es `suggestion` (`#588`): el pack que admite la edad del cumpleañero es
 * una respuesta del servidor que no publica nadie más.
 *
 * @param {Array<{reason: string, field?: string|null, suggestion?: {product_id: number, name: string}|null}>} problems  del veredicto del servidor
 * @param {Array<{key: string, label: string, min?: number|null, max?: number|null}>} eventFields  el esquema del producto, para nombrar lo que falta
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

        if (problem?.reason === 'celebrant_age_out_of_range' && problem.field) {
            const field = (Array.isArray(eventFields) ? eventFields : []).find((f) => f?.key === problem.field);
            fieldErrors[problem.field] = celebrantAgeSentence(field, problem.suggestion, messages);

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

/**
 * La frase de la edad del cumpleañero fuera de tramo (`#588`): el tramo del pack —lo publica el
 * catálogo en el propio campo (`min`/`max`)— y, si el servidor la trae, el pack que sí la admite.
 * ⚠️ Es la misma composición que `CelebrantAgeMismatch::sentence()` en el servidor, con los mismos textos.
 */
function celebrantAgeSentence(field, suggestion, messages) {
    const min = Number.isInteger(field?.min) ? field.min : null;
    const max = Number.isInteger(field?.max) ? field.max : null;

    let sentence = t(messages, 'errors.celebrant_age_generic');
    if (min !== null && max !== null) {
        sentence = tp(messages, 'errors.celebrant_age_between', { min, max });
    } else if (min !== null) {
        sentence = tp(messages, 'errors.celebrant_age_from', { min });
    } else if (max !== null) {
        sentence = tp(messages, 'errors.celebrant_age_up_to', { max });
    }

    return suggestion?.name
        ? `${sentence} ${tp(messages, 'errors.celebrant_age_try', { product: suggestion.name })}`
        : sentence;
}
