<script setup>
/**
 * «¿Lo hablamos?» (situación 12 en la barra, Z6a; `HelpNote` del diseño): cuando la página ve que se atasca
 * (`help.stuck`), una nota que se toca bajo la frase y abre la ayuda por WhatsApp. No añade un control ni quita la acción.
 */
import { ref } from 'vue';
import IconoLucide from '../ui/IconoLucide.vue';
import { useTextos } from './textos.js';

defineProps({
    help: { type: Object, required: true },
});
const emit = defineEmits(['abrir']);
const { t } = useTextos();
const hover = ref(false);
</script>

<template>
    <button
        type="button"
        :style="{
            display: 'flex', alignItems: 'center', gap: '8px', width: '100%', minHeight: '44px', margin: '0 0 -6px', padding: '0 8px',
            border: 'none', borderRadius: 'var(--r-pill)', background: hover ? 'rgba(255,255,255,0.12)' : 'transparent',
            color: 'var(--isla-sobre)', textAlign: 'left', cursor: 'pointer',
            fontFamily: 'var(--font-ui)', fontSize: '13px', fontWeight: 'var(--fw-bold)', transition: 'var(--t-hover)',
        }"
        @click="emit('abrir', $event)"
        @mouseenter="hover = true"
        @mouseleave="hover = false"
    >
        <IconoLucide
            name="message-circle"
            :size="16"
        />
        <span :style="{ flex: '1 1 auto', minWidth: 0 }">{{ t('panel.ayuda') }} {{ help.inHours ? t('menu.ayuda_en_horario') : t('menu.ayuda_fuera') }}</span>
        <IconoLucide
            name="chevron-right"
            :size="16"
        />
    </button>
</template>
