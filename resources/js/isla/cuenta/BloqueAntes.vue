<script setup>
/**
 * **Antes de venir** (`paginas/mi-cuenta/bloques.jsx`, `PmcAntes`; T5c de §4.13, `#776`): una tarea cada vez. «N de M
 * hecho» con su barra; la siguiente, entera, con «SIGUIENTE» y su plazo; el resto en filas (plegadas a partir de la
 * tercera); las autorizaciones, dichas; los extras, ofrecidos. Con todo hecho, «Todo listo para el sábado 26».
 *
 * Pinta y avisa (`tarea`, con la acción de la tarea que se toca entera: WhatsApp se abre aparte): qué se enseña lo decide
 * `antes.js`, y lo que dice, el servidor. Las acciones de las dos líneas de abajo son enlaces de verdad (a la lista).
 */
import { ref } from 'vue';
import { useTextos } from '../piezas/textos.js';
import { CUENTA } from './estilos.js';
import TarjetaTarea from '../ui/TarjetaTarea.vue';
import FilaTarea from '../ui/FilaTarea.vue';
import AvisoDestacado from '../ui/AvisoDestacado.vue';
import EnlaceSistema from '../ui/EnlaceSistema.vue';
import IconoLucide from '../ui/IconoLucide.vue';

defineProps({
    antes: { type: Object, required: true },
    idBloque: { type: String, default: 'antes' },
});
const emit = defineEmits(['tarea']);
const { t } = useTextos();
const todas = ref(false);
</script>

<template>
    <section
        :id="idBloque"
        :aria-labelledby="`${idBloque}-t`"
        :style="CUENTA.bloque"
    >
        <h2
            :id="`${idBloque}-t`"
            :style="CUENTA.h2"
        >{{ t('mi_cuenta.antes.titulo') }}</h2>
        <div
            v-if="antes.progreso"
            :style="{ display: 'grid', gap: '6px' }"
        >
            <span :style="CUENTA.pista">{{ antes.progreso.texto }}</span>
            <span
                aria-hidden="true"
                :style="{ height: '4px', borderRadius: '2px', background: 'var(--border-subtle)', overflow: 'hidden' }"
            ><span :style="{ display: 'block', height: '100%', width: `${100 * antes.progreso.por}%`, background: 'var(--text-positive)', transition: 'width var(--dur-slow) var(--ease-out)' }" /></span>
        </div>
        <TarjetaTarea
            v-if="antes.siguiente"
            :icon="antes.siguiente.icon"
            :overline="antes.siguiente.overline"
            :due="antes.siguiente.due || ''"
            :cta="antes.siguiente.action?.label || ''"
            :on-click="antes.siguiente.action ? () => emit('tarea', antes.siguiente.action) : null"
        >
            <b
                v-if="antes.siguiente.cabeza"
                :style="{ color: 'var(--text-strong)' }"
            >{{ antes.siguiente.cabeza }}</b>{{ antes.siguiente.resto }}
        </TarjetaTarea>
        <AvisoDestacado
            v-else-if="antes.todoListo"
            tone="success"
            size="sm"
            :title="antes.todoListo"
        >
            <template #icono><IconoLucide
                name="circle-check"
                :size="18"
            /></template>
        </AvisoDestacado>
        <div
            v-if="antes.filas.length"
            :style="{ display: 'grid', gap: '2px' }"
        >
            <FilaTarea
                v-for="f in (antes.plegable && ! todas ? antes.filas.slice(0, antes.aLaVista) : antes.filas)"
                :key="f.kind"
                :icon="f.icon"
                :title="f.title || ''"
                :note="f.note"
                :done="f.done"
                :cta="f.cta"
                :on-click="! f.done && f.action ? () => emit('tarea', f.action) : null"
            />
            <EnlaceSistema
                v-if="antes.plegable"
                :aria-expanded="todas ? 'true' : 'false'"
                :style="{ justifySelf: 'start', marginLeft: '10px' }"
                @click="todas = ! todas"
            >{{ todas ? antes.verMenos : antes.verMas }}</EnlaceSistema>
        </div>
        <p
            v-if="antes.estado"
            :style="[CUENTA.pista, { display: 'flex', gap: '8px', alignItems: 'flex-start' }]"
        >
            <span
                aria-hidden="true"
                :style="{ flex: '0 0 auto', marginTop: '1px', color: 'var(--icon-accent)' }"
            ><IconoLucide
                :name="antes.estado.icon"
                :size="15"
            /></span>
            <span>{{ `${antes.estado.text} ` }}<EnlaceSistema
                v-if="antes.estado.action"
                size="sm"
                underline="always"
                :href="antes.estado.action.url"
            >{{ antes.estado.action.label }}</EnlaceSistema></span>
        </p>
        <p
            v-if="antes.opcional"
            :style="[CUENTA.pista, { display: 'flex', gap: '8px', alignItems: 'flex-start', paddingTop: '10px', borderTop: '1px solid var(--border-subtle)' }]"
        >
            <span
                aria-hidden="true"
                :style="{ flex: '0 0 auto', marginTop: '1px', color: 'var(--icon-accent)' }"
            ><IconoLucide
                :name="antes.opcional.icon"
                :size="15"
            /></span>
            <span>{{ `${antes.opcional.text} ` }}<EnlaceSistema
                v-if="antes.opcional.action"
                size="sm"
                underline="always"
                :href="antes.opcional.action.url"
            >{{ antes.opcional.action.label }}</EnlaceSistema></span>
        </p>
    </section>
</template>
