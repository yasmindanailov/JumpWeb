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
import { computed, ref, watch } from 'vue';
import { t as translate, tp as translateWith } from '../../i18n.js';
import { useOrdersStore } from '../../stores/orders.js';
import { browserShareDeps, shareOrCopy } from '../share-link.js';

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

/**
 * **Compartir o copiar**, con el gesto que el dispositivo sepa hacer (`[DECIDIDO owner, 2026-09-02]`).
 *
 * ⚠️ La REGLA vive en `share-link.js` con sus casos de `node --test`; aquí solo se pinta el
 * resultado. Y **el `input` de solo lectura se queda**: si las dos APIs fallan —contexto sin TLS, sin
 * permiso— el cliente sigue pudiendo seleccionar el enlace a mano. Un botón no puede ser la única vía.
 *
 * ⚠️ El acuse se borra solo: es un estado de UN gesto, no del panel. `cancelled` NO dice nada —cerrar
 * la hoja de compartir es un «no, gracias», no un fallo—.
 */
// ⚠️ UN solo acuse y no un mapa por reserva: solo se pulsa un botón a la vez, y un mapa costaba
// bytes del chunk para representar un estado que no puede ser simultáneo.
const acuse = ref({ id: null, estado: null });

async function repartir(id, url) {
    const estado = await shareOrCopy(url, browserShareDeps());

    if (estado === 'cancelled') return;

    acuse.value = { id, estado };
    setTimeout(() => { acuse.value = { id: null, estado: null }; }, 2500);
}
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
            <div class="orders__guests-share">
                <input class="orders__guests-link" type="text" readonly :value="group.link" @focus="$event.target.select()">
                <button type="button" class="orders__guests-copy" :aria-label="a('purchases.guest_minors.share')"
                        @click="repartir(group.reservation_id, group.link)">
                    <!-- ⚠️⚠️ **La geometría es la de `<x-icons.share>`, COPIADA, no inventada**
                         (`#258`): el cajón **no tiene iconos propios** —`DRAWER_OWN` está vacía— y
                         `SidebarIconParityTest` compara dibujo a dibujo contra el set del sitio. Un
                         glifo de otra librería iría en otra rejilla y con otro trazo, y eso no falla:
                         solo se nota mirando la web entera a la vez.
                         ⚠️ Si el icono del sistema cambia, esta copia se queda vieja **y la guarda lo
                         dice** — es literalmente para lo que existe.
                         ⚠️⚠️ **A 24 y no a 18, que es su TALLA DE TRABAJO** (el artboard la declara en
                         §05). El primer intento lo encogió a 18 y el owner lo vio roto: los tres
                         puntos son de masa 3,2 sobre rejilla 24, así que al encogerlos se comen los
                         conectores y el glifo se lee como un borrón. *Un icono de rejilla 24 no se
                         escala: se pinta a 24 y se le da aire con el relleno del botón.* -->
                    <svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor" aria-hidden="true" focusable="false">
                        <circle cx="18" cy="5.8" r="3.2" />
                        <circle cx="6" cy="12" r="3.2" />
                        <circle cx="18" cy="18.2" r="3.2" />
                        <path d="M8.9 10.5l6.3-3.2M8.9 13.5l6.3 3.2" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" />
                    </svg>
                </button>
            </div>
            <!-- El acuse, y solo cuando hay algo que decir. `aria-live` para que un lector de pantalla
                 se entere: sin él, quien no ve el cambio de texto no sabe si el gesto hizo algo. -->
            <p v-if="acuse.id === group.reservation_id" class="orders__guests-ack" role="status" aria-live="polite">
                {{ a('purchases.guest_minors.' + acuse.estado) }}
            </p>
        </template>
    </div>
</template>
