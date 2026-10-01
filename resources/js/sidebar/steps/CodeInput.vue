<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { CODE_LENGTH, codeDigits, isNewlyComplete } from '../code-input.js';

/**
 * **EL CÓDIGO DE UN SOLO USO, el `CodeInput` del diseño** (zip (6) del 30-09, `components/forms/CodeInput`; A4a de
 * `docs/specs/acceso-con-codigo.md` §4.11, `#861`): seis casillas que se VEN y un solo campo de verdad encima, invisible,
 * para que el móvil lo rellene solo (`one-time-code`), pegar «482 913» funcione de un toque y borrar sea borrar. Nunca seis
 * campos sueltos. Pinta y avisa: qué se queda de lo escrito y cuándo está completo lo dice `code-input.js`.
 *
 * ⚠️ Con la sexta cifra avisa (`complete`), una vez por código. Si quien lo usa lo VACÍA (tras un «no»), vuelve a avisar.
 * ⚠️ Sin `maxlength`: el navegador cortaría lo pegado ANTES de quitar el guion, y «482-913» se quedaría en cinco cifras.
 * ⚠️ El campo va a 16 px aunque no se vea: por debajo, iOS amplía la página al enfocarlo.
 * «Pedir otro código» vive DENTRO, para que no haya dos maneras de pedir otro; siempre a mano, como en la isla (`#812`): si
 * es pronto, el servidor lo dice y su espera sale bajo las casillas, como cualquier «no».
 */
const props = defineProps({
    id: { type: String, required: true },
    label: { type: String, default: '' },
    /** La pista bajo las casillas (quién lo manda, cuánto dura); un «no» la sustituye. */
    hint: { type: String, default: '' },
    error: { type: String, default: '' },
    disabled: { type: Boolean, default: false },
    /** El rótulo de «Pedir otro código»; sin él, no hay fila. */
    resendLabel: { type: String, default: '' },
});

const emit = defineEmits(['complete', 'resend']);
const code = defineModel({ type: String, default: '' });

const field = ref(null);
const focused = ref(false);
let announced = '';

const digits = computed(() => codeDigits(code.value));
const active = computed(() => (focused.value && ! props.disabled ? Math.min(digits.value.length, CODE_LENGTH - 1) : -1));
const message = computed(() => (props.error || props.hint ? `${props.id}-m` : undefined));

watch(digits, (value) => { if (value.length < CODE_LENGTH) announced = ''; });
onMounted(() => field.value?.focus({ preventScroll: true }));

function write(event) {
    const value = codeDigits(event.target.value);

    event.target.value = value;
    code.value = value;
    if (isNewlyComplete(value, announced)) { announced = value; emit('complete', value); }
}
</script>

<template>
    <div class="code-input" :class="{ 'has-error': error, 'is-disabled': disabled }">
        <label v-if="label" class="form__label" :for="id">{{ label }}</label>
        <div class="code-input__boxes">
            <template v-for="i in 6" :key="i">
                <!-- El guion entre los dos grupos (482 – 913), como en el correo: solo se ve, no se escribe. -->
                <span v-if="i === 4" class="code-input__dash" aria-hidden="true"></span>
                <span class="code-input__box" :class="{ 'is-active': active === i - 1, 'is-filled': digits[i - 1] }" aria-hidden="true">{{ digits[i - 1] }}</span>
            </template>
            <input :id="id" ref="field" class="code-input__field" :value="digits" type="text" inputmode="numeric" pattern="[0-9]*"
                   autocomplete="one-time-code" :disabled="disabled" :aria-invalid="error ? 'true' : undefined"
                   :aria-describedby="message" @input="write" @focus="focused = true" @blur="focused = false">
        </div>
        <span v-if="error" :id="message" class="form__error" role="alert">{{ error }}</span>
        <span v-else-if="hint" :id="message" class="form__hint">{{ hint }}</span>
        <p v-if="resendLabel" class="auth__switch">
            <button type="button" :disabled="disabled" @click="emit('resend')">{{ resendLabel }}</button>
        </p>
    </div>
</template>
