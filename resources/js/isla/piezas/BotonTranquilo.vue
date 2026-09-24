<script setup>
/** La secundaria de la isla (`QuietButton` del diseño): el `Boton` del sistema en su variante `quiet`. */
import BotonSistema from '../ui/BotonSistema.vue';

defineProps({
    label: { type: String, required: true },
    pulsar: { type: Function, default: null },
});
</script>

<template>
    <BotonSistema
        variant="quiet"
        full
        :style="{ flex: 1 }"
        @click="pulsar && pulsar($event)"
    >{{ label }}</BotonSistema>
</template>
