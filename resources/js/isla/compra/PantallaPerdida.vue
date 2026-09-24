<script setup>
/**
 * La hora perdida (`PjcPerdida` del diseño): no se ha cobrado nada, y estas horas cercanas del mismo día sí están
 * libres. En el producto la hora se guarda AL PAGAR (`#688`), así que sale cuando «Pagar» encuentra la hora llena (T3e·6).
 */
import { useTextos } from '../piezas/textos.js';
import { PASO } from './estilos.js';
import SelectorHoras from '../ui/SelectorHoras.vue';

defineProps({
    cercanas: { type: Array, default: () => [] },
    horaNueva: { type: String, default: null },
});
const emit = defineEmits(['hora']);
const { t } = useTextos();
</script>

<template>
    <div
        role="alert"
        :style="PASO.paso"
    >
        <h1
            tabindex="-1"
            :style="PASO.titulo"
        >{{ t('compra.perdida.titular') }}</h1>
        <p :style="PASO.cuerpo">{{ t('compra.perdida.texto') }}</p>
        <SelectorHoras
            size="sm"
            :slots="cercanas"
            :model-value="horaNueva"
            counts="low"
            :low-threshold="6"
            @update:model-value="emit('hora', $event)"
        />
    </div>
</template>
