<script setup>
/**
 * La salida al banco de la compra de la isla (T3e·3): el formulario FIRMADO que devuelve el servidor al crear el
 * pedido (`pay.js::gatewayForm`), espejo de `sidebar/steps/RedirectStep.vue`.
 *
 * ⚠️ Los campos se emiten TAL CUAL: la firma cubre esos valores exactos, y renombrar o normalizar uno hace que la
 * pasarela rechace el cobro (SIS0042) con el pedido ya creado y el aforo retenido. `target="_top"`: la pasarela
 * nunca dentro de un marco. Se envía solo al llegar —80 ms, como el cajón: un ciclo para pintar «Te llevamos a la
 * pasarela…»— y otra vez cada vez que sube `intentos` («Continuar al pago», si no saltó).
 */
import { onMounted, ref, watch } from 'vue';

const props = defineProps({
    form: { type: Object, required: true },
    intentos: { type: Number, default: 0 },
});
const el = ref(null);
const enviar = () => el.value?.submit();

onMounted(() => setTimeout(enviar, 80));
watch(() => props.intentos, enviar);
</script>

<template>
    <form
        ref="el"
        hidden
        :action="form.url"
        :method="form.method"
        target="_top"
    >
        <input
            v-for="campo in form.fields"
            :key="campo.name"
            type="hidden"
            :name="campo.name"
            :value="campo.value"
        >
    </form>
</template>
