<script setup>
/**
 * **Sin conexión**, arriba de la capa de Mi cuenta (T5f de §4.13; el diseño, `cuenta.jsx`: `redEl` y `falloEl`, en todas
 * sus vistas, con sesión o sin ella): mientras no hay red, «Sin conexión» (se va sola cuando vuelve); y si algo que
 * guarda no se intentó, su fallo con «Volver a intentarlo». Los textos son de `mi_cuenta_alta`, que viaja también sin
 * sesión (Entra y Crea tu cuenta los necesitan).
 *
 * Pinta y avisa (`reintentar`): cuándo sale cada uno lo deciden `conexion.js` y `useConexion.js`.
 */
import { useTextos } from '../piezas/textos.js';
import AvisoDestacado from '../ui/AvisoDestacado.vue';
import EnlaceSistema from '../ui/EnlaceSistema.vue';
import IconoLucide from '../ui/IconoLucide.vue';

defineProps({
    enLinea: { type: Boolean, default: true },
    fallo: { type: Boolean, default: false },
});
const emit = defineEmits(['reintentar']);
const { t } = useTextos();
</script>

<template>
    <div
        v-if="! enLinea || fallo"
        :style="{ display: 'grid', gap: '8px', maxWidth: '520px', width: '100%', margin: '0 auto 16px' }"
    >
        <AvisoDestacado
            v-if="! enLinea"
            tone="warn"
            size="sm"
            role="status"
            :title="t('mi_cuenta_alta.red.sin')"
        >
            <template #icono><IconoLucide
                name="wifi-off"
                :size="18"
            /></template>{{ t('mi_cuenta_alta.red.sin_texto') }}
        </AvisoDestacado>
        <AvisoDestacado
            v-if="fallo"
            tone="danger"
            size="sm"
            role="alert"
            :title="t('mi_cuenta_alta.red.fallo')"
        >
            <template #icono><IconoLucide
                name="circle-alert"
                :size="18"
            /></template>
            <EnlaceSistema @click="emit('reintentar')">
                <template #icono><IconoLucide
                    name="rotate-ccw"
                    :size="16"
                /></template>{{ t('mi_cuenta_alta.red.reintentar') }}
            </EnlaceSistema>
        </AvisoDestacado>
    </div>
</template>
