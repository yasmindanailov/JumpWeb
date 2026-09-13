<script setup>
import { computed, ref } from 'vue';
import { alternarSeccion, cuerpoVisible } from '../catalog.js';
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

/**
 * ❗❗❗ **LA PUERTA DE CATEGORÍA, y es del owner** (`#553`, `[DECIDIDO owner]`).
 *
 * El canvas dibujó DOS formas para el paso 1 y el owner propuso una TERCERA que es mejor que las dos:
 * **las categorías salen cerradas, como las dos tarjetas grandes de su «puerta de categoría», y al
 * pulsarlas se abren en la «tarjeta grande sin puerta»**. Con eso se consigue lo que la puerta quería
 * —que la bifurcación se vea— **sin pantalla nueva, sin navegación y sin botón de volver**, que era
 * justo lo que el canvas le reprochaba.
 *
 * ⚠️ **El coste, dicho y asumido**: cuesta un toque llegar a los precios, el mismo que el canvas le
 * reprochaba a la puerta. Lo que lo compensa es que la otra puerta **nunca se pierde de vista**.
 *
 * `[DECIDIDO owner]` las dos cosas que lo definen: **las dos arrancan CERRADAS** y **abrir una cierra
 * la otra**. Lo segundo no es un capricho: con las dos abiertas se vuelve a la lista larga de hoy
 * —medida en **1.033 px de contenido en una ventana de 650**— y la bifurcación desaparece.
 *
 * ⚠️⚠️ Esto **reabre el plegado que `#552` retiró**, y no es una contradicción: aquélla lo quitó porque
 * era un mecanismo que *siempre estaba en el mismo estado* —los dos motores emitían `is-open` fijo
 * desde un paso del refactor viejo, no desde una decisión de diseño—. Ahora pliega de verdad.
 */
const abierta = ref('');

/** ⚠️ Las DOS reglas viven en `catalog.js`, que es plano y tiene `node --test` (`CE-6`): aquí solo se
 *  conectan con el estado de la pantalla. */
const alternar = (key) => {
    abierta.value = alternarSeccion(abierta.value, key);
};

const seVe = (section) => cuerpoVisible(abierta.value, section.key, normalised.value !== '', sectionMatches(section));
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
          ❗❗❗ **LA PUERTA DE CATEGORÍA QUE SE ABRE EN TARJETA GRANDE** (`#553`, `[DECIDIDO owner]`).
          Cada sección es una TARJETA que tiene dos caras con el MISMO marcado:
           · **cerrada** — una puerta alta: icono grande, nombre en rótulo y la frase, centrados;
           · **abierta** — la franja de `#552` con los productos debajo.
          Cambia el CSS, no el árbol: una sola pieza, dos disposiciones.

          ⚠️ **El nombre `catalog-acc` vuelve a ser cierto**: fue un acordeón, dejó de plegarse en un
          paso del refactor viejo, `#552` retiró la maquinaria muerta y `#553` la devuelve **con su
          motivo**. No se renombra.
        -->
        <section v-for="section in sections.filter((s) => (s.items ?? []).length > 0)" :key="section.key"
                 v-show="sectionMatches(section)"
                 class="catalog-acc__sec"
                 :class="[section.ink ? 'catalog-acc__sec--ink' : 'catalog-acc__sec--paper', seVe(section) ? 'is-open' : '']"
                 :aria-labelledby="'catalog-title-' + section.key">
            <!--
              ⚠️⚠️ **VUELVE A SER UN `<button>`, y con `aria-expanded`.** Un `<div>` que pliega y
              despliega no lo anuncia ningún lector de pantalla y no se alcanza con el teclado; y el
              estado no puede vivir solo en una clase, porque una clase no la lee nadie más que el CSS.
              ▶ Es el agujero que este proyecto ya pagó dos veces (`#58(f)` y `#P6`): el diff de árbol
              **descarta los `:*` como andamiaje**, así que un `aria-expanded` dinámico le resulta
              invisible. Quien lo vigila es `SidebarCatalogCardTest`, no el gate de árbol.
            -->
            <button type="button" class="catalog-acc__head"
                    :aria-expanded="seVe(section) ? 'true' : 'false'"
                    :aria-controls="'catalog-sec-' + section.key"
                    @click="alternar(section.key)">
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
                         `pack` y `party` del sistema de diseño, copiados byte a byte
                         (`SidebarIconParityTest`).
                         ❗ `#568` (`[DECIDIDO owner]`): ENTRADAS lleva el `ui/pack` vigente del set —la
                         entrada repetida, no la «tira troquelada» que no se reconocía como pack— y
                         GRUPOS los banderines de `ui/fiestas` («fiestas y grupos»), no el regalo, que
                         se leía como una tarta: un grupo no es solo un cumpleaños. -->
                    <span v-if="section.key === 'entries'" aria-hidden="true">
                        <svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor">
                            <g transform="translate(12 12) scale(1.5038) translate(-12 -12)">
                                <g transform="translate(-1 -3.4) translate(12 12) scale(0.5) rotate(-8) translate(-12 -12)">
                                    <path fill-rule="evenodd" clip-rule="evenodd" d="M4 6.6h16a2.2 2.2 0 0 1 2.2 2.2v1.4a1.9 1.9 0 0 0 0 3.6v1.4a2.2 2.2 0 0 1-2.2 2.2H4a2.2 2.2 0 0 1-2.2-2.2v-1.4a1.9 1.9 0 0 0 0-3.6V8.8A2.2 2.2 0 0 1 4 6.6zm2.2 3.2v4.8h7.6V9.8z" />
                                </g>
                                <g transform="translate(1 3.4) translate(12 12) scale(0.5) rotate(8) translate(-12 -12)">
                                    <path fill-rule="evenodd" clip-rule="evenodd" d="M4 6.6h16a2.2 2.2 0 0 1 2.2 2.2v1.4a1.9 1.9 0 0 0 0 3.6v1.4a2.2 2.2 0 0 1-2.2 2.2H4a2.2 2.2 0 0 1-2.2-2.2v-1.4a1.9 1.9 0 0 0 0-3.6V8.8A2.2 2.2 0 0 1 4 6.6zm2.2 3.2v4.8h7.6V9.8z" />
                                </g>
                            </g>
                        </svg>
                    </span>
                    <span v-else aria-hidden="true">
                        <svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor" stroke="currentColor" stroke-width="1" stroke-linejoin="round">
                            <path d="M2.6 7.2q9.4 3 18.8 0" fill="none" stroke-width="3" stroke-linecap="round" />
                            <path d="M3.4 9.4h4L5.4 14z" />
                            <path d="M10 10.2h4l-2 4.6z" />
                            <path d="M16.6 9.4h4L18.6 14z" />
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
                <!-- ❗ `#568` · **LA INVITACIÓN A ABRIR** (`[DECIDIDO owner]`). No es un control aparte —la
                     cabecera entera ya es el botón, y un botón no puede ir dentro de otro—: dice qué pasa
                     al pulsar. Solo con la tarjeta CERRADA; abierta ya no hay nada que invitar. -->
                <span class="catalog-acc__cta" aria-hidden="true">
                    {{ t('section_' + section.key + '_cta') }}
                    <!-- `arrow-right` del sistema de diseño, copiado byte a byte (`SidebarIconParityTest`). -->
                    <svg class="arrow-ico" width="16" height="16" viewBox="0 0 24 24" fill="currentColor"
                         stroke="currentColor" stroke-width="3" stroke-linejoin="round" stroke-linecap="round"
                         aria-hidden="true" focusable="false">
                        <path d="M13.6 6.4 19.2 12l-5.6 5.6z" />
                        <path d="M4.6 12h9.4" fill="none" />
                    </svg>
                </span>
                <span class="catalog-acc__count">{{ section.items.length }}</span>
            </button>
            <!-- ⚠️ El cuerpo recupera su `id` porque vuelve a tener quién lo referencie: el
                 `aria-controls` de la cabecera. Se PINTA siempre y lo esconde el CSS —la técnica de
                 rejilla `0fr → 1fr`, que anima la altura real sin medirla y es el idioma que este repo
                 ya usa en `.acct`—: con `v-if` el contenido no existiría y no habría nada que animar. -->
            <div class="catalog-acc__body" :id="'catalog-sec-' + section.key">
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
