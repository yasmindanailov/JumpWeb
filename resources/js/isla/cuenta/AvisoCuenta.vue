<script setup>
/**
 * La confirmación de Mi cuenta («QR guardado en el móvil», «Tu QR se ha renovado»), dentro de la capa y arriba: con
 * la capa abierta la isla no puede crecer a «Aviso». No se va sola (WCAG 2.2.1): se queda hasta salir de su vista
 * (`paginas/mi-cuenta/cuenta.jsx`, su `avisoEl`).
 *
 * Desde la T5e (`#778`) también dice lo que NO salió (un interruptor que no se guardó, la sesión que no se cerró):
 * `tono="danger"`, como aviso (`role="alert"`) y con su icono; lo que salió sigue siendo `status`.
 */
import AvisoDestacado from '../ui/AvisoDestacado.vue';
import IconoLucide from '../ui/IconoLucide.vue';

defineProps({
    texto: { type: String, required: true },
    tono: { type: String, default: 'success' },
});
</script>

<template>
    <div
        :role="tono === 'danger' ? 'alert' : 'status'"
        :style="{ position: 'sticky', top: 0, zIndex: 2, marginBottom: '12px' }"
    >
        <AvisoDestacado
            :tone="tono"
            size="sm"
            :title="texto"
        >
            <template #icono><IconoLucide
                :name="tono === 'danger' ? 'circle-alert' : 'check'"
                :size="18"
            /></template>
        </AvisoDestacado>
    </div>
</template>
