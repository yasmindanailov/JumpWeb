<script setup>
/**
 * El banner de la isla (`ReasonBanner` del diseño, Z6b): la razón, lo vivo, la espera o lo hecho, en el sitio de la
 * acción. Pinta (`CE-6`): su lógica y sus estilos, en `banner-razon.js`. Se toca entero (`pulsar`); la razón se abre en la
 * sexta vista de la isla (`HojaRazon`).
 */
import CargaRebote from '../ui/CargaRebote.vue';
import IconoLucide from '../ui/IconoLucide.vue';
import { PROPS_BANNER, useBannerRazon } from './banner-razon.js';

const props = defineProps(PROPS_BANNER);
const emit = defineEmits(['pulsar']);
const { hover, press, tipo, estilo, caja, punto, hecho, titulo, matiz } = useBannerRazon(props);
</script>

<template>
    <button
        type="button"
        data-isla-razon=""
        :data-tipo="tipo"
        :tabindex="quieto ? -1 : undefined"
        :style="estilo"
        @click="(e) => !quieto && emit('pulsar', e)"
        @mouseenter="hover = true"
        @mouseleave="hover = false; press = false"
        @pointerdown="press = true"
        @pointerup="press = false"
        @pointercancel="press = false"
    >
        <span :style="caja">
            <CargaRebote
                v-if="tipo === 'espera'"
                size="sm"
                :label="bn.text"
            />
            <span
                v-else-if="tipo === 'vivo'"
                :style="punto"
            />
            <span
                v-else-if="tipo === 'hecho'"
                :style="hecho"
            ><IconoLucide
                name="check"
                :size="top ? 15 : 13"
            /></span>
            <span
                v-else-if="bn.svg"
                aria-hidden="true"
                :style="{ display: 'inline-flex', width: '18px', height: '18px', lineHeight: 0 }"
                v-html="bn.svg"
            />
            <IconoLucide
                v-else
                :name="bn.icon || 'sparkles'"
                :size="18"
            />
        </span>
        <span :style="{ flex: 1, minWidth: 0, display: 'grid', gap: '1px' }">
            <b :style="titulo">{{ bn.text }}</b>
            <small
                v-if="bn.sub"
                :style="matiz"
            >{{ bn.sub }}</small>
        </span>
        <span
            v-if="top && tipo === 'razon'"
            :style="{ opacity: 0.6, display: 'inline-flex' }"
        ><IconoLucide
            name="chevron-right"
            :size="16"
        /></span>
    </button>
</template>
