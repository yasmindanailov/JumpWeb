import { defineStore } from 'pinia';
import { api as httpClient } from '../api.js';

/**
 * **El contexto de cuenta que pinta el bloque `.acct` del panel**
 * (`docs/specs/account-context-vue.md` §4.3).
 *
 * Se alimenta de dos caminos que publican **la misma forma**, porque los compone el mismo
 * `Http\Resources\Api\V1\AccountContextResource`:
 *
 *  · **la semilla** — llega en el `data-boot` del montaje, en cada carga de página con sesión. Es lo
 *    que hace que el bloque esté pintado sin pedirle nada a nadie;
 *  · **el refresco** — `GET /me/account-context`, y se pide en un solo momento: cuando el cajón
 *    consigue sesión **sin recargar** (el paso 5 del embudo). Ahí la semilla es la del invitado.
 *
 * ⚠️⚠️ **`identified` sale de si HAY contexto, y NO de `userId`.** Es la trampa de este store.
 * `userId` es una prop estática del HTML de *esa* carga de página; quien entra dentro del embudo no
 * recarga, así que después de un login `userId` sigue diciendo `null` mientras el titular ya existe.
 * Colgar de él la cara del bloque lo dejaría saludando como invitado a alguien que acaba de entrar —
 * que es literalmente el fallo por el que este bloque se hizo Livewire en 2026-06-14. `userId`
 * conserva su trabajo, que es otro: decirle a la cesta si ha cambiado de dueño.
 *
 * ⚠️ **Un fallo del refresco NO se anuncia**, y es deliberado: esto es cortesía de interfaz, no el
 * contenido de una pantalla. El servidor hace lo mismo —`CustomerAccountContext` envuelve su carga
 * en un `try` y devuelve un contexto vacío seguro— porque romper el panel por no poder saludar con
 * la próxima reserva sería un mal negocio. Mismo criterio que `stores/reservations.js::ensure()`.
 */
export const useAccountContextStore = defineStore('accountContext', {
    state: () => ({
        /**
         * El contexto tal cual, o `null` si no hay sesión.
         *
         * ⚠️ `null` **no** es «todavía no lo he pedido»: es «no hay titular». La semilla del montaje
         * llega siempre, con `null` para el anónimo, precisamente para que este store no tenga que
         * distinguir «no está» de «está vacío».
         */
        context: null,
        loading: false,
    }),

    getters: {
        /** ¿Hay titular? Lo decide el CONTEXTO, nunca `userId` (ver el docblock del store). */
        identified: (state) => state.context !== null,

        /**
         * **Lo que este titular debe antes de poder contratar** (`#349`). Lo lee el paso de PAGAR.
         *
         * ⚠️ **Se leen como getters y no `context.terms_pending` a pelo** para que el `null` del
         * anónimo no obligue a cada consumidor a defenderse: sin titular no se debe nada, que es la
         * respuesta correcta y no un caso especial.
         */
        termsPending: (state) => state.context?.terms_pending === true,
        phoneMissing: (state) => state.context?.phone_missing === true,

        /**
         * ⚠️ `#441` · **TRES estados, no dos**, y por eso NO sigue el `=== true` de sus hermanos:
         * `undefined` («aún no hay contexto») tiene que poder distinguirse de `false` («el correo no
         * está verificado»). Quien lo consume ofrece la acción cuando no lo sabe —esconderla a quien
         * sí puede firmar es peor que enseñarla a quien no—, que es el mismo criterio de
         * `accountNoticeFrom()`.
         */
        emailVerified: (state) => state.context?.email_verified,
    },

    actions: {
        /**
         * Siembra lo que el servidor dejó en el montaje.
         *
         * Se llama una vez, al montar el motor. Acepta `undefined` —un montaje sin la clave— y lo
         * trata como «sin sesión», que es la degradación honesta.
         */
        seed(context) {
            this.context = context ?? null;
        },

        /**
         * Vuelve a preguntar al servidor **quién es y qué tiene**.
         *
         * ⚠️ La identidad sale SIEMPRE del servidor: el endpoint la toma del guard y no acepta
         * ningún identificador por la petición. Pasarle aquí un id que viajara por el navegador
         * sería confiar en el cliente para decidir de quién es el contexto.
         *
         * ⚠️ **Un 401 vacía el contexto en vez de dejar el anterior.** Es el caso del dispositivo
         * compartido: si la sesión murió, seguir saludando por el nombre del anterior es peor que no
         * saludar.
         */
        async refresh({ api = httpClient } = {}) {
            if (this.loading) return;

            this.loading = true;

            try {
                const response = await api.get('/me/account-context');

                if (response.ok) {
                    this.context = response.data ?? null;
                } else if (response.status === 401) {
                    this.context = null;
                }
            } finally {
                this.loading = false;
            }
        },

        /**
         * **Despide el aviso de que la navegación puede vincularse a la cuenta** (T3a·4 de la analítica,
         * `specs/analitica.md` §4.3): el titular lo leyó, o fue a «Privacidad y datos» desde él.
         *
         * ⚠️ El aviso se quita del contexto SOLO cuando el servidor lo confirma (`204`): si la petición
         * falla, sigue pintado y el titular puede volver a intentarlo. Ocultarlo antes lo dejaría sin
         * aviso y con la marca sin poner, que es la peor de las dos mentiras: en la próxima carga
         * volvería a aparecer como si no lo hubiera leído.
         * ⚠️ Sin aviso pendiente no se llama a nadie: un `DELETE` de más sería idempotente en el
         * servidor, pero una petición que no cambia nada no se manda.
         *
         * @returns {Promise<boolean>} si el servidor lo dio por despedido
         */
        async dismissAnalyticsNotice({ api = httpClient } = {}) {
            if (! this.context?.analytics_notice) return false;

            const response = await api.delete('/me/analytics-notice');

            if (response.ok) {
                this.context = { ...this.context, analytics_notice: null };
            }

            return Boolean(response.ok);
        },
    },
});
