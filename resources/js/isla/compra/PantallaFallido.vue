<script setup>
/**
 * El pago no completado (`PjcFallido` del diseño): el banco no autorizó el cobro, no se ha cargado nada y la hora
 * sigue guardada hasta su hora real (`Order.expires_at`); debajo, el motivo del banco (`declined_reason`).
 *
 * Con el motor (T3e·3): la hora y el motivo llegan de `GET /orders/{code}/payment-status`, y si esa lectura falla
 * (la red, el limitador) no se inventan: la frase va sin su hora y el motivo no se pinta —como en el cajón—. Y
 * `aviso` es el «no» de un reintento (la pausa, el limitador), arriba, como en los demás pasos.
 */
import { useTextos } from '../piezas/textos.js';
import { PASO } from './estilos.js';
import CabeceraDesenlace from '../ui/CabeceraDesenlace.vue';
import AvisoDestacado from '../ui/AvisoDestacado.vue';
import IconoLucide from '../ui/IconoLucide.vue';

defineProps({
    hora: { type: String, default: '' },
    motivo: { type: String, default: '' },
    aviso: { type: String, default: '' },
});
const { t, tp } = useTextos();
</script>

<template>
    <div :style="PASO.paso">
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
        <CabeceraDesenlace
            kind="error"
            :title="t('compra.fallido.titular')"
        >
            {{ hora ? tp('compra.fallido.texto', { hora }) : t('compra.fallido.texto_sin_hora') }}
        </CabeceraDesenlace>
        <p
            v-if="motivo"
            :style="[PASO.hueco, { borderStyle: 'solid', borderColor: 'var(--border-subtle)', background: 'var(--surface-card)' }]"
        >{{ tp('compra.fallido.motivo', { motivo }) }}</p>
    </div>
</template>
