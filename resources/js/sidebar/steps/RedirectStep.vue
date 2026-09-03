<script setup>
import { onMounted, ref } from 'vue';
import { t as translate } from '../i18n.js';

/**
 * Paso 9 — SALIENDO hacia la pasarela (Fase 4 · paso 4.5·2).
 *
 * Ocho nodos y el momento más delicado del cajón: aquí el pedido **ya existe y retiene aforo**, y lo
 * único que queda es entregar al navegador un formulario firmado por el servidor.
 *
 * ⚠️ **Los campos vienen de un mapa OPACO y se emiten tal cual.** El cliente no conoce
 * `Ds_MerchantParameters` ni `Ds_Signature`, y no debe: la firma cubre esos valores exactos, así que
 * renombrar, filtrar o normalizar cualquiera de ellos hace que Redsys rechace el cobro con SIS0042 —
 * con el pedido ya creado y el aforo retenido.
 *
 * ⚠️ **Y esto es lo que el diff de árbol NO puede ver**: `action`, `method` y los `name` de los campos
 * **no son atributos de contrato** del normalizador, así que un formulario con los campos vacíos o mal
 * nombrados pasaría el gate en verde. Lo compara `SidebarPayParityTest` campo a campo contra el
 * formulario que emite el Blade, que es la única red posible de esto.
 *
 * ⚠️ **`target="_top"` no es decorativo**: el cajón puede vivir dentro de un panel con su propio
 * contexto, y sin él la pasarela se abriría *dentro* del sidebar.
 *
 * **El `<noscript>` se emite aunque nunca se vea**: es parte del árbol del Blade y, en la práctica, la
 * única salida de quien tenga el JS bloqueado — que es justo quien no verá el auto-envío.
 */
const props = defineProps({
    /** El formulario ya traducido por `pay.js`: `{url, method, fields: [{name, value}]}`. */
    form: { type: Object, default: null },

    messages: { type: Object, default: () => ({}) },
});

const el = ref(null);

/**
 * ¿Se está tardando? Entonces el botón manual aparece de verdad (`#450`, auditoría del cajón M6).
 *
 * ⚠️⚠️ Hasta hoy el texto decía «si no se redirige en unos segundos, pulsa el botón» y el botón vivía
 * SOLO en `<noscript>`: con JavaScript —que es como se llega aquí— no había botón, solo la frase y
 * media pantalla vacía. Se enseña pasados 2,5 s, que es cuando la frase deja de ser verdad.
 * ▶ Es `v-show` y no `v-if` a propósito: el nodo está siempre en el árbol y el contrato lo ve.
 */
const slow = ref(false);

/**
 * El auto-envío.
 *
 * ⚠️ **`onMounted` no corre en SSR**, así que el renderizador del gate compara el marcado sin dispararlo
 * — que es lo que hace comparable esta pantalla. Y el retardo es el mismo que el del Blade: da al
 * navegador un ciclo para pintar el «te estamos redirigiendo» antes de irse, en vez de dejar un
 * fogonazo en blanco.
 *
 * `submit()` —y no `requestSubmit()`— a propósito: el segundo dispara la validación del navegador, y
 * aquí no hay nada que validar en el cliente; lo que hay es un payload firmado que no se puede tocar.
 */
onMounted(() => {
    if (! props.form) return;

    setTimeout(() => el.value?.submit(), 80);
    setTimeout(() => { slow.value = true; }, 2500);
});

const t = (key) => translate(props.messages, key);
</script>

<template>
    <div v-if="form" class="purchase__redirecting" role="status" aria-live="polite">
        <p class="purchase__note">{{ t('pay_redirecting') }}</p>
        <form ref="el" :action="form.url" :method="form.method" target="_top">
            <input v-for="field in form.fields" :key="field.name" type="hidden" :name="field.name" :value="field.value">
            <button v-show="slow" type="submit" class="btn btn--lg purchase__cta">{{ t('pay_proceed_manual') }}</button>
            <noscript>
                <button type="submit" class="btn btn--lg purchase__cta">{{ t('pay_proceed_manual') }}</button>
            </noscript>
        </form>
    </div>
</template>
