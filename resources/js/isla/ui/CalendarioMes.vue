<script setup>
/**
 * El calendario de días libres del sistema de diseño (`AvailabilityCalendar.jsx`): el mes entero, con la semana en
 * lunes, «hoy» dicho con la palabra, el punto de la tarifa especial y la leyenda. Los días completos NO se esconden:
 * se tachan (esconderlos deja sin saber si hay algo). Es el de la calculadora de la página (T4d de
 * `specs/isla-y-landing-nueva.md` §4.12); la compra de la isla usa la tira (`TiraDias`).
 * `days` = `[{ date, special?, state? }]` —sin fila, el día está cerrado—; el día elegido, con `v-model`; el mes a la
 * vista, con `month` o, sin él, el del primer día. Lo que decide y sus estilos, en `calendario.js` (`CE-6`; aparte de
 * `piezas.js` para que no viaje con la compra de la isla).
 * ⚠️ Sin «Avísame si se libera» (`onNotify`): ninguna pantalla lo usa todavía; sin él, un día completo no se pulsa.
 * ▶ `months` = 2 (`#830`): el mes siguiente, DEBAJO y con su nombre, y las flechas mueven los dos a la vez (la
 * calculadora a fin de mes, `calculadora/vista.js::mesesDelCalendario`). La compra de la isla lo abre desde «Más fechas».
 */
import { computed, ref, watch } from 'vue';
import IconoLucide from './IconoLucide.vue';
import { estiloDiaCalendario, estiloFlechaMes, estiloHoyCalendario, estiloPuntoCalendario, mesDesplazado, vistaCalendario } from './calendario.js';
import { useTextos } from '../piezas/textos.js';

const props = defineProps({
    month: { type: String, default: null },
    days: { type: Array, default: () => [] },
    modelValue: { type: String, default: null },
    minMonth: { type: String, default: null },
    maxMonth: { type: String, default: null },
    specialLabel: { type: String, default: '' },
    today: { type: String, default: null },
    legend: { type: Boolean, default: true },
    locale: { type: String, default: 'es' },
    months: { type: Number, default: 1 },
});
const emit = defineEmits(['update:modelValue', 'update:month']);
const { t, tp } = useTextos();

const mes = ref(props.month || props.days[0]?.date?.slice(0, 7) || '2026-09');
// Solo se mueve si el mes pedido NO está ya a la vista: elegir un día del mes de abajo no hace saltar la pareja.
watch(() => props.month, (m) => { if (m && m !== mes.value && ! (props.months > 1 && m === mesDesplazado(mes.value, 1))) mes.value = m; });
const sobre = ref(null);
const especial = computed(() => props.specialLabel || t('pieza.calendario.tarifa_especial'));
const vistaDe = (m) => vistaCalendario({ ...props, mes: m, value: props.modelValue, especial: especial.value, sobre: sobre.value }, { t, tp });
const v = computed(() => vistaDe(mes.value));
// Con dos meses, la pareja: el de arriba manda en «atrás»; el de abajo, en «adelante».
const vistas = computed(() => (props.months > 1 ? [v.value, vistaDe(mesDesplazado(mes.value, 1))] : [v.value]));
const alante = computed(() => vistas.value[vistas.value.length - 1].puedeAlante);

function ir(delta) {
    mes.value = mesDesplazado(mes.value, delta);
    emit('update:month', mes.value);
}
</script>

<template>
    <div :style="{ minWidth: 0 }">
        <div :style="{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: '8px', marginBottom: '6px' }">
            <button type="button" :disabled="! v.puedeAtras" :aria-label="t('pieza.calendario.mes_anterior')" :style="estiloFlechaMes(v.puedeAtras)" @click="v.puedeAtras && ir(-1)">
                <IconoLucide name="chevron-left" :size="20" />
            </button>
            <strong aria-live="polite" :style="{ fontFamily: 'var(--font-display)', fontWeight: 'var(--fw-bold)', fontSize: 'var(--fs-h4)', letterSpacing: 'var(--tracking-heading)', color: 'var(--text-strong)', textTransform: 'capitalize' }">{{ v.nombre }} {{ v.anio }}</strong>
            <button type="button" :disabled="! alante" :aria-label="t('pieza.calendario.mes_siguiente')" :style="estiloFlechaMes(alante)" @click="alante && ir(1)">
                <IconoLucide name="chevron-right" :size="20" />
            </button>
        </div>

        <template v-for="(vm, k) in vistas" :key="`${vm.anio}-${vm.nombre}`">
            <strong v-if="k > 0" :style="{ display: 'block', margin: '18px 0 6px', textAlign: 'center', fontFamily: 'var(--font-display)', fontWeight: 'var(--fw-bold)', fontSize: 'var(--fs-h4)', letterSpacing: 'var(--tracking-heading)', color: 'var(--text-strong)', textTransform: 'capitalize' }">{{ vm.nombre }} {{ vm.anio }}</strong>
            <div :style="{ display: 'grid', gridTemplateColumns: 'repeat(7, minmax(0, 1fr))', gap: '4px', marginBottom: '4px' }">
                <span
                    v-for="(inicial, i) in vm.iniciales"
                    :key="i"
                    :style="{ textAlign: 'center', font: 'var(--type-overline)', letterSpacing: 'var(--tracking-overline)', color: 'var(--text-muted)', padding: '6px 0' }"
                >{{ inicial }}</span>
            </div>

            <div :style="{ display: 'grid', gridTemplateColumns: 'repeat(7, minmax(0, 1fr))', gap: '4px' }">
                <template v-for="(c, i) in vm.celdas" :key="c ? c.date : `e${i}`">
                    <span v-if="! c" />
                    <button
                        v-else
                        type="button"
                        :disabled="! c.e.pulsable"
                        :aria-pressed="c.e.activo"
                        :aria-label="c.aria"
                        :style="estiloDiaCalendario(c.e, c.sobre)"
                        @click="c.e.libre && emit('update:modelValue', c.date)"
                        @mouseenter="sobre = c.date"
                        @mouseleave="sobre = null"
                    >{{ c.n }}<span v-if="c.e.hoy" aria-hidden="true" :style="estiloHoyCalendario(c.e)"><span v-if="c.e.especial" :style="estiloPuntoCalendario(c.e)" />{{ t('pieza.calendario.hoy') }}</span><span v-else aria-hidden="true" :style="estiloPuntoCalendario(c.e)" /></button>
                </template>
            </div>
        </template>

        <div v-if="legend" :style="{ display: 'flex', flexWrap: 'wrap', gap: '16px', marginTop: '12px', font: 'var(--type-mono)', color: 'var(--text-muted)' }">
            <span :style="{ display: 'inline-flex', alignItems: 'center', gap: '7px' }"><span :style="{ width: '14px', height: '14px', borderRadius: '4px', border: '1px solid var(--border-subtle)', background: 'var(--control-bg)' }" />{{ t('pieza.calendario.libre') }}</span>
            <span :style="{ display: 'inline-flex', alignItems: 'center', gap: '7px' }"><span :style="{ width: '6px', height: '6px', borderRadius: 'var(--r-pill)', background: 'var(--isla-especial)' }" />{{ especial.toLowerCase() }}</span>
            <span :style="{ display: 'inline-flex', alignItems: 'center', gap: '7px', textDecoration: 'line-through' }">{{ t('pieza.calendario.completo') }}</span>
        </div>
    </div>
</template>
