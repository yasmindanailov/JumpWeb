<script setup>
import { computed, defineAsyncComponent } from 'vue';
import { t as translate, tp as translateWith } from '../i18n.js';
import { useProfileStore } from '../stores/profile.js';

// El `CodeInput` baja con la cara que lo pinta y no con el motor: es el MISMO trozo que el de la puerta.
const CodeInput = defineAsyncComponent(() => import('../steps/CodeInput.vue'));

/**
 * **CONFIRMAR CON UN CÓDIGO AL CORREO** lo sensible de Mi cuenta (A4b de `docs/specs/acceso-con-codigo.md` §4.11, `#813`;
 * sustituye a «Contraseña actual»): cerrar las otras sesiones, desvincular Google, cambiar el correo —y el código del
 * correo NUEVO— y borrar la cuenta. La pieza de la isla es `isla/cuenta/CampoCodigoConfirmar.vue`; ésta lleva las seis
 * casillas de la puerta (`#861`), para que en el cajón no haya dos maneras de escribir un código.
 *
 * Antes de pedirlo, solo a dónde irá («Para confirmarlo, te enviaremos un código a …»): lo pide la acción, y pedirlo al
 * entrar mandaría un correo a quien solo mira. Pedido, el `CodeInput` con quién lo mandó («otro» al repetir), su «no» y
 * «Pedir otro código». **Pinta y avisa**: el estado y las peticiones son de `stores/confirm.js`.
 */
const props = defineProps({
    id: { type: String, required: true },
    /** El grupo `account`: `confirm.for` y los del código de la puerta (`login.*`). */
    account: { type: Object, default: () => ({}) },
    /** A dónde va (o fue) el código. Sin él, el de la cuenta (el perfil, que pide la zona); el NUEVO, al confirmarlo. */
    email: { type: String, default: '' },
    /** ¿Se pidió? Sin pedir, la frase; pedido, las casillas. */
    shown: { type: Boolean, default: false },
    /** Cuántos se pidieron OTRA vez: con uno o más, la pista dice «otro». */
    resends: { type: Number, default: 0 },
    error: { type: String, default: '' },
    disabled: { type: Boolean, default: false },
});

defineEmits(['complete', 'resend']);

const code = defineModel({ type: String, default: '' });

const profile = useProfileStore();
const to = computed(() => props.email || profile.user?.email || '');
const a = (key) => translate(props.account, key);
const sent = () => translateWith(props.account, props.resends ? 'login.code_resent' : 'login.code_sent', { email: to.value });
</script>

<template>
    <CodeInput v-if="shown" :id="id" v-model="code" :label="a('login.code')" :hint="sent()" :error="error"
               :disabled="disabled" :resend-label="a('login.code_again')"
               @complete="$emit('complete')" @resend="$emit('resend')" />
    <p v-else-if="to" class="form__hint">{{ translateWith(account, 'account.confirm.for', { email: to }) }}</p>
</template>
