import { defineStore } from 'pinia';
import { isIdentifying, modeOf, STEPS } from '../machine.js';

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
        /**
         * Las dos señales que el cajón PUBLICA hacia fuera, derivadas del paso.
         *
         * ⚠️⚠️ **Se derivan de `state.step`, NO de `state.machine`, y no es estilo: es lo único que
         * funciona.** Hasta el 2026-08-22 esto era `state.machine?.mode`, y estaba **muerto**
         * (`DECISIONES #118`). Un getter de Pinia es un `computed`: solo se recalcula cuando cambia
         * algo REACTIVO que haya leído. `state.machine` es un objeto plano cuya identidad nunca
         * cambia, y su `get mode()` devuelve `modeOf(current)` sobre una variable de CLOSURE que Vue
         * no puede ver. Resultado: el valor se cacheaba en el primer render y no se invalidaba jamás.
         *
         * Medido en navegador: en el paso 5, `machine.mode` decía `cart` y `machine.identifying`
         * decía `true`, mientras el store seguía publicando `catalog` y `false`.
         *
         * `state.step` sí es estado reactivo de Pinia —lo copian `boot()`, `go()` y `enter()`—, y
         * `modeOf`/`isIdentifying` son las MISMAS funciones puras que usa la máquina, así que no hay
         * segunda fuente de verdad: solo se lee el dato por el lado que Vue puede observar.
         */
        mode: (state) => modeOf(state.step),

        /** `true` en el paso de identificación: bloquea los botones de login de FUERA del cajón. */
        identifying: (state) => isIdentifying(state.step),

        /**
         * `true` cuando el embudo está en la pantalla de compra CONFIRMADA (F4 · T3b).
         *
         * ⚠️ Existe para que la RAÍZ del cajón no tenga que conocer los pasos del embudo —lo prohíbe `CE-6` y
         * lo vigila `SidebarComponentBudgetTest`—: ella publica el hecho hacia fuera (`jw:cajon:purchased`) y
         * el vocabulario de los pasos se queda aquí, que es donde ya vive. Se deriva de `state.step` por el
         * mismo motivo que sus dos hermanas: es lo único que Vue puede observar.
         */
        confirmed: (state) => state.step === STEPS.CONFIRMED,
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
