<script setup>
/**
 * Lo que va junto a la acción de pagar, bajo ella en la isla (`PjcPagoJunto` del diseño): el pago secundario, la
 * pasarela y las condiciones. Sin segundo método (el botón de Bizum llega con la v2.0.0) no queda hueco. La segunda
 * mitad de las condiciones —sus plazos— es dato de la instalación. Las marcas ya no van aquí: el diseño del 26-09 las
 * pasó al final del recibo (`PantallaPagar`, `#784`).
 */
import { useTextos } from '../piezas/textos.js';
import { PASO } from './estilos.js';
import IconoLucide from '../ui/IconoLucide.vue';
import BotonSistema from '../ui/BotonSistema.vue';
import EnlaceSistema from '../ui/EnlaceSistema.vue';

defineProps({
    secundario: { type: Object, default: null },
    condicionesHref: { type: String, default: undefined },
    condiciones: { type: String, default: '' },
});
const emit = defineEmits(['pagar', 'condiciones']);
const { t } = useTextos();
</script>

<template>
    <div :style="{ display: 'grid', gap: '10px' }">
        <BotonSistema
            v-if="secundario"
            variant="quiet"
            full
            @click="emit('pagar', secundario.metodo)"
        >
            {{ secundario.etiqueta }}
        </BotonSistema>
        <p :style="[PASO.pista, { display: 'flex', alignItems: 'center', justifyContent: 'center', gap: '8px', color: 'var(--text-body)', fontWeight: 'var(--fw-semibold)', textAlign: 'center' }]"><IconoLucide
            name="lock"
            :size="15"
        />{{ t('compra.pagar.pasarela') }}</p>
        <p :style="[PASO.pista, { textAlign: 'center' }]">{{ t('compra.pagar.condiciones') }}<EnlaceSistema
            :href="condicionesHref"
            size="sm"
            underline="always"
            @click="emit('condiciones', $event)"
        >{{ t('compra.pagar.condiciones_enlace') }}</EnlaceSistema>{{ condiciones }}</p>
    </div>
</template>
