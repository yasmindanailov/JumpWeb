<script setup>
/**
 * **Cambiar o cancelar** (`paginas/mi-cuenta/pantallas.jsx`, `PmcCambiar`; T5b de §4.13): solo hace fácil PEDIRLO —lo
 * hace el personal—. Dentro del plazo, qué se puede y hasta cuándo (y si se devuelve la señal, solo si el producto lo
 * promete, `#775`); fuera, su aviso. El mensaje va ya escrito, y se ENSEÑA: quien lo manda sabe lo que manda. La
 * acción de la capa es «Escribirnos por WhatsApp»; aquí queda «Llamar». Lo decide `reservas.js::cambiarDe`.
 */
import { useTextos } from '../piezas/textos.js';
import { PASO } from '../compra/estilos.js';
import PasoCompra from '../compra/PasoCompra.vue';
import AvisoDestacado from '../ui/AvisoDestacado.vue';
import EnlaceSistema from '../ui/EnlaceSistema.vue';
import IconoLucide from '../ui/IconoLucide.vue';

defineProps({
    cambiar: { type: Object, required: true },
});
const { t } = useTextos();
</script>

<template>
    <PasoCompra :titulo="t('mi_cuenta.cambiar.titulo')">
        <AvisoDestacado
            v-if="cambiar.fuera"
            tone="warn"
            size="sm"
            role="status"
        >
            <template #icono><IconoLucide
                name="clock-alert"
                :size="18"
            /></template>{{ cambiar.texto }}
        </AvisoDestacado>
        <p
            v-else
            :style="PASO.cuerpo"
        >{{ cambiar.texto }}</p>
        <figure :style="{ margin: 0, display: 'grid', gap: '8px' }">
            <figcaption :style="PASO.pista">{{ t('mi_cuenta.cambiar.mensaje_titulo') }}</figcaption>
            <blockquote :style="{ margin: 0, padding: '12px 14px', borderRadius: 'var(--r-md) var(--r-md) var(--r-md) 4px', background: 'var(--control-bg)', border: '1px solid var(--border-subtle)', fontFamily: 'var(--font-ui)', fontSize: 'var(--fs-body-sm)', lineHeight: 1.5, color: 'var(--text-strong)' }">{{ cambiar.mensaje }}</blockquote>
        </figure>
        <EnlaceSistema
            v-if="cambiar.llamar"
            :href="cambiar.llamar.href"
            :style="{ justifySelf: 'start' }"
        >
            <template #icono><IconoLucide
                name="phone"
                :size="17"
            /></template>{{ cambiar.llamar.texto }}
        </EnlaceSistema>
    </PasoCompra>
</template>
