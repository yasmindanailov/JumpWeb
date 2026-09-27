<script setup>
/**
 * EL DÍA de la compra de la isla (`#830`): la tira del sistema (`TiraDias`, dos semanas) y, si el motor vende más allá,
 * «Más fechas», que abre debajo el calendario de meses de la página (`CalendarioMes`, el de la calculadora) —el owner,
 * 27-09: la tira se quedaba en una semana y el motor vende cinco meses; en una fiesta, que se reserva con semanas, no
 * había forma de llegar—. Elegir en él lo cierra, y el día, si cae fuera de la tira, entra al final de ella
 * (`vista.js::tiraDias`). Qué días y qué meses, de `calendarioDeTira`; aquí solo se pinta.
 * ⚠️ El calendario se carga AL ABRIRLO (`defineAsyncComponent`): dentro de la compra pesaba +5,6 KiB en su descarga
 * (medido en la T4d·4, `ui/calendario.js`), y casi nadie lo abre.
 */
import { defineAsyncComponent, ref } from 'vue';
import TiraDias from '../ui/TiraDias.vue';
import EnlaceSistema from '../ui/EnlaceSistema.vue';
import IconoLucide from '../ui/IconoLucide.vue';
import { useTextos } from '../piezas/textos.js';

const CalendarioMes = defineAsyncComponent(() => import('../ui/CalendarioMes.vue'));

defineProps({
    label: { type: String, default: '' },
    days: { type: Array, default: () => [] },
    modelValue: { type: String, default: null },
    calendario: { type: Object, default: null },
});
const emit = defineEmits(['update:modelValue']);
const { t } = useTextos();

const abierto = ref(false);
const elegir = (dia) => { abierto.value = false; emit('update:modelValue', dia); };
</script>

<template>
    <div :style="{ display: 'grid', gap: '10px', minWidth: 0 }">
        <TiraDias
            :label="label"
            :days="days"
            :model-value="modelValue"
            @update:model-value="emit('update:modelValue', $event)"
        />
        <EnlaceSistema
            v-if="calendario"
            :aria-expanded="abierto ? 'true' : 'false'"
            :style="{ justifySelf: 'start' }"
            @click="abierto = !abierto"
        >
            <!-- Abre y cierra DEBAJO: el chevrón lo dice, y ya viaja con la compra (`ui/iconos.js`: un icono nuevo pesa). -->
            <template #icono><IconoLucide
                :name="abierto ? 'chevron-up' : 'chevron-down'"
                :size="18"
            /></template>{{ t(abierto ? 'compra.cuando.menos_fechas' : 'compra.cuando.mas_fechas') }}
        </EnlaceSistema>
        <CalendarioMes
            v-if="calendario && abierto"
            :month="calendario.month"
            :min-month="calendario.minMonth"
            :max-month="calendario.maxMonth"
            :days="calendario.days"
            :today="calendario.today"
            :model-value="modelValue"
            :locale="calendario.locale"
            :legend="false"
            @update:model-value="elegir"
        />
    </div>
</template>
