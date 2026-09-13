/**
 * LA LÓGICA PURA del formulario post-reserva (`docs/specs/celebracion-e-invitacion.md` §4.2, T2, `#571`).
 *
 * Sin DOM: la página lee las fichas, llama aquí y escribe el resultado. Así se prueba con `node --test`
 * (`resources/js/guest-form/logic.test.js`) sin montar un navegador.
 *
 * ⚠️ **Es un fichero ESTÁTICO y no pasa por Vite, a propósito**: la página es de mejora progresiva y no
 * carga `app.js` ni el manifiesto (como `site.css`), y un módulo que no se sirve deja la página en
 * `no-js`, que es un formulario completo y usable. Se sirve con `?v=` de fecha de fichero.
 */

/** Viñetas y numeraciones que trae una lista copiada de un chat o de un documento. */
const LIST_MARK = /^\s*(?:[-–—•·*▪◦●]+|\d{1,3}\s*[.)ºª:-]?)\s+/u;

/**
 * Una línea pegada, limpia: sin viñeta ni numeración delante y con los espacios normalizados.
 * ⚠️ Solo quita una numeración SEGUIDA DE ESPACIO («1. Lucía», «3 Hugo»): un nombre no empieza por
 * cifras, pero un «1.º» pegado a una palabra no es una lista.
 */
export function cleanName(line) {
    return String(line ?? '')
        .replace(/ /g, ' ')
        .replace(LIST_MARK, '')
        .replace(/\s+/g, ' ')
        .trim();
}

/**
 * Los nombres de un texto pegado, en su orden.
 *
 * ▶ Uno por línea, que es lo que pide la ayuda. ⚠️ Y si todo llega en UNA sola línea con comas o
 * puntos y coma («Lucía, Mateo, Hugo»), se parte por ellos: es como se escribe una lista en un chat, y
 * tratarla como un único nombre metería tres niños en una ficha.
 */
export function parseNames(text) {
    const lines = String(text ?? '').split(/\r\n|\r|\n/);
    const filled = lines.map((line) => line.trim()).filter((line) => line !== '');
    const parts = filled.length === 1 && /[,;]/.test(filled[0]) ? filled[0].split(/[,;]/) : lines;

    return parts.map(cleanName).filter((name) => name !== '');
}

/**
 * Reparte los nombres sobre las fichas VACÍAS, en el orden en que se leen. **Nunca pisa una ficha con
 * datos**: es lo que el diálogo promete antes de aplicar («las que ya tienen datos no se tocan»).
 *
 * @param {string[]} names
 * @param {{index: number, empty: boolean}[]} fiches  en el orden de la página
 * @returns {{assignments: {index: number, name: string}[], placed: number, overflow: number, kept: number}}
 */
export function planPaste(names, fiches) {
    const empty = fiches.filter((fiche) => fiche.empty);
    const assignments = names.slice(0, empty.length).map((name, k) => ({ index: empty[k].index, name }));

    return {
        assignments,
        placed: assignments.length,
        overflow: Math.max(0, names.length - empty.length),
        kept: fiches.length - empty.length,
    };
}

/**
 * El ESTADO de una ficha, la misma regla que pinta el servidor al cargar.
 *
 * ▶ Completa = ninguna columna obligatoria vacía (vacuamente completa si no hay ninguna) y sin edad sin
 * producto, que la decide el servidor (`data-no-product`). ▶ «Falta …» nombra la PRIMERA obligatoria
 * vacía, y solo si la ficha ya tiene algún dato: en una ficha en blanco sería ruido.
 *
 * @param {{required: boolean, filled: boolean, label: string}[]} fields  en el orden de las columnas
 * @returns {{complete: boolean, hasData: boolean, missing: string|null}}
 */
export function ficheState(fields, noProduct = false) {
    const hasData = fields.some((field) => field.filled);
    const firstEmpty = fields.find((field) => field.required && !field.filled);

    return {
        complete: !noProduct && firstEmpty === undefined,
        hasData,
        missing: hasData && firstEmpty !== undefined ? firstEmpty.label : null,
    };
}

/**
 * Una cadena con plural en el formato de Laravel, resuelta en el navegador.
 *
 * ⚠️⚠️ `trans_choice` no existe aquí: una cadena con `|` llegaba ENTERA a la pantalla (la lección de
 * `count_warn_discard`, `#444`). Esto admite las dos formas que usa el diccionario —«uno|varios» y los
 * intervalos `{0}`, `{1}`, `[2,*]`— y sustituye `:count` y el resto de marcadores.
 */
export function choice(template, count, replacements = {}) {
    const forms = String(template ?? '').split('|');
    let chosen = null;

    for (const form of forms) {
        const exact = form.match(/^\s*\{(\d+)\}\s*/);
        const range = form.match(/^\s*\[(\d+),\s*(\d+|\*)\]\s*/);
        if (exact && Number(exact[1]) === count) {
            chosen = form.slice(exact[0].length);
            break;
        }
        if (range && count >= Number(range[1]) && (range[2] === '*' || count <= Number(range[2]))) {
            chosen = form.slice(range[0].length);
            break;
        }
    }

    if (chosen === null) {
        const plain = forms.filter((form) => !/^\s*[{[]/.test(form));
        const pool = plain.length > 0 ? plain : forms;
        chosen = pool.length > 1 && count !== 1 ? pool[pool.length - 1] : pool[0];
    }

    const values = { count, ...replacements };

    return Object.keys(values)
        .sort((a, b) => b.length - a.length)
        .reduce((text, key) => text.split(`:${key}`).join(String(values[key])), chosen.trim());
}
