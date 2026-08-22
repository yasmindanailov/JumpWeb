import { defineStore } from 'pinia';
import { DEFAULT_SECTION, SECTIONS, isSection } from '../section.js';

/**
 * **Qué SECCIÓN del cajón está activa** (`docs/specs/area-cliente.md` §4.1).
 *
 * Envuelve `section.js`; no reimplementa nada. Las reglas —qué secciones existen, qué señales publica
 * cada una— viven en el módulo plano, que se prueba con `node --test`; aquí solo se les pone
 * reactividad, que es lo único que Pinia aporta y lo único que un `.vue` necesita.
 *
 * ⚠️ **Store propio y no un campo de `purchase`, y el motivo es de arquitectura.** La sección activa
 * es del CAJÓN, no de la compra: metida en el store del embudo, la sección de cuenta tendría que
 * importar el store de compra para saber si le toca pintarse, y ese es el hilo del que se tira hasta
 * volver a tener los dos dominios enredados — justo lo que `DECISIONES #119` desmontó.
 */
export const useSectionStore = defineStore('section', {
    state: () => ({
        /** La sección visible. El cajón abre en la compra (`DEFAULT_SECTION`). */
        active: DEFAULT_SECTION,
    }),

    getters: {
        /** ¿Se está viendo el embudo de compra? Lo pregunta la raíz para enrutar. */
        onPurchase: (state) => state.active === SECTIONS.PURCHASE,

        /** ¿Se está viendo el área de cliente? */
        onAccount: (state) => state.active === SECTIONS.ACCOUNT,
    },

    actions: {
        /**
         * Conmuta de sección. Devuelve si se movió.
         *
         * ⚠️ **Una sección desconocida se rechaza en silencio**, igual que hace la máquina del embudo
         * con una transición imposible: quien llama es la interfaz, y lo que no puede pasar es que el
         * cajón acabe enseñando una pantalla que no existe. Devolver `false` deja constancia para
         * quien sí quiera mirarlo.
         */
        show(section) {
            if (! isSection(section) || section === this.active) return false;

            this.active = section;

            return true;
        },

        /** Volver a la compra. Con `<KeepAlive>` el embudo sigue donde estaba (§4.1). */
        showPurchase() {
            return this.show(SECTIONS.PURCHASE);
        },

        /** Ir al área de cliente. */
        showAccount() {
            return this.show(SECTIONS.ACCOUNT);
        },
    },
});
