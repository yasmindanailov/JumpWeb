<script setup>
/**
 * El QR de la persona, del sistema de diseño (`QrPass.jsx`): lo que se enseña en la puerta, uno solo para toda la
 * web (Listo de la compra, Mi QR, Mi cuenta). Siempre sobre blanco —un lector no lee un QR invertido— y con el
 * código debajo, en mono, para leerlo en voz alta en el mostrador. Con `src` pinta la imagen REAL, la del carné
 * que dibuja el servidor; sin ella, el QR de muestra del diseño (`qr-muestra.js`), que no se lee. El fondo, la
 * tinta y la sombra son roles (`--isla-qr-*`).
 */
import { computed, onMounted, ref, watch } from 'vue';
import { dibujar } from './qr-muestra.js';
import { useTextos } from '../piezas/textos.js';

const props = defineProps({
    code: { type: String, default: '' },
    size: { type: String, default: 'md' },
    label: { type: String, default: '' },
    showCode: { type: Boolean, default: true },
    src: { type: String, default: '' },
});
const { t, tp } = useTextos();

const lienzo = ref(null);
const px = computed(() => (props.size === 'sm' ? 92 : props.size === 'lg' ? 216 : 152));
const nombre = computed(() => props.label || (props.code ? tp('pieza.qr_de', { codigo: props.code }) : t('pieza.qr')));
const pintar = () => {
    if (props.src) return;
    const tinta = getComputedStyle(document.documentElement).getPropertyValue('--isla-qr-tinta').trim() || '#101418';
    dibujar(lienzo.value, props.code, px.value, tinta);
};
onMounted(pintar);
watch([() => props.code, px, () => props.src], pintar, { flush: 'post' });
</script>

<template>
    <figure :style="{ margin: 0, display: 'inline-flex', flexDirection: 'column', alignItems: 'center', gap: size === 'sm' ? 0 : '10px', padding: size === 'sm' ? '8px' : '16px 16px 12px', background: 'var(--isla-qr-fondo)', borderRadius: size === 'sm' ? 'var(--r-md)' : 'var(--r-lg)', boxShadow: 'var(--isla-qr-sombra)' }">
        <img
            v-if="src"
            :src="src"
            :alt="nombre"
            :width="px"
            :height="px"
            :style="{ display: 'block', width: `${px}px`, height: `${px}px` }"
        >
        <canvas
            v-else
            ref="lienzo"
            role="img"
            :aria-label="nombre"
            :style="{ display: 'block', width: `${px}px`, height: `${px}px` }"
        />
        <figcaption
            v-if="code && size !== 'sm' && showCode"
            :style="{ fontFamily: 'var(--font-mono)', fontSize: '13px', fontWeight: 500, letterSpacing: '0.08em', color: 'var(--isla-qr-tinta)' }"
        >{{ code }}</figcaption>
    </figure>
</template>
