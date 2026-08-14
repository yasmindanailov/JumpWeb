<script setup>
import BookingProgress from './steps/BookingProgress.vue';
import Foot from './steps/Foot.vue';
import PausedNotice from './steps/PausedNotice.vue';

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

    /**
     * El pie ya compuesto (`foot.js`), o `null`.
     *
     * ⚠️ `null` es un estado real y no un descuido: en el catálogo con la cesta vacía y en la cesta
     * vacía el servidor **no emite pie**. Un motor que pintara la barra igual enseñaría «0,00 €»
     * donde la web no enseña nada.
     */
    footer: { type: Object, default: null },

    /**
     * El aviso de reservas EN PAUSA ya compuesto (`paused.js`), o `null`.
     *
     * ⚠️ **Cuando llega, apaga TRES bloques además de sustituir el contenido**: la banda de progreso,
     * el pie y la banda de desglose del pago. En el Blade la misma condición gobierna los cuatro
     * sitios, y transcribir solo el contenido dejaría un «Ir a pagar» vivo sobre un aviso que dice
     * que no se puede comprar — que es exactamente la divergencia que 4.3·2 dejó abierta.
     *
     * ⚠️ Y la guarda vive AQUÍ y no en `buildFooter()`/`buildProgress()` a propósito: el servidor
     * sigue componiendo los dos view-models durante la pausa —lo que los oculta es la vista—, así que
     * anularlos en los módulos pondría en rojo las paridades que los comparan campo a campo.
     */
    notice: { type: Object, default: null },

    /** El grupo `tickets` del idioma activo. */
    messages: { type: Object, default: () => ({}) },

    /**
     * El grupo `ui`, que hoy tiene una sola clave (`loading`). Va aparte y no mezclado con `tickets`
     * porque son dos grupos distintos de `lang/` y aplanarlos aquí crearía una tercera forma del
     * diccionario que nadie más tiene.
     */
    ui: { type: Object, default: () => ({}) },
});

defineEmits(['back', 'action']);
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

        <BookingProgress :progress="notice ? null : progress" :messages="messages" @back="$emit('back')" />

        <!--
          TODO el contenido de los pasos vive aquí dentro. El pie queda FUERA, que es lo que lo deja
          anclado al fondo del panel en vez de scrollear con el contenido.
        -->
        <div class="purchase__scroll">
            <PausedNotice v-if="notice" :notice="notice" />
            <slot v-else />
        </div>

        <!--
          La banda de desglose del PAGO. Es exclusiva del paso 8 (`splitMode === 'band'`) y solo cuando
          de verdad queda algo para el parque.

          ⚠️ Va FUERA del scroll y PEGADA encima del pie: `.bk-paybreakdown + .bk-foot` es un selector
          de hermano adyacente, así que meterla dentro de `.purchase__scroll` —o dejar cualquier nodo
          entre las dos— le quita el borde que las une. Es el orden que `SHELL_BLOCKS_NOT_YET_IN_SPA`
          llevaba declarando desde 4.3·1.

          ⚠️ Y la apaga el aviso de pausa, igual que a la banda de progreso y al pie: la misma condición
          gobierna los cuatro sitios en el Blade.
        -->
        <div v-if="! notice && footer && footer.splitMode === 'band' && footer.split" class="bk-paybreakdown">
            <div class="bk-paybreakdown__row">
                <span class="bk-paybreakdown__l">{{ footer.split.nowLabel }}</span>
                <span class="bk-paybreakdown__v">{{ footer.split.now }}</span>
            </div>
            <div class="bk-paybreakdown__row">
                <span class="bk-paybreakdown__l">{{ messages.pay_at_park ?? '' }}</span>
                <span class="bk-paybreakdown__v">{{ footer.split.park }}</span>
            </div>
        </div>

        <Foot v-if="footer && ! notice" :footer="footer" :messages="messages" @action="$emit('action', $event)" />
    </div>
</template>
