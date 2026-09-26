<script setup>
/**
 * LA ISLA — la carcasa de compra del sistema de diseño nuevo, portada 1:1 de `ParkIsland.jsx`
 * (`specs/isla-y-landing-nueva.md` §4.9, `DECISIONES #682`).
 *
 * Dos controles y solo dos: el menú y la acción. Cuenta, Mi QR, teléfono, WhatsApp, idioma y cookies viven
 * DENTRO del menú: cada icono extra en la barra le resta clics al botón que vende. El contenedor mide su
 * contenido y anima alto y ancho, así que cambiar de situación es una transformación, no un salto.
 *
 * ⚠️ Es un PORT, y por eso sigue el orden del diseño bloque a bloque: cuando el diseño cambie, se compara
 * fichero con fichero. Se juzga contra él píxel a píxel (`scripts/pixel.mjs`, banco de la isla). Este fichero
 * PINTA (`CE-6`): el JSX del diseño está aquí; su estado y sus efectos, en `useIsla.js` y los `use*` que llama;
 * sus estilos en línea, en `forma.js`; sus props, en `props.js`.
 * En la COMPRA (situación 10, `checkout`) la isla es el contenedor de la reserva (`piezas/CompraIsla.vue`): el
 * paso va en la ranura de siempre y lo que acompaña a su acción, en la ranura `junto` (§4.10, T3c). El panel
 * «Mi QR» se pinta vacío hasta la T5 (necesita el carné), y sin sesión no se llega a él.
 */
import { provide, ref } from 'vue';
import { PROPS_ISLA } from './props.js';
import { useIsla } from './useIsla.js';
import { CLAVE_TEXTOS } from './piezas/textos.js';
import LineaContexto from './piezas/LineaContexto.vue';
import ControlIcono from './piezas/ControlIcono.vue';
import BotonAccion from './piezas/BotonAccion.vue';
import PanelIsla from './piezas/PanelIsla.vue';
import BloqueCookies from './piezas/BloqueCookies.vue';
import BloqueAviso from './piezas/BloqueAviso.vue';
import BloqueFallo from './piezas/BloqueFallo.vue';
import CompraIsla from './piezas/CompraIsla.vue';

const props = defineProps(PROPS_ISLA);
provide(CLAVE_TEXTOS, () => props.textos);

// Las cuatro referencias al DOM son del componente; lo que hace con ellas, de `useIsla()`.
const wrapRef = ref(null);
const islandRef = ref(null);
const sizerRef = ref(null);
const panelRef = ref(null);

const {
    t, s, stack, view, top, r, isOpen, inCheckout, openRow, stretch, pendiente, titleInRow, panelTitle, shownNotice,
    hayLinea, lineaAbre, accion, accionHref, accionAbierta, pulsarAccion, alTeclear, alternarPanel, panelProps, anuncio,
    cerrar, atras, apilarPanel, elegirPlan, abrirCapa, navegar, cambio, hundir, soltar, veloSaliente,
    tamano, estiloRaiz, estiloIsla, estiloMedida,
} = useIsla(props, { wrapRef, islandRef, sizerRef, panelRef });
// El velo: entra fundido y se va fundido (`isla-velo-sale`, en `isla.css`; Z3, `#782`). Sin `<Transition>` de Vue, a
// propósito: su maquinaria pesaba 10–14 KiB en cada trozo de la isla (medido); el que se va es otro nodo, que se quita solo.
const velo = { position: 'fixed', inset: 0, zIndex: -1, background: 'var(--isla-velo)', WebkitBackdropFilter: 'var(--blur-veil)', backdropFilter: 'var(--blur-veil)' };
</script>

<template>
    <div
        ref="wrapRef"
        data-isla=""
        :data-situation="s.id"
        :data-size="tamano"
        :style="estiloRaiz"
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
            <span
                role="status"
                aria-live="polite"
                :style="{ position: 'absolute', width: '1px', height: '1px', overflow: 'hidden', clip: 'rect(0 0 0 0)', whiteSpace: 'nowrap' }"
            >{{ inCheckout ? anuncio : '' }}</span>
            <div
                ref="sizerRef"
                :style="estiloMedida"
            >
                <CompraIsla
                    v-if="inCheckout"
                    :ck="checkout"
                    :top="top"
                    :cookies="cookies"
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
                        @capa="abrirCapa"
                        @navegar="navegar"
                        @elegir="elegirPlan"
                    />
                    <BloqueAviso
                        v-if="!top && shownNotice"
                        :texto="shownNotice"
                        :top="top"
                    />
                    <div
                        v-if="!r.row && hayLinea"
                        :style="{ padding: '2px 4px 7px', display: 'flex', alignItems: 'flex-start', gap: '6px' }"
                    >
                        <div :style="{ flex: '1 1 auto', minWidth: 0 }">
                            <LineaContexto
                                :key="`${s.id}|${s.line || ''}`"
                                :situation="s"
                                :top="top"
                                :abrir="lineaAbre"
                                :expanded="Boolean(s.opens) && view === s.opens"
                                :retraso="cambio === 'frase' ? 120 : 0"
                            />
                        </div>
                    </div>
                    <BloqueFallo
                        v-if="!top && s.extra"
                        :extra="s.extra"
                        :top="top"
                    />

                    <div :style="{ display: 'flex', alignItems: 'center', gap: '8px', width: '100%' }">
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
                            :dot="pendiente && !isOpen ? (bookingToday ? 'var(--isla-vivo)' : 'var(--isla-alerta)') : null"
                            @click="(e) => alternarPanel('menu', e)"
                        />
                        <span
                            v-if="titleInRow"
                            :style="{ flex: '1 1 auto', minWidth: 0, padding: '0 6px', fontFamily: 'var(--font-ui)', fontWeight: 'var(--fw-bold)', fontSize: '15px', color: 'var(--isla-sobre)' }"
                        >{{ panelTitle }}</span>
                        <div
                            v-if="r.row && hayLinea"
                            :style="{ flex: '1 1 auto', minWidth: 0, maxWidth: top ? 'min(420px, 46vw)' : 'calc(100vw - 110px)', display: 'flex', justifyContent: top ? 'flex-end' : 'flex-start', paddingRight: top ? 0 : '8px' }"
                        >
                            <LineaContexto
                                :key="`${s.id}|${s.line || ''}`"
                                :situation="s"
                                :top="top"
                                :abrir="lineaAbre"
                                :expanded="Boolean(s.opens) && view === s.opens"
                                :retraso="cambio === 'frase' ? 120 : 0"
                            />
                        </div>
                        <span
                            v-if="(top || openRow) && !hayLinea && !inCheckout && !(stretch && accion) && !titleInRow"
                            :style="{ flex: '1 1 auto', minWidth: '8px' }"
                        />
                        <BotonAccion
                            v-if="accion"
                            :top="stretch ? false : top"
                            :label="accion.label"
                            :sublabel="r.lineInButton ? s.line : null"
                            :href="accionHref"
                            :expanded="accionAbierta"
                            :pulsar="pulsarAccion"
                            :llega="cambio === 'llega'"
                        />
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
                    </div>

                    <BloqueFallo
                        v-if="top && s.extra"
                        :extra="s.extra"
                        :top="top"
                    />
                    <BloqueAviso
                        v-if="top && shownNotice"
                        :texto="shownNotice"
                        :top="top"
                    />
                    <PanelIsla
                        v-if="top && isOpen"
                        :key="view"
                        ref="panelRef"
                        v-bind="panelProps"
                        @abrir="apilarPanel"
                        @capa="abrirCapa"
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
