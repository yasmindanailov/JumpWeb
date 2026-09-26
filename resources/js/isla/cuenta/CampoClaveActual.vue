<script setup>
/**
 * «Contraseña actual», el campo que piden cinco pasos de Ajustes (T5e, `#778`): cambiarla, cambiar el correo, cerrar las
 * otras sesiones, desvincular Google y borrar la cuenta (lo exige el servidor; el mockup solo lo dibuja en la primera).
 *
 * ⚠️ **Con su salida debajo, siempre**: el servidor no distingue una cuenta nacida con Google —sin contraseña propia— de
 * una que la tiene (`DEUDA.md`), así que la pista se enseña a todos, como el cajón (`NoPasswordHint`, owner 02-09):
 * para quien la olvidó sigue siendo cierta, y para quien no la tiene es la única puerta. El enlace va al correo de la
 * cuenta (`POST /auth/password/forgot`).
 */
import { useTextos } from '../piezas/textos.js';
import CampoSistema from '../ui/CampoSistema.vue';
import EnlaceSistema from '../ui/EnlaceSistema.vue';

defineProps({
    id: { type: String, required: true },
    modelValue: { type: String, default: '' },
    error: { type: String, default: '' },
});
const emit = defineEmits(['update:modelValue', 'enlace']);
const { t } = useTextos();
</script>

<template>
    <div :style="{ display: 'grid', gap: '6px' }">
        <CampoSistema
            :id="id"
            :label="t('mi_cuenta.clave.actual')"
            type="password"
            autocomplete="current-password"
            :model-value="modelValue"
            :error="error"
            @update:model-value="(v) => emit('update:modelValue', v)"
        />
        <p :style="{ margin: 0, fontFamily: 'var(--font-ui)', fontSize: 'var(--fs-caption)', lineHeight: 1.5, color: 'var(--text-muted)' }">
            {{ t('mi_cuenta.clave.sin_clave') }}
            <EnlaceSistema
                size="sm"
                @click="emit('enlace')"
            >{{ t('mi_cuenta.clave.enlace') }}</EnlaceSistema>
        </p>
    </div>
</template>
