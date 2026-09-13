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

    /**
     * Adónde lleva «Leer las condiciones». La compone el servidor con `route()` (`#562`), igual que
     * las de los desenlaces: el cajón no conoce las rutas, y un `href` no es atributo de contrato del
     * diff de árbol, así que si se quemara aquí un slug roto pasaría el gate en verde.
     */
    termsUrl: { type: String, default: '' },
});

// ⚠️ Este paso ya no emite nada: su «Volver» lo trae la banda desde `#555`.
defineEmits([]);

/**
 * Lo que el comprador teclea aquí. Sube al padre con `v-model`, que es quien lo manda: esta pantalla
 * PINTA y recoge, no decide si hace falta ni lo envía.
 */
const acceptTerms = defineModel('acceptTerms', { type: Boolean, default: false });
const phone = defineModel('phone', { type: String, default: '' });

const t = (key) => translate(props.messages, key);
</script>

<template>
    <!-- ⚠️ El «Volver» de esta pantalla lo trae la BANDA desde `#555`, con el rótulo «Volver al
         carrito» que ya tenía: el pedido AÚN NO existe (se crea al confirmar), así que volver es
         seguro y no pierde la cesta. -->

    <!-- ⚠️ **El titular dice el TRABAJO, y la entradilla se cae con él** (`#562`, artboard
         `Pago y Desenlaces PJP`): decía «Pago», con «Revisa tu reserva antes de pagar» debajo, la fase
         llamándose «Pagar» y el botón «Pagar con tarjeta» — cuatro veces la misma palabra en 390 px.
         Hoy titula el trabajo («Repasa tu reserva») y la entradilla sobra, porque decía eso mismo.
         ▶ Esto NO contradice a `#561`: aquélla fijó que la entradilla de un paso es `.wiz__lede` y no
         `.purchase__note`; aquí no hay entradilla que colocar. -->
    <h3 class="wiz__title">{{ t('pay_title') }}</h3>

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
        <!-- ⚠️ **El bloque se NOMBRA** (`#562`): sin rótulo, el teléfono y la casilla aparecían sueltos
             bajo el resumen sin nada que dijera que son lo que queda por dar. Es la etiqueta mono del
             sistema, la misma que rotula los bloques de las otras pantallas de esta parada. -->
        <p class="paydue__heading">{{ t('due_heading') }}</p>

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
            <!-- ⚠️⚠️ **La casilla se lee entera y ya NO lleva el enlace dentro** (`#562`,
                 `[DECIDIDO owner]`): metido en la frase medía 19 px de alto contra el suelo táctil de
                 48 del producto —la grieta 13 de la auditoría—, y es la misma regla que la parada 03
                 aplicó al descargo. ▶ Al quedarse en texto plano desaparece el `v-html`: un literal de
                 `lang/` que ya no trae marcado no necesita inyectarse como HTML. -->
            <label class="check">
                <input v-model="acceptTerms" type="checkbox">
                <span>{{ t('due_terms') }}</span>
            </label>
            <span v-if="dueErrors.accept_terms" class="form__error">{{ dueErrors.accept_terms }}</span>

            <!-- ⚠️ **La FILA que abre las condiciones**: misma receta que «Ver más fechas» (`#557`),
                 que es la pieza con la que este embudo dice «esto es una puerta a otra cosa». Lleva
                 `arrow-right` y no el chevron de aquélla a propósito: el chevron despliega AQUÍ y esto
                 SALE a otra página, en otra pestaña. -->
            <a :href="termsUrl" target="_blank" rel="noopener" class="cal-more paydue__terms">
                <span>{{ t('due_terms_read') }}</span>
                <!-- `arrow-right` del sistema de diseño, copiado byte a byte
                     (`SidebarIconParityTest`). -->
                <svg class="arrow-ico paydue__terms-ico" viewBox="0 0 24 24" fill="currentColor"
                     stroke="currentColor" stroke-width="3" stroke-linejoin="round" stroke-linecap="round"
                     aria-hidden="true" focusable="false">
                    <path d="M13.6 6.4 19.2 12l-5.6 5.6z" />
                    <path d="M4.6 12h9.4" fill="none" />
                </svg>
            </a>
        </div>
    </div>

    <!--
      ⚠️⚠️ **EL ENLACE A LAS CONDICIONES ESTÁ SIEMPRE EN LA PANTALLA, pero no SIEMPRE en esta línea.**
      Medido antes de esta tanda: el embudo no las enseñaba en NINGÚN sitio, así que quien ya tenía
      cuenta compraba sin que se le mostraran nunca. La LCGC (art. 5) pide que el consumidor haya
      podido conocerlas para que se incorporen al contrato, y el TRLGDCU (art. 97) las sitúa antes de
      quedar vinculado.
      ▶ **Cuando hay casilla, esta línea sobra** y lo que cumple la obligación es la fila «Leer las
      condiciones» de arriba: puestas las dos, la pantalla decía «léelas y acéptalas» y treinta píxeles
      más abajo «al reservar las aceptas» — dos frases casi iguales que se estorban. *Lo vio la
      captura, no la suite.*
      ⚠️ **`#562` cambió DÓNDE vive el enlace cuando hay casilla, no si lo hay**: antes iba dentro de
      la frase de la casilla y hoy es su propia fila. Esta línea sigue existiendo para el otro caso —el
      cliente que ya aceptó—, donde además dice la VINCULACIÓN («al reservar aceptas»), que es lo que
      esa persona necesita leer y la fila no dice.
    -->
    <!-- eslint-disable-next-line vue/no-v-html -- literal de `lang/` + `route()`, sin entrada de usuario -->
    <p v-if="! need.terms" class="paydue__legal" v-html="t('terms_link')"></p>
    <!-- La política de cambios y el pago seguro, dichos ANTES de pagar (`#588`, contenido T5): es lo que
         el cliente quiere saber justo antes de comprometerse. El documento entero sigue en su fila. -->
    <p class="paydue__legal">{{ t('pay_policy') }}</p>
    <p class="paydue__legal">{{ t('pay_notice') }}</p>

    <div class="purchase__foot purchase__foot--info">
        <p v-if="error" class="form__error">{{ error }}</p>
    </div>
</template>
