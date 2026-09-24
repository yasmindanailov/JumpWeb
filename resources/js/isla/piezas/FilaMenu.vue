<script setup>
/** Una fila del menú de la isla (`Row` del diseño): icono, título, nota, dato y flecha. */
import { ref } from 'vue';
import IconoLucide from '../ui/IconoLucide.vue';

defineProps({
    icon: { type: String, default: null },
    title: { type: String, required: true },
    note: { type: String, default: null },
    meta: { type: String, default: null },
    href: { type: String, default: undefined },
    chevron: { type: Boolean, default: false },
    tone: { type: String, default: undefined },
    active: { type: Boolean, default: false },
});
const emit = defineEmits(['click']);

const hover = ref(false);
</script>

<template>
    <component
        :is="href ? 'a' : 'button'"
        :href="href"
        :type="href ? undefined : 'button'"
        :style="{
            display: 'flex', alignItems: 'center', gap: '12px', width: '100%', boxSizing: 'border-box',
            minHeight: '48px', padding: '9px 12px', marginBottom: '2px', border: 'none', borderRadius: 'var(--r-md)',
            background: active ? 'rgba(255,255,255,0.16)' : hover ? 'rgba(255,255,255,0.1)' : 'transparent',
            color: 'var(--isla-sobre)', textAlign: 'left', textDecoration: 'none', cursor: 'pointer', transition: 'var(--t-hover)',
        }"
        @click="emit('click', $event)"
        @mouseenter="hover = true"
        @mouseleave="hover = false"
    >
        <IconoLucide
            v-if="icon"
            :name="icon"
            :size="19"
            :color="tone === 'live' ? 'var(--isla-vivo)' : tone === 'alert' ? 'var(--isla-alerta-texto)' : 'currentColor'"
        />
        <span :style="{ flex: 1, minWidth: 0 }">
            <b :style="{ display: 'block', fontFamily: 'var(--font-ui)', fontWeight: 'var(--fw-bold)', fontSize: '15px', letterSpacing: '-0.01em' }">{{ title }}</b>
            <span
                v-if="note"
                :style="{ display: 'block', marginTop: '1px', fontFamily: 'var(--font-ui)', fontSize: '12.5px', lineHeight: 1.35, color: tone === 'alert' ? 'var(--isla-alerta-texto)' : 'rgba(255,255,255,0.68)' }"
            >{{ note }}</span>
        </span>
        <span
            v-if="meta"
            :style="{ fontFamily: 'var(--font-ui)', fontSize: '13px', fontWeight: 'var(--fw-semibold)', color: 'var(--text-muted)', whiteSpace: 'nowrap' }"
        >{{ meta }}</span>
        <IconoLucide
            v-if="chevron"
            name="chevron-right"
            :size="17"
            color="rgba(255,255,255,0.5)"
        />
    </component>
</template>
