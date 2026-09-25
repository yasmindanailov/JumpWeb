<script setup>
/**
 * **La calculadora de la página de una zona** (la pieza 3 de Kids y Jump con su widget; T4d de
 * `specs/isla-y-landing-nueva.md` §4.12): las preguntas donde se monta —la columna principal de la pieza— y el
 * total en la columna del lado que la página declara (`lado`, un selector), dentro de la MISMA retícula de la
 * página, como en el diseño. La tabla de tarifas, el aviso de grupo y las dudas son de la página (Blade).
 * PINTA: la vista llega hecha en `v`; `cambiar` y `reservar` suben a quien la monta.
 */
import CalculadoraPreguntas from './CalculadoraPreguntas.vue';
import CalculadoraResumen from './CalculadoraResumen.vue';

defineProps({
    v: { type: Object, required: true },
    lado: { type: String, required: true },
});
defineEmits(['cambiar', 'reservar']);
</script>

<template>
    <CalculadoraPreguntas :v="v" @cambiar="(campo, valor) => $emit('cambiar', campo, valor)" />
    <Teleport :to="lado"><CalculadoraResumen :v="v" @reservar="$emit('reservar')" /></Teleport>
</template>
