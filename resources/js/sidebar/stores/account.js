import { defineStore } from 'pinia';
import { DEFAULT_ZONE, createNavigation } from '../account/navigation.js';
import { useSectionStore } from './section.js';

/**
 * **La navegación del ÁREA DE CLIENTE**: en qué zona está y cómo se vuelve
 * (`docs/specs/area-cliente.md` §4.2).
 *
 * Envuelve `account/navigation.js`; no reimplementa la pila. Las reglas —que la historia no crezca
 * sin límite, que «volver» en la raíz signifique salir— viven en el módulo plano y se prueban con
 * `node --test`.
 *
 * ⚠️⚠️ **`zone` y `canBack` se COPIAN al estado, no se leen de la navegación**, y no es estilo: es
 * lo único que funciona. Un getter de Pinia es un `computed` y solo se recalcula cuando cambia algo
 * REACTIVO que haya leído; la navegación es un objeto plano con su pila en una variable de closure
 * que Vue **no puede observar**. Exactamente ahí murió el «modo» del panel durante meses
 * (`DECISIONES #118`): el getter cacheaba el primer valor y no se invalidaba jamás. La misma trampa
 * y la misma solución que `stores/purchase.js`.
 */
export const useAccountStore = defineStore('account', {
    state: () => ({
        /** La zona visible. La mueve la navegación; aquí solo se refleja para que Vue la observe. */
        zone: DEFAULT_ZONE,

        /** ¿Hay a dónde volver DENTRO del área? Copiado por lo mismo que `zone`. */
        canBack: false,

        /** La navegación, sin reactividad: es un objeto plano y se lee SOLO a través de `sync()`. */
        nav: null,
    }),

    actions: {
        /**
         * Arranca el área sobre una navegación.
         *
         * Se inyecta en vez de crearse aquí para que un test pueda darla ya colocada en la zona que
         * quiera probar, sin llegar a ella a base de saltos — el mismo patrón que `purchase.boot()`.
         */
        boot(nav) {
            this.nav = nav;
            this.sync();
        },

        /** Copia al estado lo que la navegación acaba de decidir. El único sitio que la lee. */
        sync() {
            this.zone = this.nav.zone;
            this.canBack = this.nav.canBack;
        },

        /** Va a una zona. Devuelve si se movió. */
        go(zone) {
            const moved = this.nav.go(zone);
            this.sync();

            return moved;
        },

        /**
         * **Volver.** Devuelve `true` si se quedó dentro del área.
         *
         * ⚠️ **Y cuando no hay historia, SALE a la compra en vez de no hacer nada.** Un «volver» que
         * a veces no responde es peor que no tenerlo: el cliente lo pulsa dos veces y acaba cerrando
         * el cajón —que es lo que le hace perder de vista la cesta—. La decisión vive aquí y no en el
         * módulo plano a propósito: aquél sabe de zonas, no de secciones, y mezclarlo devolvería los
         * dos niveles de navegación al mismo mapa (`DECISIONES #119`).
         */
        back() {
            if (this.nav.back()) {
                this.sync();

                return true;
            }

            useSectionStore().showPurchase();

            return false;
        },

        /**
         * Entra al área por una zona, **vaciando la historia de la visita anterior**.
         *
         * Un recorrido de hace media hora no describe nada de lo que el cliente tiene delante ahora,
         * y dejarlo haría que «volver» le llevara a una pantalla que no pidió (§4.2).
         *
         * ⚠️ **`under` deja una zona DEBAJO**, y quién lo pide lo decide `parentZoneFor()`: quien
         * llega por una PUERTA a recuperar contraseña necesita que «volver» le lleve a entrar, y
         * quien llega desde el paso 5 del embudo necesita justo lo contrario —salir de la sección y
         * encontrarse la compra donde la dejó—. Con la pila vacía, «volver» sale.
         */
        enter(zone = DEFAULT_ZONE, { under = null } = {}) {
            this.nav.reset(zone, under);
            this.sync();
        },
    },
});
