/**
 * **El almacén del consentimiento de cookies** (`docs/sistemas/COOKIES.md` §4; `specs/analitica.md` §4.3, T3a).
 *
 * Vivía dentro de `app.js` como un objeto literal con las dos categorías ESCRITAS a mano (`maps`, `social`), y la
 * T3a trae dos más (`analytics`, `marketing`): las categorías dejan de estar quemadas y se leen del `<body>`
 * (`data-consent-categories`, que escribe el servidor desde `CookieConsent::OPTIONAL`). Sacarlo a un módulo es lo
 * que permite probarlo con `node --test` como al resto de `ui/`: hasta hoy la atomicidad de `RGPD-05` —el banner
 * solo se da por decidido si el POST volvió `res.ok`— se verificaba «por revisión + build».
 *
 * El SERVIDOR es la autoridad (`CookieConsent::state()`): el estado inicial llega por `data-cookie-*` del `<body>`
 * y este almacén solo refleja la UI y dispara el POST que persiste la cookie canónica y la fila-prueba.
 *
 * ⚠️ **La tarjeta no se enseña con el cajón de compra delante** (`showing`): con el cajón abierto —los pasos del
 * desenlace incluidos— el aviso espera a que se cierre; la barra móvil de compra no se esconde por el banner.
 * ⚠️ `consent_shown` se cuenta UNA vez por página, cuando la primera capa se enseña de verdad, y va por
 * `JumpWeb.track()` —el buzón que el tracker vacía al llegar—, así que no se pierde aunque el tracker cargue
 * después (`analitica.md` §4.2).
 */

/** Por si el `<body>` no trae la lista (una página ajena, F4): las dos de siempre. */
export const FALLBACK_CATEGORIES = ['maps', 'social'];

const datasetKey = (category) => 'cookie' + category.charAt(0).toUpperCase() + category.slice(1);

/**
 * @param {{doc: Document, win: Window, fetchFn?: Function, purchase?: () => ({isOpen?: boolean} | null | undefined)}} deps
 */
export function createCookiesStore({ doc, win, fetchFn, purchase }) {
    const data = doc.body?.dataset ?? {};
    const listed = String(data.consentCategories ?? '').split(',').map((c) => c.trim()).filter(Boolean);
    const categories = listed.length ? listed : FALLBACK_CATEGORIES;
    const send = fetchFn ?? ((...args) => win.fetch(...args));
    const drawer = purchase ?? (() => win.Alpine?.store?.('purchase'));

    const fromBody = () => Object.fromEntries(categories.map((c) => [c, data[datasetKey(c)] === '1']));
    const every = (value) => Object.fromEntries(categories.map((c) => [c, value]));

    return {
        categories,
        enabled: data.cookieEnabled === '1',
        decided: data.cookieDecided === '1',
        panel: false,   // 2.ª capa (preferencias) abierta
        prefs: fromBody(),
        shownOnce: false,

        /** Primera capa pendiente: habilitado y sin decisión registrada. */
        get visible() {
            return this.enabled && ! this.decided;
        },

        /** Lo que de verdad se pinta: la primera capa o el panel, y nunca con el cajón de compra delante. */
        get showing() {
            return (this.visible || this.panel) && ! (drawer()?.isOpen);
        },

        /** El hecho `consent_shown`, una vez por página y solo cuando la PRIMERA capa se ha enseñado. */
        noteShown() {
            if (this.shownOnce || ! this.visible || drawer()?.isOpen) return;

            this.shownOnce = true;
            win.JumpWeb?.track?.('consent_shown');
        },

        acceptAll() {
            return this.persist(every(true));
        },

        rejectAll() {
            return this.persist(every(false));
        },

        /** Conceder UNA categoría desde el placeholder del propio contenido («Cargar mapa»). */
        grant(category) {
            return this.persist({ ...this.prefs, [category]: true });
        },

        openPanel() {
            this.panel = true;
        },

        closePanel() {
            this.panel = false;
        },

        savePanel(prefs) {
            return this.persist(prefs);
        },

        /**
         * Persiste la decisión. Atomicidad «tercero cargado ⇔ prueba RGPD persistida» (`RGPD-05`): solo se da la
         * decisión por buena —se oculta el banner (`decided`) y se avisa a los contenidos gateados
         * (`cookies-updated`)— si el SERVIDOR confirmó (`res.ok`). `fetch` NO rechaza ante un HTTP de error:
         * un 419 (CSRF caducado), 429 (limitador) o 500 deja el banner visible y los terceros bloqueados, y la
         * próxima visita vuelve a pedir la decisión. El `.catch` cubre además los fallos de red.
         *
         * @returns {Promise<boolean>} si el servidor la confirmó
         */
        persist(prefs) {
            this.prefs = Object.fromEntries(categories.map((c) => [c, !! prefs?.[c]]));
            this.panel = false;

            const meta = doc.querySelector?.('meta[name="csrf-token"]');
            // La URI llega por data-attr del body (route('cookies.consent')): un rename de la ruta no rompe la
            // persistencia en silencio. Fallback defensivo a la ruta conocida.
            const endpoint = data.cookieEndpoint || '/cookies/consentimiento';

            return send(endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': meta ? meta.content : '',
                },
                body: JSON.stringify(this.prefs),
            })
                .then((res) => {
                    if (! res || ! res.ok) return false;

                    this.decided = true;
                    win.dispatchEvent(new win.CustomEvent('cookies-updated', { detail: { ...this.prefs } }));

                    return true;
                })
                .catch(() => false);
        },
    };
}
