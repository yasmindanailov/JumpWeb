<script setup>
/**
 * Una FILA de Ajustes (`bloques-2.jsx`, la `fila()` de `PmcAjustes`): el rótulo, su pista debajo y su acción a la derecha,
 * con 48px de toque y la raya de abajo. Si abre un paso (`abre`), la fila ENTERA es el botón, con su chevron; si no, es
 * un `div` y la acción es su enlace. Pinta y avisa (`click`).
 */
import { CUENTA } from './estilos.js';
import IconoLucide from '../ui/IconoLucide.vue';

defineProps({
    label: { type: String, required: true },
    sub: { type: String, default: '' },
    abre: { type: Boolean, default: false },
});
const emit = defineEmits(['click']);
</script>

<template>
    <component
        :is="abre ? 'button' : 'div'"
        :type="abre ? 'button' : undefined"
        :style="{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: '12px', width: '100%', minHeight: '48px', padding: '6px 0', border: 'none', borderBottom: '1px solid var(--border-subtle)', background: 'none', textAlign: 'left', font: 'inherit', color: 'inherit', cursor: abre ? 'pointer' : 'default' }"
        @click="abre && emit('click')"
    >
        <span :style="{ display: 'grid', gap: '2px', minWidth: 0 }">
            <span :style="[CUENTA.cuerpo, { color: 'var(--text-strong)' }]">{{ label }}</span>
            <span
                v-if="sub"
                :style="[CUENTA.pista, { overflowWrap: 'anywhere' }]"
            >{{ sub }}</span>
        </span>
        <IconoLucide
            v-if="abre"
            name="chevron-right"
            :size="18"
            color="var(--text-muted)"
        />
        <span
            v-else
            :style="{ display: 'inline-flex', alignItems: 'center', gap: '10px', flex: '0 0 auto' }"
        ><slot /></span>
    </component>
</template>
