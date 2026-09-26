<script setup>
/**
 * Una tarea antes de venir, del sistema de diseño (`TaskCard.jsx`): añadir a los hijos, el formulario de
 * invitados, los calcetines. La misma en Listo de la compra y en «Antes de venir» de Mi cuenta. Todas
 * opcionales: se dicen como una ayuda, nunca como un deber. Con `due` lleva su plazo real arriba (y con `overline`,
 * «SIGUIENTE» delante, T5c); con `@click` y sin la ranura `acciones`, se toca entera (y su acción se lee en color de
 * enlace, `cta`). Sirve sobre claro y sobre tinta. La fila de la lista de tareas (`variant="row"` del diseño) es
 * `FilaTarea.vue`: solo la pinta Mi cuenta, y aquí viajaría con «Listo» de la compra.
 */
import { computed, ref, useSlots } from 'vue';
import IconoLucide from './IconoLucide.vue';

const props = defineProps({
    icon: { type: String, default: 'circle-check' },
    title: { type: String, default: '' },
    overline: { type: String, default: '' },
    due: { type: String, default: '' },
    steps: { type: Array, default: null },
    note: { type: String, default: '' },
    cta: { type: String, default: '' },
    done: { type: Boolean, default: false },
    // Declarado, y no escuchado con `defineEmits`, para saber si hay quien lo escuche: es lo que decide «entera».
    onClick: { type: Function, default: null },
});
const slots = useSlots();

const encima = ref(false);
const entera = computed(() => Boolean(props.onClick) && ! slots.acciones);
</script>

<template>
    <component
        :is="entera ? 'button' : 'div'"
        :type="entera ? 'button' : undefined"
        :style="{ display: 'grid', gridTemplateColumns: `auto minmax(0,1fr)${entera ? ' auto' : ''}`, gap: '12px', alignItems: 'start', width: '100%', boxSizing: 'border-box', padding: '14px', textAlign: 'left', font: 'inherit', color: 'inherit', borderRadius: 'var(--r-md)', border: '1px solid var(--border-subtle)', background: entera && encima ? 'var(--control-bg-hover)' : 'var(--surface-card)', cursor: entera ? 'pointer' : 'default', transition: 'var(--t-hover)' }"
        @click="entera ? onClick($event) : undefined"
        @mouseenter="encima = true"
        @mouseleave="encima = false"
    >
        <span
            aria-hidden="true"
            :style="{ display: 'inline-flex', alignItems: 'center', justifyContent: 'center', width: '36px', height: '36px', borderRadius: '50%', background: 'var(--notice-info-bg)', color: done ? 'var(--text-positive)' : 'var(--icon-accent)' }"
        ><IconoLucide
            :name="done ? 'check' : icon"
            :size="18"
        /></span>
        <span :style="{ display: 'grid', gap: '8px', minWidth: 0 }">
            <span
                v-if="overline || due"
                :style="{ display: 'flex', flexWrap: 'wrap', gap: '4px 10px', fontFamily: 'var(--font-ui)', fontSize: 'var(--fs-caption)', fontWeight: 'var(--fw-bold)' }"
            >
                <span
                    v-if="overline"
                    :style="{ color: 'var(--text-strong)', textTransform: 'uppercase', letterSpacing: '0.06em' }"
                >{{ overline }}</span>
                <span
                    v-if="due"
                    :style="{ color: 'var(--text-low)' }"
                >{{ due }}</span>
            </span>
            <strong
                v-if="title"
                :style="{ fontFamily: 'var(--font-ui)', fontSize: 'var(--fs-body-sm)', fontWeight: 'var(--fw-bold)', lineHeight: 1.4, color: 'var(--text-strong)' }"
            >{{ title }}</strong>
            <span
                v-if="$slots.default"
                :style="{ fontFamily: 'var(--font-ui)', fontSize: 'var(--fs-body-sm)', lineHeight: 1.5, color: 'var(--text-body)', textWrap: 'pretty' }"
            ><slot /></span>
            <ol
                v-if="steps && steps.length"
                :style="{ margin: 0, paddingLeft: '20px', display: 'grid', gap: '6px', fontFamily: 'var(--font-ui)', fontSize: 'var(--fs-body-sm)', lineHeight: 1.5, color: 'var(--text-body)' }"
            >
                <li
                    v-for="(x, i) in steps"
                    :key="i"
                >{{ x }}</li>
            </ol>
            <span
                v-if="$slots.acciones"
                :style="{ display: 'flex', flexWrap: 'wrap', gap: '8px' }"
            ><slot name="acciones" /></span>
            <span
                v-if="note"
                :style="{ fontFamily: 'var(--font-ui)', fontSize: 'var(--fs-caption)', lineHeight: 1.45, color: 'var(--text-muted)' }"
            >{{ note }}</span>
            <span
                v-if="entera && cta"
                :style="{ fontFamily: 'var(--font-ui)', fontSize: 'var(--fs-body-sm)', fontWeight: 'var(--fw-bold)', color: 'var(--text-link)' }"
            >{{ cta }}</span>
        </span>
        <span
            v-if="entera"
            aria-hidden="true"
            :style="{ display: 'inline-flex', alignSelf: 'center', color: 'var(--text-muted)' }"
        ><IconoLucide
            name="chevron-right"
            :size="18"
        /></span>
    </component>
</template>
