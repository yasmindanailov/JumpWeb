<script setup>
/**
 * El pago fallido (situación 11), bajo o sobre la fila: volver a intentar con tarjeta y, si la instalación lo
 * ofrece, el pedido manual. La acción principal («Pagar con Bizum») va en la fila, como todas.
 */
import BotonTranquilo from './BotonTranquilo.vue';
import EnlaceIsla from './EnlaceIsla.vue';

defineProps({
    extra: { type: Object, required: true },
    top: { type: Boolean, default: false },
});
</script>

<template>
    <div :style="{ display: 'grid', gap: '8px', padding: top ? '10px 2px 2px' : '0 2px 8px' }">
        <BotonTranquilo
            :label="extra.secondary.label"
            :pulsar="extra.secondary.onClick"
        />
        <div
            v-if="extra.manual"
            :style="{ display: 'flex', justifyContent: 'center' }"
        >
            <EnlaceIsla
                :label="extra.manual.label"
                :pulsar="extra.manual.onClick"
            />
        </div>
    </div>
</template>
