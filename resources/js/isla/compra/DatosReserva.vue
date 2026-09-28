<script setup>
/**
 * **Los datos que un PACK pide AL RESERVAR** (`#839`, `[DECIDIDO owner]`; T6c·3 de `docs/specs/isla-y-landing-nueva.md`
 * §4.19): el centro y su responsable de una excursión, que el servidor exige para admitir la línea. Uno por campo de la
 * ficha (`vista.js::datosDeReserva`), con su etiqueta del panel; el diseño no dibuja este paso: es el campo del sistema.
 *
 * ⚠️ En el trozo de los pasos, pedido solo cuando un pack tiene algo que pedir (`datos-reserva.js`): el campo del sistema
 * viaja con ellos, y metido en la pantalla 0 la compra pesaba +7 kB (`SidebarBundleBudgetTest`, medido).
 */
import CampoSistema from '../ui/CampoSistema.vue';

defineProps({
    datos: { type: Array, required: true },
});
const emit = defineEmits(['cambiar']);
</script>

<template>
    <div :style="{ display: 'grid', gap: '12px' }">
        <CampoSistema
            v-for="d in datos"
            :id="`pjc-dato-${d.key}`"
            :key="d.key"
            :label="d.label"
            :required="d.required"
            :optional="!d.required"
            :inputmode="d.numero ? 'numeric' : undefined"
            :model-value="d.valor"
            @update:model-value="emit('cambiar', { key: d.key, valor: $event })"
        />
    </div>
</template>
