<script setup>
import { computed, ref } from 'vue';
import { t as translate, tp as translateWith } from '../i18n.js';
import { money } from '../money.js';

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
                    <span v-if="section.key === 'entries'" class="icon ic-e5" aria-hidden="true">
                        <svg viewBox="0 0 50 32" width="24" height="15">
                            <g class="t-deal">
                                <path d="M 12 4 L 46 4 L 46 7 A 2.2 2.2 0 0 0 46 11.4 L 46 16 L 12 16 L 12 11.4 A 2.2 2.2 0 0 0 12 7 Z" />
                            </g>
                            <path class="occ" d="M 8 9 L 42 9 L 42 12 A 2.2 2.2 0 0 0 42 16.4 L 42 21 L 8 21 L 8 16.4 A 2.2 2.2 0 0 0 8 12 Z" />
                            <path class="occ" d="M 4 14 L 38 14 L 38 17 A 2.2 2.2 0 0 0 38 21.4 L 38 26 L 4 26 L 4 21.4 A 2.2 2.2 0 0 0 4 17 Z" />
                            <path d="M 11 16.5 L 11 23.5" class="dashed" />
                            <path d="M 15 18.5 L 33 18.5" />
                            <path d="M 15 22 L 27 22" class="thin" />
                        </svg>
                    </span>
                    <span v-else class="icon ic-b1" aria-hidden="true">
                        <svg viewBox="0 0 40 40" width="18" height="18">
                            <path d="M 7 33 L 33 33" />
                            <path d="M 10 33 L 10 25 Q 10 22 13 22 L 27 22 Q 30 22 30 25 L 30 33" />
                            <path d="M 11.5 27.5 L 28.5 27.5" class="dashed thin" />
                            <path d="M 20 22 L 20 15" />
                            <path class="flame accent-fill" d="M 20 14.5 Q 22.4 11.6 20 8.6 Q 17.6 11.6 20 14.5 Z" />
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
                            <span class="catalog__tk">
                                <span v-if="item.is_pack" class="icon ic-b1" aria-hidden="true">
                                    <svg viewBox="0 0 40 40" width="26" height="26">
                                        <path d="M 7 33 L 33 33" />
                                        <path d="M 10 33 L 10 25 Q 10 22 13 22 L 27 22 Q 30 22 30 25 L 30 33" />
                                        <path d="M 11.5 27.5 L 28.5 27.5" class="dashed thin" />
                                        <path d="M 20 22 L 20 15" />
                                        <path class="flame accent-fill" d="M 20 14.5 Q 22.4 11.6 20 8.6 Q 17.6 11.6 20 14.5 Z" />
                                    </svg>
                                </span>
                                <span v-else class="tk" aria-hidden="true">
                                    <svg viewBox="0 0 60 36" width="38" height="24" fill="none">
                                        <g class="body">
                                            <path d="M 4 4 L 42 4 L 42 8 A 1.4 1.4 0 0 0 42 12 L 42 16 A 1.4 1.4 0 0 0 42 20 L 42 24 A 1.4 1.4 0 0 0 42 28 L 42 32 L 4 32 L 4 28 A 1.4 1.4 0 0 0 4 24 L 4 20 A 1.4 1.4 0 0 0 4 16 L 4 12 A 1.4 1.4 0 0 0 4 8 Z" />
                                            <line x1="14" y1="14" x2="34" y2="14" class="thin" />
                                            <line x1="14" y1="20" x2="34" y2="20" class="thin" />
                                        </g>
                                        <path class="dashed" d="M 42 5.5 L 42 30.5" />
                                        <g class="stub">
                                            <path d="M 42 4 L 56 4 L 56 8 A 1.4 1.4 0 0 0 56 12 L 56 16 A 1.4 1.4 0 0 0 56 20 L 56 24 A 1.4 1.4 0 0 0 56 28 L 56 32 L 42 32 L 42 28 A 1.4 1.4 0 0 1 42 24 L 42 20 A 1.4 1.4 0 0 1 42 16 L 42 12 A 1.4 1.4 0 0 1 42 8 Z" />
                                            <text class="stubnum" x="49" y="22.5" text-anchor="middle">1</text>
                                        </g>
                                    </svg>
                                </span>
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
