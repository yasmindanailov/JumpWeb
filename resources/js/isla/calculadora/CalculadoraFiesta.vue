<script setup>
/**
 * **La calculadora de la fiesta de la página** (la pieza 6 de Cumpleaños, T6b·3 de `specs/isla-y-landing-nueva.md`
 * §4.18): las preguntas donde se monta —la columna principal de la pieza— y el total en la columna del lado que la
 * página declara (`lado`), dentro de la MISMA retícula de la página, como la de entradas. Lo secundario («Niños de dos
 * edades») es de la página (Blade). PINTA: la vista llega hecha en `v`; `cambiar` y `reservar` suben a quien la monta.
 */
import CalculadoraFiestaPreguntas from './CalculadoraFiestaPreguntas.vue';
import CalculadoraFiestaResumen from './CalculadoraFiestaResumen.vue';

defineProps({
    v: { type: Object, required: true },
    lado: { type: [String, Object], required: true },
    marcas: { type: Array, default: () => [] },
});
defineEmits(['cambiar', 'reservar']);
</script>

<template>
    <CalculadoraFiestaPreguntas :v="v" @cambiar="(campo, valor) => $emit('cambiar', campo, valor)" />
    <Teleport :to="lado"><CalculadoraFiestaResumen :v="v" :marcas="marcas" @cambiar="(campo, valor) => $emit('cambiar', campo, valor)" @reservar="$emit('reservar')" /></Teleport>
</template>
