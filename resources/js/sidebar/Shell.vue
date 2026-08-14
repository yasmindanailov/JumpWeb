<script setup>
import BookingProgress from './steps/BookingProgress.vue';

/**
 * El ARMAZÓN del cajón (Fase 4 · paso 4.3·1).
 *
 * ⚠️ **Existe porque el motor SPA no emitía nada de esto.** Su raíz era `<div class="purchase">` con
 * el paso colgando directamente: sin velo de carga, sin banda de progreso y sin la zona scrollable.
 * Y el diff de árbol no lo veía porque **todos sus casos anclan DENTRO** (`catalog-acc`, `wiz__title`),
 * así que nunca miraba a los hermanos de arriba.
 *
 * Los nodos de aquí no son decoración: son la cadena flex que sostiene el panel
 * (`.sidecart__body` → `.purchase` → `.purchase__scroll` que scrollea + el pie anclado fuera), y el
 * orden entre ellos es contrato —`.bk-paybreakdown + .bk-foot` es un selector de hermano adyacente—.
 *
 * **Es SSR-renderizable a propósito**: recibe TODO por props y no toca `document`, `window` ni el
 * store. Esa es la condición para que el gate pueda compararlo en Node contra el Blade, y es también
 * la razón de que no viva dentro de `Sidebar.vue`, que sí lee `window.Alpine` y el idioma del
 * documento.
 */
defineProps({
    /**
     * ¿Hay una petición en vuelo? Enseña el velo, que en Livewire gobierna `wire:loading.delay`.
     *
     * ⚠️ El nodo se emite SIEMPRE y solo se OCULTA (`v-show`), igual que el Blade, que lo sirve en el
     * HTML y deja que Livewire lo tape. Con `v-if` el árbol de Vue tendría cinco nodos menos que el
     * de Livewire y el diff caería por una diferencia que no es de contrato.
     */
    busy: { type: Boolean, default: false },

    /** La banda de progreso ya compuesta (`progress.js`), o `null` en los pasos que no la llevan. */
    progress: { type: Object, default: null },

    /** El grupo `tickets` del idioma activo. */
    messages: { type: Object, default: () => ({}) },

    /**
     * El grupo `ui`, que hoy tiene una sola clave (`loading`). Va aparte y no mezclado con `tickets`
     * porque son dos grupos distintos de `lang/` y aplanarlos aquí crearía una tercera forma del
     * diccionario que nadie más tiene.
     */
    ui: { type: Object, default: () => ({}) },
});

defineEmits(['back']);
</script>

<template>
    <div class="purchase" data-engine="spa">
        <!--
          Velo de carga (nivel 2 de `docs/sistemas/UI-SPINNER.md`): cubre el panel entero mientras el
          cajón pide datos. Su CSS depende de que `.purchase` sea `position: relative` y de que el
          spinner sea hijo DIRECTO (`.jj-loading > *` lo centra), así que un envoltorio de más lo
          descoloca sin que ninguna clase falte.
        -->
        <div v-show="busy" class="jj-loading">
            <span class="jj-spinner-with-label">
                <span class="jj-spinner jj-spinner--lg" role="status">
                    <span class="jj-spinner__sr">{{ ui.loading ?? '' }}</span>
                </span>
                <span class="jj-spinner-label" aria-hidden="true">{{ ui.loading ?? '' }}</span>
            </span>
        </div>

        <BookingProgress :progress="progress" :messages="messages" @back="$emit('back')" />

        <!--
          TODO el contenido de los pasos vive aquí dentro. El pie dinámico queda FUERA (llega en el
          paso 4.3·2), que es lo que lo deja anclado al fondo del panel en vez de scrollear con el
          contenido.
        -->
        <div class="purchase__scroll">
            <slot />
        </div>
    </div>
</template>
