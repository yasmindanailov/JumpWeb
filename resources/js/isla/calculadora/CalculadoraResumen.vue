<script setup>
/**
 * **El total de la calculadora de la página** (el lado de `PrecioEntradas`, `paginas/entradas/pieza-3.jsx`): un
 * RECIBO —lo elegido, sus líneas y el total del servidor—, «Reservar y pagar» apagado hasta tener la línea del
 * servidor (`listo`: con día y hora; el enlace lleva a lo que falta), la línea de la cuenta y el descargo antes del botón, y compartirlo por WhatsApp. PINTA:
 * todo llega hecho en `v.resumen` y `v.compartir`; avisa `reservar`.
 * Sin JumpPoints, [Jump Club] ni [Bono] (`#699`: corchetes apagados, sin hueco).
 */
import BotonSistema from '../ui/BotonSistema.vue';
import FilaCompartir from '../ui/FilaCompartir.vue';
import IconoLucide from '../ui/IconoLucide.vue';
import ResumenPrecio from '../ui/ResumenPrecio.vue';
import { LINEA, LINEA_ICONO } from './estilos.js';

defineProps({ v: { type: Object, required: true } });
defineEmits(['reservar']);
</script>

<template>
    <ResumenPrecio :selection="v.resumen.seleccion" :lines="v.resumen.lineas" :total="v.resumen.total" :incomplete="v.resumen.falta" :incomplete-href="v.resumen.faltaHref" :cta-note="v.resumen.junto">
        <p :style="LINEA"><span :style="LINEA_ICONO"><IconoLucide name="qr-code" :size="16" /></span><span>{{ v.resumen.nota }}</span></p>
        <template #cta>
            <BotonSistema variant="primary" size="xl" full :disabled="! v.resumen.listo" @click="$emit('reservar')">{{ v.resumen.boton }}</BotonSistema>
        </template>
    </ResumenPrecio>
    <FilaCompartir :value="v.compartir.value" :items="v.compartir.items" />
</template>
