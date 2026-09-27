<script setup>
/**
 * **Cada bloque de Mi cuenta, protegido** (T5f de §4.13; el diseño, `cuenta.jsx`: `PmcSeguro` y la regla «si uno falla,
 * deja su hueco y el resto sigue»). Dos maneras de fallar, un solo hueco:
 *   · sus DATOS (`datos`) no se pudieron componer: salen `ROTO` de `seguro.js::protegido`, dentro de su `computed`;
 *   · o su PINTURA revienta (un componente de dentro): `onErrorCaptured` lo detiene aquí —no sube y no se lleva la capa—.
 * Se apunta en la consola, como el diseño: es un defecto y tiene que verse.
 */
import { onErrorCaptured, ref } from 'vue';
import { useTextos } from '../piezas/textos.js';
import { estaRoto } from './seguro.js';

const props = defineProps({
    nombre: { type: String, required: true },
    datos: { type: null, default: null },
});
const { tp } = useTextos();
const fallo = ref(false);

onErrorCaptured((error) => {
    globalThis.console?.error?.('Mi cuenta · bloque', props.nombre, error?.message);
    fallo.value = true;

    return false;
});
</script>

<template>
    <div
        v-if="estaRoto(datos) || fallo"
        role="status"
        :style="{ padding: '14px', border: '1px dashed var(--control-border)', borderRadius: 'var(--r-md)', font: 'var(--type-mono)', color: 'var(--text-muted)' }"
    >{{ tp('mi_cuenta.hueco', { nombre }) }}</div>
    <slot v-else />
</template>
