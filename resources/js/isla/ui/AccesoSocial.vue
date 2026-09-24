<script setup>
/**
 * Entrar o crear la cuenta con Google o con Apple, del sistema de diseño (`SocialSignIn.jsx`): el único camino
 * sin contraseña, y va ANTES de los campos (después ya no ahorra nada). Dentro del navegador de Instagram,
 * Facebook o TikTok no pinta Google, porque ahí solo lleva a un error (`enNavegadorDeApp()`). Los botones son la
 * secundaria del sistema. ⚠️ Apple aún no existe en el producto (v2.0.0, `#683`): quien monta esta pieza decide
 * si lo enseña.
 */
import { computed } from 'vue';
import { enNavegadorDeApp } from './piezas.js';
import { useTextos } from '../piezas/textos.js';
import BotonSistema from './BotonSistema.vue';

const props = defineProps({
    mode: { type: String, default: 'continue' },
    inApp: { type: Boolean, default: null },
    labels: { type: Object, default: null },
});
const emit = defineEmits(['google', 'apple']);
const { t } = useTextos();

const sinGoogle = computed(() => (props.inApp == null ? enNavegadorDeApp(typeof navigator !== 'undefined' ? navigator.userAgent : '') : props.inApp));
const textos = computed(() => props.labels || (props.mode === 'signin'
    ? { google: t('pieza.entrar_google'), apple: t('pieza.entrar_apple') }
    : { google: t('pieza.continuar_google'), apple: t('pieza.continuar_apple') }));
</script>

<template>
    <div :style="{ display: 'grid', gap: '8px' }">
        <BotonSistema
            v-if="!sinGoogle"
            variant="quiet"
            full
            @click="emit('google')"
        >
            {{ textos.google }}
        </BotonSistema>
        <BotonSistema
            variant="quiet"
            full
            @click="emit('apple')"
        >
            {{ textos.apple }}
        </BotonSistema>
    </div>
</template>
