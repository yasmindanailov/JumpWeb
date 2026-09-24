<script setup>
/**
 * Verificando (`PjcVerificando` del diseño): el banco procesó el pago y se confirma con la pasarela. Se puede
 * cerrar sin perder nada: se avisa por correo y el estado está en Mi cuenta.
 */
import { useTextos } from '../piezas/textos.js';
import { PASO } from './estilos.js';
import CabeceraDesenlace from '../ui/CabeceraDesenlace.vue';

const { t } = useTextos();
</script>

<template>
    <CabeceraDesenlace
        kind="pending"
        :style="{ padding: '32px 0' }"
    >
        <p :style="[PASO.cuerpo, { color: 'var(--text-strong)', fontWeight: 'var(--fw-semibold)' }]">{{ t('compra.verificando.fuerte') }}</p>
        <p :style="[PASO.pista, { marginTop: '8px' }]">{{ t('compra.verificando.texto') }}</p>
    </CabeceraDesenlace>
</template>
