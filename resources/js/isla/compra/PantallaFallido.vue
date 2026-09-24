<script setup>
/**
 * El pago no completado (`PjcFallido` del diseño): el banco no autorizó el cobro, no se ha cargado nada y la hora
 * sigue guardada hasta su hora real (`Order.expires_at`); debajo, el motivo del banco (`declined_reason`).
 */
import { useTextos } from '../piezas/textos.js';
import { PASO } from './estilos.js';
import CabeceraDesenlace from '../ui/CabeceraDesenlace.vue';

defineProps({
    hora: { type: String, required: true },
    motivo: { type: String, required: true },
});
const { t, tp } = useTextos();
</script>

<template>
    <div :style="PASO.paso">
        <CabeceraDesenlace
            kind="error"
            :title="t('compra.fallido.titular')"
        >
            {{ tp('compra.fallido.texto', { hora }) }}
        </CabeceraDesenlace>
        <p :style="[PASO.hueco, { borderStyle: 'solid', borderColor: 'var(--border-subtle)', background: 'var(--surface-card)' }]">{{ tp('compra.fallido.motivo', { motivo }) }}</p>
    </div>
</template>
