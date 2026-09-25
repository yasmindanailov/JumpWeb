<script setup>
/**
 * Compartir el cálculo (`ShareRow.jsx`): casi nadie decide solo, y es una acción SECUNDARIA —píldoras tranquilas, nunca
 * naranja—. `items` = `[{ kind: 'whatsapp' | 'email' | 'copy' | 'link', label, href?, onClick? }]`; `copy` copia
 * `value` y dice `confirm` 2,6 s. En la calculadora de la página, «Envíaselo por WhatsApp» (T4d).
 */
import { onBeforeUnmount, ref, watch } from 'vue';
import IconoLucide from './IconoLucide.vue';
import { ICONOS_COMPARTIR, atributosCompartir, estiloCompartir } from './compartir.js';
import { useTextos } from '../piezas/textos.js';

const props = defineProps({
    items: { type: Array, default: () => [] },
    confirm: { type: String, default: '' },
    value: { type: String, default: '' },
    align: { type: String, default: 'start' },
    tone: { type: String, default: 'light' },
});
const { t } = useTextos();
const sobre = ref(null);
const copiado = ref(false);
let reloj = null;
watch(copiado, (si) => { clearTimeout(reloj); if (si) reloj = setTimeout(() => { copiado.value = false; }, 2600); });
onBeforeUnmount(() => clearTimeout(reloj));

function pulsar(e, item) {
    if (item.kind === 'copy') {
        e.preventDefault();
        try { if (navigator.clipboard && props.value) navigator.clipboard.writeText(props.value); } catch { /* sin portapapeles */ }
        copiado.value = true;
    }
    item.onClick?.();
}
</script>

<template>
    <div :style="{ display: 'flex', flexWrap: 'wrap', alignItems: 'center', gap: '10px', justifyContent: align === 'center' ? 'center' : 'flex-start' }">
        <component
            :is="it.href ? 'a' : 'button'"
            v-for="(it, i) in items"
            :key="i"
            v-bind="atributosCompartir(it)"
            :style="estiloCompartir({ tinta: tone === 'ink', sobre: sobre === i })"
            @mouseenter="sobre = i"
            @mouseleave="sobre = null"
            @click="pulsar($event, it)"
        ><IconoLucide :name="ICONOS_COMPARTIR[it.kind] || 'share-2'" :size="18" />{{ it.label }}</component>
        <span
            v-if="copiado"
            role="status"
            :style="{ display: 'inline-flex', alignItems: 'center', gap: '7px', fontFamily: 'var(--font-ui)', fontSize: 'var(--fs-body-sm)', fontWeight: 'var(--fw-semibold)', color: tone === 'ink' ? 'var(--volt-400)' : 'var(--success-600)', animation: 'pj-pop var(--dur-base) var(--ease-spring)' }"
        ><IconoLucide name="check" :size="16" />{{ confirm || t('pieza.copiado') }}</span>
    </div>
</template>
