<script setup>
/**
 * El ACORDEÓN del sistema (`content/Accordion.jsx`): filas que se abren y se cierran, cada pregunta un encabezado de
 * verdad (`headingLevel`: el lector de pantalla salta de una a otra) con su botón `aria-expanded` y su región. Por tokens,
 * así que dentro de la isla cambia solo. Lo usa Mi cuenta para los Ajustes (T5e, `#778`), con varias abiertas a la vez.
 *
 * ⚠️ **Controlado, a diferencia del diseño** (que guarda las abiertas dentro): quien lo usa decide qué está abierto
 * (`abiertos`) y oye `alternar`. Mi cuenta necesita conservarlo al ir a un paso y volver —«la flecha vuelve al mismo
 * punto»—, y el componente se desmonta al cambiar de vista. Sin `columns` ni `hint`: Ajustes no los usa (se portan cuando
 * los pinte alguien).
 *
 * Ranuras: `icono-<id>` y `<id>` (la respuesta) por cada fila.
 */
import { useId } from 'vue';
import IconoLucide from './IconoLucide.vue';

defineProps({
    /** `[{ id, question }]`: la respuesta y el icono van en sus ranuras. */
    items: { type: Array, default: () => [] },
    abiertos: { type: Array, default: () => [] },
    headingLevel: { type: Number, default: 3 },
});
const emit = defineEmits(['alternar']);
const uid = useId();
</script>

<template>
    <div :style="{ display: 'grid', gap: '10px' }">
        <div
            v-for="it in items"
            :key="it.id"
            :style="{ background: 'var(--surface-card)', border: abiertos.includes(it.id) ? '1px solid var(--control-border-strong)' : '1px solid var(--border-subtle)', borderRadius: 'var(--r-lg)', boxShadow: 'none', transition: 'border-color var(--dur-fast) var(--ease-out), box-shadow var(--dur-fast) var(--ease-out)', overflow: 'hidden' }"
        >
            <component
                :is="`h${headingLevel}`"
                :style="{ margin: 0, font: 'inherit' }"
            >
                <button
                    :id="`${uid}-${it.id}-b`"
                    type="button"
                    :aria-expanded="abiertos.includes(it.id)"
                    :aria-controls="`${uid}-${it.id}`"
                    :style="{ display: 'flex', alignItems: 'center', gap: 'var(--space-4)', width: '100%', minHeight: '60px', padding: 'var(--space-4) var(--space-5)', background: 'transparent', border: 'none', cursor: 'pointer', textAlign: 'left', fontFamily: 'var(--font-ui)', fontSize: 'var(--fs-body)', fontWeight: 'var(--fw-bold)', letterSpacing: 'normal', color: 'var(--text-strong)' }"
                    @click="emit('alternar', it.id)"
                >
                    <span
                        v-if="$slots[`icono-${it.id}`]"
                        :style="{ color: 'var(--icon-accent)', display: 'flex' }"
                    ><slot :name="`icono-${it.id}`" /></span>
                    <span :style="{ flex: 1, minWidth: 0 }">{{ it.question }}</span>
                    <IconoLucide
                        name="chevron-down"
                        :size="20"
                        color="var(--text-muted)"
                        :style="{ transform: abiertos.includes(it.id) ? 'rotate(180deg)' : 'none', transition: 'transform var(--dur-base) var(--ease-spring)' }"
                    />
                </button>
            </component>
            <div
                :id="`${uid}-${it.id}`"
                role="region"
                :aria-labelledby="`${uid}-${it.id}-b`"
                :style="{ display: 'grid', gridTemplateRows: abiertos.includes(it.id) ? '1fr' : '0fr', transition: 'grid-template-rows var(--dur-base) var(--ease-out)' }"
            >
                <!-- Plegado, fuera del recorrido del teclado: lo oculto no se enfoca (`inert`; el diseño lo dejaba alcanzable
                     con el tabulador). ⚠️ `|| undefined` y no `false`: un atributo que no se quita vale por estar. -->
                <div
                    :inert="! abiertos.includes(it.id) || undefined"
                    :style="{ minHeight: 0, overflow: 'hidden' }"
                >
                    <div :style="{ padding: '0 var(--space-5) var(--space-5)', paddingLeft: $slots[`icono-${it.id}`] ? 'calc(var(--space-5) + 34px)' : 'var(--space-5)', font: 'var(--type-body)', color: 'var(--text-body)', maxWidth: '72ch' }">
                        <slot :name="it.id" />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
