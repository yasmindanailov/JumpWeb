<script setup>
/**
 * El tamaño «Compra» de la isla (situación 10, el `checkoutEl` de `ParkIsland.jsx`): la isla pasa a ser el
 * contenedor de la reserva. Arriba, el paso y la X (cerrar guarda), y Volver si hay un paso detrás; la barra de
 * progreso; en medio, el paso (la ranura), con su scroll y su dirección —avanzar entra por la derecha, Volver por
 * la izquierda—; abajo, lo que la isla enseña siempre (qué, cuándo, el total y «Hoy pagas…», y la nota con su
 * reloj) y la acción del paso, una sola, con lo que va junto a ella (la ranura `junto`). Sin menú. En móvil ocupa
 * toda la pantalla.
 *
 * `ck` es la descripción del paso que da quien lleva la compra: `key` (cambia con el paso), `dir` (`fwd` · `back`),
 * `stepStrong` y `step` («Paso 1 de 2» en negrita y « · Tus datos»), `progress` ([hecho, total]), `onBack`,
 * `onClose`, `summary`, `total`, `today`, `note` (con `noteIcon` y, si es lo que falta, `onNote`) y `action` ({ label,
 * onClick, disabled, loading }).
 * ▶ El movimiento (Z3, `#782`): al avanzar, el tramo de la barra se LLENA de izquierda a derecha (se ve el avance) y el
 * total RUEDA hasta su valor (`NumeroRodante`).
 * ▶ **El teclado del móvil** (zip del 26-09, §4.16; `useTeclado.js`): con él abierto, la capa mide lo que se ve —la
 * acción, siempre encima del teclado— y el pie se queda en la acción (el resumen, la nota y lo de debajo vuelven al
 * cerrarlo); Intro pasa al campo siguiente y, en el último, hace la acción del paso.
 */
import { ref } from 'vue';
import { useTextos } from './textos.js';
import { useIntro } from '../useTeclado.js';
import BloqueCookies from './BloqueCookies.vue';
import ControlIcono from './ControlIcono.vue';
import BotonAccion from './BotonAccion.vue';
import IconoLucide from '../ui/IconoLucide.vue';
import NumeroRodante from '../ui/NumeroRodante.vue';

const props = defineProps({
    ck: { type: Object, required: true },
    top: { type: Boolean, default: false },
    cookies: { type: Object, default: null },
    // La ventana visible con el teclado abierto (`useTeclado`, en la isla: ciñe también la raíz), o `null`.
    kb: { type: Object, default: null },
});
const { t } = useTextos();
const cajaRef = ref(null);
const { alIntro } = useIntro({ cajaRef, accion: () => props.ck.action, kb: () => props.kb });
</script>

<template>
    <div :style="{ display: 'flex', flexDirection: 'column', minHeight: 0, height: top ? 'auto' : kb ? `${kb.h - 16}px` : 'calc(100dvh - 32px - env(safe-area-inset-top) - env(safe-area-inset-bottom))', maxHeight: top ? 'calc(100dvh - 2 * max(16px, 4vh) - 16px)' : undefined }">
        <BloqueCookies
            v-if="cookies"
            :cookies="cookies"
            :top="top"
        />
        <div :style="{ display: 'flex', alignItems: 'center', gap: '10px' }">
            <ControlIcono
                v-if="ck.onBack"
                :label="t('control.volver')"
                icon="chevron-left"
                @click="ck.onBack"
            />
            <span
                id="isla-compra-paso"
                :style="{ flex: '1 1 auto', minWidth: 0, marginLeft: ck.onBack ? 0 : '6px', fontFamily: 'var(--font-ui)', fontSize: '14px', fontWeight: 'var(--fw-semibold)', lineHeight: 1.3, color: 'var(--text-muted)' }"
            ><b
                v-if="ck.stepStrong"
                :style="{ color: 'var(--isla-sobre)', fontWeight: 700 }"
            >{{ ck.stepStrong }}</b>{{ ck.step }}</span>
            <ControlIcono
                :label="t('control.cerrar')"
                icon="x"
                @click="ck.onClose"
            />
        </div>
        <div
            v-if="ck.progress"
            aria-hidden="true"
            :style="{ display: 'grid', gridTemplateColumns: `repeat(${ck.progress[1]}, minmax(0, 1fr))`, gap: '6px', margin: '10px 6px 0' }"
        >
            <i
                v-for="i in ck.progress[1]"
                :key="i"
                :style="{ position: 'relative', height: '4px', borderRadius: '2px', overflow: 'hidden', background: 'rgba(255,255,255,0.2)' }"
            ><b :style="{ position: 'absolute', inset: 0, borderRadius: '2px', background: 'var(--isla-vivo)', transformOrigin: 'left center', transform: i - 1 < ck.progress[0] ? 'scaleX(1)' : 'scaleX(0)', transition: 'transform var(--dur-slow) var(--ease-out)' }" /></i>
        </div>
        <div
            :key="ck.key"
            ref="cajaRef"
            data-isla-scroll=""
            tabindex="-1"
            :style="{ outline: 'none', flex: '1 1 auto', minHeight: 0, overflowY: 'auto', overscrollBehavior: 'contain', padding: '18px 8px 18px', scrollbarWidth: 'thin', scrollbarColor: 'rgba(255,255,255,0.28) transparent', animation: ck.dir === 'back' ? 'isla-step-back var(--dur-slow) var(--ease-out) both' : ck.dir === 'fwd' ? 'isla-step-fwd var(--dur-slow) var(--ease-out) both' : 'isla-swap var(--dur-slow) var(--ease-island) both' }"
            @keydown="alIntro"
        >
            <slot />
        </div>
        <div :style="{ display: 'grid', gap: '10px', paddingTop: '12px', borderTop: '1px solid rgba(255,255,255,0.14)' }">
            <div
                v-if="ck.summary && !kb"
                aria-live="polite"
                :style="{ display: 'flex', alignItems: 'flex-start', justifyContent: 'space-between', gap: '14px', padding: '0 6px' }"
            >
                <span :style="{ minWidth: 0, fontFamily: 'var(--font-ui)', fontSize: '14.5px', fontWeight: 'var(--fw-semibold)', lineHeight: 1.4, color: 'var(--isla-sobre)', textWrap: 'pretty' }">{{ ck.summary }}</span>
                <span
                    v-if="ck.total"
                    :style="{ display: 'flex', flexDirection: 'column', alignItems: 'flex-end', gap: '3px', flex: '0 0 auto' }"
                >
                    <b :style="{ fontFamily: 'var(--font-display)', fontWeight: 'var(--fw-black)', fontSize: '22px', lineHeight: 1, letterSpacing: '-0.02em', color: 'var(--isla-sobre)', whiteSpace: 'nowrap', fontVariantNumeric: 'tabular-nums' }"><NumeroRodante :value="ck.total" /></b>
                    <small
                        v-if="ck.today"
                        :style="{ fontFamily: 'var(--font-ui)', fontSize: '14px', fontWeight: 'var(--fw-bold)', color: 'var(--isla-vivo)', whiteSpace: 'nowrap' }"
                    >{{ ck.today }}</small>
                </span>
            </div>
            <!-- La nota, con su icono (el reloj, de serie). Con `onNote` es LO QUE FALTA (M2, `#881`): se toca y hace lo mismo
                 que el botón, como el «Elige la hora para ver el total» de la calculadora de la página. -->
            <component
                :is="ck.onNote ? 'button' : 'div'"
                v-if="ck.note && !kb"
                :type="ck.onNote ? 'button' : undefined"
                aria-live="polite"
                :style="{ display: 'flex', alignItems: 'center', gap: '8px', margin: 0, padding: '0 6px', border: 0, background: 'none', textAlign: 'left', fontFamily: 'var(--font-ui)', fontSize: '14px', fontWeight: 'var(--fw-semibold)', color: 'var(--isla-foco)', cursor: ck.onNote ? 'pointer' : 'auto' }"
                @click="ck.onNote?.()"
            ><IconoLucide
                :name="ck.noteIcon || 'clock'"
                :size="16"
            />{{ ck.note }}</component>
            <BotonAccion
                v-if="ck.action"
                big
                :label="ck.action.label"
                :pulsar="ck.action.onClick"
                :disabled="Boolean(ck.action.disabled)"
                :loading="ck.action.loading || false"
            />
            <slot
                v-if="!kb"
                name="junto"
            />
        </div>
    </div>
</template>
