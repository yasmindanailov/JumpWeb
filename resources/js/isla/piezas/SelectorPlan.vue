<script setup>
/**
 * El selector de plan (`PlanPicker` + `PlanOption` del diseño): vive en la isla y solo ahí. Abierto desde
 * «Reservar para hoy», no repite la opción [Hoy]: ya lo ha dicho el botón.
 */
import { computed, ref } from 'vue';

const props = defineProps({
    plans: { type: Object, required: true },
    fromToday: { type: Boolean, default: false },
});
const emit = defineEmits(['elegir']);

const opciones = computed(() => props.plans.options.filter((o) => !(props.fromToday && o.today)));
const hover = ref(null);
</script>

<template>
    <div>
        <button
            v-for="o in opciones"
            :key="o.title"
            type="button"
            :style="{
                display: 'flex', alignItems: 'center', gap: '12px', width: '100%', boxSizing: 'border-box', padding: '12px',
                marginBottom: '8px', border: `1px solid ${o.highlight ? 'var(--isla-vivo)' : 'rgba(255,255,255,0.16)'}`,
                background: o.highlight ? 'var(--isla-destacado-fondo)' : hover === o.title ? 'rgba(255,255,255,0.12)' : 'rgba(255,255,255,0.06)',
                borderRadius: 'var(--r-md)', color: 'var(--isla-sobre)', textAlign: 'left', cursor: 'pointer', transition: 'var(--t-hover)',
            }"
            @click="emit('elegir', o)"
            @mouseenter="hover = o.title"
            @mouseleave="hover = null"
        >
            <span :style="{ flex: 1, minWidth: 0 }">
                <b :style="{ display: 'block', fontFamily: 'var(--font-ui)', fontWeight: 'var(--fw-bold)', fontSize: '14.5px' }">{{ o.title }}</b>
                <span
                    v-if="o.note"
                    :style="{ display: 'block', fontFamily: 'var(--font-ui)', fontSize: '12.5px', color: 'var(--text-muted)', marginTop: '1px' }"
                >{{ o.note }}</span>
            </span>
            <em
                v-if="o.price"
                :style="{ fontStyle: 'normal', fontFamily: 'var(--font-display)', fontWeight: 'var(--fw-black)', fontSize: '15px', whiteSpace: 'nowrap' }"
            >{{ o.price }}</em>
        </button>
    </div>
</template>
