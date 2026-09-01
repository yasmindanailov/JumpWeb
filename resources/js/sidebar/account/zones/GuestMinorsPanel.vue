<script setup>
/**
 * **Los JUSTIFICANTES de menores invitados de un pedido, vistos por su RESPONSABLE**
 * (`specs/waiver-por-reserva.md` §4.10, tanda T3).
 *
 * Quién ha firmado ya y **el enlace para repartir** a los padres que faltan.
 *
 * ⚠️⚠️ **Se pide BAJO DEMANDA y nunca viaja en el contexto sembrado.** El enlace es una credencial
 * portadora —quien lo tenga puede firmar sin sesión— y `AccountContextResource` tiene escrita la
 * prohibición para su gemelo del post-form: esa respuesta se siembra en el HTML de cada página con
 * sesión. Éste se pide al desplegar el pedido, que es la acción explícita que §4.10 exige.
 *
 * ⚠️ **Lo que NO llega y por eso no se puede pintar**: ni el nombre, ni la relación, ni el contacto
 * de ningún otro adulto. No es que este componente los oculte — es que el servidor devuelve la forma
 * `forResponsible()`, que no los tiene.
 *
 * ⚠️ **Sin denominador inventado**: «3 justificantes · la reserva es de 100 personas», nunca «3 de
 * 100». No se puede saber cuántos de los comprados son menores.
 */
import { computed, watch } from 'vue';
import { t as translate, tp as translateWith } from '../../i18n.js';
import { useOrdersStore } from '../../stores/orders.js';

const props = defineProps({
    /** El código del pedido. */
    code: { type: String, required: true },
    account: { type: Object, default: () => ({}) },
});

const a = (key) => translate(props.account, key);
const aw = (key, params) => translateWith(props.account, key, params);

// ⚠️ **Pinta y no decide, y no habla con la API** (`CE-6`): quien la llama es el store, que además
// cachea por código. La primera versión llamaba a `api.get` desde aquí y lo cazó
// `SidebarComponentBudgetTest` — con la regla escrita en el docblock de la tarjeta de al lado.
const store = useOrdersStore();
const data = computed(() => store.guestMinors[props.code] ?? null);

watch(() => props.code, (code) => store.ensureGuestMinors(code), { immediate: true });
</script>

<template>
    <!-- Solo aparece si este pedido TIENE justificantes o enlace que repartir: en un pedido normal
         —que son casi todos— no existe, en vez de existir vacío. -->
    <div v-if="data && (data.minors.length || data.link)" class="orders__guests" data-guest-minors>
        <p class="orders__guests-count">
            <!-- ⚠️ **Sin denominador y ahora tampoco capacidad**: se dice «3 firmados», nunca «3 de
                 100». No se puede saber cuántos de los comprados son menores (§4.10), y el rótulo de
                 la capacidad se podó porque los textos del montaje los paga **cada página con
                 sesión** (`SidebarMountTest`, techo de 9.200 B). El dato sigue en la respuesta por
                 si algún día se enseña donde no se pague en cada carga. -->
            {{ aw('purchases.guest_minors.count', { count: data.minors.length }) }}
        </p>

        <ul v-if="data.minors.length" class="orders__guests-list">
            <li v-for="(m, i) in data.minors" :key="i" class="orders__guests-item" data-guest-minor>
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
        <template v-if="data.link">
            <p class="orders__guests-hint">{{ a('purchases.guest_minors.hint') }}</p>
            <input class="orders__guests-link" type="text" readonly :value="data.link" @focus="$event.target.select()">
        </template>
    </div>
</template>
