<script setup>
/**
 * **La mejora que se añade mientras se reserva** (`marketing/UpgradeRow.jsx` del diseño; la hora extra de la calculadora
 * de la fiesta, T6b·3): nunca viene marcada, enseña su precio y se quita de un toque. Apagada, dice por qué (`notaNo`);
 * encendida, su nota. Solo el tono claro (el que pinta la página). ⚠️ El icono es `clock` y no el `timer` del diseño: el
 * registro de la isla (`iconos.js`) viaja en todos sus trozos, y uno nuevo lo pagaría cada uno.
 */
import { computed, ref } from 'vue';
import IconoLucide from './IconoLucide.vue';
import { cajaMejora, casillaMejora, CIFRA_MEJORA, NOTA_MEJORA, TEXTO_MEJORA, TITULO_MEJORA } from './filaMejora.js';

const props = defineProps({
    titulo: { type: String, required: true },
    descripcion: { type: String, default: '' },
    precio: { type: String, default: '' },
    total: { type: String, default: '' },
    nota: { type: String, default: '' },
    notaNo: { type: String, default: '' },
    marcada: { type: Boolean, default: false },
    disponible: { type: Boolean, default: true },
});
const emit = defineEmits(['cambiar']);
const encima = ref(false);
const caja = computed(() => cajaMejora({ marcada: props.marcada, apagada: ! props.disponible, encima: encima.value }));
</script>

<template>
    <label :style="caja" @mouseenter="encima = true" @mouseleave="encima = false">
        <input type="checkbox" :checked="marcada" :disabled="! disponible" :style="{ position: 'absolute', opacity: 0, width: '1px', height: '1px', margin: 0 }" @change="emit('cambiar', $event.target.checked)">
        <span aria-hidden="true" :style="casillaMejora(marcada)"><IconoLucide :name="marcada ? 'check' : 'clock'" :size="15" /></span>
        <span :style="{ display: 'flex', flexDirection: 'column', gap: '4px', minWidth: 0, flex: 1 }">
            <span :style="{ display: 'flex', flexWrap: 'wrap', alignItems: 'baseline', gap: '4px 10px', justifyContent: 'space-between' }">
                <span :style="TITULO_MEJORA">{{ titulo }}</span>
                <span v-if="precio" :style="CIFRA_MEJORA">{{ precio }}<span v-if="total" :style="{ color: 'var(--text-muted)', whiteSpace: 'nowrap' }"> · {{ total }}</span></span>
            </span>
            <span v-if="descripcion" :style="TEXTO_MEJORA">{{ descripcion }}</span>
            <span v-if="! disponible && notaNo" :style="NOTA_MEJORA(false)"><IconoLucide name="clock" :size="14" />{{ notaNo }}</span>
            <span v-else-if="nota" :style="NOTA_MEJORA(true)"><IconoLucide name="wallet" :size="14" />{{ nota }}</span>
        </span>
    </label>
</template>
