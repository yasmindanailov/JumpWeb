<script setup>
/** El título de un panel (`PanelHeader`). Volver y Cerrar no viven aquí: viven en la fila de la isla. */
defineProps({ title: { type: String, required: true } });
</script>

<template>
    <div :style="{ display: 'flex', alignItems: 'center', gap: '10px', margin: '2px 2px 10px' }">
        <span :style="{ flex: 1, fontFamily: 'var(--font-ui)', fontWeight: 'var(--fw-bold)', fontSize: '13px', color: 'var(--text-muted)' }">{{ title }}</span>
    </div>
</template>
