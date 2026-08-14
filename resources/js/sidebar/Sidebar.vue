<script setup>
import { watch } from 'vue';
import { usePurchaseStore } from './store.js';

/**
 * La raíz del cajón SPA (Fase 4 · paso 4.1).
 *
 * ⚠️ **Este paso va SIN NEGOCIO a propósito** (§4.10): aquí solo está el andamio —el nodo raíz y el
 * puente de señales—. Los pasos se transcriben a partir de 4.2, y cada uno cierra su paridad al
 * final, no toda al final del todo.
 *
 * **El nodo raíz emite `class="purchase"` y eso no es decorativo**: el contrato visual es el ÁRBOL
 * (§4.2), y 90 de los 292 selectores que estilan el cajón son estructurales o dependen del tipo de
 * elemento. Un `<section>` donde había un `<div>` pierde estilo con el contrato de clases cumplido
 * al 100%.
 */
const props = defineProps({
    /** El grupo `tickets` del locale activo, inyectado por el servidor en el montaje (§4.5). */
    messages: { type: Object, default: () => ({}) },
});

const store = usePurchaseStore();

/**
 * El PUENTE de señales hacia fuera del cajón.
 *
 * ⚠️ Sin esto, dos regresiones silenciosas: el panel se queda en `is-catalog` para siempre y los
 * botones de invitado siguen activos durante la identificación. Ninguna de las dos clases aparece en
 * el marcado del cajón —viven en `layout.blade.php` y en `account-context`—, así que no se ve nada
 * roto aquí dentro.
 *
 * Lo escribe EL MOTOR, sea cual sea: en Livewire es un `x-effect` sobre `$wire.step`; aquí, este
 * `watch`. El store de Alpine sigue siendo el punto de encuentro, porque lo consumen once vistas.
 */
watch(
    () => store.step,
    () => {
        const alpine = window.Alpine?.store('purchase');
        if (! alpine) return;

        alpine.setMode(store.mode);
        alpine.identifying = store.identifying;
    },
    { immediate: true },
);
</script>

<template>
    <div class="purchase" data-engine="spa">
        <!--
          Andamio del paso 4.1: el cajón monta, publica sus señales y espera a que 4.2 traiga el
          catálogo. Se deja un texto visible en vez de un hueco vacío para que activar el flag en un
          entorno de prueba diga qué está pasando, en lugar de parecer que el cajón se rompió.
        -->
        <p class="purchase__booting">{{ messages['title'] ?? '' }}</p>
    </div>
</template>
