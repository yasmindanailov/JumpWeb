/**
 * **¿Se le ofrece el opt-in de comunicaciones al acabar de comprar?** (`docs/specs/analitica.md` §4.3
 * «Comunicaciones» y §4.6, T4c).
 *
 * El consentimiento de marketing ya se da y se retira en «Mi cuenta → Privacidad» (`PUT /me/marketing`, único
 * escritor). Lo nuevo es el MOMENTO: la pantalla de «reserva creada» es cuando el cliente está contento y tiene
 * la cuenta delante. La casilla se ofrece DESMARCADA y solo cuando:
 *  · hay sesión (a esta pantalla se llega también por el enlace de verificación de correo, sin sesión);
 *  · el perfil está cargado y dice `marketing_opt_in === false` (quien ya lo dio no ve la pregunta);
 *  · y **nunca lo retiró**: una fila de `consents` de tipo `marketing` con `revoked_at` es un «no» que ya se
 *    dijo, y volver a preguntar tras un «no» es insistir (art. 7.3 en espíritu). Sin la lista de
 *    consentimientos cargada aún no se decide: se espera, no se ofrece.
 *
 * Todo por parámetro (`CE-6`): la pantalla solo llama a esto y pinta.
 *
 * @param {{hasSession: boolean, user: {marketing_opt_in?: boolean}|null|undefined, consents: Array<{type?: string, revoked_at?: string|null}>|null|undefined}} input
 * @returns {boolean}
 */
export function offersMarketingOptIn({ hasSession, user, consents }) {
    if (! hasSession || ! user || user.marketing_opt_in !== false) return false;

    const rows = consentRows(consents);
    if (rows === null) return false;

    return ! rows.some((c) => c?.type === 'marketing' && c?.revoked_at);
}

/**
 * La lista tal cual la guarda el store: `GET /me/consents` llega con SOBRE (`{ data: [...] }`, `ApiCollection`), y
 * un test o un store viejo pueden darla plana. `null` = todavía no se ha pedido.
 *
 * @param {unknown} consents
 * @returns {Array<{type?: string, revoked_at?: string|null}>|null}
 */
export function consentRows(consents) {
    if (Array.isArray(consents)) return consents;
    if (consents && Array.isArray(consents.data)) return consents.data;

    return null;
}

/**
 * ¿Hace falta traer la lista de consentimientos para poder decidir? Solo con sesión y con el perfil diciendo
 * que no hay opt-in: en los demás casos la respuesta ya es «no» sin pedir nada.
 *
 * @param {{hasSession: boolean, user: {marketing_opt_in?: boolean}|null|undefined, consents: unknown}} input
 * @returns {boolean}
 */
export function needsConsentsToDecide({ hasSession, user, consents }) {
    return Boolean(hasSession && user && user.marketing_opt_in === false && consentRows(consents) === null);
}
