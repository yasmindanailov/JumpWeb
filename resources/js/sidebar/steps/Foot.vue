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
                <!-- `arrow-right` del sistema de diseño, copiado byte a byte (`SidebarIconParityTest`). -->
                <svg viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="3" stroke-linejoin="round" stroke-linecap="round" aria-hidden="true">
                    <path d="M13.6 6.4 19.2 12l-5.6 5.6z" />
                    <path d="M4.6 12h9.4" fill="none" />
                </svg>
            </span>
        </button>

        <template v-else>
            <div class="bk-foot__row">
                <span class="bk-foot__total">
                    <span class="bk-foot__l">{{ footer.label }}<span v-if="footer.split && footer.splitMode === 'popover'" class="bk-foot__info" @keydown.escape="open = false">
                        <button type="button" class="bk-foot__info-btn" :aria-label="messages.deposit_info ?? ''"
                                :aria-expanded="open ? 'true' : 'false'"
                                @click="open = ! open">
                            <!-- `info` del sistema de diseño, copiado byte a byte
                                 (`SidebarIconParityTest`). Era propio del cajón por no haber
                                 componente; desde `#257` existe (`ui/info`). -->
                            <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" clip-rule="evenodd" d="M12 2.4a9.6 9.6 0 1 0 0 19.2 9.6 9.6 0 0 0 0-19.2zm0 3.8a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3zm-1.5 5.4a1.5 1.5 0 0 1 3 0v5.2a1.5 1.5 0 0 1-3 0z" />
                            </svg>
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
                    <!-- ⚠️⚠️ **Dejan de ser un solo NODO con dos `<template>` dentro, y no es un
                         capricho** (`#257`). Los dos dibujos son ahora los del set, y **no
                         comparten pintura**: `card` es masa (`fill="currentColor"`, sin trazo) y
                         `arrow-right` es masa MÁS un trazo de 3 que dibuja su asta. Con un solo
                         `<svg>` habría que poner la unión de atributos, y entonces la tarjeta
                         saldría con un borde de 3 px que no lleva.
                         ▶ Sigue habiendo UN `<svg>` en el árbol servido —solo una rama se pinta—,
                         así que el contrato de árbol ve lo mismo que antes.
                         ⚠️ El diff no desciende dentro de un `<svg>`, así que tampoco ve si el
                         dibujo falta: quien lo vigila es `SidebarIconParityTest` (4.7·2b·4). -->
                    <svg v-if="footer.icon === 'card'" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" clip-rule="evenodd" d="M3.4 4.6h17.2a1.9 1.9 0 0 1 1.9 1.9v11a1.9 1.9 0 0 1-1.9 1.9H3.4a1.9 1.9 0 0 1-1.9-1.9v-11a1.9 1.9 0 0 1 1.9-1.9zm.1 4.2v2.4h17v-2.4zm2.1 5.8a1.4 1.4 0 0 0 0 2.8h4a1.4 1.4 0 0 0 0-2.8z" />
                    </svg>
                    <svg v-else viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="3" stroke-linejoin="round" stroke-linecap="round" aria-hidden="true">
                        <path d="M13.6 6.4 19.2 12l-5.6 5.6z" />
                        <path d="M4.6 12h9.4" fill="none" />
                    </svg>
                </button>
            </div>
            <p v-if="footer.note" class="bk-foot__note">{{ footer.note }}</p>
        </template>
    </div>
</template>
