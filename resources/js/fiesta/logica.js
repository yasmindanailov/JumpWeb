/**
 * LA LÓGICA PURA de la lista de invitados del sistema nuevo (`specs/fiesta-sistema-nuevo.md` §4.5): sin DOM, para
 * probarla con `node --test` (`logica.test.js`). Es el port de lo que `paginas/lista-invitados/estado.jsx` y
 * `zonas-1-2.jsx` del diseño hacen en el navegador —limpiar una lista pegada, normalizar un nombre, las cuentas, el
 * estado de una ficha— y de `choice()`, el plural de Laravel resuelto en el navegador (`public/js/guest-form/logic.js`,
 * que se retira en la T4).
 */

/** La clave de un nombre: sin tildes, en minúsculas y con los espacios colapsados (el `norm()` del diseño). */
export function clave(s) {
    return String(s ?? '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().replace(/\s+/g, ' ').trim();
}

/** Un nombre tecleado, con los espacios normalizados y en mayúscula inicial si venía todo en minúsculas. */
export function capitalizar(s) {
    const t = String(s ?? '').trim().replace(/\s+/g, ' ');

    return t === t.toLowerCase() ? t.replace(/(^|\s)\S/g, (m) => m.toUpperCase()) : t;
}

/**
 * «Pegar una lista»: la del grupo de WhatsApp sirve tal cual. Se parte por líneas, comas y puntos y coma; se quitan
 * los teléfonos, las viñetas y las numeraciones; y los repetidos (contra la lista y entre sí) no entran.
 *
 * @param {string} texto
 * @param {string[]} existentes  los nombres que ya están en la lista
 * @returns {{nombres: string[], repetidos: number}}
 */
export function limpiar(texto, existentes = []) {
    const vistos = new Set(existentes.map(clave));
    const nombres = [];
    let repetidos = 0;
    String(texto ?? '').split(/\n|,|;/).forEach((l) => {
        let s = l.replace(/\+?\d[\d\s().-]{6,}\d/g, ' ').replace(/^[\s\-–—•*·~>\d.)\]]+/, '').replace(/\s+/g, ' ').trim();
        if (s.length < 2 || !/[a-záéíóúñü]/i.test(s)) return;
        if (s === s.toLowerCase()) s = s.replace(/(^|\s)\S/g, (m) => m.toUpperCase());
        const k = clave(s);
        if (vistos.has(k)) { repetidos++; return; }
        vistos.add(k);
        nombres.push(s);
    });

    return { nombres, repetidos };
}

/**
 * El ESTADO de una ficha, la misma regla que pinta el servidor: completa si ninguna columna obligatoria está vacía,
 * y «Falta …» nombra la primera obligatoria vacía de una ficha que ya tiene algún dato.
 *
 * @param {{required: boolean, filled: boolean, label: string}[]} campos
 * @returns {{completa: boolean, conDatos: boolean, falta: string|null}}
 */
export function estadoFicha(campos, sinProducto = false) {
    const conDatos = campos.some((c) => c.filled);
    const primeraVacia = campos.find((c) => c.required && !c.filled);

    return {
        completa: !sinProducto && primeraVacia === undefined,
        conDatos,
        falta: conDatos && primeraVacia !== undefined ? primeraVacia.label : null,
    };
}

/**
 * Las cuentas de la zona 1 sobre las filas de la página: confirmados (los «sí»), no pueden y sin contestar; y
 * cuántas hay en la lista (con datos).
 *
 * @param {{vacia: boolean, respuesta: 'si'|'no'|null}[]} filas
 */
export function cuentas(filas) {
    const c = { confirmados: 0, noPueden: 0, sinContestar: 0, enLista: 0 };
    filas.forEach((f) => {
        if (f.vacia) return;
        c.enLista++;
        if (f.respuesta === 'si') c.confirmados++;
        else if (f.respuesta === 'no') c.noPueden++;
        else c.sinContestar++;
    });

    return c;
}

/**
 * Una cadena con plural en el formato de Laravel, resuelta en el navegador: admite «uno|varios» y los intervalos
 * `{0}`, `{1}`, `[2,*]`, y sustituye `:count` y el resto de marcadores.
 */
export function choice(template, count, replacements = {}) {
    const forms = String(template ?? '').split('|');
    let chosen = null;

    for (const form of forms) {
        const exact = form.match(/^\s*\{(\d+)\}\s*/);
        const range = form.match(/^\s*\[(\d+),\s*(\d+|\*)\]\s*/);
        if (exact && Number(exact[1]) === count) { chosen = form.slice(exact[0].length); break; }
        if (range && count >= Number(range[1]) && (range[2] === '*' || count <= Number(range[2]))) { chosen = form.slice(range[0].length); break; }
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

/** El espacio duro entre una cifra y su unidad («16 €», «7 años»), como lo escribe el servidor. */
export const NBSP = String.fromCharCode(160);

/** Un importe en céntimos como lo escribe el servidor en español («16 €», «16,95 €»). Solo anticipa: el que vale lo recalcula el servidor. */
export function euros(cents) {
    const n = Math.round(cents) / 100;
    const s = Number.isInteger(n) ? String(n) : n.toFixed(2).replace('.', ',');

    return `${s}${NBSP}€`;
}
