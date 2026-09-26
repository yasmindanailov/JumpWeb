<script setup>
/**
 * Una línea de la lista de tareas, del sistema de diseño (`TaskCard.jsx`, su `variant="row"`; T5c): nombre y plazo o
 * estado, y se toca entera. Hecha, lleva el visto bueno, se apaga y dice «Hecho» (`cta`): ya no pide nada.
 *
 * ⚠️ En el diseño es una variante de la tarjeta; aquí, su propio componente: la tarjeta viaja con «Listo» de la compra,
 * que se descarga en cada página con la isla, y la fila solo la pinta Mi cuenta (medido: +2 KiB a los pasos de la compra).
 */
import { ref } from 'vue';
import IconoLucide from './IconoLucide.vue';

defineProps({
    icon: { type: String, default: 'circle-check' },
    title: { type: String, default: '' },
    note: { type: String, default: '' },
    cta: { type: String, default: '' },
    done: { type: Boolean, default: false },
    onClick: { type: Function, default: null },
});
const encima = ref(false);
</script>

<template>
    <component
        :is="onClick ? 'button' : 'div'"
        :type="onClick ? 'button' : undefined"
        :style="{ display: 'flex', alignItems: 'center', gap: '12px', width: '100%', boxSizing: 'border-box', minHeight: '52px', padding: '8px 10px', border: 'none', borderRadius: 'var(--r-md)', background: onClick && encima ? 'var(--control-bg-hover)' : 'transparent', textAlign: 'left', font: 'inherit', color: 'inherit', cursor: onClick ? 'pointer' : 'default', transition: 'var(--t-hover)' }"
        @click="onClick ? onClick($event) : undefined"
        @mouseenter="encima = true"
        @mouseleave="encima = false"
    >
        <span
            aria-hidden="true"
            :style="{ display: 'inline-flex', alignItems: 'center', justifyContent: 'center', flex: '0 0 auto', width: '28px', height: '28px', borderRadius: '50%', background: done ? 'var(--notice-success-bg)' : 'var(--notice-info-bg)', color: done ? 'var(--text-positive)' : 'var(--icon-accent)' }"
        ><IconoLucide
            :name="done ? 'check' : icon"
            :size="15"
        /></span>
        <span :style="{ display: 'grid', gap: '1px', minWidth: 0, flex: 1 }">
            <span :style="{ fontFamily: 'var(--font-ui)', fontSize: 'var(--fs-body-sm)', fontWeight: 'var(--fw-semibold)', color: done ? 'var(--text-muted)' : 'var(--text-strong)' }">{{ title }}</span>
            <span
                v-if="note"
                :style="{ fontFamily: 'var(--font-ui)', fontSize: 'var(--fs-caption)', color: 'var(--text-muted)' }"
            >{{ note }}</span>
        </span>
        <span
            v-if="done && cta"
            :style="{ fontFamily: 'var(--font-ui)', fontSize: 'var(--fs-caption)', fontWeight: 'var(--fw-bold)', color: 'var(--text-positive)' }"
        >{{ cta }}</span>
        <span
            v-if="onClick"
            aria-hidden="true"
            :style="{ display: 'inline-flex', color: 'var(--text-muted)' }"
        ><IconoLucide
            name="chevron-right"
            :size="17"
        /></span>
    </component>
</template>
