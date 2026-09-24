<script setup>
/**
 * La carga del sistema de diseño (`BounceLoader.jsx`): una bola que bota en una cama elástica, cae con gravedad,
 * se aplasta al tocar y cambia de color en cada bote (los cuatro roles `--isla-fiesta-*`). Es para esperar un
 * PROCESO —salir al banco, confirmar un pago, entrar—; cuando se sabe la forma de lo que llega, va
 * `EsqueletoCarga`. Todo en `em`: el tamaño es el `font-size`. Con «reducir movimiento» la bola se queda quieta y
 * se sigue leyendo por su texto.
 */
import { computed } from 'vue';
import { useTextos } from '../piezas/textos.js';

const TALLAS = { sm: 20, md: 48, lg: 72 };

const props = defineProps({
    size: { type: [String, Number], default: 'md' },
    label: { type: String, default: '' },
    showLabel: { type: Boolean, default: false },
});

const { t } = useTextos();
const px = computed(() => (typeof props.size === 'number' ? props.size : TALLAS[props.size] || TALLAS.md));
const diminuta = computed(() => px.value < 32);
const texto = computed(() => props.label || t('pieza.cargando'));
</script>

<template>
    <span
        role="status"
        aria-live="polite"
        :style="{ display: 'inline-flex', flexDirection: 'column', alignItems: 'center', gap: '10px' }"
    >
        <span
            aria-hidden="true"
            :style="{ position: 'relative', display: 'block', width: '1em', height: '1em', fontSize: `${px}px`, flex: '0 0 auto' }"
        >
            <span
                v-if="!diminuta"
                :style="{ position: 'absolute', left: '22%', right: '22%', bottom: '0.02em', height: '0.06em', borderRadius: '50%', background: 'var(--loader-bed)', animation: 'isla-bounce-shadow 0.72s linear infinite' }"
            />
            <span :style="{ position: 'absolute', left: '12%', right: '12%', bottom: diminuta ? '0.1em' : '0.14em', height: '0.12em', borderBottom: `${diminuta ? '0.1em' : '0.065em'} solid var(--loader-bed)`, borderRadius: '0 0 50% 50% / 0 0 100% 100%', transformOrigin: 'top', animation: 'isla-bounce-bed 0.72s linear infinite' }" />
            <span :style="{ position: 'absolute', left: '50%', bottom: diminuta ? '0.2em' : '0.26em', width: diminuta ? '0.34em' : '0.28em', height: diminuta ? '0.34em' : '0.28em', marginLeft: diminuta ? '-0.17em' : '-0.14em', borderRadius: '50%', transformOrigin: '50% 100%', background: 'var(--isla-fiesta-1)', animation: 'isla-bounce-ball 0.72s infinite, isla-bounce-hue calc(0.72s * 4) linear infinite' }" />
        </span>
        <span
            v-if="showLabel"
            :style="{ fontFamily: 'var(--font-ui)', fontSize: 'var(--fs-body-sm)', fontWeight: 'var(--fw-semibold)', color: 'var(--text-body)', textAlign: 'center', textWrap: 'pretty' }"
        >{{ texto }}</span>
        <span
            v-else
            :style="{ position: 'absolute', width: '1px', height: '1px', overflow: 'hidden', clip: 'rect(0 0 0 0)', whiteSpace: 'nowrap' }"
        >{{ texto }}</span>
    </span>
</template>
