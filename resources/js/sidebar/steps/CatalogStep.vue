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

        <section v-for="section in sections.filter((s) => (s.items ?? []).length > 0)" :key="section.key"
                 v-show="sectionMatches(section)"
                 class="catalog-acc__sec" :aria-labelledby="'catalog-title-' + section.key">
            <!-- Cabecera NO plegable: las secciones están siempre abiertas (P6). -->
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
                <span class="catalog-acc__title" :id="'catalog-title-' + section.key">{{ t('section_' + section.key) }}</span>
                <span class="catalog-acc__count">{{ section.items.length }}</span>
            </div>
            <!-- ⚠️ **`is-open` NO es decorativa: sin ella el catálogo no enseña NADA.** El CSS colapsa
                 el cuerpo con `grid-template-rows: 0fr` y solo `.is-open` lo abre. El Blade la emite
                 SIEMPRE —su `isOpen()` devuelve `true` fijo: las secciones no son plegables desde #P6—
                 y aquí faltaba, así que con el motor SPA las secciones salían a altura 0 y no se podía
                 comprar nada. Lo encontró el extremo a extremo con navegador; **el diff de árbol no
                 podía verlo**, porque en el Blade la clase la pone un `:class` de Alpine y el
                 normalizador descarta los `:*` como andamiaje — el mismo agujero que `aria-expanded`. -->
            <div class="catalog-acc__body is-open" :id="'catalog-sec-' + section.key">
                <div class="catalog-acc__body-inner">
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
                            <span v-if="item.from !== null || item.deposit_label" class="catalog__pricecol">
                                <span v-if="item.from !== null" class="catalog__price"><span class="price__from">{{ t('from') }}</span>{{ money(item.from) }}<span v-if="item.is_pack" class="catalog__per"> {{ item.period_label || t('per_child') }}</span></span>
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
            </div>
        </section>

        <p v-if="! hasItems" class="purchase__empty">{{ t('no_dates') }}</p>

        <p v-if="searchEnabled" v-show="! anyMatch" class="purchase__empty catalog-acc__none">{{ t('catalog_search_none') }}</p>
    </div>
</template>
