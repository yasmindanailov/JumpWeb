/**
 * **Los menores a cargo vistos desde el cajón** (Fase 6 · C, `docs/specs/menores-a-cargo.md` §4.1,
 * §4.3, §9.8).
 *
 * Módulo PLANO, sin Vue (`CE-6`): lo que la pantalla necesita decidir sobre lo que publica
 * `GET /me/dependents` —qué frase enseñar de cada uno, si hay algo que firmar en su nombre y cómo
 * se lee su fecha— vive aquí con su `node --test`, y `stores/dependents.js` solo coloca lo que
 * responde el servidor.
 *
 * ⚠️ **El cliente no decide NADA sobre un menor** (`CE-4`): ni su edad, ni si sigue siendo menor,
 * ni si su exención está al día. Lo publica el servidor (`age`, `is_minor`, `adult_from`,
 * `waiver.*`), derivado con el «hoy» del parque; aquí solo se traduce a claves de texto y booleanos.
 * Un cliente que restara fechas por su cuenta cumpliría los 18 dos horas antes o después que la puerta.
 */

/** Los campos del formulario de alta, vacíos. `born_on` viaja en `Y-m-d`, que es lo que da `<input type="date">`. */
export function dependentForm() {
    return { name: '', born_on: '' };
}

/**
 * La fecha de nacimiento como la lee el cliente (`dd/mm/aaaa`), a partir del `Y-m-d` del servidor.
 *
 * ⚠️ Sin `Date` a propósito: es una fecha SIN hora y sin zona, y construir un `Date` con ella la
 * movería un día en los husos negativos. Reordenar la cadena no puede equivocarse. Una entrada que
 * no tenga esa forma sale tal cual: enseñar algo raro es mejor que enseñar nada.
 */
export function bornOnLabel(iso) {
    const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(String(iso ?? ''));

    return match ? `${match[3]}/${match[2]}/${match[1]}` : String(iso ?? '');
}

/**
 * La clave de `account.dependents.*` que describe la EXENCIÓN de un menor, o `''` fuera del modo
 * interno (ahí no hay nada que decir: la gestiona el parque, y la tarjeta del titular ya lo cuenta).
 *
 *  · sin firma → «sin firmar» · firmada en una versión anterior → «anterior» · vigente → «firmada».
 *
 * @param {{waiver?: {mode?: string, signed?: boolean, outdated?: boolean}}|null} dependent
 */
export function dependentWaiverKey(dependent) {
    const waiver = dependent?.waiver;

    if (waiver?.mode !== 'interno') return '';
    if (waiver.signed !== true) return 'account.dependents.waiver_unsigned';

    return waiver.outdated === true
        ? 'account.dependents.waiver_outdated'
        : 'account.dependents.waiver_current';
}

/**
 * ¿La tarjeta tiene que ofrecer FIRMAR en su nombre? Solo en modo interno, solo si sigue siendo
 * menor (§4.1: a los 18 la exención del adulto ya no le cubre, y el servidor rechazaría la firma), y
 * solo si no hay firma o la que hay es de una versión anterior.
 *
 * @param {{is_minor?: boolean, waiver?: {mode?: string, signed?: boolean, outdated?: boolean}}|null} dependent
 */
export function dependentNeedsSignature(dependent) {
    const waiver = dependent?.waiver;

    return dependent?.is_minor === true
        && waiver?.mode === 'interno'
        && (waiver.signed !== true || waiver.outdated === true);
}

/**
 * La clave del aviso de COBERTURA, o `''`. Hoy solo uno: «ya tiene 18 años» (§4.1: la fila
 * sobrevive a la mayoría de edad y se MARCA, no se borra).
 *
 * @param {{is_minor?: boolean}|null} dependent
 */
export function coverageKey(dependent) {
    return dependent?.is_minor === false ? 'account.dependents.adult' : '';
}

/**
 * La lista con un menor sustituido por su versión nueva (tras firmar) — o añadido, si no estaba.
 *
 * @param {Array<{id: number}>} list
 * @param {{id: number}} dependent
 */
export function replaceDependent(list, dependent) {
    const found = (list ?? []).some((row) => row.id === dependent.id);

    return found
        ? (list ?? []).map((row) => (row.id === dependent.id ? dependent : row))
        : [...(list ?? []), dependent];
}
