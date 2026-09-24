<script setup>
/** El control redondo de 46px de la isla (`IconControl` del diseño): el menú, Volver y Cerrar. */
import { ref } from 'vue';
import IconoLucide from '../ui/IconoLucide.vue';

defineProps({
    label: { type: String, required: true },
    icon: { type: String, required: true },
    expanded: { type: Boolean, default: undefined },
    dot: { type: String, default: null },
});
const emit = defineEmits(['click']);

const hover = ref(false);
const ring = ref(false);
</script>

<template>
    <button
        type="button"
        :aria-label="label"
        :title="label"
        :aria-expanded="expanded"
        :style="{
            display: 'inline-flex', alignItems: 'center', justifyContent: 'center', position: 'relative',
            flex: '0 0 auto', width: '46px', height: '46px', border: 'none', borderRadius: 'var(--r-pill)',
            background: hover || expanded ? 'rgba(255,255,255,0.24)' : 'rgba(255,255,255,0.13)',
            color: 'var(--isla-sobre)', cursor: 'pointer', transition: 'var(--t-hover)',
            boxShadow: ring ? 'inset 0 0 0 2px var(--focus-ring)' : 'none',
        }"
        @click="emit('click', $event)"
        @mouseenter="hover = true"
        @mouseleave="hover = false"
        @focus="ring = $event.currentTarget.matches(':focus-visible')"
        @blur="ring = false"
    >
        <IconoLucide
            :name="icon"
            :size="21"
        />
        <span
            v-if="dot"
            :style="{ position: 'absolute', top: '9px', right: '10px', width: '8px', height: '8px', borderRadius: '50%', background: dot, border: '2px solid var(--isla-punto-borde)' }"
        />
    </button>
</template>
