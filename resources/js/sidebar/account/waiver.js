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
 * ❗❗ **`#329` — «ACEPTADA, ESPERANDO A QUE VERIFIQUE EL CORREO» ES UN ESTADO PROPIO, y no mirarlo
 * era mentirle al cliente.**
 *
 * El alta NO firma: guarda la aceptación en espera y la convierte en firma al verificar el correo
 * (`#179`, porque una firma sobre un buzón sin demostrar no prueba nada). En esa ventana el servidor
 * publica DOS datos —`pending` («la aceptó») y `required` («aún no hay firma»)— y hasta hoy la
 * pantalla solo leía el segundo: le decía **«tienes pendiente la exención»** a quien acababa de
 * marcarla, con un botón «Firmarla» que **no puede funcionar** (`POST /me/waiver` → 409
 * `waiver_email_unverified`).
 *
 * Y no es una ventana de dos minutos: **se puede iniciar sesión sin verificar**, así que quien no
 * abre el correo ve ese aviso falso cada vez que entra en su cuenta.
 *
 * Lo que falta ahí no es firmar, es VERIFICAR — y eso es lo que la pantalla ofrece ahora.
 */
export const WAIVER_NOTICE_SIGN = 'sign';

export const WAIVER_NOTICE_VERIFY = 'verify';

/**
 * La clave de `account.privacy.waiver.*` que describe el estado.
 *
 *  · `externo` → «lo gestiona el parque» (aquí solo hay sello, no hay nada que firmar);
 *  · `interno` aceptada y sin verificar → «la firmaremos al verificar tu correo» (`#329`);
 *  · `interno` sin firma → «pendiente»; firmado en una versión anterior → «anterior»; vigente → «vigente».
 *
 * Sin estado (aún no se pidió, o falló) devuelve `''`, que `i18n.js` pinta como nada — el mismo
 * criterio que el resto de listas de cortesía del cajón: un fallo de lectura no se anuncia.
 *
 * @param {{mode?: string, signed?: boolean, outdated?: boolean, pending?: boolean}|null} status
 */
export function waiverStatusKey(status) {
    if (! status || typeof status.mode !== 'string') return '';
    if (status.mode === 'externo') return 'account.privacy.waiver.status_external';
    if (status.mode !== 'interno') return '';
    if (status.signed !== true) {
        return waiverAwaitsVerification(status)
            ? 'account.privacy.waiver.status_awaiting_verification'
            : 'account.privacy.waiver.status_unsigned';
    }

    return status.outdated === true
        ? 'account.privacy.waiver.status_outdated'
        : 'account.privacy.waiver.status_current';
}

/**
 * ¿La aceptación está guardada esperando a que el cliente verifique su correo?
 *
 * ⚠️ Se pregunta al SERVIDOR, no se deduce: `pending` es exactamente «hay una aceptación retenida»
 * (`users.waiver_pending_document_id`). El cliente no puede saberlo de otro modo, y un cliente que lo
 * dedujera acabaría discrepando de la puerta.
 *
 * @param {{mode?: string, signed?: boolean, pending?: boolean}|null} status
 */
export function waiverAwaitsVerification(status) {
    return status?.mode === 'interno' && status.signed !== true && status.pending === true;
}

/**
 * ¿La pantalla tiene que ofrecer la FIRMA? Solo en modo interno, y solo si no hay firma o la que hay
 * es de una versión anterior del texto (§4.8: la re-firma se pide aquí, no en el mostrador).
 *
 * ⚠️⚠️ **Con la aceptación en espera, NO.** El botón existía y llevaba a un 409: no se puede firmar
 * sin el correo verificado (`WaiverSigner`), así que ofrecerlo era ofrecer un callejón. Ahí lo que se
 * ofrece es reenviar la verificación, que es lo que de verdad desbloquea la firma.
 *
 * @param {{mode?: string, signed?: boolean, outdated?: boolean, pending?: boolean}|null} status
 */
export function waiverNeedsSignature(status) {
    if (waiverAwaitsVerification(status)) return false;

    return status?.mode === 'interno' && (status.signed !== true || status.outdated === true);
}

/**
 * **El aviso ÚNICO del índice de la cuenta** (`#331`), a partir del contexto de cuenta.
 *
 * `[DECIDIDO owner, 2026-09-01]`: *«mejor 1 mensaje con los dos estados, para no saturar»*. Desde que
 * el alta suelta abre sesión, quien acaba de registrarse llega aquí con **dos cosas pendientes que en
 * realidad son una sola acción suya**: verificar el correo, y —si dejó la exención aceptada— que esa
 * verificación la convierta en firma. Dos avisos apilados dirían dos veces «abre tu correo».
 *
 * Devuelve `null` o `{ kind, withWaiver }`:
 *  · `verify` → falta verificar el correo. `withWaiver` dice si además hay una exención esperando a
 *    ese mismo clic, que es lo único que cambia en la frase;
 *  · `sign` → el correo está verificado y lo que falta es firmar o RE-firmar la exención (una versión
 *    nueva del texto caduca una firma sin que nadie toque nada).
 *
 * ⚠️ **El orden importa y no es arbitrario**: si falta verificar, ése es el aviso — aunque el waiver
 * también «haga falta». Ofrecer «Firmar» a quien no puede firmar es el callejón que `#329` cerró.
 *
 * ⚠️ **`email_verified` en snake_case**: las dos vías del contexto —la semilla del montaje y
 * `GET /me/account-context`— las compone el MISMO `AccountContextResource`, así que aquí llega la
 * forma de la API y no la del array de PHP.
 *
 * @param {{email_verified?: boolean, waiver?: {mode?: string, required?: boolean, outdated?: boolean, pending?: boolean}}|null} context
 * @returns {{kind: 'sign'|'verify', withWaiver: boolean}|null}
 */
export function accountNoticeFrom(context) {
    if (! context) return null;

    const waiver = context.waiver;

    if (context.email_verified === false) {
        return { kind: WAIVER_NOTICE_VERIFY, withWaiver: waiver?.mode === 'interno' && waiver.pending === true };
    }

    if (waiver?.mode !== 'interno') return null;

    return (waiver.required === true || waiver.outdated === true)
        ? { kind: WAIVER_NOTICE_SIGN, withWaiver: true }
        : null;
}
