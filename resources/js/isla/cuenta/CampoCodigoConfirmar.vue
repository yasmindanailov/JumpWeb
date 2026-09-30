<script setup>
/**
 * **Confirmar con un código al correo**, el campo de los pasos sensibles de Ajustes (A3b de `specs/acceso-con-codigo.md`
 * §4.10, `#857`; sustituye a «Contraseña actual»): cerrar las otras sesiones, desvincular Google, cambiar el correo y
 * borrar la cuenta. Con las piezas de la isla, las mismas que el código de «Entra».
 *
 * Antes de pedirlo, solo lo que va a pasar («te enviaremos un código a …»): la acción del paso lo pide y SOLO entonces
 * sale el campo —pedirlo al entrar mandaría un correo a quien solo mira—. Pedido, el campo con su pista y «Pedir otro
 * código». `correo`, a dónde va: el de la cuenta, o el NUEVO al confirmarlo.
 */
import { useTextos } from '../piezas/textos.js';
import { CUENTA } from './estilos.js';
import CampoSistema from '../ui/CampoSistema.vue';
import EnlaceSistema from '../ui/EnlaceSistema.vue';

defineProps({
    id: { type: String, required: true },
    modelValue: { type: String, default: '' },
    error: { type: String, default: '' },
    enviado: { type: Boolean, default: false },
    reenvios: { type: Number, default: 0 },
    correo: { type: String, default: '' },
    ocupado: { type: Boolean, default: false },
});
const emit = defineEmits(['update:modelValue', 'otro']);
const { t, tp } = useTextos();
</script>

<template>
    <div
        v-if="enviado"
        :style="{ display: 'grid', gap: '4px' }"
    >
        <CampoSistema
            :id="id"
            :label="t('compra.datos.codigo')"
            inputmode="numeric"
            autocomplete="one-time-code"
            maxlength="7"
            :hint="tp(reenvios ? 'compra.datos.codigo_otro_enviado' : 'compra.datos.codigo_enviado', { correo })"
            :model-value="modelValue"
            :error="error"
            @update:model-value="(v) => emit('update:modelValue', v)"
        />
        <EnlaceSistema
            :style="{ justifySelf: 'start' }"
            :disabled="ocupado"
            @click="emit('otro')"
        >{{ t('compra.datos.otro_codigo') }}</EnlaceSistema>
    </div>
    <p
        v-else
        :style="CUENTA.cuerpo"
    >{{ tp('mi_cuenta.codigo.para', { correo }) }}</p>
</template>
