<script setup>
/**
 * Lo que va junto a la acción de pagar, bajo ella en la isla (`PjcPagoJunto` del diseño): el pago secundario, las
 * marcas que se aceptan, la pasarela y las condiciones. Sin segundo método (hoy Bizum no existe, `#683`) no queda
 * hueco. Las marcas y la segunda mitad de las condiciones —sus plazos— son datos de la instalación.
 */
import { useTextos } from '../piezas/textos.js';
import { PASO } from './estilos.js';
import IconoLucide from '../ui/IconoLucide.vue';
import BotonSistema from '../ui/BotonSistema.vue';
import EnlaceSistema from '../ui/EnlaceSistema.vue';

defineProps({
    secundario: { type: Object, default: null },
    marcas: { type: Array, default: () => [] },
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
        <div :style="{ display: 'flex', flexWrap: 'wrap', justifyContent: 'center', gap: '6px' }">
            <span
                v-for="m in marcas"
                :key="m"
                :style="{ padding: '4px 9px', border: '1px solid var(--border-subtle)', borderRadius: '6px', fontFamily: 'var(--font-ui)', fontSize: 'var(--fs-overline)', fontWeight: 'var(--fw-bold)', color: 'var(--text-body)' }"
            >{{ m }}</span>
        </div>
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
