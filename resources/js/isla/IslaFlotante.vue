<script setup>
/**
 * LA ISLA — la carcasa de compra del sistema de diseño nuevo, portada 1:1 de `ParkIsland.jsx`
 * (`specs/isla-y-landing-nueva.md` §4.9, `DECISIONES #682`).
 *
 * Desde la Z6a (zip (6), «tres huecos, cristal y morph», §4.27): TRES huecos fijos, en móvil y en escritorio —el menú a la
 * izquierda, la acción en el centro y la cuenta a la derecha, los dos solo icono—, y la frase SIEMPRE: encima en móvil,
 * en la fila arriba (tras el menú y una línea vertical). Teléfono, WhatsApp, idioma y cookies viven dentro del menú. Es
 * cristal, y cada cambio es un morph: lo viejo se desenfoca y se va mientras lo nuevo llega. Desde la Z6b·2, un aviso sin
 * panel ni cookies ocupa la isla entera (`piezas/AvisoIsla.vue`) y la fila se esconde mientras dura.
 *
 * ⚠️ Es un PORT, y por eso sigue el orden del diseño bloque a bloque: cuando el diseño cambie, se compara
 * fichero con fichero. Se juzga contra él píxel a píxel (`scripts/pixel.mjs`, banco de la isla). Este fichero
 * PINTA (`CE-6`): el JSX del diseño está aquí; su estado y sus efectos, en `useIsla.js` y los `use*` que llama;
 * sus estilos en línea, en `forma.js`; sus props, en `props.js`.
 * En la COMPRA (situación 10, `checkout`) la isla es el contenedor de la reserva (`piezas/CompraIsla.vue`): el
 * paso va en la ranura de siempre y lo que acompaña a su acción, en la ranura `junto` (§4.10, T3c).
 */
import { provide, ref } from 'vue';
import { PROPS_ISLA } from './props.js';
import { useIsla } from './useIsla.js';
import { CLAVE_TEXTOS } from './piezas/textos.js';
import FraseIsla from './piezas/FraseIsla.vue';
import ControlIcono from './piezas/ControlIcono.vue';
import BotonAccion from './piezas/BotonAccion.vue';
import BannerRazon from './piezas/BannerRazon.vue';
import HuecoAccion from './piezas/HuecoAccion.vue';
import PanelIsla from './piezas/PanelIsla.vue';
import BloqueCookies from './piezas/BloqueCookies.vue';
import BloqueAviso from './piezas/BloqueAviso.vue';
import AvisoIsla from './piezas/AvisoIsla.vue';
import BloqueFallo from './piezas/BloqueFallo.vue';
import CompraIsla from './piezas/CompraIsla.vue';

const props = defineProps(PROPS_ISLA);
provide(CLAVE_TEXTOS, () => props.textos);

// Las referencias al DOM son del componente; lo que hace con ellas, de `useIsla()`. La fila y la frase de encima
// miden el alto en reposo que la isla publica (`--island-h`, la primera pantalla).
const wrapRef = ref(null);
const islandRef = ref(null);
const sizerRef = ref(null);
const panelRef = ref(null);
const rowRef = ref(null);
const lineRowRef = ref(null);

const {
    t, s, stack, view, top, r, isOpen, inCheckout, openRow, stretch, titleInRow, panelTitle, shownNotice,
    avisoEntero, avisoPausa, avisoTranscurrido, quitarAviso, pausarAviso, hayLinea, lineaAbre, accion, accionHref, accionAbierta, pulsarAccion, alTeclear, alternarPanel, panelProps, anuncio,
    cerrar, atras, apilarPanel, elegirPlan, navegar, cruce, cuenta, pulsarCuenta, ayudaEnFrase, tocar, hundir, soltar,
    veloSaliente, kb, tono, tamano, estiloRaiz, estiloIsla, estiloMedida, banner, pulsarBanner, hueco, huecoSale,
} = useIsla(props, { wrapRef, islandRef, sizerRef, panelRef, rowRef, lineRowRef });
// El velo: entra fundido y se va fundido (`isla-velo-sale`, en `isla.css`; Z3, `#782`). Sin `<Transition>` de Vue, a
// propósito: su maquinaria pesaba 10–14 KiB en cada trozo de la isla (medido); el que se va es otro nodo, que se quita solo.
const velo = { position: 'fixed', inset: 0, zIndex: -1, background: 'var(--isla-velo)', WebkitBackdropFilter: 'var(--blur-veil)', backdropFilter: 'var(--blur-veil)' };
// La línea vertical entre el menú y la frase, en la fila de arriba.
const raya = { flex: '0 0 auto', alignSelf: 'center', width: '1px', height: '24px', margin: '0 4px', background: 'var(--ink-surface-border)' };
</script>

<template>
    <div
        ref="wrapRef"
        data-isla=""
        :data-situation="s.id"
        :data-size="tamano"
        :data-tono="tono"
        :style="estiloRaiz"
        @pointerdown.capture="tocar"
        @keydown.capture="tocar"
    >
        <div
            v-if="scrim && isOpen"
            :style="{ ...velo, pointerEvents: 'auto', animation: 'isla-fade-in var(--dur-base) var(--ease-out) both' }"
            @pointerdown="inCheckout ? null : cerrar()"
        />
        <!-- El velo que se va, fundido: el de un panel que se cierra, o el de la capa grande que la píldora releva. -->
        <div
            v-if="veloSaliente"
            class="isla-velo-sale"
            :style="{ ...velo, pointerEvents: 'none' }"
            @animationend="veloSaliente = false"
        />

        <div
            ref="islandRef"
            data-surface="ink"
            :data-isla-velo="scrim && isOpen ? '1' : undefined"
            :style="estiloIsla"
            :role="inCheckout ? 'dialog' : undefined"
            :aria-modal="inCheckout ? 'true' : undefined"
            :aria-labelledby="inCheckout ? 'isla-compra-paso' : undefined"
            @keydown="alTeclear"
            @pointerdown.capture="hundir"
            @pointerup.capture="soltar"
            @pointercancel.capture="soltar"
            @pointerleave="soltar"
        >
            <!-- Fuera de la compra dice el aviso (Z6b·2): llega sin foco y sin esto no lo oye quien no lo ve (WCAG 4.1.3). -->
            <span
                role="status"
                aria-live="polite"
                :style="{ position: 'absolute', width: '1px', height: '1px', overflow: 'hidden', clip: 'rect(0 0 0 0)', whiteSpace: 'nowrap' }"
            >{{ inCheckout ? anuncio : shownNotice || '' }}</span>
            <div
                ref="sizerRef"
                :style="estiloMedida"
            >
                <CompraIsla
                    v-if="inCheckout"
                    :ck="checkout"
                    :top="top"
                    :cookies="cookies"
                    :kb="kb"
                >
                    <slot />
                    <template #junto>
                        <slot name="junto" />
                    </template>
                </CompraIsla>
                <template v-else>
                    <BloqueCookies
                        v-if="!top && cookies && view !== 'cookies'"
                        :cookies="cookies"
                        :top="top"
                    />
                    <PanelIsla
                        v-if="!top && isOpen"
                        :key="view"
                        ref="panelRef"
                        v-bind="panelProps"
                        @abrir="apilarPanel"
                        @navegar="navegar"
                        @elegir="elegirPlan"
                    />
                    <BloqueAviso
                        v-if="!top && shownNotice && !avisoEntero"
                        :texto="shownNotice"
                        :top="top"
                    />
                    <!-- La frase (Z6a): siempre. En móvil, en su renglón encima de la fila, nunca dentro del botón. -->
                    <div
                        v-if="!r.row && hayLinea && !avisoEntero"
                        ref="lineRowRef"
                        :style="{ padding: '2px 4px 7px', display: 'flex', alignItems: 'flex-start', gap: '6px' }"
                    >
                        <div :style="{ flex: '1 1 auto', minWidth: 0 }">
                            <FraseIsla
                                :s="s"
                                :cruce="cruce"
                                :abrir="lineaAbre"
                                :expanded="Boolean(s.opens) && view === s.opens"
                                :ayuda="ayudaEnFrase ? help : null"
                                @ayuda="(e) => alternarPanel('help', e)"
                            />
                        </div>
                    </div>
                    <BloqueFallo
                        v-if="!top && s.extra"
                        :extra="s.extra"
                        :top="top"
                    />

                    <!-- El aviso a isla entera (Z6b·2): sin panel ni cookies ocupa la isla, y la fila se esconde sin desmontarse. -->
                    <AvisoIsla
                        v-if="avisoEntero"
                        :key="shownNotice"
                        :texto="shownNotice"
                        :etiqueta="`${shownNotice} · ${t('control.cerrar_aviso')}`"
                        :top="top"
                        :pausado="avisoPausa"
                        :transcurrido="avisoTranscurrido"
                        @quitar="quitarAviso"
                        @pausa="pausarAviso"
                    />
                    <!-- Los tres huecos: el menú (o Volver), la acción y la cuenta (o la X). -->
                    <div
                        ref="rowRef"
                        :style="{ display: avisoEntero ? 'none' : 'flex', alignItems: 'center', gap: '8px', width: '100%' }"
                    >
                        <ControlIcono
                            v-if="openRow && stack.length > 1"
                            :label="t('control.volver')"
                            icon="chevron-left"
                            @click="atras"
                        />
                        <ControlIcono
                            v-else-if="!openRow"
                            :label="t('control.menu')"
                            icon="menu"
                            :expanded="view === 'menu'"
                            @click="(e) => alternarPanel('menu', e)"
                        />
                        <span
                            v-if="titleInRow"
                            :style="{ flex: '1 1 auto', minWidth: 0, padding: '0 6px', fontFamily: 'var(--font-ui)', fontWeight: 'var(--fw-bold)', fontSize: '15px', color: 'var(--isla-sobre)' }"
                        >{{ panelTitle }}</span>
                        <template v-if="r.row && hayLinea">
                            <i
                                v-if="!openRow"
                                aria-hidden="true"
                                :style="raya"
                            />
                            <div :style="{ flex: '1 1 auto', minWidth: 0, maxWidth: 'min(420px, 46vw)', display: 'flex' }">
                                <FraseIsla
                                    :s="s"
                                    :cruce="cruce"
                                    :abrir="lineaAbre"
                                    :expanded="Boolean(s.opens) && view === s.opens"
                                    :ayuda="ayudaEnFrase ? help : null"
                                    @ayuda="(e) => alternarPanel('help', e)"
                                />
                            </div>
                        </template>
                        <!-- Con el banner en el hueco no hay relleno (el owner, Z6b): con él, el banner quedaba a 24px del menú y a 8 de la cuenta. -->
                        <span
                            v-if="(top || openRow) && !hayLinea && !inCheckout && !(stretch && accion) && !titleInRow && !banner"
                            :style="{ flex: '1 1 auto', minWidth: '8px' }"
                        />
                        <!-- El hueco de la acción (Z6b): la acción o, en su sitio, el banner; y lo que se va, encima. -->
                        <HuecoAccion
                            v-if="hueco"
                            :clave="hueco.clave"
                            :crece="!top || isOpen || stretch"
                            :saliendo="Boolean(huecoSale)"
                        >
                            <BannerRazon
                                v-if="banner"
                                :bn="banner"
                                :top="top"
                                @pulsar="pulsarBanner"
                            />
                            <BotonAccion
                                v-else
                                :top="stretch ? false : top"
                                :label="accion.label"
                                :href="accionHref"
                                :expanded="accionAbierta"
                                :pulsar="pulsarAccion"
                                :calm="Boolean(s.calm) && !isOpen"
                                :entra="cruce.nA > 0"
                                :sale="cruce.accSale"
                            />
                            <template #sale>
                                <BannerRazon
                                    v-if="huecoSale && huecoSale.bn"
                                    :bn="huecoSale.bn"
                                    :top="top"
                                    quieto
                                />
                                <BotonAccion
                                    v-else-if="huecoSale && huecoSale.accion"
                                    :top="stretch ? false : top"
                                    :label="huecoSale.accion.label"
                                    :calm="huecoSale.accion.calm"
                                />
                            </template>
                        </HuecoAccion>
                        <ControlIcono
                            v-if="openRow"
                            :label="t('control.cerrar')"
                            icon="x"
                            @click="cerrar"
                        />
                        <ControlIcono
                            v-else-if="s.extra && s.extra.onDismiss"
                            :label="t('control.cerrar')"
                            icon="x"
                            @click="s.extra.onDismiss"
                        />
                        <ControlIcono
                            v-else
                            :label="cuenta.label"
                            :icon="cuenta.icon"
                            :dot="cuenta.dot"
                            @click="pulsarCuenta"
                        />
                    </div>

                    <BloqueFallo
                        v-if="top && s.extra"
                        :extra="s.extra"
                        :top="top"
                    />
                    <BloqueAviso
                        v-if="top && shownNotice && !avisoEntero"
                        :texto="shownNotice"
                        :top="top"
                    />
                    <PanelIsla
                        v-if="top && isOpen"
                        :key="view"
                        ref="panelRef"
                        v-bind="panelProps"
                        @abrir="apilarPanel"
                        @navegar="navegar"
                        @elegir="elegirPlan"
                    />
                    <BloqueCookies
                        v-if="top && cookies && view !== 'cookies'"
                        :cookies="cookies"
                        :top="top"
                    />
                </template>
            </div>
        </div>
    </div>
</template>
