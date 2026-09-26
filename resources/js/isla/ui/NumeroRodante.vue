<script setup>
/**
 * Una cifra que cambia RUEDA hasta su valor en vez de saltar (`core/RollingNumber.jsx` del diseño, 26-09; Z3, `#782`):
 * se ve que el precio ha reaccionado a lo que has elegido. Cada cifra gira en su columna con el muelle del sistema, 30ms
 * detrás de la anterior; los símbolos («€», la coma) no se mueven. Las columnas se cuentan desde la DERECHA (`clave`),
 * así que al pasar de 96 a 108 las unidades giran y la cifra nueva aparece en su sitio. Con «reducir movimiento»,
 * cambia sin rodar (sus duraciones caen a 1ms).
 * ⚠️ Una diferencia con el diseño, a propósito: allí las diez cifras de cada columna son TEXTO, y el de la página decía
 * «0 1 2 3 4 5 6 7 8 9, 0 1 2…» a quien lo leyera o lo copiara (medido en la sonda de Mi cuenta). Aquí lo que se ve va en
 * contenido generado (`[data-isla-rueda]::before`, en `isla.css`), que no es texto; el número, una vez, en su texto oculto.
 */
import { computed } from 'vue';
import { columnasRodantes } from './piezas.js';

const CIFRAS = '0\n1\n2\n3\n4\n5\n6\n7\n8\n9';
const props = defineProps({ value: { type: [String, Number], default: '' } });
const texto = computed(() => String(props.value ?? ''));
const columnas = computed(() => columnasRodantes(texto.value));
</script>

<template>
    <span :style="{ position: 'relative', display: 'inline-flex', alignItems: 'flex-start', lineHeight: 1.1, fontVariantNumeric: 'tabular-nums', whiteSpace: 'nowrap' }">
        <span :style="{ position: 'absolute', width: '1px', height: '1px', overflow: 'hidden', clip: 'rect(0 0 0 0)', whiteSpace: 'nowrap' }">{{ texto }}</span>
        <span
            aria-hidden="true"
            :style="{ display: 'inline-flex' }"
        >
            <template
                v-for="c in columnas"
                :key="c.clave"
            >
                <span
                    v-if="c.simbolo !== null"
                    :data-isla-rueda="c.simbolo"
                />
                <span
                    v-else
                    :style="{ display: 'inline-block', height: '1.1em', overflow: 'hidden' }"
                ><span
                    :data-isla-rueda="CIFRAS"
                    :style="{ display: 'block', transform: `translateY(${-c.cifra * 1.1}em)`, transition: `transform var(--dur-slow) var(--ease-spring) ${c.retraso}ms` }"
                /></span>
            </template>
        </span>
    </span>
</template>
