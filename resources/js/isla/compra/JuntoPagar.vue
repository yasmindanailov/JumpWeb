<script setup>
/**
 * Lo que va junto a la acción de pagar, bajo ella en la isla (`PjcPagoJunto` del diseño): el pago secundario, las
 * formas de pago, la pasarela y las condiciones. Sin segundo método (el botón de Bizum llega con la v2.0.0) no queda
 * hueco. La segunda mitad de las condiciones —sus plazos— es dato de la instalación. Las formas de pago (`marcas`,
 * `#786`, el owner: «debajo del botón, sutil»): sus logotipos oficiales para fondo oscuro, pequeños y sin chapa, bajo la
 * acción que las usa (el diseño del 26-09 las ponía al final del recibo, `#784`). Desde `#823` (el owner: su fila propia
 * saturaba), DENTRO de la frase de la pasarela —«Tu tarjeta no se guarda» y con qué se paga, juntos—, un poco más
 * pequeñas: el pie pasa de tres bloques a dos.
 */
import { useTextos } from '../piezas/textos.js';
import { PASO } from './estilos.js';
import IconoLucide from '../ui/IconoLucide.vue';
import BotonSistema from '../ui/BotonSistema.vue';
import EnlaceSistema from '../ui/EnlaceSistema.vue';
import MarcasDePago from '../ui/MarcasDePago.vue';

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
        <!-- Un `div` y no un `p`: la lista de las marcas no cabe en un párrafo. -->
        <div :style="[PASO.pista, { display: 'flex', flexWrap: 'wrap', alignItems: 'center', justifyContent: 'center', gap: '6px 8px', color: 'var(--text-body)', fontWeight: 'var(--fw-semibold)', textAlign: 'center' }]"><IconoLucide
            name="lock"
            :size="15"
        />{{ t('compra.pagar.pasarela') }}<MarcasDePago
            :marcas="marcas"
            tinta
            en-linea
        /></div>
        <p :style="[PASO.pista, { textAlign: 'center' }]">{{ t('compra.pagar.condiciones') }}<EnlaceSistema
            :href="condicionesHref"
            size="sm"
            underline="always"
            @click="emit('condiciones', $event)"
        >{{ t('compra.pagar.condiciones_enlace') }}</EnlaceSistema>{{ condiciones }}</p>
    </div>
</template>
