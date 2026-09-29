<script setup>
import { t as translate } from '../i18n.js';

/**
 * **La fecha de nacimiento del TITULAR** (TP·1, `DECISIONES #792`): entera y OPCIONAL, en el alta, en la pantalla tras Google
 * y en «Tus datos». Una pieza para las tres, con un solo texto.
 *
 * ⚠️ Es el MISMO control que pide la fecha de un hijo (`DependentsZone.vue`): un `<input type="date">`, que viaja en `Y-m-d`.
 * Dos maneras de pedir una fecha en el mismo cajón serían dos cosas que aprender.
 *
 * ⚠️ La pista va atada con `aria-describedby`, como la del teléfono (`#561`): un lector de pantalla que solo leyera el
 * rótulo no sabría que es opcional. Desde el 29-09 dice solo eso, «Opcional», bajo «Tu cumpleaños», como la isla: el
 * owner quitó el «para qué» (`#792`). Qué fechas valen lo decide el SERVIDOR (`BirthDatePolicy`).
 */
const props = defineProps({
    id: { type: String, required: true },
    /** El grupo `account`: el rótulo y la pista viven en `register`, y los reutiliza «Tus datos». */
    account: { type: Object, default: () => ({}) },
    error: { type: String, default: '' },
});

const bornOn = defineModel({ type: String, default: '' });

const a = (key) => translate(props.account, key);
</script>

<template>
    <div class="form__field">
        <label class="form__label" :for="id">{{ a('register.born_on') }}</label>
        <input :id="id" v-model="bornOn" type="date" autocomplete="bday" :aria-describedby="`${id}-hint`">
        <span :id="`${id}-hint`" class="form__hint">{{ a('register.born_on_hint') }}</span>
        <span v-if="error" class="form__error">{{ error }}</span>
    </div>
</template>
