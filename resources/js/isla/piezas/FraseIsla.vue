<script setup>
/**
 * La frase de la isla (Z6a): la nueva entra enfocándose (`entra`, a partir del primer cambio); la vieja, encima y quieta,
 * se desenfoca y se va (`isla-swap-out`, el cruce de `useCruce.js`). Debajo, cuando la página ve que se atasca,
 * «¿Lo hablamos?». La isla la pinta en su renglón (móvil) o en la fila (arriba): la misma pieza en los dos sitios.
 */
import LineaContexto from './LineaContexto.vue';
import NotaAyuda from './NotaAyuda.vue';
import { claveDeLinea } from '../useCruce.js';

defineProps({
    s: { type: Object, required: true },
    cruce: { type: Object, required: true },
    abrir: { type: Function, default: null },
    expanded: { type: Boolean, default: false },
    /** La ayuda (`help`) si la página ve que se atasca; si no, `null`. */
    ayuda: { type: Object, default: null },
});
const emit = defineEmits(['ayuda']);
</script>

<template>
    <div :style="{ display: 'grid', minWidth: 0, width: '100%' }">
        <div :style="{ position: 'relative', minWidth: 0 }">
            <LineaContexto
                :key="claveDeLinea(s)"
                :entra="cruce.nL > 0"
                :situation="s"
                :abrir="abrir"
                :expanded="expanded"
            />
            <div
                v-if="cruce.lineaSale"
                :key="`sale|${claveDeLinea(cruce.lineaSale)}`"
                aria-hidden="true"
                :style="{ position: 'absolute', inset: 0, display: 'flex', alignItems: 'center', pointerEvents: 'none', animation: 'isla-swap-out calc(var(--dur-island) * 0.45) var(--ease-out) both' }"
            >
                <LineaContexto
                    :situation="cruce.lineaSale"
                    quieta
                />
            </div>
        </div>
        <NotaAyuda
            v-if="ayuda"
            :help="ayuda"
            @abrir="(e) => emit('ayuda', e)"
        />
    </div>
</template>
