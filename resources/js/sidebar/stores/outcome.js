import { defineStore } from 'pinia';
import { declinedReasonText, loadConfirmation } from '../outcome.js';

/**
 * El estado del DESENLACE del pago — pasos 6, 9, 10 y 11 (reorganización del SPA, 2026-08-22).
 *
 * ⚠️ **Aquí no se decide si un pago valió.** Eso lo dice `GET orders/{code}/payment-status` con sus
 * dos ejes, y traducirlo es de `outcome.js`. Este store guarda el código del pedido en juego, el
 * formulario firmado que devuelve la pasarela, el resumen, el motivo del rechazo y los dos
 * indicadores de «hay algo en vuelo».
 *
 * ⚠️⚠️ **El SONDEO no vive aquí, y es deliberado**: su temporizador se para al desmontar el motor y su
 * desenlace MUEVE el paso. Las dos cosas son del embudo. El store guarda lo que el sondeo averigua.
 */
export const useOutcomeStore = defineStore('outcome', {
    state: () => ({
        /** El formulario firmado que devuelve `POST /orders`. Mientras sea `null`, el paso 9 no pinta. */
        gateway: null,

        /**
         * El código del pedido en juego.
         *
         * Nace del montaje —lo que dejó la vuelta de la pasarela— y lo reescribe la creación del
         * pedido. Las dos fuentes no compiten: cuando hay desenlace no hay compra en curso.
         */
        orderCode: '',

        /** `true` mientras el pedido se crea: impide el doble clic en el botón más caro del cajón. */
        confirming: false,

        /**
         * El resumen del pedido pagado, o `null`.
         *
         * ⚠️ **`null` es un estado LEGÍTIMO, no un fallo que gritar**: la pantalla se pinta igual sin
         * resumen —con su código y el aviso del correo—, y ese es el caso de quien perdió la sesión
         * entre la ida a la pasarela y la vuelta. Enseñarle «ha fallado algo» a quien acaba de pagar
         * sería mucho peor.
         */
        confirmation: null,

        /**
         * El motivo del rechazo YA traducido que pinta el paso 10, o cadena vacía.
         *
         * ⚠️ Vacío significa «no se pudo preguntar», no «no hay motivo»: el servidor cae a `default`
         * cuando no conoce el código, así que con respuesta el bloque se pinta siempre.
         */
        declinedReason: '',

        /** `true` mientras se reintenta el cobro. */
        retrying: false,

        /**
         * El bloque de «registro del parque» que publica `GET /config`, o `null`.
         *
         * Lo pinta SOLO el paso 6. Se guarda del `/config` del montaje en vez de pedirlo aparte: ya
         * viaja en esa respuesta, y una petición más en la pantalla del desenlace sería regalar espera
         * justo donde el cliente ya ha pagado.
         */
        registration: null,
    }),

    getters: {
        /** ¿Hay un pedido del que hablar? Sin código no se pregunta nada por él. */
        hasOrder: (state) => state.orderCode !== '',
    },

    actions: {
        setOrderCode(code) {
            this.orderCode = code ?? '';
        },

        setGateway(form) {
            this.gateway = form ?? null;
        },

        setRegistration(block) {
            this.registration = block ?? null;
        },

        setDeclinedReason(text) {
            this.declinedReason = text ?? '';
        },

        /** Pide el resumen del pedido pagado. `null` si no se pudo, que es un estado legítimo. */
        async loadConfirmation({ api }) {
            this.confirmation = await loadConfirmation({ orderCode: this.orderCode, api });

            return this.confirmation;
        },

        /**
         * Traduce el motivo del rechazo a partir de la respuesta del estado de pago.
         *
         * ⚠️ **`null` y «hay respuesta» no son lo mismo, y la diferencia es visible**: sin respuesta no
         * se pinta el bloque —espejo del `null` de Livewire cuando no hay sesión o el pedido no es de
         * quien pregunta—; CON respuesta se pinta siempre, porque el servidor cae a `default` cuando no
         * conoce el código. La regla vive aquí y no en quien llama, que es donde se olvidaría.
         */
        applyDeclinedReason(messages, status) {
            this.declinedReason = status === null ? '' : declinedReasonText(messages, status.declined_reason);

            return this.declinedReason;
        },

        /**
         * Barre el contexto del pedido anterior.
         *
         * ⚠️ **No se ve en el paso 1, y por eso hay que hacerlo igual**: dejar latentes el código, el
         * formulario firmado y el resumen es lo que hace que una segunda compra arrastre el desenlace
         * de la primera. Espejo del barrido de `Purchase::addAnother()`.
         */
        clear() {
            this.gateway = null;
            this.orderCode = '';
            this.confirmation = null;
            this.declinedReason = '';
            this.confirming = false;
            this.retrying = false;
        },
    },
});
