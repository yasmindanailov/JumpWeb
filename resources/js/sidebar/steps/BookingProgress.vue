<script setup>
/**
 * La banda de progreso del flujo de reserva (pasos 2 y 3).
 *
 * ⚠️ **Vive FUERA del bloque de cada paso en el Blade**, y por eso el diff de árbol la incluye: un
 * motor que no la emitiera pasaría un diff que empezara en el título del paso, y el cliente perdería
 * el «volver» y el contador de fases sin que nada avisara.
 *
 * **No calcula el progreso**: qué fase está activa, cómo se llama la tercera —«Extras» en una
 * entrada, «Datos» en un pack— y qué contexto se enseña lo decide el servidor. Aquí se pinta.
 */
defineProps({
    /** `{active, total, steps: [{label, state}], context}`, tal cual lo compone el servidor. */
    progress: { type: Object, default: null },
    messages: { type: Object, default: () => ({}) },
});

defineEmits(['back']);
</script>

<template>
    <div v-if="progress" class="bk-progress">
        <div class="bk-progress__top">
            <button type="button" class="bk-back" @click="$emit('back')">
                <svg class="arrow-ico" viewBox="0 0 24 24" aria-hidden="true"></svg>
                <span>{{ messages.back ?? '' }}</span>
            </button>
            <span class="bk-step-count">{{ (messages.step_count ?? '').replace(':n', progress.active).replace(':total', progress.total) }}</span>
        </div>
        <div class="bk-seg" aria-hidden="true">
            <span v-for="(segment, i) in progress.steps" :key="i" class="bk-seg__item" :class="'is-' + segment.state">
                <span class="bk-seg__bar"></span>
                <span class="bk-seg__label">{{ segment.label }}</span>
            </span>
        </div>
        <!-- El contexto —producto · día · hora— lo COMPONE el servidor: se va llenando conforme el
             cliente elige, y sus fechas salen del formateador de Carbon con el locale activo.
             Recomponerlo aquí con `Intl` daría un texto distinto (§4.5). -->
        <div v-if="progress.context" class="bk-context"><span class="jj-block" aria-hidden="true"></span><span>{{ progress.context }}</span></div>
    </div>
</template>
