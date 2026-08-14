/**
 * Los TEXTOS del cajón (Fase 4 · paso 4.3·1, `sidebar-spa.md` §4.5).
 *
 * El servidor inyecta en el montaje el grupo `tickets` del idioma activo; aquí solo se lee. **No hay
 * traducciones en el cliente y no debe haberlas**: son 169 claves × 3 idiomas que ya viven en `lang/`.
 *
 * Este módulo existe porque el helper que los pasos traían inline —`props.messages[key] ?? ''`— falla
 * en tres cosas MEDIDAS, y las tres en silencio:
 *
 * 1. **El payload NO es plano.** `__('tickets')` devuelve 121 claves de primer nivel, de las que
 *    cuatro son subarrays (`errors`, `paused`, `statuses`, `payment_failed`). `messages['errors.choose_one']`
 *    es `undefined` y el helper devolvía `''`: el error se pintaba VACÍO, sin excepción ni aviso.
 * 2. **`String.replace` con un patrón de texto sustituye la PRIMERA aparición.** `cart_items` es
 *    `':count artículo|:count artículos'` —el `:count` sale dos veces—, así que la segunda quedaba sin
 *    sustituir.
 * 3. **`cart_items` es la única clave del grupo con pluralización de Laravel**, y se sirve CRUDA con
 *    su barra dentro: sin resolverla, la barra-carrito enseñaría literalmente «2 artículo|2 artículos».
 *
 * ⚠️ **El gate de árbol no ve nada de esto**: descarta los nodos de texto a propósito. La red de este
 * módulo son su `node --test` y `SidebarTextParityTest`, que compara su salida con la de `__()` y
 * `trans_choice()` reales.
 */

/**
 * Un texto del diccionario, por CAMINO (`errors.choose_one`), no por clave literal.
 *
 * Devuelve `''` cuando falta, como hacía el helper que sustituye: en producción, un texto que falta no
 * puede tumbar el cajón. Que no falte lo comprueban las paridades, que comparan contra `__()`.
 *
 * @param {object} messages  el grupo `tickets` inyectado en el montaje
 * @param {string} key  clave, con puntos para bajar a un subarray
 * @returns {string}
 */
export function t(messages, key) {
    const value = String(key).split('.').reduce((node, part) => (node == null ? undefined : node[part]), messages);

    return typeof value === 'string' ? value : '';
}

/**
 * Sustituye los marcadores `:clave` de un texto, como hace `__()` en servidor.
 *
 * ⚠️ **Todas las apariciones, y las claves largas primero.** Lo segundo es lo que hace Laravel y no es
 * cosmético: con `:name` y `:names` en el mismo texto, sustituir la corta antes deja `:names` roto a
 * medias.
 *
 * @param {string} text
 * @param {Record<string, string|number>} params
 * @returns {string}
 */
function interpolate(text, params) {
    return Object.keys(params)
        .sort((a, b) => b.length - a.length)
        .reduce(
            (out, key) => out.split(':' + key).join(String(params[key])),
            text
        );
}

/**
 * Un texto con parámetros. Equivalente de `__('tickets.clave', [...])`.
 *
 * @param {object} messages
 * @param {string} key
 * @param {Record<string, string|number>} params
 * @returns {string}
 */
export function tp(messages, key, params = {}) {
    return interpolate(t(messages, key), params);
}

/**
 * El índice de forma plural que elegiría Laravel para ese idioma y ese número.
 *
 * Se implementan **solo los idiomas del sitio público** (`SetLocale::SUPPORTED` = es · en · fr).
 * Añadir un cuarto idioma sin tocar esto daría un plural equivocado en silencio, así que
 * `SidebarTextParityTest` recorre esa constante: el día que crezca, cae.
 *
 * ⚠️ **El francés NO es `n === 1`**: en `fr`, el cero cae en el SINGULAR (medido: `0 article`). Un
 * ternario ingenuo diverge de Laravel en el caso más visible de todos, la cesta vacía.
 */
function pluralIndex(locale, count) {
    const base = String(locale || 'es').toLowerCase().replace('_', '-').split('-')[0];

    if (base === 'fr') {
        return count === 0 || count === 1 ? 0 : 1;
    }

    return count === 1 ? 0 : 1;
}

/**
 * Un texto PLURALIZADO. Equivalente de `trans_choice('tickets.clave', $n, [...])`.
 *
 * Solo entiende la forma de dos ramas `singular|plural`, que es la única que usa el grupo `tickets`
 * (medido: `cart_items` es la única clave con barra, en los tres idiomas, y ninguna usa la sintaxis de
 * rangos `{0}…[2,*]…`). `SidebarTextParityTest` vigila que siga siendo así: si alguien añade una clave
 * con rangos, este módulo la pintaría mal y el test lo dice.
 *
 * @param {object} messages
 * @param {string} key
 * @param {number} count
 * @param {string} locale
 * @param {Record<string, string|number>} params
 * @returns {string}
 */
export function tc(messages, key, count, locale, params = {}) {
    const forms = t(messages, key).split('|');
    const chosen = forms.length > 1 ? forms[pluralIndex(locale, count)] : forms[0];

    return interpolate(chosen ?? '', { count, ...params });
}
