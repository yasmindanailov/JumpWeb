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
 * ⚠️⚠️ **El identificador que se acepta es SIEMPRE el que el servidor sirvió**, y el cliente no lo
 * inventa ni lo recuerda de otra sesión: sale de la respuesta que tiene en memoria. Si el texto se
 * publicó de nuevo entre medias, el servidor responde `409 waiver_document_stale`; aquí se descarta lo
 * leído y se vuelve a pedir, para que la pantalla enseñe el texto NUEVO. Aceptar sin releer sería
 * exactamente lo que la spec prohíbe.
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
    }),

    getters: {
        /** El texto vigente, o `null` si aquí no se firma. */
        document: (state) => state.legal?.document ?? null,

        signed: (state) => state.status?.signed === true,
        outdated: (state) => state.status?.outdated === true,
        signatures: (state) => state.status?.signatures ?? [],

        /**
         * El `id` que se manda al aceptar. El del estado propio manda (es el vigente en MI idioma
         * negociado al pedirlo); si aún no se pidió, el del texto público.
         */
        currentDocumentId: (state) => state.status?.current_document_id ?? state.legal?.document?.id ?? null,
    },

    actions: {
        /** Deja los formularios como si nunca se hubiera intentado nada. Se llama al ENTRAR en la zona. */
        reset() {
            resetForm(this);
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
                (response) => { this.status = response.data; },
            );

            if (! ok && stale) {
                this.legal = null;
                this.status = null;
                await Promise.all([this.ensureLegal({ api }), this.ensureStatus({ api })]);
            }

            return ok;
        },
    },
});
