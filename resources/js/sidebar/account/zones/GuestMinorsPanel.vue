<script setup>
/**
 * **Los JUSTIFICANTES de menores invitados de una RESERVA, vistos por su RESPONSABLE**
 * (`specs/waiver-por-reserva.md` §4.10, §13).
 *
 * Quién ha firmado ya y **el enlace para repartir** a los padres que faltan.
 *
 * ❗❗ **Vive en «Mis RESERVAS» desde `#343`, que es donde el owner lo buscó** —*«sigo sin ver el
 * enlace para copiar en mis reservas, ni en ningún lado»*—. Estaba en «Mis pedidos» y **dentro del
 * desplegable del desglose**: dos clics y una pantalla de distancia de donde tiene sentido.
 *
 * ⚠️⚠️ **Se pide BAJO DEMANDA y nunca viaja en el contexto sembrado.** El enlace es una credencial
 * portadora —quien lo tenga puede firmar sin sesión— y `AccountContextResource` tiene escrita la
 * prohibición para su gemelo del post-form: esa respuesta se siembra en el HTML de cada página con
 * sesión.
 *
 * ⚠️ **Lo que NO llega y por eso no se puede pintar**: ni el nombre, ni la relación, ni el contacto
 * de ningún otro adulto. No es que este componente los oculte — es que el servidor devuelve la forma
 * `forResponsible()`, que no los tiene.
 */
import { computed, watch } from 'vue';
import { t as translate, tp as translateWith } from '../../i18n.js';
import { useOrdersStore } from '../../stores/orders.js';

const props = defineProps({
    /** El código del pedido: es por lo que se pide (una petición por pedido, cacheada). */
    code: { type: String, required: true },
    /**
     * La RESERVA que pinta esta tarjeta. Con id, se enseña **solo la suya**; sin id —«Mis pedidos»,
     * que habla del pedido entero— se enseñan todas las del pedido, cada una con su enlace.
     */
    reservationId: { type: Number, default: null },
    account: { type: Object, default: () => ({}) },
});

const a = (key) => translate(props.account, key);
const aw = (key, params) => translateWith(props.account, key, params);

// ⚠️ **Pinta y no decide, y no habla con la API** (`CE-6`): quien la llama es el store, que además
// cachea por código. La primera versión llamaba a `api.get` desde aquí y lo cazó
// `SidebarComponentBudgetTest` — con la regla escrita en el docblock de la tarjeta de al lado.
const store = useOrdersStore();

const groups = computed(() => {
    const all = store.guestMinors[props.code]?.reservations ?? [];

    return props.reservationId === null
        ? all
        : all.filter((r) => Number(r.reservation_id) === Number(props.reservationId));
});

watch(() => props.code, (code) => store.ensureGuestMinors(code), { immediate: true });
</script>

<template>
    <!-- Solo aparece si hay algo que enseñar: en una reserva normal —que son casi todas— no existe,
         en vez de existir vacía. -->
    <div v-for="group in groups" :key="group.reservation_id" class="orders__guests" data-guest-minors>
        <!-- QUÉ visita. ⚠️ Solo cuando se pintan VARIAS —«Mis pedidos»—: en la tarjeta de una reserva
             el nombre ya está tres líneas más arriba, y repetirlo sería ruido. -->
        <p v-if="reservationId === null" class="orders__guests-what">{{ group.product_name }}</p>

        <p class="orders__guests-count">
            <!-- ⚠️ **Ahora SÍ hay denominador, y es un HECHO**: las plazas LIBRES de esta reserva —su
                 cantidad menos los menores a cargo ya asignados y los justificantes ya firmados—. Lo
                 que §4.10 prohíbe es inventar «3 de 100» sin saber cuántos menores vienen; esto sí se
                 sabe, y era justo lo que el owner echó en falta al ver «3 plazas» en una entrada que
                 ya tenía dueño. -->
            {{ aw('purchases.guest_minors.count', { count: group.minors.length }) }}
            <span class="orders__guests-free">· {{ aw('purchases.guest_minors.places', { count: group.places }) }}</span>
        </p>

        <ul v-if="group.minors.length" class="orders__guests-list">
            <li v-for="(m, i) in group.minors" :key="i" class="orders__guests-item" data-guest-minor>
                {{ m.minor }}
                <!-- Solo la EXCEPCIÓN lleva marca (`#320`): un justificante vigente es la condición
                     para estar en esta lista, no una noticia. -->
                <em v-if="m.waiver && m.waiver !== 'current'">{{ a('purchases.guest_minors.waiver_' + m.waiver) }}</em>
            </li>
        </ul>

        <!-- ⚠️ **Sin botón de copiar, y es una poda con su motivo**: el chunk del cajón va con menos
             de 1 KiB de holgura y el `input` de solo lectura ya se autoselecciona al enfocarlo, así
             que copiar sigue siendo un gesto. Lo que no cabía era la lógica de portapapeles con su
             respaldo y sus dos rótulos de estado. -->
        <template v-if="group.link">
            <p class="orders__guests-hint">{{ a('purchases.guest_minors.hint') }}</p>
            <input class="orders__guests-link" type="text" readonly :value="group.link" @focus="$event.target.select()">
        </template>
    </div>
</template>
