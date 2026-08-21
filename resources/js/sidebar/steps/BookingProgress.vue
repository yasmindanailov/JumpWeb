<script setup>
/**
 * La banda de progreso del flujo de reserva (pasos 2 y 3).
 *
 * ⚠️ **Vive FUERA del bloque de cada paso en el Blade**, y por eso el diff de árbol la incluye: un
 * motor que no la emitiera pasaría un diff que empezara en el título del paso, y el cliente perdería
 * el «volver» y el contador de fases sin que nada avisara.
 *
 * **No calcula el progreso**: en qué fase está el cliente, cómo se llama la tercera —«Extras» en una
 * entrada, «Datos» en un pack— y qué contexto se enseña lo compone `progress.js`, un módulo plano con
 * su propia paridad contra el servidor. Aquí se pinta.
 */
import { t as translate, tp as translateWith } from '../i18n.js';

const props = defineProps({
    /** `{active, total, steps: [{label, state}], context}`, tal cual lo compone el servidor. */
    progress: { type: Object, default: null },
    messages: { type: Object, default: () => ({}) },
});

defineEmits(['back']);

const t = (key) => translate(props.messages, key);
const tp = (key, params) => translateWith(props.messages, key, params);
</script>

<template>
    <div v-if="progress" class="bk-progress">
        <div class="bk-progress__top">
            <button type="button" class="bk-back" @click="$emit('back')">
                <svg class="arrow-ico" width="16" height="16" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"
                 aria-hidden="true" focusable="false">
                <line x1="19" y1="12" x2="5" y2="12" />
                <polyline points="12 19 5 12 12 5" />
            </svg>
                <span>{{ t('back') }}</span>
            </button>
            <span class="bk-step-count">{{ tp('step_count', { n: progress.active, total: progress.total }) }}</span>
        </div>
        <div class="bk-seg" aria-hidden="true">
            <span v-for="(segment, i) in progress.steps" :key="i" class="bk-seg__item" :class="'is-' + segment.state">
                <span class="bk-seg__bar"></span>
                <span class="bk-seg__label">{{ segment.label }}</span>
            </span>
        </div>
        <!-- El contexto —producto · día · hora— se va llenando conforme el cliente elige.
             ⚠️ Su fecha es el ÚNICO texto del cajón que los dos motores no sacan de la misma fuente:
             el servidor usa Carbon y el cliente `Intl` (§4.5). Medido: en inglés y en francés el
             resultado es idéntico; en español difieren los puntos de abreviatura. Ver `progress.js`. -->
        <div v-if="progress.context" class="bk-context"><span class="jj-block" aria-hidden="true"></span><span>{{ progress.context }}</span></div>
    </div>
</template>
