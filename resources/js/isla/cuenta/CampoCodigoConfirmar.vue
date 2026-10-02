<script setup>
/**
 * **Confirmar con un código al correo**, el campo de los pasos sensibles de Ajustes (A3b de `specs/acceso-con-codigo.md`
 * §4.10, `#857`; sustituye a «Contraseña actual»): cerrar las otras sesiones, desvincular Google, cambiar el correo y
 * borrar la cuenta. Con las seis casillas del código de «Entra» (`CampoCodigo`, Z6g·1): con la sexta avisa (`completo`) y
 * quien lo pinta hace la acción del paso —salvo borrar, que va con su casilla y su botón (`#813`)—.
 *
 * Antes de pedirlo, solo lo que va a pasar («te enviaremos un código a …»): la acción del paso lo pide y SOLO entonces
 * sale el campo —pedirlo al entrar mandaría un correo a quien solo mira—. Pedido, las casillas con su pista y «Pedir otro
 * código». `correo`, a dónde va: el de la cuenta, o el NUEVO al confirmarlo.
 */
import { useTextos } from '../piezas/textos.js';
import { CUENTA } from './estilos.js';
import CampoCodigo from '../ui/CampoCodigo.vue';

defineProps({
    id: { type: String, required: true },
    modelValue: { type: String, default: '' },
    error: { type: String, default: '' },
    enviado: { type: Boolean, default: false },
    reenvios: { type: Number, default: 0 },
    correo: { type: String, default: '' },
    ocupado: { type: Boolean, default: false },
});
const emit = defineEmits(['update:modelValue', 'otro', 'completo']);
const { t, tp } = useTextos();
</script>

<template>
    <CampoCodigo
        v-if="enviado"
        :id="id"
        :label="t('compra.datos.codigo')"
        :hint="tp(reenvios ? 'compra.datos.codigo_otro_enviado' : 'compra.datos.codigo_enviado', { correo })"
        :model-value="modelValue"
        :error="error"
        :disabled="ocupado"
        :otro="t('compra.datos.otro_codigo')"
        :ocupado="ocupado"
        @update:model-value="(v) => emit('update:modelValue', v)"
        @completo="emit('completo')"
        @otro="emit('otro')"
    />
    <p
        v-else
        :style="CUENTA.cuerpo"
    >{{ tp('mi_cuenta.codigo.para', { correo }) }}</p>
</template>
