<script setup>
/**
 * **Otras reservas**, con el historial plegado (`paginas/mi-cuenta/bloques.jsx`, `PmcOtras`; T5b de §4.13): una línea
 * por reserva viva, que se toca entera y abre «Tu reserva»; el historial, detrás de «Ver el historial», con su estado
 * (pasada, cancelada, devuelta, sin pagar) y «Ver más» si quedan.
 *
 * ⚠️ El diseño no pinta el bloque sin reservas vivas (`if (!otras.length) return null`), y con él se iba el historial:
 *    su propia cuenta de prueba «Sin reservas» tiene historial y no se podía ver. Aquí sale si hay una cosa u otra.
 */
import { ref } from 'vue';
import { useTextos } from '../piezas/textos.js';
import { CUENTA } from './estilos.js';
import TarjetaReserva from '../ui/TarjetaReserva.vue';
import EnlaceSistema from '../ui/EnlaceSistema.vue';
import IconoLucide from '../ui/IconoLucide.vue';

defineProps({
    otras: { type: Array, default: () => [] },
    historial: { type: Array, default: () => [] },
    hayMas: { type: Boolean, default: false },
    cargandoMas: { type: Boolean, default: false },
});
const emit = defineEmits(['abrir', 'mas']);
const { t } = useTextos();
const ver = ref(false);
</script>

<template>
    <section
        v-if="otras.length || historial.length"
        id="otras"
        aria-labelledby="otras-t"
        :style="CUENTA.bloque"
    >
        <h2
            id="otras-t"
            :style="CUENTA.h2"
        >{{ t('mi_cuenta.otras.titulo') }}</h2>
        <div
            v-if="otras.length"
            :style="{ display: 'grid', gap: '8px' }"
        >
            <TarjetaReserva
                v-for="o in otras"
                :key="o.id"
                variant="row"
                clicable
                v-bind="o.tarjeta"
                @click="emit('abrir', o.id)"
            />
        </div>
        <template v-if="historial.length">
            <EnlaceSistema
                :aria-expanded="ver ? 'true' : 'false'"
                :style="{ justifySelf: 'start' }"
                @click="ver = ! ver"
            >
                <template #icono><IconoLucide
                    :name="ver ? 'chevron-up' : 'history'"
                    :size="17"
                /></template>{{ ver ? t('mi_cuenta.otras.ocultar') : t('mi_cuenta.otras.historial') }}
            </EnlaceSistema>
            <div
                v-if="ver"
                :style="{ display: 'grid', gap: '8px' }"
            >
                <TarjetaReserva
                    v-for="(h, i) in historial"
                    :key="i"
                    variant="row"
                    v-bind="h"
                />
                <EnlaceSistema
                    v-if="hayMas"
                    :disabled="cargandoMas"
                    :style="{ justifySelf: 'start' }"
                    @click="emit('mas')"
                >{{ t('mi_cuenta.otras.mas') }}</EnlaceSistema>
            </div>
        </template>
    </section>
</template>
