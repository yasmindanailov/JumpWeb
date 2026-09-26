<script setup>
/**
 * La confirmación de Mi cuenta («QR guardado en el móvil», «Tu QR se ha renovado»), dentro de la capa y arriba: con
 * la capa abierta la isla no puede crecer a «Aviso». No se va sola (WCAG 2.2.1): se queda hasta salir de su vista
 * (`paginas/mi-cuenta/cuenta.jsx`, su `avisoEl`).
 *
 * Desde la T5e (`#778`) también dice lo que NO salió (un interruptor que no se guardó, la sesión que no se cerró):
 * `tono="danger"`, como aviso (`role="alert"`) y con su icono; lo que salió sigue siendo `status`. Y desde la T5e·2
 * (`#779`), lo que solo informa (`info`, la vuelta de Google cancelada), con el suyo.
 *
 * ⚠️ **Pegado arriba y con el fondo de la isla debajo, DOS veces**: el color de un aviso sobre tinta es translúcido, y
 * al bajar por Mi cuenta el texto que pasaba por debajo se leía a través (lo enseñó la captura de la sonda; el mockup
 * tenía lo mismo). `--ink-surface` es cristal (al 94 %): con una capa, el blanco de debajo aún se leía; con dos, queda al
 * 99,6 % y del mismo color, sin inventar uno.
 */
import { computed } from 'vue';
import AvisoDestacado from '../ui/AvisoDestacado.vue';
import IconoLucide from '../ui/IconoLucide.vue';

const props = defineProps({
    texto: { type: String, required: true },
    tono: { type: String, default: 'success' },
});
const ICONOS = { success: 'check', danger: 'circle-alert', info: 'info', warn: 'triangle-alert' };
const icono = computed(() => ICONOS[props.tono] ?? 'check');
</script>

<template>
    <div
        :role="tono === 'danger' ? 'alert' : 'status'"
        :style="{ position: 'sticky', top: 0, zIndex: 2, marginBottom: '12px', borderRadius: 'var(--r-md)', background: 'linear-gradient(var(--ink-surface), var(--ink-surface)), var(--ink-surface)' }"
    >
        <AvisoDestacado
            :tone="tono"
            size="sm"
            :title="texto"
        >
            <template #icono><IconoLucide
                :name="icono"
                :size="18"
            /></template>
        </AvisoDestacado>
    </div>
</template>
