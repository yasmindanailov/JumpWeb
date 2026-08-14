<script setup>
/**
 * El PIE sticky del cajón (Fase 4 · paso 4.3·2).
 *
 * ⚠️ **Son TRES árboles distintos, no dos**, y confundirlos pierde estilo con las clases correctas:
 *  1. rama `cart` — la barra-carrito del catálogo: UN solo hijo y **sin nota de IVA**;
 *  2. rama `bar` SIN desglose — la de los pasos 2, 3-sin-hora y la cesta sin señal;
 *  3. rama `bar` CON desglose — donde `.bk-foot__info` cuelga **DENTRO** de `<span class="bk-foot__l">`,
 *     pegado al texto del rótulo, y añade seis nodos. Sacarlo un nivel rompe su alineación vertical.
 *
 * ⚠️ **El desglose se OCULTA, no se quita.** En el Blade el popover está siempre en el HTML servido y
 * lo tapan `x-show` + `x-cloak`; con `v-if` el árbol de Vue tendría seis nodos menos que el de
 * Livewire y el diff caería por algo que no es contrato. `v-show` sí vale: el normalizador descarta
 * `style`, así que los dos árboles coinciden.
 *
 * **No decide nada**: el view-model lo compone `foot.js` y los importes llegan ya formateados.
 */
import { ref } from 'vue';

defineProps({
    /** El view-model de `foot.js`. Nunca se pinta si es `null`: eso lo decide quien lo monta. */
    footer: { type: Object, required: true },
    messages: { type: Object, default: () => ({}) },
});

defineEmits(['action']);

/** ¿Está abierto el desglose de la señal? Es estado de interfaz y no sale de ningún dato. */
const open = ref(false);
</script>

<template>
    <div class="bk-foot">
        <!-- Catálogo: un único botón con toda la información de la cesta. -->
        <button v-if="footer.type === 'cart'" type="button" class="cartbar" @click="$emit('action', footer.action)">
            <span class="cartbar__count">{{ footer.count }}</span>
            <span class="cartbar__txt">
                <span class="cartbar__label">{{ footer.label }}</span>
                <span class="cartbar__total">{{ footer.amount }}</span>
            </span>
            <span class="cartbar__go">{{ footer.cta }}
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"></svg>
            </span>
        </button>

        <template v-else>
            <div class="bk-foot__row">
                <span class="bk-foot__total">
                    <span class="bk-foot__l">{{ footer.label }}<span v-if="footer.split && footer.splitMode === 'popover'" class="bk-foot__info" @keydown.escape="open = false">
                        <button type="button" class="bk-foot__info-btn" :aria-label="messages.deposit_info ?? ''"
                                @click="open = ! open">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"></svg>
                        </button>
                        <span v-show="open" class="bk-foot__pop">
                            <span class="bk-foot__pop-row"><span>{{ footer.split.nowLabel }}</span><span>{{ footer.split.now }}</span></span>
                            <span class="bk-foot__pop-row"><span>{{ messages.pay_at_park ?? '' }}</span><span>{{ footer.split.park }}</span></span>
                        </span>
                    </span></span>
                    <span class="bk-foot__v">{{ footer.amount }}</span>
                </span>
                <button type="button" class="bk-cta" :disabled="footer.disabled" @click="$emit('action', footer.action)">
                    <span>{{ footer.cta }}</span>
                    <!-- Mismo NODO para el icono de tarjeta y el de flecha: el diff no desciende dentro
                         de un `<svg>`, así que lo que cambia es el dibujo, no el árbol. -->
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"></svg>
                </button>
            </div>
            <p v-if="footer.note" class="bk-foot__note">{{ footer.note }}</p>
        </template>
    </div>
</template>
