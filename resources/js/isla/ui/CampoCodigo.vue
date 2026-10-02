<script setup>
/**
 * **EL CÓDIGO DE UN SOLO USO en la isla**, el `CodeInput` del sistema de diseño (zip (6), `components/forms/CodeInput`; la
 * Z6g·1 de `specs/isla-y-landing-nueva.md` §4.27, `#867`): seis casillas que se VEN y un solo campo de verdad encima,
 * invisible, para que el móvil lo rellene solo (`one-time-code`), pegar «482-913» funcione de un toque y borrar sea borrar.
 * Nunca seis campos sueltos.
 *
 * Con la sexta cifra avisa (`completo`), UNA vez por código: quien lo usa hace lo que haría su botón. Qué se queda de lo
 * escrito y cuándo está completo es la regla del cajón (`sidebar/code-input.js`): la misma en las dos carcasas. Si quien
 * lo usa lo vacía (tras un «no»), vuelve a avisar. Como el cajón (`#811`, `#812`): «Pedir otro código» dentro (`otro`),
 * siempre a mano; la espera y los «no» los dice el servidor, en la línea de error.
 *
 * ⚠️ Sin `maxlength`: el navegador cortaría lo pegado ANTES de quitar el guion, y «482-913» se quedaría en cinco cifras.
 * ⚠️ El campo va a 16 px aunque no se vea: por debajo, iOS amplía la página al enfocarlo.
 * `data-isla-foco`: al llegar a su paso, la isla enfoca el campo y no el titular (el diseño, «El foco, donde se escribe»).
 */
import { computed, nextTick, ref, watch } from 'vue';
import { CODE_LENGTH, codeDigits, isNewlyComplete } from '../../sidebar/code-input.js';
import { estiloCasilla } from './codigo.js';
import EnlaceSistema from './EnlaceSistema.vue';

const props = defineProps({
    id: { type: String, required: true },
    label: { type: String, default: '' },
    /** Bajo las casillas: quién lo manda, dónde mirar. Un «no» la sustituye. */
    hint: { type: String, default: '' },
    error: { type: String, default: '' },
    disabled: { type: Boolean, default: false },
    modelValue: { type: String, default: '' },
    /** El rótulo de «Pedir otro código»; sin él, no hay enlace. */
    otro: { type: String, default: '' },
    /** Mientras se pide otro (o se comprueba), el enlace espera. */
    ocupado: { type: Boolean, default: false },
});
const emit = defineEmits(['update:modelValue', 'completo', 'otro']);

const campo = ref(null);
const foco = ref(false);
let avisado = '';

const cifras = computed(() => codeDigits(props.modelValue));
const activa = computed(() => (foco.value && ! props.disabled ? Math.min(cifras.value.length, CODE_LENGTH - 1) : -1));
const mensaje = computed(() => (props.error || props.hint ? `${props.id}-m` : undefined));

watch(cifras, (valor) => { if (valor.length < CODE_LENGTH) avisado = ''; });
// Tras comprobarlo, con un «no» (y las casillas vacías), el foco vuelve aquí para escribir el bueno. Lo hace el campo y no
// quien lo pinta: ése enfoca su error mientras las casillas aún esperan apagadas, y un campo apagado no toma el foco.
watch(() => props.disabled, (apagado, antes) => {
    if (antes && ! apagado && props.error) nextTick(() => campo.value?.focus({ preventScroll: true }));
});

function escribir(evento) {
    const valor = codeDigits(evento.target.value);

    evento.target.value = valor;
    emit('update:modelValue', valor);
    if (isNewlyComplete(valor, avisado)) {
        avisado = valor;
        emit('completo', valor);
    }
}

/** El cursor, al final: se sigue escribiendo donde se quedó (el diseño). */
function alEnfocar(evento) {
    foco.value = true;
    const n = evento.target.value.length;

    try { evento.target.setSelectionRange(n, n); } catch { /* sin selección en este navegador */ }
}

const casilla = (i) => estiloCasilla({ activa: i === activa.value, llena: Boolean(cifras.value[i]), error: Boolean(props.error) });
</script>

<template>
    <div :style="{ display: 'flex', flexDirection: 'column', gap: '7px', minWidth: 0 }">
        <label
            v-if="label"
            :for="id"
            :style="{ font: 'var(--type-label)', color: 'var(--text-strong)' }"
        >{{ label }}</label>
        <div :style="{ position: 'relative', display: 'flex', gap: '8px', maxWidth: '400px', opacity: disabled ? 0.42 : 1, transition: 'var(--t-hover)' }">
            <template
                v-for="i in CODE_LENGTH"
                :key="i"
            >
                <!-- El guion entre los dos grupos (482 – 913), como en el correo: solo se ve, no se escribe. -->
                <span
                    v-if="i === CODE_LENGTH / 2 + 1"
                    aria-hidden="true"
                    :style="{ flex: '0 0 auto', alignSelf: 'center', width: '10px', height: '2px', borderRadius: '1px', background: 'var(--text-muted)' }"
                />
                <span
                    aria-hidden="true"
                    :style="casilla(i - 1)"
                >{{ cifras[i - 1] ?? '' }}<span
                    v-if="! cifras[i - 1] && activa === i - 1"
                    :style="{ width: '2px', height: '24px', borderRadius: '1px', background: 'var(--control-fg)', animation: 'isla-caret 1s steps(1) infinite' }"
                /></span>
            </template>
            <input
                :id="id"
                ref="campo"
                :value="cifras"
                type="text"
                inputmode="numeric"
                pattern="[0-9]*"
                autocomplete="one-time-code"
                data-isla-foco
                :disabled="disabled"
                :aria-invalid="error ? 'true' : undefined"
                :aria-describedby="mensaje"
                :style="{ position: 'absolute', inset: 0, width: '100%', height: '100%', margin: 0, padding: 0, border: 'none', outline: 'none', boxShadow: 'none', opacity: 0, background: 'transparent', color: 'transparent', caretColor: 'transparent', fontSize: '16px', cursor: disabled ? 'not-allowed' : 'text' }"
                @input="escribir"
                @focus="alEnfocar"
                @blur="foco = false"
            >
        </div>
        <span
            v-if="error"
            :id="mensaje"
            role="alert"
            :style="{ fontFamily: 'var(--font-ui)', fontSize: 'var(--fs-caption)', fontWeight: 'var(--fw-semibold)', lineHeight: 1.45, color: 'var(--text-danger)' }"
        >{{ error }}</span>
        <span
            v-else-if="hint"
            :id="mensaje"
            :style="{ fontFamily: 'var(--font-ui)', fontSize: 'var(--fs-caption)', lineHeight: 1.45, color: 'var(--text-muted)' }"
        >{{ hint }}</span>
        <EnlaceSistema
            v-if="otro"
            :style="{ justifySelf: 'start', alignSelf: 'flex-start' }"
            :disabled="ocupado"
            @click="emit('otro')"
        >{{ otro }}</EnlaceSistema>
    </div>
</template>
