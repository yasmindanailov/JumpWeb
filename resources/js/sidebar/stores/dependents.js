import { defineStore } from 'pinia';
import { api as httpClient } from '../api.js';
import { formState, resetForm, runForm } from '../account/form-run.js';
import { replaceDependent } from '../account/dependents.js';

/**
 * **El estado de los menores a cargo en el cajón** (Fase 6 · C, `docs/specs/menores-a-cargo.md`
 * §4.2, §4.4, §4.5, §9.8).
 *
 * Una lectura y tres escrituras, y las cuatro son del SERVIDOR:
 *  · `GET /me/dependents` — los declarados y no retirados, con su edad, si siguen siendo menores y
 *    el estado de SU exención, todo derivado allí con el «hoy» del parque.
 *  · `POST /me/dependents` — declarar uno (nombre y fecha de nacimiento, nada más). El tope por
 *    cuenta y la regla de los 18 los aplica el servidor: aquí se enseña lo que responda.
 *  · `DELETE /me/dependents/{id}` — quitarlo. Desvincular o borrar lo decide el servidor; para la
 *    pantalla desaparece igual.
 *  · `POST /me/dependents/{id}/waiver` — firmar la exención EN SU NOMBRE con el `document_id` del
 *    texto que se ENSEÑA (`stores/waiver.js`, CAJ-1: el id sale del texto en pantalla y de ningún
 *    otro sitio). Si el texto cambió entre medias (`409 waiver_document_stale`), se devuelve `stale`
 *    para que quien llame relea ANTES de que nadie vuelva a firmar.
 *
 * Todas las escrituras van por `runForm`, como el resto del área: `busy`, `fields`, `notice`,
 * `done`, `expired`. Los rechazos de dominio sin campo —`dependents_limit_reached`,
 * `dependent_not_minor`— llegan como `notice` con el texto que publicó el servidor.
 *
 * ⚠️ **RGPD**: la lista vive solo en memoria mientras la zona está abierta y NO se persiste: son
 * nombres y fechas de nacimiento de menores (`#38(d)`, la misma regla que las respuestas del pack).
 */
export const useDependentsStore = defineStore('dependents', {
    state: () => ({
        ...formState(),

        /** `data` de `GET /me/dependents`, cruda. `null` mientras no se haya pedido. */
        list: null,
        listLoading: false,

        /** El menor cuya firma o retirada está en vuelo, para que SU tarjeta lo diga y no las demás. */
        signingId: null,
        removingId: null,

        /** El último menor cuya exención se acaba de firmar bien: su tarjeta confirma. */
        signedId: null,
    }),

    getters: {
        loaded: (state) => state.list !== null,
        items: (state) => state.list ?? [],
    },

    actions: {
        /** Deja los formularios como si nunca se hubiera intentado nada. Se llama al ENTRAR en la zona. */
        reset() {
            resetForm(this);
            this.signingId = null;
            this.removingId = null;
            this.signedId = null;
        },

        /** Pide la lista **solo si no la tiene**. Bandera propia, no `busy` (misma razón que `stores/privacy.js`). */
        async ensure({ api = httpClient } = {}) {
            if (this.loaded || this.listLoading) return;

            this.listLoading = true;

            try {
                const response = await api.get('/me/dependents');

                if (response.ok) this.list = response.data?.data ?? [];
                else if (response.status === 401) this.expired = true;
            } finally {
                this.listLoading = false;
            }
        },

        /** Olvida la lista: el siguiente `ensure()` vuelve a preguntar (al cambiar de titular). */
        invalidate() {
            this.list = null;
        },

        /** Declara un menor. Devuelve si salió. La respuesta ES el menor, y se añade al final. */
        async add(form, { api = httpClient, messages = {}, auth = {} } = {}) {
            this.signedId = null;

            return runForm(
                this,
                () => api.post('/me/dependents', { name: form.name, born_on: form.born_on }),
                { messages, auth },
                (response) => { this.list = replaceDependent(this.list, response.data); },
            );
        },

        /** Quita un menor. Devuelve si salió. Ajeno, retirado o inexistente es un 404 que se enseña. */
        async remove(id, { api = httpClient, messages = {}, auth = {} } = {}) {
            this.removingId = id;
            this.signedId = null;

            try {
                return await runForm(
                    this,
                    () => api.delete(`/me/dependents/${id}`),
                    { messages, auth },
                    () => { this.list = (this.list ?? []).filter((row) => row.id !== id); },
                );
            } finally {
                this.removingId = null;
            }
        },

        /**
         * Firma la exención en nombre de un menor. Devuelve `{ok, stale}`.
         *
         * Al salir bien, el servidor devuelve el menor con su exención ya firmada y se coloca en la
         * lista tal cual. `stale` es el 409 del texto caducado: quien llama relee el texto y desmarca
         * la casilla — sin `documentId` no se firma nada.
         */
        async signWaiver({ id, documentId }, { api = httpClient, messages = {}, auth = {} } = {}) {
            if (documentId === null || documentId === undefined) return { ok: false, stale: false };

            this.signingId = id;
            this.signedId = null;
            let stale = false;

            try {
                const ok = await runForm(
                    this,
                    async () => {
                        const response = await api.post(`/me/dependents/${id}/waiver`, { document_id: documentId });

                        stale = response?.error?.code === 'waiver_document_stale';

                        return response;
                    },
                    { messages, auth },
                    (response) => { this.list = replaceDependent(this.list, response.data); this.signedId = id; },
                );

                return { ok, stale };
            } finally {
                this.signingId = null;
            }
        },
    },
});
