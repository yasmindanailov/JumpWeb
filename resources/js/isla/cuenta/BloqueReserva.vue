<script setup>
/**
 * **Tu próxima reserva** —y «Tu reserva», la de «Otras reservas» abierta— (`paginas/mi-cuenta/bloques.jsx`, `PmcProxima`;
 * T5b de §4.13). Se entiende en tres segundos: el día en su hoja, la hora grande, qué, y debajo lo que falta saber
 * (lo comprado, la señal, el plazo; `reservas.js::lineasDe`). Una acción de bloque, «Cambiar o cancelar»; «Ver el
 * pago» despliega el resumen en el sitio (`reservas.js::pagoDe`, del libro de la reserva).
 *
 * `oculto`: en «Tu reserva» el título ya está en la banda de la capa, y no se repite.
 */
import { ref } from 'vue';
import { useTextos } from '../piezas/textos.js';
import { CUENTA } from './estilos.js';
import LineaCuenta from './LineaCuenta.vue';
import TarjetaReserva from '../ui/TarjetaReserva.vue';
import BotonSistema from '../ui/BotonSistema.vue';
import EnlaceSistema from '../ui/EnlaceSistema.vue';
import ResumenPrecio from '../ui/ResumenPrecio.vue';
import IconoLucide from '../ui/IconoLucide.vue';

defineProps({
    idBloque: { type: String, default: 'proxima' },
    titulo: { type: String, required: true },
    oculto: { type: Boolean, default: false },
    tarjeta: { type: Object, required: true },
    lineas: { type: Array, default: () => [] },
    pago: { type: Object, required: true },
});
const emit = defineEmits(['cambiar']);
const { t } = useTextos();
const abierto = ref(false);
</script>

<template>
    <section
        :id="idBloque"
        :aria-labelledby="`${idBloque}-t`"
        :style="CUENTA.bloque"
    >
        <h2
            :id="`${idBloque}-t`"
            :style="oculto ? CUENTA.oculto : CUENTA.h2"
        >{{ titulo }}</h2>
        <TarjetaReserva v-bind="tarjeta">
            <template
                v-if="lineas.length"
                #default
            >
                <LineaCuenta
                    v-for="(l, i) in lineas"
                    :key="i"
                    :icon="l.icon"
                    :fuerte="Boolean(l.fuerte)"
                >{{ l.texto }}</LineaCuenta>
            </template>
            <template #acciones>
                <BotonSistema
                    variant="outline"
                    @click="emit('cambiar')"
                >{{ t('mi_cuenta.proxima.cambiar') }}</BotonSistema>
                <EnlaceSistema
                    :aria-expanded="abierto ? 'true' : 'false'"
                    @click="abierto = ! abierto"
                >
                    <template #icono><IconoLucide
                        :name="abierto ? 'chevron-up' : 'receipt'"
                        :size="17"
                    /></template>{{ t('mi_cuenta.proxima.ver_pago') }}
                </EnlaceSistema>
            </template>
        </TarjetaReserva>
        <ResumenPrecio
            v-if="abierto"
            size="md"
            :lines="pago.lines"
            :total="pago.total"
            :total-label="pago.totalLabel"
            :now="pago.now"
            :later="pago.later"
            :note="pago.note"
        />
    </section>
</template>
