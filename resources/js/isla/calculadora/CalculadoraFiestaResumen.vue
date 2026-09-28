<script setup>
/**
 * **El total de la calculadora de la fiesta** (el lado de `Pieza6`, `paginas/piezas-5-6.jsx`): un RECIBO —lo elegido,
 * su desglose si hay algo cobrado aparte y el total del servidor—, lo que se paga hoy (la señal) y el resto el día de la
 * fiesta, la hora extra (`FilaMejora`, entre el total y el botón, como el diseño), la nota de los niños, «Reservar y
 * pagar la señal» apagado hasta tener la línea del servidor, las formas de pago y compartirlo SOLO por WhatsApp (`#833`).
 * PINTA: todo llega hecho en `v`; avisa `cambiar` (la hora extra) y `reservar`. Sin [Jump Club] ni precio de lanzamiento
 * (`#699`, `#761`·3: lo que el panel no sostiene no se dice).
 */
import BotonSistema from '../ui/BotonSistema.vue';
import FilaCompartir from '../ui/FilaCompartir.vue';
import FilaMejora from '../ui/FilaMejora.vue';
import MarcasDePago from '../ui/MarcasDePago.vue';
import ResumenPrecio from '../ui/ResumenPrecio.vue';

defineProps({ v: { type: Object, required: true }, marcas: { type: Array, default: () => [] } });
defineEmits(['cambiar', 'reservar']);
</script>

<template>
    <ResumenPrecio :selection="v.resumen.seleccion" :lines="v.resumen.lineas" :total="v.resumen.total" :incomplete="v.resumen.falta" :incomplete-href="v.resumen.faltaHref" :now="v.resumen.ahora" :later="v.resumen.luego" :note="v.resumen.nota" :cta-note="v.resumen.junto">
        <FilaMejora v-if="v.horaExtra" :titulo="v.horaExtra.titulo" :descripcion="v.horaExtra.descripcion" :precio="v.horaExtra.precio" :total="v.horaExtra.total" :nota="v.horaExtra.nota" :nota-no="v.horaExtra.notaNo" :marcada="v.horaExtra.marcada" :disponible="v.horaExtra.disponible" @cambiar="$emit('cambiar', 'horaExtra', $event)" />
        <template #cta>
            <BotonSistema variant="primary" size="xl" full data-isla-cta :disabled="! v.resumen.listo" :loading="v.resumen.abriendo" @click="$emit('reservar')">{{ v.resumen.boton }}</BotonSistema>
        </template>
        <template #pie><MarcasDePago :marcas="marcas" centro /></template>
    </ResumenPrecio>
    <FilaCompartir :value="v.compartir.value" :items="v.compartir.items" />
</template>
