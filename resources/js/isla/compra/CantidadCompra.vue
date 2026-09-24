<script setup>
/** La cantidad con su unidad (`PjcCantidad` del diseño): − «2 niños» +. El número, con `v-model`. */
import ContadorCantidad from '../ui/ContadorCantidad.vue';

const props = defineProps({
    modelValue: { type: Number, required: true },
    min: { type: Number, default: 0 },
    max: { type: Number, default: 20 },
    uno: { type: String, required: true },
    varios: { type: String, required: true },
});
const emit = defineEmits(['update:modelValue']);
const conUnidad = (v) => `${v} ${v === 1 ? props.uno : props.varios}`;
</script>

<template>
    <ContadorCantidad
        variant="bare"
        :model-value="modelValue"
        :min="min"
        :max="max"
        :format="conUnidad"
        @update:model-value="emit('update:modelValue', $event)"
    />
</template>
