<script setup>
import { nextTick, ref } from 'vue';

/**
 * **LA PREGUNTA QUE SE HACE DENTRO DEL CAJÓN** (`#565`, grieta 09 de la parada 05).
 *
 * Nació en «renovar mi QR» (`#217`) y hasta esta tanda era la ÚNICA: quedaban dos `window.confirm`
 * vivos —quitar un menor a cargo y **borrar la cuenta**, la única acción irreversible del producto—.
 * Eso tenía tres problemas medidos en la prueba del owner en staging: el diálogo lo pinta el
 * NAVEGADOR —fuera del cajón, con la tipografía del sistema y un «Aceptar/Cancelar» que no habla
 * nuestros tres idiomas—, el owner **no llegó a verlo**, y el de menores enseña además **el nombre de
 * un menor** en un diálogo del sistema operativo.
 *
 * ⚠️⚠️ **Lo que hay aquí es el GESTO ENTERO, no solo la pregunta**, y ésa es la diferencia entre una
 * pieza y tres copias: el disparador **desaparece** mientras se pregunta —dejarlo permitiría pulsarlo
 * otra vez sobre la pregunta abierta—, el foco **salta** al botón que confirma —quien navega con
 * teclado tendría que tabular a ciegas hasta encontrarla— y **vuelve** al disparador al cerrar, que
 * si no lo hace deja el foco en el `<body>` y obliga a tabular el cajón entero.
 * ▶ *Las tres se pierden al copiar y ninguna falla: la pregunta se pinta igual.* La primera versión
 * de esta pieza dejaba fuera el disparador y las tres zonas volvían a escribirlas — y una de ellas se
 * pasó de su techo de líneas, que es lo que lo delató.
 *
 * ⚠️ **El disparador llega por SLOT porque los tres son distintos**: un botón suelto, un botón dentro
 * de la tarjeta de un menor y **el formulario entero** de borrar la cuenta —que se envía con su botón
 * y también con la tecla Intro desde el campo de la contraseña, y las dos vías tienen que preguntar—.
 * El slot recibe `ask`, así que cada zona decide con qué gesto se pregunta sin que esta pieza sepa
 * nada de ellos. ▶ Y por eso `ask` NO se expone con `defineExpose`: con el slot no hace falta, y un
 * método expuesto sin consumidor es una puerta abierta a que alguien abra la pregunta sin pasar por
 * el gesto que la esconde.
 *
 * ⚠️⚠️ **El foco vuelve a lo que lo TENÍA, no a una `ref`**: se guarda `document.activeElement` al
 * abrir. Con una referencia habría que pasarla desde fuera, y en el caso del formulario el disparador
 * es un `submit` que puede activarse con la tecla Intro desde otro campo — ahí no hay botón al que
 * volver, hay un campo.
 *
 * ⚠️ `role="group"` + `aria-labelledby`, **no `alertdialog`**: no hay trampa de foco propia —la del
 * panel del cajón ya envuelve todo esto— y anunciar un diálogo que no lo es deja al lector de
 * pantalla esperando un cierre que nadie va a emitir.
 */
defineProps({
    /** El identificador del párrafo de la pregunta, para el `aria-labelledby`. Único por pantalla. */
    id: { type: String, required: true },

    /** La pregunta, ya traducida y con sus datos dentro (el nombre de un menor, por ejemplo). */
    question: { type: String, default: '' },

    /** Rótulo del botón que CONFIRMA. Lleva el verbo, no un «Sí» a secas. */
    confirmLabel: { type: String, default: '' },

    /** Rótulo del que cancela. */
    cancelLabel: { type: String, default: '' },

    /** Mientras el servidor responde: desactiva los dos y alterna el rótulo de confirmar. */
    busy: { type: Boolean, default: false },

    /** Rótulo mientras se espera. Vacío = se queda el de confirmar. */
    busyLabel: { type: String, default: '' },

    /**
     * ¿Lo que se confirma es DESTRUCTIVO?
     *
     * ⚠️ Con `danger`, el botón que confirma lleva el relleno de ERROR y no el de tinta. Es la única
     * diferencia entre las tres preguntas, y es la que importa: renovar un QR se deshace comprando
     * otra vez; borrar una cuenta, no.
     */
    danger: { type: Boolean, default: false },
});

const emit = defineEmits(['confirm']);

const asking = ref(false);
const confirmBtn = ref(null);
/** Lo que tenía el foco al abrir. Es a donde vuelve al cerrar. */
let volverA = null;

function ask() {
    volverA = typeof document === 'undefined' ? null : document.activeElement;
    asking.value = true;
    // El foco viaja al botón que CONFIRMA, no al que cancela: es donde está la pregunta. Cancelar
    // sigue a un `Tab`. Con `nextTick` porque el botón no existe hasta que Vue repinta.
    nextTick(() => confirmBtn.value?.focus());
}

function close() {
    asking.value = false;
    nextTick(() => volverA?.focus?.());
}

function confirm() {
    close();
    emit('confirm');
}

</script>

<template>
    <!-- El disparador, que cada zona pone por su cuenta. Desaparece mientras se pregunta. -->
    <slot v-if="! asking" name="trigger" :ask="ask" />

    <div v-if="asking" class="purchase__confirm" role="group" :aria-labelledby="id">
        <p :id="id">{{ question }}</p>

        <div class="acc-actions">
            <button ref="confirmBtn" type="button" :class="danger ? 'btn account__delete-btn' : 'btn btn--ink'"
                    :disabled="busy" @click="confirm">
                {{ busy && busyLabel ? busyLabel : confirmLabel }}
            </button>
            <button type="button" class="btn btn--ghost" :disabled="busy" @click="close">
                {{ cancelLabel }}
            </button>
        </div>
    </div>
</template>
