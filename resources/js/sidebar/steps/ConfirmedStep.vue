<script setup>
import { t as translate } from '../i18n.js';
import { money } from '../money.js';
import SummaryLine from './SummaryLine.vue';

/**
 * Paso 6 — **la reserva creada** (Fase 4 · paso 4.6·1).
 *
 * Es la primera pantalla del cajón a la que se llega **desde fuera**: el cliente se fue a la pasarela,
 * pagó y volvió con una navegación completa. Nada de lo que el cajón tenía en memoria sobrevive, así
 * que todo lo que aquí se pinta lo pide `outcome.js` con el código del pedido.
 *
 * ⚠️ **Se llega por DOS caminos y el marcado tiene que servir a los dos**: la vuelta OK de la pasarela
 * (pedido `paid`) y el enlace de verificación de correo (pedido `pending`). Por eso la nota del pago
 * ramifica sobre el estado REAL —hasta #224 decía «pendiente de pago» SIEMPRE, también a quien acababa
 * de pagar— y por eso el desglose de la señal solo aparece con el pedido pagado.
 *
 * ⚠️ **`confirmation` puede ser `null` y la pantalla sigue teniendo sentido.** Es fiel al Blade: sin
 * resumen quedan el código del pedido, el aviso del correo y el CTA. Es lo que se enseña cuando la
 * sesión se perdió por el camino, y es mucho mejor que un «ha fallado algo» a quien acaba de pagar.
 *
 * ⚠️ **Los importes se PINTAN, no se suman** (`PAY-12`): `park_cents` viene compuesto por el servidor
 * y `total − online` no es lo mismo.
 */
const props = defineProps({
    /** El view-model de `outcome.js`, o `null` si el resumen no se pudo traer. */
    confirmation: { type: Object, default: null },

    /** El código del pedido. Se pinta aunque no haya resumen: es lo que el cliente necesita. */
    orderCode: { type: String, default: '' },

    /**
     * ¿Hay sesión con la que ofrecer la cuenta? (`#563`; lleva al índice desde `#567`)
     *
     * ⚠️ **La puerta a la CUENTA NO se puede ofrecer siempre**: a esta pantalla se llega también por el
     * enlace de verificación de correo, y ahí puede no haber sesión — el botón llevaría a una zona de
     * cuenta que pediría identificarse, justo al que acaba de pagar. Sin sesión, la única acción es
     * «hacer otra reserva», y entonces vuelve a ser primaria.
     */
    hasSession: { type: Boolean, default: false },

    /**
     * El bloque de «registro del parque» que publica `GET /config`, o `null`.
     *
     * ⚠️ Su `url` llega **ya saneada por el servidor** (`SEC-07`): la edita un operador y en la web el
     * escape de Blade remataba la defensa, pero un cliente JSON no tiene escape que la remate. No se
     * vuelve a tocar aquí — sanearla otra vez sería fingir que este es el sitio donde ocurre.
     */
    registration: { type: Object, default: null },

    messages: { type: Object, default: () => ({}) },
    locale: { type: String, default: 'es' },
});

defineEmits(['add-another', 'go-account']);

const t = (key) => translate(props.messages, key);
</script>

<template>
    <div class="purchase__confirm purchase__done" role="status">
        <!-- ⚠️⚠️ **La PEGATINA DE ÉXITO sustituye al confeti** (`#258`, §04 del artboard: «Éxito ·
             reserva creada, pago correcto · Verde Salta»). El confeti marcaba lo mismo, así que
             tenerlos los dos rompía la regla que el propio artboard escribe: «la pegatina de estado
             nunca convive con otra en la misma pantalla».
             ▶ La celebración NO se pierde: `celebrate()` sigue lanzando el confeti a pantalla
             completa al confirmar (`app.js`), que es donde el gesto se nota.
             `check` del sistema de diseño, copiado byte a byte (`SidebarIconParityTest`). -->
        <div class="purchase__party" aria-hidden="true">
            <span class="state-badge state-badge--ok">
                <svg viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round" aria-hidden="true">
                    <path d="M9.8 18.6 3.6 12.4l2.6-2.6 3.6 3.6 8-8 2.6 2.6z" />
                </svg>
            </span>
        </div>
        <h3 class="wiz__title">{{ t('reservation_created') }}</h3>

        <template v-if="confirmation">
            <ul class="cart cart--summary">
                <SummaryLine v-for="(line, i) in confirmation.lines" :key="i" :line="line" :messages="messages" :locale="locale" />
            </ul>
            <!-- ⚠️ **Los tres importes van JUNTOS, en una caja** (`#563`, artboard
                 `Pago y Desenlaces PJP`): sueltos, «Total», «Pagado online» y «A pagar en el parque»
                 se leían como tres frases de la pantalla y no como las tres partes de una misma
                 cuenta. Es la misma pieza de superficie que el resto del cajón, sin estrenar nada.
                 ⚠️ **Los importes se PINTAN, no se suman** (`PAY-12`), como dice el docblock. -->
            <div class="purchase__money">
                <div class="purchase__total">
                    <span>{{ t('total') }}</span>
                    <strong>{{ money(confirmation.total_cents) }}</strong>
                </div>
                <!-- #225 F3: el agregado se etiqueta NEUTRO «Pagado online» —lo cobrado ahora es señal(es)
                     + productos de pago completo— y la señal por-producto se nombra en su card. Solo con el
                     pedido PAGADO y algo pendiente en el parque: en «verifica tu correo» sigue `pending`. -->
                <template v-if="confirmation.status === 'paid' && confirmation.park_cents > 0">
                    <!-- ⚠️ **Lo COBRADO lleva el rol de CIFRA** (`--money`, `#479`) y lo pendiente no:
                         son dos cosas distintas y el artboard las distingue así. Con `--money` valiendo
                         la tinta por defecto, estrenarlo aquí no mueve un píxel en esta instalación —y
                         en la que le dé color, la cifra cobrada se separa sola. -->
                    <div class="purchase__split purchase__split--paid">
                        <span>{{ t('paid_online_confirmed') }}</span>
                        <strong>{{ money(confirmation.online_cents) }}</strong>
                    </div>
                    <div class="purchase__split">
                        <span>{{ t('pending_at_park') }}</span>
                        <strong>{{ money(confirmation.park_cents) }}</strong>
                    </div>
                </template>
            </div>
        </template>

        <!-- ⚠️ **El SELLO va sobre el CÓDIGO, que es lo que el cliente se lleva** (`#278`). En el
             artboard sella «PLAZA 12» —la cosa conseguida—; aquí la cosa conseguida es el localizador.
             ▶ El `<strong>` sigue siendo texto normal: se puede seleccionar y copiar. Lo que cambia
             es su caja, no su naturaleza. -->
        <p class="purchase__code">{{ t('order_code') }}: <strong class="purchase__stamp">{{ orderCode }}</strong></p>
        <p class="purchase__note">{{ t('email_sent_note') }}</p>
        <p v-if="confirmation?.status === 'paid'" class="purchase__note">{{ t('payment_confirmed_note') }}</p>
        <p v-else-if="confirmation?.status === 'pending'" class="purchase__note">{{ t('pending_payment') }}</p>

        <!-- #217: solo si algún producto lo pide de verdad. Un pack sin formulario no lo promete. -->
        <p v-if="confirmation?.has_guest_form" class="purchase__note purchase__note--guestform">{{ t('guest_form_notice') }}</p>

        <div v-if="registration" class="purchase__reginfo">
            <p class="purchase__reginfo-text">{{ registration.description }}</p>
            <a :href="registration.url" target="_blank" rel="noopener" class="btn btn--ghost purchase__reginfo-btn">{{ registration.label }} →</a>
        </div>

        <!--
          **LAS DOS SALIDAS** (`#563`, `[DECIDIDO owner]`; la principal cambia en `#567`).

          ❗❗ **La principal lleva a la CUENTA, no al carné** (`#567`, `[DECIDIDO owner, 2026-09-12]`).
          `#563` ponía aquí «Ver Mi QR», y con el bloque de cuenta visible en el desenlace el QR salía
          DOS veces; hoy ese bloque se oculta en las pantallas finales y el botón lleva al ÍNDICE, donde
          están el QR, «Mis reservas» y el resto. ⚠️ **Sigue sin haber un QR por pedido**: el carné es
          el de siempre, a un toque desde el índice.

          ⚠️ **Aquí no se vende, así que no hay naranja**: las dos van en tinta y fantasma. La regla
          del sistema dice que el relleno de acción significa comprar, y cuando el trabajo ya está
          hecho no hay nada que comprar (`#551`).

          ⚠️ Sin sesión la cuenta no se puede ofrecer (ver `hasSession`), y entonces «hacer otra
          reserva» recupera el peso primario: una sola acción es primaria.
        -->
        <div class="purchase__final-actions">
            <button v-if="hasSession" type="button" class="btn btn--ink btn--lg purchase__cta" @click="$emit('go-account')">{{ t('go_to_account') }}</button>
            <!-- ⚠️ La clase de ancho va DENTRO de cada rama, no en la base: `.purchase__cta` y
                 `.purchase__cta-secondary` declaran lo mismo (`width: 100%` + centrado), así que
                 llevarlas las dos sería decir dos veces lo mismo — y el fantasma queda idéntico al de
                 su hermano del paso 10, que es lo que impide que diverjan. -->
            <button type="button" class="btn"
                    :class="hasSession ? 'btn--ghost purchase__cta-secondary' : 'btn--ink btn--lg purchase__cta'"
                    @click="$emit('add-another')">{{ t('new_purchase') }}</button>
        </div>
    </div>
</template>
