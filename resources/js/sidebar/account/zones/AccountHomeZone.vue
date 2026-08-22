<script setup>
import { HOME_ENTRIES, titleKeyOf } from '../navigation.js';
import { t as translate, tp as translateWith } from '../../i18n.js';

/**
 * **El índice del área de cliente**: quién eres, qué tienes por delante y desde dónde se llega a todo
 * lo demás (`docs/specs/area-cliente.md` §4.2). Espeja `/mi-cuenta` más el contexto que el bloque
 * `.acct` da en el panel.
 *
 * ⚠️ **La próxima reserva se pinta aquí aunque `.acct` también la enseñe, y no es duplicación**:
 * medido en `VERIFICACION-E2E-CAJON.md` **V4**, dentro de esta sección ese bloque **se colapsa** —lo
 * hace el modo `account`— y su altura es 0. Dentro del área, esa información no se ve.
 *
 * ⚠️ **Las entradas salen de `HOME_ENTRIES`, no de marcado repetido**: añadir una zona es una línea
 * en `navigation.js`. Con la tanda 2 pasaron de una a tres —contraseña y sesiones tienen endpoint
 * desde el paso 6a—; **perfil y privacidad siguen fuera** hasta que los suyos existan, porque pintar
 * una fila que no lleva a ningún sitio es peor que no pintarla.
 */
defineProps({
    /** El grupo `account` podado: de ahí salen los rótulos. */
    account: { type: Object, default: () => ({}) },
    /** El grupo `tickets`, para el contexto de la próxima reserva. */
    messages: { type: Object, default: () => ({}) },
    /** La próxima reserva tal cual la publica `GET /me/reservations`, o `null`. */
    next: { type: Object, default: null },
    /** Cuántas reservas quedan por delante (`meta.total`). */
    upcoming: { type: Number, default: 0 },
});

const emit = defineEmits(['go']);
</script>

<template>
    <!--
      El contexto de cortesía. Va antes que los accesos porque es lo que el cliente viene a mirar:
      medido en la web, «¿cuándo es lo mío?» es la pregunta que trae aquí a la mayoría.
    -->
    <p v-if="next" class="acct__sub">
        {{ next.date_label }}<template v-if="next.time_window"> · {{ next.time_window }}</template> · {{ next.product_name }}
    </p>

    <div class="catalog">
        <button v-for="zone in HOME_ENTRIES" :key="zone" type="button" class="catalog__item" @click="emit('go', zone)">
            <span class="catalog__name">
                {{ translate(account, titleKeyOf(zone)) }}
                <template v-if="zone === HOME_ENTRIES[0] && upcoming > 0">
                    <!--
                      ⚠️ El número va `aria-hidden` y su lectura la da el `sr-only` de al lado, con la
                      MISMA clave que usa el bloque `.acct` del panel para lo mismo. Medido en
                      navegador: el primer intento puso ahí el subtítulo de la zona, y un lector de
                      pantalla leía «Mis reservas 1 Aquí tienes tus reservas y su estado» — que no
                      dice qué es ese 1.
                    -->
                    <span class="acct__count" aria-hidden="true">{{ upcoming }}</span>
                    <span class="sr-only">{{ translateWith(account, 'sidecart.upcoming_count', { count: upcoming }) }}</span>
                </template>
            </span>
            <span class="catalog__go" aria-hidden="true">
                <svg class="arrow-ico" width="16" height="16" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"
                     aria-hidden="true" focusable="false">
                    <line x1="5" y1="12" x2="19" y2="12" />
                    <polyline points="12 5 19 12 12 19" />
                </svg>
            </span>
        </button>
    </div>
</template>
