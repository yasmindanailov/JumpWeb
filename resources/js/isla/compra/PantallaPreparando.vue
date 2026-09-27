<script setup>
/**
 * **La compra, preparándose** (`#785`): lo que se ve mientras va SOLA al paso que toca —«Reservar y pagar» de la
 * calculadora, la vuelta de Google—, en vez de la pantalla 0 (el owner: «al usuario le da tiempo a presionar el botón
 * continuar o editar algo, y es un UX malo»). Solo el esqueleto de un paso —su titular y un recibo—, sin titular de
 * verdad: aún no se sabe si toca «Tus datos» o «Pagar», y el que se escribiera podría mentir. Sin nada que tocar; si
 * el servidor responde antes de 300ms, ni se ve (`EsqueletoCarga`).
 */
import { useTextos } from '../piezas/textos.js';
import PasoCompra from './PasoCompra.vue';
import EsqueletoCarga from '../ui/EsqueletoCarga.vue';

const { t } = useTextos();
</script>

<template>
    <PasoCompra>
        <div
            role="status"
            :aria-label="t('pieza.cargando')"
            :style="{ display: 'grid', gap: '24px' }"
        >
            <EsqueletoCarga
                kind="title"
                width="60%"
            />
            <EsqueletoCarga
                kind="card"
                height="176px"
            />
        </div>
    </PasoCompra>
</template>
