<script setup>
import { computed, ref } from 'vue';
import { t as translate, tp as translateWith } from '../i18n.js';
import { money } from '../money.js';
import ProductIcon from '../ProductIcon.vue';

/**
 * Paso 1 — el CATÁLOGO (Fase 4 · paso 4.2).
 *
 * ⚠️ **El contrato que este componente cumple es el ÁRBOL, no las clases** (§4.2): 90 de los 292
 * selectores que estilan el cajón son estructurales o dependen del tipo de elemento —`.catalog__go
 * svg`, `.catalog-acc__head span`—, así que un `<div>` donde el Blade pone un `<button>` pierde el
 * estilo con el contrato de clases cumplido al 100%. Lo verifica `SidebarDomContractTest` comparando
 * el árbol renderizado de los dos motores, no extrayendo `class=` del código.
 *
 * **Ninguna regla de negocio vive aquí** (`CE-4`): qué productos hay, cuál va destacado, desde qué
 * precio y qué señal anuncia lo decide `GET /api/v1/catalog/products` y llega ya resuelto. Este
 * componente ordena nodos.
 *
 * **El buscador filtra en cliente y eso NO es una regla de servidor**: el umbral a partir del cual
 * aparece (`search_min_items`) lo publica `GET /config`, y filtrar una lista que ya se ha descargado
 * entera es presentación. Lo que sí se respeta es la semántica medida: el buscador compara contra el
 * mismo `search` que el servidor compone, no contra el nombre visible.
 */
const props = defineProps({
    /** Secciones del catálogo, tal y como las agrupa el servidor: `entries` y `services`. */
    sections: { type: Array, default: () => [] },
    /** ¿Se enseña el buscador? Lo decide el umbral de `GET /config`, no este componente. */
    searchEnabled: { type: Boolean, default: false },
    /** Textos del grupo `tickets`, inyectados en el montaje (§4.5). */
    messages: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['select']);

const query = ref('');

const t = (key) => translate(props.messages, key);
const tp = (key, params) => translateWith(props.messages, key, params);

/** Mismo saneo que el filtro de Alpine que sustituye: minúsculas y sin extremos. */
const normalised = computed(() => query.value.toString().toLowerCase().trim());

const hasItems = computed(() => props.sections.some((section) => (section.items ?? []).length > 0));

/** ⚠️ Se compara contra `item.search`, que lo compone el SERVIDOR, no contra el nombre visible. */
const matches = (item) => normalised.value === '' || (item.search ?? '').includes(normalised.value);

const sectionMatches = (section) => (section.items ?? []).some((item) => matches(item));

const anyMatch = computed(() => normalised.value === '' || props.sections.some((s) => sectionMatches(s)));
</script>

<template>
    <div class="catalog-acc">
        <div v-if="searchEnabled" class="catalog-search">
            <!-- `search` del sistema de diseño, copiado byte a byte (`SidebarIconParityTest`).
                 Era un dibujo PROPIO del cajón —declarado en `DRAWER_OWN`— porque no existía
                 componente; desde `#257` sí existe (`ui/buscar`). -->
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.2" stroke-linecap="round" aria-hidden="true">
                <circle cx="10.6" cy="10.6" r="6.4" />
                <path d="M15.6 15.6 20 20" />
            </svg>
            <input v-model="query" type="search" class="catalog-search__input"
                   :placeholder="t('catalog_search')" :aria-label="t('catalog_search')">
        </div>

        <!--
          ❗❗❗ **LA TARJETA GRANDE, SIN PUERTA** (`#552`, `[DECIDIDO owner]`, `Decisiones SPA PJP` 02).
          Cada sección es una TARJETA con su franja arriba: icono grande, nombre en rótulo y una frase
          que dice a qué vienes. El argumento del canvas, medido: *«Vengo a saltar» y «celebro un cumple»
          son dos clientes distintos con dos precios distintos, y eso merece verse* — pero **una pantalla
          de categorías cobraría un toque a todo el mundo** para repartir cinco productos en dos montones.
          Así que se ve la bifurcación **y los precios siguen debajo, a un toque. Cero pantallas nuevas.**

          ⚠️ **El nombre `catalog-acc` es HISTÓRICO**: esto fue un acordeón y dejó de plegarse en `#P6`.
          No se renombra aquí a propósito —tocaría el manifiesto congelado y cuatro guardas por cero
          ganancia para el cliente—, pero ya no describe nada: si alguien lo renombra algún día, que sea
          su propio cambio.
        -->
        <section v-for="section in sections.filter((s) => (s.items ?? []).length > 0)" :key="section.key"
                 v-show="sectionMatches(section)"
                 class="catalog-acc__sec" :class="section.ink ? 'catalog-acc__sec--ink' : 'catalog-acc__sec--paper'"
                 :aria-labelledby="'catalog-title-' + section.key">
            <!-- La franja. NO se pliega: las secciones están siempre abiertas (P6). -->
            <div class="catalog-acc__head">
                <span class="catalog-acc__icon" aria-hidden="true">
                    <!--
                      Los iconos son componentes Blade que envuelven su SVG en un `<span class="icon …">`,
                      y ese envoltorio ES contrato: `.catalog-acc__head span` y `.catalog__go svg` son
                      selectores estructurales. Se replica el árbol, no el dibujo — el interior del
                      `<svg>` es geometría y el diff no desciende en él.
                    -->
                    <!-- ⚠️⚠️ **Sin la clase `.icon`, y no es un descuido** (`#259`): esa clase fuerza
                         `fill: none; stroke-width: 1.6` sobre cada `path`, o sea **dibuja a línea**.
                         Es correcta para las ilustraciones del idioma anterior y convertiría estos
                         dos glifos de MASA en un contorno fino. El envoltorio que sí es contrato es
                         `.catalog-acc__icon`, que sigue igual.
                         `pack` y `gift` del sistema de diseño, copiados byte a byte
                         (`SidebarIconParityTest`). La sección de ENTRADAS lleva `ui/pack` —la tira
                         troquelada, «varias entradas»— y no `ui/entrada`, porque rotula un GRUPO. -->
                    <span v-if="section.key === 'entries'" aria-hidden="true">
                        <svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor">
                            <path fill-rule="evenodd" clip-rule="evenodd" d="M4.4 6.4h2.9a1.5 1.5 0 0 0 3 0h3.4a1.5 1.5 0 0 0 3 0h2.9A2.4 2.4 0 0 1 22 8.8v6.4a2.4 2.4 0 0 1-2.4 2.4h-2.9a1.5 1.5 0 0 0-3 0h-3.4a1.5 1.5 0 0 0-3 0H4.4A2.4 2.4 0 0 1 2 15.2V8.8a2.4 2.4 0 0 1 2.4-2.4zM8.8 9.1a0.8 0.8 0 1 0 0 1.6 0.8 0.8 0 1 0 0-1.6zm0 2.1a0.8 0.8 0 1 0 0 1.6 0.8 0.8 0 1 0 0-1.6zm0 2.1a0.8 0.8 0 1 0 0 1.6 0.8 0.8 0 1 0 0-1.6zm6.4-4.2a0.8 0.8 0 1 0 0 1.6 0.8 0.8 0 1 0 0-1.6zm0 2.1a0.8 0.8 0 1 0 0 1.6 0.8 0.8 0 1 0 0-1.6zm0 2.1a0.8 0.8 0 1 0 0 1.6 0.8 0.8 0 1 0 0-1.6z" />
                        </svg>
                    </span>
                    <span v-else aria-hidden="true">
                        <svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor">
                            <path fill-rule="evenodd" clip-rule="evenodd" d="M3.4 9.2h17.2v3.4h-1.2v7.2a1.8 1.8 0 0 1-1.8 1.8H6.4a1.8 1.8 0 0 1-1.8-1.8v-7.2H3.4zm7 3.4v6.6h3.2v-6.6z" />
                            <circle cx="8.9" cy="5.6" r="2.8" />
                            <circle cx="15.1" cy="5.6" r="2.8" />
                        </svg>
                    </span>
                </span>
                <!-- ⚠️ El título y la FRASE van en el mismo bloque para que la franja los alinee como
                     una unidad: el icono y el recuento se centran contra los DOS, no contra el título.
                     La frase es lo que convierte un rótulo de categoría en una bifurcación legible. -->
                <span class="catalog-acc__txt">
                    <span class="catalog-acc__title" :id="'catalog-title-' + section.key">{{ t('section_' + section.key) }}</span>
                    <span class="catalog-acc__sub">{{ t('section_' + section.key + '_sub') }}</span>
                </span>
                <span class="catalog-acc__count">{{ section.items.length }}</span>
            </div>
            <!-- ❗❗ **AQUÍ VIVÍA LA MAQUINARIA DE PLEGADO, Y SE RETIRA CON SU SUJETO** (`#552`).
                 Eran tres piezas —`__body` con `grid-template-rows: 0fr`, `__body-inner` con su
                 `overflow`, y la clase `is-open` que lo abría— para un acordeón que **no se pliega desde
                 `#P6`**: el Blade emitía `is-open` fijo y esta plantilla también.
                 ⚠️⚠️ Y no era inofensivo: cuando el motor SPA se olvidó de emitir `is-open`, **el
                 catálogo entero salía a altura 0 y no se podía comprar nada**, y el diff de árbol no lo
                 veía. *Un mecanismo que siempre está en el mismo estado no es un mecanismo: es una
                 trampa esperando a que alguien se olvide de su clase.*
                 ⚠️ El `id` del cuerpo se va con ellas: no lo referenciaba ningún `aria-controls` — la
                 cabecera dejó de ser un `<button>` cuando dejó de plegarse. -->
            <div class="catalog-acc__body">
                <div class="catalog">
                    <button v-for="item in section.items" :key="item.id"
                            v-show="matches(item)"
                            type="button"
                            class="catalog__item"
                            :class="{ 'catalog__item--feat': item.featured }"
                            :data-search="item.search"
                            @click="emit('select', item.id)">
                        <!-- ⚠️⚠️ **Aquí el catálogo elegía su dibujo con `v-if="item.is_pack"`** — el patrón
                             exacto que `#140` retiró de la cesta y del resumen, y que sobrevivió aquí porque
                             la guarda miraba una lista de DOS ficheros escrita a mano y ésta no estaba
                             (`#259`). Con él, un catálogo entero se repartía en dos dibujos y elegir el
                             icono de un producto exigía tocar Vue.
                             ▶ La clave la manda ahora el servidor en `item.icon`, resuelta por
                             `Booking\Services\ProductIcon`, igual que en las otras dos superficies. -->
                        <span class="catalog__tk">
                            <ProductIcon :icon="item.icon" />
                        </span>
                        <span class="catalog__info">
                            <span class="catalog__name">{{ item.name }}<span v-if="item.badge" class="catalog__badge">{{ item.badge }}</span></span>
                            <span v-if="item.features" class="catalog__feat">{{ item.features }}</span>
                        </span>
                        <!-- ❗❗ **LA UNIDAD DEL PACK BAJA A SU PROPIA LÍNEA** (`#552`), y no es cosmética.
                             Vivía DENTRO de `.catalog__price`, que es `white-space: nowrap`, así que
                             «desde 14,95 € por niño» ocupaba una sola línea irrompible: medido, la
                             columna del precio de un pack se llevaba **159 px de 324** y dejaba la
                             descripción en **55**, recortada a mitad de palabra. Las entradas, sin
                             sufijo, tenían 115. ▶ *Una unidad pegada a su cifra dentro de un `nowrap`
                             no es un detalle tipográfico: es una columna que no se puede maquetar.* -->
                        <span v-if="item.from !== null || item.deposit_label" class="catalog__pricecol">
                            <span v-if="item.from !== null" class="catalog__price"><span class="price__from">{{ t('from') }}</span>{{ money(item.from) }}</span>
                            <span v-if="item.from !== null && item.is_pack" class="catalog__per">{{ item.period_label || t('per_child') }}</span>
                            <span v-if="item.deposit_label" class="catalog__deposit">{{ tp('deposit_catalog', { amount: item.deposit_label }) }}</span>
                        </span>
                        <span class="catalog__go" aria-hidden="true">
                            <!-- `arrow-right` del sistema de diseño, copiado byte a byte
                                 (`SidebarIconParityTest`). -->
                            <svg class="arrow-ico" width="16" height="16" viewBox="0 0 24 24" fill="currentColor"
                                 stroke="currentColor" stroke-width="3" stroke-linejoin="round" stroke-linecap="round"
                                 aria-hidden="true" focusable="false">
                                <path d="M13.6 6.4 19.2 12l-5.6 5.6z" />
                                <path d="M4.6 12h9.4" fill="none" />
                            </svg>
                        </span>
                    </button>
                </div>
            </div>
        </section>

        <p v-if="! hasItems" class="purchase__empty">{{ t('no_dates') }}</p>

        <p v-if="searchEnabled" v-show="! anyMatch" class="purchase__empty catalog-acc__none">{{ t('catalog_search_none') }}</p>
    </div>
</template>
