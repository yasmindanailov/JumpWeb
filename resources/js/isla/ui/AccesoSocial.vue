<script setup>
/**
 * Entrar o crear la cuenta con Google o con Apple, del sistema de diseño (`SocialSignIn.jsx`): el único camino
 * sin contraseña, y va ANTES de los campos (después ya no ahorra nada). Dentro del navegador de Instagram,
 * Facebook o TikTok no pinta Google, porque ahí solo lleva a un error (`enNavegadorDeApp()`). Los botones son la
 * secundaria del sistema. ⚠️ Apple aún no existe en el producto (v2.0.0, `#683`): quien monta esta pieza decide
 * si lo enseña (`apple`).
 *
 * `marca` (T3e·4, `#695`, `[DECIDIDO owner]`): la «G» de Google a la izquierda de su botón, la que exigen sus normas
 * de marca, en la forma del sistema. Sin ella, el botón del diseño tal cual (el banco). Si no queda ningún botón,
 * la pieza no pinta nada: una caja vacía sumaría el hueco de la rejilla del paso.
 *
 * `separador` (el owner, 30-09, `#857`): debajo, «— o —» antes de los campos, para que nadie tome el botón de Google por
 * el que envía el correo que escribe debajo. Va DENTRO de la pieza porque solo existe si queda algún botón (en el
 * navegador de Instagram, sin Google y sin Apple, no hay nada que separar). El sistema no dibuja uno: dos filetes del
 * borde suave (`--border-subtle`) y la «o» con la letra de la pista (13px, apagada); su margen deja el mismo aire arriba
 * que la rejilla del paso deja abajo (24).
 */
import { computed } from 'vue';
import { enNavegadorDeApp } from './piezas.js';
import { useTextos } from '../piezas/textos.js';
import BotonSistema from './BotonSistema.vue';

const props = defineProps({
    mode: { type: String, default: 'continue' },
    inApp: { type: Boolean, default: null },
    labels: { type: Object, default: null },
    apple: { type: Boolean, default: true },
    marca: { type: String, default: '' },
    separador: { type: Boolean, default: false },
});
const emit = defineEmits(['google', 'apple']);
const { t } = useTextos();

const sinGoogle = computed(() => (props.inApp == null ? enNavegadorDeApp(typeof navigator !== 'undefined' ? navigator.userAgent : '') : props.inApp));
const textos = computed(() => props.labels || (props.mode === 'signin'
    ? { google: t('pieza.entrar_google'), apple: t('pieza.entrar_apple') }
    : { google: t('pieza.continuar_google'), apple: t('pieza.continuar_apple') }));
</script>

<template>
    <div
        v-if="!sinGoogle || apple"
        :style="{ display: 'grid', gap: '8px' }"
    >
        <BotonSistema
            v-if="!sinGoogle"
            variant="quiet"
            full
            @click="emit('google')"
        >
            <template
                v-if="marca"
                #icono-izquierda
            ><img
                :src="marca"
                alt=""
                width="18"
                height="18"
            ></template>{{ textos.google }}
        </BotonSistema>
        <BotonSistema
            v-if="apple"
            variant="quiet"
            full
            @click="emit('apple')"
        >
            {{ textos.apple }}
        </BotonSistema>
        <div
            v-if="separador"
            :style="{ display: 'grid', gridTemplateColumns: 'minmax(0,1fr) auto minmax(0,1fr)', alignItems: 'center', gap: '12px', marginTop: '16px' }"
        >
            <span
                aria-hidden="true"
                :style="{ height: '1px', background: 'var(--border-subtle)' }"
            />
            <span :style="{ fontFamily: 'var(--font-ui)', fontSize: 'var(--fs-caption)', lineHeight: 1.5, color: 'var(--text-muted)' }">{{ t('pieza.o') }}</span>
            <span
                aria-hidden="true"
                :style="{ height: '1px', background: 'var(--border-subtle)' }"
            />
        </div>
    </div>
</template>
