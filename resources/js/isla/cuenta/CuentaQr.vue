<script setup>
/**
 * **Tu QR, la vista** (`paginas/mi-cuenta/cuenta.jsx`, la vista `qr`; T5a de §4.13): la única vista de QR de la web —Mi
 * QR del menú, «Enseñar mi QR» y «Ir a Mi QR» de Listo llevan aquí—. El QR entero (`BloqueQr.vue`, en su forma de
 * vista), con la reserva HOY «Cómo llegar» (la situación 14, T5b) y, llegando de fuera, «Ir a mi cuenta».
 */
import { useTextos } from '../piezas/textos.js';
import AvisoCuenta from './AvisoCuenta.vue';
import BloqueQr from './BloqueQr.vue';
import EnlaceSistema from '../ui/EnlaceSistema.vue';
import IconoLucide from '../ui/IconoLucide.vue';

defineProps({
    qr: { type: Object, required: true },
    renovar: { type: Boolean, default: false },
    renovando: { type: Boolean, default: false },
    irCuenta: { type: Boolean, default: false },
    aviso: { type: String, default: '' },
    sinQr: { type: String, default: '' },
    comoLlegar: { type: String, default: '' },
});
const emit = defineEmits(['guardar', 'preguntar', 'renovar', 'cuenta']);
const { t } = useTextos();
</script>

<template>
    <div :style="{ display: 'grid', gap: '16px', maxWidth: '520px', margin: '0 auto' }">
        <AvisoCuenta
            v-if="aviso"
            :texto="aviso"
        />
        <BloqueQr
            vista
            :qr="qr"
            :renovar="renovar"
            :renovando="renovando"
            :sin-qr="sinQr"
            @guardar="emit('guardar')"
            @preguntar="(si) => emit('preguntar', si)"
            @renovar="emit('renovar')"
        />
        <EnlaceSistema
            v-if="comoLlegar"
            :href="comoLlegar"
            target="_blank"
            rel="noopener"
            :style="{ justifySelf: 'center' }"
        >
            <template #icono><IconoLucide
                name="map-pin"
                :size="17"
            /></template>{{ t('mi_cuenta.qr.como_llegar') }}
        </EnlaceSistema>
        <EnlaceSistema
            v-if="irCuenta"
            :style="{ justifySelf: 'center' }"
            @click="emit('cuenta')"
        >
            <template #icono><IconoLucide
                name="user-round"
                :size="17"
            /></template>{{ t('cuenta.ir') }}
        </EnlaceSistema>
    </div>
</template>
