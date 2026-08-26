import { defineStore } from 'pinia';
import { api as httpClient } from '../api.js';
import { formState, resetForm, runForm } from '../account/form-run.js';

/**
 * **El estado del waiver en el cajón** (Fase 6, `docs/specs/waiver-probatorio.md` §4.4, §4.8, §9.9).
 *
 * Dos lecturas y una escritura, y las tres son del SERVIDOR:
 *  · `GET /legal/waiver` — el texto firmable VIGENTE con su `id`, en el idioma de la petición. Lo pide
 *    el formulario de alta al montarse y la zona de privacidad; `document: null` significa «aquí no
 *    se firma» (modo externo o sin versión publicada) y NO es un error.
 *  · `GET /me/waiver` — mi estado: firmado, anticuado, qué texto toca aceptar y mis firmas con su PDF.
 *  · `POST /me/waiver` — aceptar el texto vigente. Va por `runForm`, como el resto de formularios del
 *    área: `busy`, `notice`, `done`.
 *
 * ⚠️⚠️ **El identificador que se acepta es SIEMPRE el del texto que se ENSEÑA**, y el cliente no lo
 * inventa ni lo recuerda de otra sesión: sale de `GET /legal/waiver` en memoria. Si el estado propio
 * dice que el vigente es otro, se relee ANTES de que nadie firme (`#175`: hasta entonces el id salía
 * del estado y el texto de otra respuesta, y podían divergir). Si el texto se publicó de nuevo entre
 * medias, el servidor responde `409 waiver_document_stale`; aquí se descarta lo leído y se vuelve a
 * pedir, para que la pantalla enseñe el texto NUEVO — y `reread` le dice a la pantalla que desmarque
 * la casilla. Aceptar sin releer sería exactamente lo que la spec prohíbe.
 *
 * ⚠️ **Un fallo de lectura no se anuncia** (`legal`/`status` se quedan en `null`): es el mismo criterio
 * que `stores/privacy.js::ensureConsents()` — una lista de cortesía no tumba una pantalla—. Y las dos
 * lecturas van en `try/catch` porque el formulario de alta se monta también en el SSR del contrato de
 * árbol, donde no hay red que responda.
 */
export const useWaiverStore = defineStore('waiver', {
    state: () => ({
        ...formState(),

        /** `GET /legal/waiver` crudo: `{mode, document}`. `null` mientras no se haya pedido (o falló). */
        legal: null,
        legalLoading: false,

        /** `GET /me/waiver` crudo. `null` mientras no se haya pedido (o falló). */
        status: null,
        statusLoading: false,

        /**
         * `true` cuando el texto en pantalla se acaba de RELEER porque el que había ya no era el
         * vigente (409 al aceptar, 422 del alta, o el estado propio apuntando a otro id). La pantalla
         * lo usa para desmarcar «he leído y acepto»: lo que se leyó ya no es lo que se firma (CAJ-3).
         */
        reread: false,
    }),

    getters: {
        /** El texto vigente, o `null` si aquí no se firma. */
        document: (state) => state.legal?.document ?? null,

        signed: (state) => state.status?.signed === true,
        outdated: (state) => state.status?.outdated === true,
        signatures: (state) => state.status?.signatures ?? [],

        /**
         * El `id` que se manda al aceptar: el del texto ENSEÑADO, y solo ese. Hasta `#175` prefería
         * `status.current_document_id` —otra respuesta, pedida en otro momento— y en la ruta del
         * embudo (alta en el paso 5 → sesión sin recarga → Privacidad) podía enseñar la versión vieja
         * y firmar la nueva sin que el servidor viera nada raro (revisión `#169` §10.3, CAJ-1). Si el
         * estado dice que el vigente es otro, `ensureStatus()` relee el texto ANTES.
         */
        currentDocumentId: (state) => state.legal?.document?.id ?? null,
    },

    actions: {
        /** Deja los formularios como si nunca se hubiera intentado nada. Se llama al ENTRAR en la zona. */
        reset() {
            resetForm(this);
            this.reread = false;
        },

        /** Olvida el texto en pantalla, lo vuelve a pedir, y deja dicho que se releyó. */
        async reloadLegal({ api = httpClient } = {}) {
            this.legal = null;
            this.reread = true;
            await this.ensureLegal({ api });
        },

        /** Pide el texto firmable **solo si no lo tiene**. */
        async ensureLegal({ api = httpClient } = {}) {
            if (this.legal !== null || this.legalLoading) return;

            this.legalLoading = true;

            try {
                const response = await api.get('/legal/waiver');

                if (response.ok) this.legal = response.data;
            } catch {
                // Sin red (o en el SSR): se queda sin texto, y sin texto no hay casilla.
            } finally {
                this.legalLoading = false;
            }
        },

        /** Pide mi estado **solo si no lo tiene**. */
        async ensureStatus({ api = httpClient } = {}) {
            if (this.status !== null || this.statusLoading) return;

            this.statusLoading = true;

            try {
                const response = await api.get('/me/waiver');

                if (response.ok) this.status = response.data;
            } catch {
                // Cortesía de UI: la tarjeta se queda sin estado y no anuncia nada.
            } finally {
                this.statusLoading = false;
            }

            // El texto en pantalla puede venir de otra página (el alta lo cachea): si el estado propio
            // dice que el vigente es OTRO, se relee antes de que nadie lo firme (CAJ-1).
            const current = this.status?.current_document_id ?? null;

            if (current !== null && this.legal !== null && current !== (this.legal?.document?.id ?? null)) {
                await this.reloadLegal({ api });
            }
        },

        /**
         * Acepta el texto vigente. Devuelve si salió.
         *
         * Al salir bien, el servidor devuelve el estado nuevo y se coloca tal cual: es el mismo cuerpo
         * que `GET /me/waiver`. Si el texto cambió entre medias (`409 waiver_document_stale`), se olvida
         * lo leído y se vuelve a pedir, para que quien firme lea lo que va a firmar.
         */
        async accept({ api = httpClient, messages = {}, auth = {} } = {}) {
            const documentId = this.currentDocumentId;

            if (documentId === null) return false;

            // El 409 del texto caducado no señala ningún campo: se reconoce por su CÓDIGO en la
            // respuesta cruda, antes de que `formOutcome` lo convierta en un aviso.
            let stale = false;

            const ok = await runForm(
                this,
                async () => {
                    const response = await api.post('/me/waiver', { document_id: documentId });

                    stale = response?.error?.code === 'waiver_document_stale';

                    return response;
                },
                { messages, auth },
                (response) => { this.status = response.data; this.reread = false; },
            );

            if (! ok && stale) {
                this.status = null;
                await Promise.all([this.reloadLegal({ api }), this.ensureStatus({ api })]);
            }

            return ok;
        },
    },
});
