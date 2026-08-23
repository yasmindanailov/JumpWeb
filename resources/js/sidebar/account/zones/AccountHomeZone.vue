<script setup>
import { useReservationsStore } from '../../stores/reservations.js';
import { HOME_ENTRIES, titleKeyOf } from '../navigation.js';
import { t as translate, tp as translateWith } from '../../i18n.js';
import ZoneLoading from '../ZoneLoading.vue';
import ZoneIcon from '../ZoneIcon.vue';

/**
 * **El índice del área de cliente**: quién eres, qué tienes por delante y desde dónde se llega a todo
 * lo demás (`docs/specs/area-cliente.md` §4.2).
 *
 * ⚠️ **La próxima reserva se pinta aquí aunque `.acct` también la enseñe, y no es duplicación**:
 * medido en `VERIFICACION-E2E-CAJON.md` **V4**, dentro de esta sección ese bloque **se colapsa** —lo
 * hace el modo `account`— y su altura es 0. Dentro del área, esa información no se ve.
 *
 * ⚠️ **Las entradas salen de `HOME_ENTRIES`, no de marcado repetido**: añadir una zona es una línea
 * en `navigation.js`.
 */
const props = defineProps({
    account: { type: Object, default: () => ({}) },
    /** El grupo `ui`: el rótulo del spinner mientras la zona trae sus datos. */
    ui: { type: Object, default: () => ({}) },
    messages: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['go']);

const store = useReservationsStore();

store.ensure();
</script>

<template>
    <!--
      El contexto de cortesía. Va antes que los accesos porque es lo que el cliente viene a mirar:
      medido en la web, «¿cuándo es lo mío?» es la pregunta que trae aquí a la mayoría.
    -->
    <ZoneLoading v-if="store.loading && ! store.loaded" :ui="ui" />

    <p v-else-if="store.next" class="acct__sub">
        {{ store.next.date_label }}<template v-if="store.next.time_window"> · {{ store.next.time_window }}</template> · {{ store.next.product_name }}
    </p>

    <div class="catalog">
        <button v-for="zone in HOME_ENTRIES" :key="zone" type="button" class="catalog__item" @click="emit('go', zone)">
            <span class="catalog__name">
                <!-- ⚠️ El icono va DENTRO del rótulo, no como tercer hijo de `.catalog__item`: ése es
                     `space-between` y un hijo más dejaría el texto flotando en el medio. -->
                <ZoneIcon :zone="zone" />
                {{ translate(account, titleKeyOf(zone)) }}
                <template v-if="zone === HOME_ENTRIES[0] && upcoming > 0">
                    <!--
                      ⚠️ El número va `aria-hidden` y su lectura la da el `sr-only` de al lado, con la
                      MISMA clave que usa el bloque `.acct` del panel para lo mismo. Medido en
                      navegador: el primer intento puso ahí el subtítulo de la zona, y un lector de
                      pantalla leía «Mis reservas 1 Aquí tienes tus reservas y su estado» — que no
                      dice qué es ese 1.
                    -->
                    <span class="acct__count" aria-hidden="true">{{ store.upcoming }}</span>
                    <span class="sr-only">{{ translateWith(account, 'sidecart.upcoming_count', { count: store.upcoming }) }}</span>
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
