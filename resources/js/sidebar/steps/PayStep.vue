<script setup>
import { t as translate } from '../i18n.js';
import SummaryLine from './SummaryLine.vue';

/**
 * Paso 8 — la pantalla de PAGO (Fase 4 · paso 4.5·2).
 *
 * Es el carrito otra vez, pero en modo **resumen**: lo que aquí se enseña ya no se toca, se paga. El
 * total y el CTA viven en el pie, y el desglose de la señal sube a su propia banda
 * (`bk-paybreakdown`), porque en esta pantalla el cliente tiene que ver **siempre** lo que se le va a
 * cobrar, no detrás de un ⓘ.
 *
 * ⚠️ **Cuatro diferencias con el paso 4 que el diff SÍ ve y que no se adivinan** (medidas contra el
 * Blade el 2026-08-14):
 *  1. la lista lleva `cart--summary` además de `cart`;
 *  2. **no hay botón de quitar**: el pedido está a un clic de crearse y retener aforo;
 *  3. el precio de la línea es un `<span>` **sin clase**, no el `.cart__price` del carrito;
 *  4. el pie de aviso (`purchase__foot--info`) se emite **siempre**, con el error dentro o vacío —
 *     no lleva ni «añadir otra reserva» ni el aviso de «carrito listo».
 *
 * ⚠️ Y una que el diff **no** ve: el CTA del pie estrena `icon: 'card'`. El normalizador no desciende
 * dentro de un `<svg>`, así que la tarjeta y la flecha son el mismo nodo para el gate; lo que cambia
 * es el dibujo, y de eso responde `SidebarCartParityTest` comparando el view-model del pie.
 *
 * ⚠️ **La FILA vive en `SummaryLine.vue` desde 4.6·1**, porque la pantalla de reserva creada emite
 * exactamente el mismo árbol: dos copias de un marcado que el CSS mira por estructura divergirían en
 * silencio, con el diff de cada pantalla verde por separado.
 */
const props = defineProps({
    /** Las líneas del presupuesto, ya emparejadas con las respuestas del pack (`cart.js`). */
    lines: { type: Array, default: () => [] },

    /** Aviso de la cesta, ya traducido. Ocupa el sitio del `@error('cart')` del Blade. */
    error: { type: String, default: '' },

    messages: { type: Object, default: () => ({}) },
    locale: { type: String, default: 'es' },

    /**
     * Qué hay que pedirle y con qué palabras: `{terms, phone, termsUpdated}`, ya resuelto por
     * `buyer-due.js` juntando la pista del servidor con su «no» del último intento.
     */
    need: { type: Object, default: () => ({ terms: false, phone: false, termsUpdated: false }) },
    /** Los «no» del servidor por campo, ya traducidos: `{accept_terms, phone}`. */
    dueErrors: { type: Object, default: () => ({}) },
});

defineEmits(['back']);

/**
 * Lo que el comprador teclea aquí. Sube al padre con `v-model`, que es quien lo manda: esta pantalla
 * PINTA y recoge, no decide si hace falta ni lo envía.
 */
const acceptTerms = defineModel('acceptTerms', { type: Boolean, default: false });
const phone = defineModel('phone', { type: String, default: '' });

const t = (key) => translate(props.messages, key);
</script>

<template>
    <!-- El pedido AÚN NO existe (se crea al confirmar), así que volver al carrito es seguro y no
         pierde la cesta. Este paso no tiene banda de progreso, igual que la identificación. -->
    <button type="button" class="bk-back purchase__back" @click="$emit('back')">
        <!-- `arrow-left` del sistema de diseño, copiado byte a byte (`SidebarIconParityTest`). -->
        <svg class="arrow-ico" width="16" height="16" viewBox="0 0 24 24" fill="currentColor"
             stroke="currentColor" stroke-width="3" stroke-linejoin="round" stroke-linecap="round"
             aria-hidden="true" focusable="false">
            <g transform="translate(24 0) scale(-1 1)">
                <path d="M13.6 6.4 19.2 12l-5.6 5.6z" />
                <path d="M4.6 12h9.4" fill="none" />
            </g>
        </svg>
        <span>{{ t('back_to_cart') }}</span>
    </button>

    <h3 class="wiz__title">{{ t('pay_title') }}</h3>
    <p class="purchase__note">{{ t('pay_intro') }}</p>

    <ul class="cart cart--summary">
        <SummaryLine v-for="line in lines" :key="line.index" :line="line" :messages="messages" :locale="locale" />
    </ul>

    <!--
      **LO QUE FALTA ANTES DE PAGAR** (`specs/auth-con-google.md` §21.4.2, `#349`).

      ⚠️ **Reutiliza `.eventfields`, que es el patrón del embudo para campos extra** (el del paso 3 y
      el de las respuestas pendientes del carrito). Inventar aquí una tarjeta habría sido un cuarto
      tratamiento para lo mismo, que es como murió el sistema de sombras de `#196`.

      ⚠️ **No hay botón muerto.** La regla de esta pantalla está escrita en `foot.js`: los campos
      obligatorios *«se validan AL PULSAR, con un aviso que dice qué falta, en vez de con un botón
      muerto que no lo explica»*. Aquí el «no» lo da el SERVIDOR y vuelve pegado a su campo.
    -->
    <div v-if="need.phone || need.terms" class="paydue">
        <div v-if="need.phone" class="eventfields">
            <label class="eventfields__field">
                <span class="eventfields__label">{{ t('due_phone_label') }}</span>
                <input v-model="phone" type="tel" autocomplete="tel" inputmode="tel">
            </label>
            <!-- Amable y con el porqué: se pide porque hace falta para la reserva, no «porque sí». -->
            <p class="paydue__hint">{{ t('due_phone_hint') }}</p>
            <span v-if="dueErrors.phone" class="form__error">{{ dueErrors.phone }}</span>
        </div>

        <div v-if="need.terms" class="form__checks paydue__checks">
            <!-- ⚠️ **A quien ya las aceptó se le DICE que han cambiado** (`[owner]`: *«se pide de nuevo
                 diciendo que las condiciones se han actualizado»*), y a quien nunca lo hizo NO: eso
                 sería contarle una historia que no es la suya. Los dos hechos vienen separados del
                 servidor por eso mismo. -->
            <p v-if="need.termsUpdated" class="paydue__hint">{{ t('due_terms_updated') }}</p>
            <label class="check">
                <input v-model="acceptTerms" type="checkbox">
                <!-- eslint-disable-next-line vue/no-v-html -- literal de `lang/` + `route()`, sin entrada de usuario -->
                <span v-html="t('due_terms')"></span>
            </label>
            <span v-if="dueErrors.accept_terms" class="form__error">{{ dueErrors.accept_terms }}</span>
        </div>
    </div>

    <!--
      ⚠️⚠️ **EL ENLACE A LAS CONDICIONES ESTÁ SIEMPRE EN LA PANTALLA, pero no SIEMPRE en esta línea.**
      Medido antes de esta tanda: el embudo no las enseñaba en NINGÚN sitio, así que quien ya tenía
      cuenta compraba sin que se le mostraran nunca. La LCGC (art. 5) pide que el consumidor haya
      podido conocerlas para que se incorporen al contrato, y el TRLGDCU (art. 97) las sitúa antes de
      quedar vinculado.
      ▶ **Cuando hay casilla, el enlace va DENTRO de ella** y esta línea sobra: puestas las dos, la
      pantalla decía «léelas y acéptalas» y treinta píxeles más abajo «al reservar las aceptas» — dos
      frases casi iguales que se estorban. *Lo vio la captura, no la suite.* La obligación se cumple
      igual: el enlace está, y donde de verdad hay que leerlo.
    -->
    <!-- eslint-disable-next-line vue/no-v-html -- literal de `lang/` + `route()`, sin entrada de usuario -->
    <p v-if="! need.terms" class="paydue__legal" v-html="t('terms_link')"></p>

    <div class="purchase__foot purchase__foot--info">
        <p v-if="error" class="form__error">{{ error }}</p>
    </div>
</template>
