<script setup>
/**
 * **Los avisos de la cuenta, arriba de Mi cuenta** (T5e·2 de §4.13, `#779`): los del índice del cajón, que el mockup no
 * dibuja, con las piezas del sistema (`#773`·d). El de la CUENTA —uno, en el orden del cajón (`#331`): confirmar el correo
 * con su reenvío, firmar tu descargo o el de tus hijos— y, aparte, el de la analítica, que en esa cadena lo taparía un
 * descargo sin firmar durante semanas.
 *
 * Pinta y avisa (`aviso` con lo que hace su enlace; `analitica`, `privacidad` o `despedir`): qué dice lo decide `avisos.js`.
 */
import IconoLucide from '../ui/IconoLucide.vue';
import AvisoDestacado from '../ui/AvisoDestacado.vue';
import EnlaceSistema from '../ui/EnlaceSistema.vue';

defineProps({
    cuenta: { type: Object, default: null },
    analitica: { type: Object, default: null },
});
const emit = defineEmits(['aviso', 'analitica']);
</script>

<template>
    <AvisoDestacado
        v-if="cuenta"
        tone="warn"
        size="sm"
        role="status"
        :title="cuenta.texto"
    >
        <template #icono><IconoLucide
            :name="cuenta.icono"
            :size="18"
        /></template>
        <div
            v-if="cuenta.detalle || cuenta.accion"
            :style="{ display: 'grid', gap: '8px', justifyItems: 'start' }"
        >
            <span v-if="cuenta.detalle">{{ cuenta.detalle }}</span>
            <EnlaceSistema
                v-if="cuenta.accion"
                :disabled="cuenta.accion.deshabilitada"
                @click="emit('aviso', cuenta.accion.hace)"
            >{{ cuenta.accion.texto }}</EnlaceSistema>
        </div>
    </AvisoDestacado>
    <AvisoDestacado
        v-if="analitica"
        tone="info"
        size="sm"
    >
        <template #icono><IconoLucide
            name="info"
            :size="18"
        /></template>
        <div :style="{ display: 'grid', gap: '8px', justifyItems: 'start' }">
            <span>{{ analitica.texto }}</span>
            <span :style="{ display: 'flex', flexWrap: 'wrap', gap: '8px 18px' }">
                <EnlaceSistema @click="emit('analitica', 'privacidad')">{{ analitica.privacidad }}</EnlaceSistema>
                <EnlaceSistema @click="emit('analitica', 'despedir')">{{ analitica.despedir }}</EnlaceSistema>
            </span>
        </div>
    </AvisoDestacado>
</template>
