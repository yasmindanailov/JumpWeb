import { defineStore } from 'pinia';
import { createMachine, STEPS } from './machine.js';

/**
 * El store del flujo de compra (Fase 4 · paso 4.1).
 *
 * **Envuelve la máquina; no la reimplementa.** Las transiciones viven en `machine.js` —módulo plano,
 * probado con `node --test`— y aquí solo se les pone reactividad y se publican las señales hacia
 * fuera. Si la lógica bajara a este fichero perdería su red: probar un store de Pinia exige montar
 * un runner de componentes, que es la dependencia que §4.8 decidió no añadir.
 *
 * ⚠️ Pinia se eligió por un requisito del owner (`DECISIONES #38c`): el cajón hospedará **tres
 * dominios** —compra, cuenta y gestión de entradas— que comparten sesión y saltan entre sí. Con uno
 * solo sería ceremonia. Y queda dicho para que nadie lo confunda: **Pinia no resuelve el salto entre
 * estados**; eso es la máquina.
 */
export const usePurchaseStore = defineStore('purchase', {
    state: () => ({
        /** El paso actual. Lo mueve la máquina; aquí solo se refleja para que Vue lo observe. */
        step: STEPS.CATALOG,

        /** La máquina, sin reactividad: es un objeto plano y se marca como tal más abajo. */
        machine: null,
    }),

    getters: {
        /** El «modo» que el layout pinta como `is-{modo}` en el panel del cajón. */
        mode: (state) => state.machine?.mode ?? 'catalog',

        /** `true` en el paso de identificación: bloquea los botones de login de FUERA del cajón. */
        identifying: (state) => state.machine?.identifying ?? false,
    },

    actions: {
        /**
         * Arranca el store sobre una máquina.
         *
         * La máquina se inyecta en vez de crearse aquí para que un test pueda darle una ya colocada
         * en el paso que quiera probar, sin tener que llegar a él a base de transiciones.
         */
        boot(machine) {
            this.machine = machine;
            this.step = machine.step;
        },

        /** Intenta ir a un paso. Devuelve si se movió. */
        go(step) {
            const moved = this.machine.go(step);
            this.step = this.machine.step;

            return moved;
        },

        /** Aterriza en un paso sin comprobar la transición (la vuelta de la pasarela). */
        enter(step) {
            this.machine.enter(step);
            this.step = this.machine.step;
        },
    },
});
