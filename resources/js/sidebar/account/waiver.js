/**
 * **El waiver visto desde el cajón** (Fase 6, `docs/specs/waiver-probatorio.md` §4.4, §4.8, §9.9).
 *
 * Módulo PLANO, sin Vue (`CE-6`): lo que la pantalla necesita decidir sobre el estado que publica
 * `GET /me/waiver` —qué frase enseñar y si hay algo que firmar— vive aquí, con su `node --test`, y
 * `stores/waiver.js` solo coloca lo que responde el servidor.
 *
 * ⚠️ **El cliente no decide nada sobre el waiver**: ni si hace falta, ni qué texto, ni si el que firmó
 * sigue valiendo. Eso lo dice el servidor (`mode`, `signed`, `outdated`, `current_document_id`), y aquí
 * solo se traduce a una clave de texto y a un booleano. Un cliente que dedujera «hay que firmar» por
 * su cuenta acabaría discrepando de la puerta.
 */

/**
 * La clave de `account.privacy.waiver.*` que describe el estado.
 *
 *  · `externo` → «lo gestiona el parque» (aquí solo hay sello, no hay nada que firmar);
 *  · `interno` sin firma → «pendiente»; firmado en una versión anterior → «anterior»; vigente → «vigente».
 *
 * Sin estado (aún no se pidió, o falló) devuelve `''`, que `i18n.js` pinta como nada — el mismo
 * criterio que el resto de listas de cortesía del cajón: un fallo de lectura no se anuncia.
 *
 * @param {{mode?: string, signed?: boolean, outdated?: boolean}|null} status
 */
export function waiverStatusKey(status) {
    if (! status || typeof status.mode !== 'string') return '';
    if (status.mode === 'externo') return 'account.privacy.waiver.status_external';
    if (status.mode !== 'interno') return '';
    if (status.signed !== true) return 'account.privacy.waiver.status_unsigned';

    return status.outdated === true
        ? 'account.privacy.waiver.status_outdated'
        : 'account.privacy.waiver.status_current';
}

/**
 * ¿La pantalla tiene que ofrecer la FIRMA? Solo en modo interno, y solo si no hay firma o la que hay
 * es de una versión anterior del texto (§4.8: la re-firma se pide aquí, no en el mostrador).
 *
 * @param {{mode?: string, signed?: boolean, outdated?: boolean}|null} status
 */
export function waiverNeedsSignature(status) {
    return status?.mode === 'interno' && (status.signed !== true || status.outdated === true);
}

/**
 * Lo que el índice y el bloque de cuenta enseñan como AVISO, a partir del contexto de cuenta
 * (`GET /me/account-context` → `waiver`). Misma regla que arriba, sobre la forma del contexto.
 *
 * @param {{waiver?: {mode?: string, required?: boolean, outdated?: boolean}}|null} context
 */
export function waiverPendingFrom(context) {
    const waiver = context?.waiver;

    return waiver?.mode === 'interno' && (waiver.required === true || waiver.outdated === true);
}
