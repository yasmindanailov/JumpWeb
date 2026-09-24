<script setup>
/**
 * Paso 2 de la compra, «Repasa y paga» (`PjcPagar` del diseño). El recibo es el resumen del sistema: cada línea
 * con quién la usa y su − / + debajo, los calcetines, el descuento en positivo y el total grande; la cantidad se
 * cambia aquí mismo, sin salir. «Añadir otra entrada» abre la pantalla 0 para una línea más. Sin calcetines, una
 * sola línea que los ofrece, como opción y no como paso.
 *
 * ⚠️ Ningún importe se calcula aquí (`PAY-12`): las líneas y el total llegan hechos, del cálculo del servidor.
 * `lineas` son las del resumen (`{ id, label, sub, value, tone, control: { n, min, max, uno, varios } }`) y
 * `calcetines`, si se ofrecen, `{ texto, uno, varios }`.
 */
import { useTextos } from '../piezas/textos.js';
import PasoCompra from './PasoCompra.vue';
import CantidadCompra from './CantidadCompra.vue';
import IconoLucide from '../ui/IconoLucide.vue';
import EnlaceSistema from '../ui/EnlaceSistema.vue';
import ResumenPrecio from '../ui/ResumenPrecio.vue';
import AvisoDestacado from '../ui/AvisoDestacado.vue';

defineProps({
    lineas: { type: Array, required: true },
    total: { type: String, required: true },
    nota: { type: String, default: '' },
    otraEntrada: { type: Boolean, default: false },
    calcetines: { type: Object, default: null },
    // El «no» del servidor al pagar o al cambiar una cantidad (T3e·3): la hora se llenó, la cantidad no cabe…
    aviso: { type: String, default: '' },
});
const emit = defineEmits(['cantidad', 'otra', 'calcetines']);
const { t } = useTextos();
</script>

<template>
    <PasoCompra :titulo="t('compra.pagar.titular')">
        <AvisoDestacado
            v-if="aviso"
            tone="danger"
            size="sm"
            role="alert"
            :title="aviso"
        >
            <template #icono><IconoLucide
                name="circle-alert"
                :size="18"
            /></template>
        </AvisoDestacado>
        <ResumenPrecio
            size="md"
            :lines="lineas"
            :total="total"
            :total-label="t('compra.pagar.total')"
            :note="nota"
        >
            <template #control="{ linea }">
                <CantidadCompra
                    :model-value="linea.control.n"
                    :min="linea.control.min"
                    :max="linea.control.max"
                    :uno="linea.control.uno"
                    :varios="linea.control.varios"
                    @update:model-value="emit('cantidad', linea.id, $event)"
                />
            </template>
        </ResumenPrecio>
        <EnlaceSistema
            v-if="otraEntrada"
            :style="{ justifySelf: 'start', marginTop: '-8px' }"
            @click="emit('otra')"
        >
            <template #icono><IconoLucide
                name="plus"
                :size="18"
            /></template>{{ t('compra.pagar.otra') }}
        </EnlaceSistema>
        <AvisoDestacado
            v-if="calcetines"
            tone="neutral"
            size="sm"
        >
            <template #icono><IconoLucide
                name="footprints"
                :size="18"
            /></template>
            <div :style="{ display: 'grid', gap: '10px' }">
                <span>{{ calcetines.texto }}</span>
                <CantidadCompra
                    :model-value="0"
                    :min="0"
                    :max="40"
                    :uno="calcetines.uno"
                    :varios="calcetines.varios"
                    @update:model-value="emit('calcetines', $event)"
                />
            </div>
        </AvisoDestacado>
    </PasoCompra>
</template>
