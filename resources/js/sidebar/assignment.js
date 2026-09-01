import { tp } from './i18n.js';

/**
 * **La ASIGNACIÓN de entradas a menores a cargo, en el cajón** (Fase 6 · tanda 4,
 * `docs/specs/menores-a-cargo.md` §4.7–§4.9, §9.9.3 D8/D9; `DECISIONES #202`).
 *
 * Módulo PLANO, sin Vue y sin red (`CE-6`): aquí viven las reglas que el selector aplica y que un
 * árbol no puede probar — a quién se ofrece, cuántos caben, qué pasa al fundir dos líneas o al bajar
 * la cantidad, y qué hacer con un id que ya no vale. Los `.vue` pintan; los stores guardan.
 *
 * ⚠️ **El cliente no decide NADA sobre un menor** (`CE-4`): la edad, la minoría y el estado de su
 * exención llegan derivados del servidor (`GET /me/dependents`), y este módulo solo los traduce a
 * «se puede marcar / no, y por qué». Quien manda es `DependentAssigner::check()` en `POST /orders`:
 * si aquí se cuela un id, el servidor responde 422 por campo y {@see applyRejections} lo quita.
 *
 * ⚠️ **La exención firmada es CONDICIÓN para asignar** (`[DECIDIDO owner]` `#202`·2): en modo
 * interno un menor sin firma vigente aparece en la lista pero NO se puede marcar, y el motivo se
 * dice al lado. Fuera del modo interno no hay firma que comprobar.
 */

export const REASON_ADULT = 'dependents.adult';
export const REASON_UNSIGNED = 'dependents.unsigned';
export const REASON_OUTDATED = 'dependents.outdated';

/**
 * El estado POSITIVO de la exención: «firmada».
 *
 * ⚠️ **No es el reverso de `reasonFor()` y por eso es una clave aparte.** «Se puede marcar» tiene DOS
 * causas distintas —la exención está firmada, o la instalación no comprueba ninguna— y decir
 * «exención firmada» en el segundo caso sería afirmar algo que nadie ha firmado. Fuera del modo
 * interno la fila no lleva estado, que es lo correcto: ahí no hay nada que informar.

/** ¿La exención de este menor permite asignarle una entrada? Fuera del modo interno, siempre. */
function waiverAllows(dependent) {
    const waiver = dependent?.waiver ?? {};

    if (waiver.mode !== 'interno') {
        return true;
    }

    return waiver.signed === true && waiver.outdated !== true;
}

/** Por qué NO se puede marcar, o `null` si se puede. */
function reasonFor(dependent) {
    if (dependent?.is_minor !== true) {
        return REASON_ADULT;
    }

    if (waiverAllows(dependent)) {
        return null;
    }

    return dependent?.waiver?.outdated === true ? REASON_OUTDATED : REASON_UNSIGNED;
}

/**
 * Lo que el selector OFRECE por cada menor declarado: **nombre y edad por separado**, si se puede
 * marcar y —cuando no se puede— por qué.
 *
 * ⚠️⚠️ **El estado POSITIVO («exención firmada») se RETIRÓ el 2026-08-29** (`DECISIONES #242`,
 * `[OWNER]`: «lo de exención firmada lo quitamos, es innecesario porque es obvio: no podemos asignar
 * menores sin firmar la exención»). Tiene razón y es la mitad que importa: **una fila marcable YA
 * significa que la exención está en regla**, así que el rótulo repetía con palabras lo que el propio
 * control ya decía. Lo que NO se retira es el motivo cuando NO se puede marcar: eso no es obvio, es
 * la única pista de qué hay que hacer para poder asignar.
 *
 * Se ofrecen TODOS los declarados —también los que no se pueden marcar— a propósito: ver a Lucas
 * deshabilitado con «exención sin firmar» es exactamente el «te enteras comprando» de §4.7. Ocultarlo
 * dejaría al titular pensando que no lo declaró.
 *
 * ⚠️⚠️ **`label` (el «Lucas · 9 años» de una sola cadena) se RETIRÓ el 2026-08-28** (§9.11 D·2,
 * `DECISIONES #217`). El owner probó el embudo en staging y leyó «Vera · 6 años — exención sin
 * firmar» como si el motivo fuera parte del nombre: era un único `<span>`, así que ni el rótulo ni el
 * motivo podían tener peso propio. Componer la cadena aquí impedía al selector maquetarla, y maquetar
 * es lo único que hace. Ahora salen `name`, `age` y la clave del estado, y el marcado los coloca.
 *
 * @param {Array<object>} dependents  `data` de `GET /me/dependents`
 * @param {object} messages  el grupo del EMBUDO (`tickets`), que viaja siempre: `dependents.age`
 * @returns {Array<{id: number, name: string, age: string, assignable: boolean, reasonKey: string|null}>}
 */
export function assignableOptions(dependents, messages = {}) {
    return (Array.isArray(dependents) ? dependents : []).map((dependent) => ({
        id: Number(dependent.id),
        name: String(dependent.name ?? ''),
        age: tp(messages, 'dependents.age', { age: dependent.age ?? '' }),
        assignable: reasonFor(dependent) === null,
        reasonKey: reasonFor(dependent),
    }));
}

/**
 * Los ids que HOY se pueden asignar. Es lo que se compara con la cesta guardada al restaurar y al
 * conseguir sesión (§4.8·2): un id que no esté aquí se quita de la línea, sin romperla.
 *
 * @returns {number[]}
 */
export function assignableIds(dependents) {
    return assignableOptions(dependents).filter((option) => option.assignable).map((option) => option.id);
}

/** `{ id: {id, name} }`, para pintar «Para: Lucas, Vera» sin llevar el nombre dentro de la línea. */
export function dependentsById(dependents) {
    return (Array.isArray(dependents) ? dependents : []).reduce((map, dependent) => {
        map[Number(dependent.id)] = { id: Number(dependent.id), name: String(dependent.name ?? '') };

        return map;
    }, {});
}

/**
 * Marca o desmarca un menor en una línea. Es un CONJUNTO acotado por la cantidad (D4): con la línea
 * llena, marcar otro no hace nada — el selector deshabilita las casillas libres, y esto es la red por
 * si alguien las pulsa igual.
 *
 * @param {number[]} ids
 * @param {number} id
 * @param {number} quantity
 * @returns {number[]}
 */
export function toggleDependent(ids, id, quantity) {
    const current = Array.isArray(ids) ? ids.map(Number) : [];
    const target = Number(id);

    if (current.includes(target)) {
        return current.filter((each) => each !== target);
    }

    if (current.length >= Number(quantity)) {
        return current;
    }

    return [...current, target];
}

/** Nunca más menores que unidades: al bajar la cantidad se quitan los últimos marcados. */
export function trimToQuantity(ids, quantity) {
    return (Array.isArray(ids) ? ids.map(Number) : []).slice(0, Math.max(0, Number(quantity) || 0));
}

/**
 * Quita de cada línea los ids que ya no se pueden asignar (menor retirado, cumplió 18, firma que
 * caducó al publicar un texto nuevo). La línea se queda sin ESA asignación, no se rompe (§4.8·2).
 *
 * @param {Array<object>} lines  la cesta
 * @param {number[]} assignable  ver {@see assignableIds}
 * @returns {{lines: Array<object>, changed: boolean}}
 */
export function reconcileAssignments(lines, assignable) {
    const allowed = new Set((Array.isArray(assignable) ? assignable : []).map(Number));
    let changed = false;

    const next = (Array.isArray(lines) ? lines : []).map((line) => {
        const ids = Array.isArray(line?.dependent_ids) ? line.dependent_ids.map(Number) : [];
        const kept = ids.filter((id) => allowed.has(id));

        if (kept.length === ids.length) {
            return line;
        }

        changed = true;

        return { ...line, dependent_ids: kept };
    });

    return { lines: next, changed };
}

/**
 * **La puerta 2** (`[DECIDIDO owner]` `#202`·1): quien se identifica en el paso 5 VUELVE AL CARRITO
 * —en vez de ir a pagar— solo si tiene menores asignables y alguna ENTRADA de la cesta tiene unidades
 * sin asignar. Sin menores, o con todo asignado, el camino sigue siendo el de siempre.
 *
 * Recorre las FILAS ya compuestas (`cartRows()`), no la cesta cruda: el presupuesto es quien dice qué
 * línea es un pack y cuántas unidades tiene de verdad.
 *
 * @param {Array<{is_pack?: boolean, quantity?: number, dependent_ids?: number[]}>} rows
 * @param {number} assignableCount
 */
export function needsAssignment(rows, assignableCount) {
    if (! (Number(assignableCount) > 0)) {
        return false;
    }

    return (Array.isArray(rows) ? rows : []).some((row) => row?.is_pack !== true
        && (Array.isArray(row?.dependent_ids) ? row.dependent_ids.length : 0) < Number(row?.quantity ?? 0));
}

/**
 * Los rechazos de `POST /orders` sobre la asignación, por línea de la cesta.
 *
 * El servidor responde `422 validation_failed` con `fields['items.{i}.dependent_ids[.{j}]']` y el
 * mensaje ya traducido (`api.dependents.*`): aquí solo se agrupan por línea. Cualquier otro campo del
 * 422 no es de este módulo.
 *
 * @param {Record<string, string[]>|null|undefined} fields
 * @returns {Record<number, string[]>}
 */
export function assignmentRejections(fields) {
    const byLine = {};

    for (const [key, messages] of Object.entries(fields ?? {})) {
        const match = /^items\.(\d+)\.dependent_ids(?:\.\d+)?$/.exec(key);

        if (! match) {
            continue;
        }

        const index = Number(match[1]);
        byLine[index] = [...(byLine[index] ?? []), ...(Array.isArray(messages) ? messages : [String(messages)])];
    }

    return byLine;
}

/**
 * Aplica el 422 del checkout a la cesta: las líneas rechazadas se quedan SIN asignar, y se devuelve el
 * primer aviso para pintarlo. El pedido no se creó (la comprobación va antes del dinero), así que el
 * titular corrige y vuelve a pagar.
 *
 * @returns {{lines: Array<object>, changed: boolean, message: string}}
 */
export function applyRejections(lines, fields) {
    const rejections = assignmentRejections(fields);
    const indexes = Object.keys(rejections).map(Number);

    if (indexes.length === 0) {
        return { lines, changed: false, message: '' };
    }

    const next = (Array.isArray(lines) ? lines : []).map((line, index) => (
        indexes.includes(index) ? { ...line, dependent_ids: [] } : line
    ));

    return { lines: next, changed: true, message: rejections[indexes[0]][0] ?? '' };
}

/**
 * **El rótulo del bloque plegado «¿quiénes vienen?»** (`specs/waiver-por-reserva.md` §13.5).
 *
 * ⚠️ Vive AQUÍ y no en el componente porque es una REGLA —cuál de cuatro rótulos toca— y una regla
 * dentro de un `.vue` pierde su red: los componentes se comparan por su árbol, y un árbol no dice qué
 * rama se eligió. Lo pidió `SidebarComponentBudgetTest` y tiene razón.
 *
 * ⚠️ Devuelve la CLAVE y no el texto: quien traduce es quien tiene el diccionario, y devolver texto
 * desde aquí obligaría a pasarle los mensajes a un módulo que no los necesita.
 *
 * @param {{dependents: number, guardian: boolean}} state
 * @returns {'who_block.both'|'who_block.some'|'who_block.guardian'|'who_block.none'}
 */
export function whoSummaryKey({ dependents = 0, guardian = false } = {}) {
    const marcados = Number.isFinite(dependents) && dependents > 0 ? dependents : 0;

    if (marcados > 0 && guardian) return 'who_block.both';
    if (marcados > 0) return 'who_block.some';
    if (guardian) return 'who_block.guardian';

    return 'who_block.none';
}

/**
 * ¿Se puede marcar «viene un menor que no está a mi cargo»? **No si la línea ya está llena**: una
 * plaza es una persona, y el owner pudo comprar UNA entrada, asignarla a su hija y pedir además un
 * justificante que la puerta iba a rechazar.
 *
 * ⚠️ **Ya marcado se deja desmarcar**, aunque no queden plazas: si no, quien se equivoca queda
 * atrapado con una casilla que no puede apagar.
 */
export function guardianIsBlocked({ quantity = 0, dependents = 0, checked = false } = {}) {
    return ! checked && Math.max(0, quantity - dependents) < 1;
}
