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
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"></circle><line x1="21" y1="21" x2="16.5" y2="16.5"></line></svg>
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
                        <svg viewBox="0 0 50 32" width="24" height="15"></svg>
                    </span>
                    <span v-else class="icon ic-b1" aria-hidden="true">
                        <svg viewBox="0 0 40 40" width="18" height="18"></svg>
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
                                    <svg viewBox="0 0 40 40" width="26" height="26"></svg>
                                </span>
                                <span v-else class="tk" aria-hidden="true">
                                    <svg viewBox="0 0 60 36" width="38" height="24" fill="none"></svg>
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
                                <svg class="arrow-ico" viewBox="0 0 24 24" aria-hidden="true"></svg>
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
