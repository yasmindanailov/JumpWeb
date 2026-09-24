<script setup>
/**
 * Junto a la acción del pago no completado (`PjcFallidoJunto` del diseño): volver a intentar con tarjeta y,
 * debajo, los enlaces —pagar con nuestra ayuda, si la instalación lo ofrece, y escribirnos—.
 *
 * `tarjeta: false` (T3e·3): sin Bizum (`#683`), reintentar con tarjeta ES la acción de la isla, y no se repite aquí.
 */
import { useTextos } from '../piezas/textos.js';
import BotonSistema from '../ui/BotonSistema.vue';
import EnlaceSistema from '../ui/EnlaceSistema.vue';

defineProps({
    ayuda: { type: Boolean, default: false },
    tarjeta: { type: Boolean, default: true },
});
const emit = defineEmits(['tarjeta', 'ayuda', 'escribir']);
const { t } = useTextos();
</script>

<template>
    <div :style="{ display: 'grid', gap: '8px' }">
        <BotonSistema
            v-if="tarjeta"
            variant="quiet"
            full
            @click="emit('tarjeta')"
        >
            {{ t('compra.fallido.tarjeta') }}
        </BotonSistema>
        <div :style="{ display: 'flex', flexWrap: 'wrap', justifyContent: 'center', gap: '0 24px' }">
            <EnlaceSistema
                v-if="ayuda"
                @click="emit('ayuda')"
            >{{ t('compra.fallido.ayuda') }}</EnlaceSistema>
            <EnlaceSistema @click="emit('escribir')">{{ t('compra.fallido.escribir') }}</EnlaceSistema>
        </div>
    </div>
</template>
