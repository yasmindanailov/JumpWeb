<script setup>
/**
 * La confirmación de Mi cuenta («QR guardado en el móvil», «Tu QR se ha renovado»), dentro de la capa y arriba: con
 * la capa abierta la isla no puede crecer a «Aviso». No se va sola (WCAG 2.2.1): se queda hasta salir de su vista
 * (`paginas/mi-cuenta/cuenta.jsx`, su `avisoEl`).
 */
import AvisoDestacado from '../ui/AvisoDestacado.vue';
import IconoLucide from '../ui/IconoLucide.vue';

defineProps({
    texto: { type: String, required: true },
});
</script>

<template>
    <div
        role="status"
        :style="{ position: 'sticky', top: 0, zIndex: 2, marginBottom: '12px' }"
    >
        <AvisoDestacado
            tone="success"
            size="sm"
            :title="texto"
        >
            <template #icono><IconoLucide
                name="check"
                :size="18"
            /></template>
        </AvisoDestacado>
    </div>
</template>
