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
 * `onClose`, `summary`, `total`, `today`, `note` y `action` ({ label, onClick, disabled, loading }).
 */
import { useTextos } from './textos.js';
import BloqueCookies from './BloqueCookies.vue';
import ControlIcono from './ControlIcono.vue';
import BotonAccion from './BotonAccion.vue';
import IconoLucide from '../ui/IconoLucide.vue';

defineProps({
    ck: { type: Object, required: true },
    top: { type: Boolean, default: false },
    cookies: { type: Object, default: null },
});
const { t } = useTextos();
</script>

<template>
    <div :style="{ display: 'flex', flexDirection: 'column', minHeight: 0, height: top ? 'auto' : 'calc(100dvh - 32px - env(safe-area-inset-top) - env(safe-area-inset-bottom))', maxHeight: top ? 'calc(100dvh - 2 * max(16px, 4vh) - 16px)' : undefined }">
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
                :style="{ height: '4px', borderRadius: '2px', background: i - 1 < ck.progress[0] ? 'var(--isla-vivo)' : 'rgba(255,255,255,0.2)', transition: 'background var(--dur-base) var(--ease-out)' }"
            />
        </div>
        <div
            :key="ck.key"
            data-isla-scroll=""
            tabindex="-1"
            :style="{ outline: 'none', flex: '1 1 auto', minHeight: 0, overflowY: 'auto', overscrollBehavior: 'contain', padding: '18px 8px 18px', scrollbarWidth: 'thin', scrollbarColor: 'rgba(255,255,255,0.28) transparent', animation: ck.dir === 'back' ? 'isla-step-back var(--dur-slow) var(--ease-out) both' : ck.dir === 'fwd' ? 'isla-step-fwd var(--dur-slow) var(--ease-out) both' : 'isla-swap var(--dur-slow) var(--ease-island) both' }"
        >
            <slot />
        </div>
        <div :style="{ display: 'grid', gap: '10px', paddingTop: '12px', borderTop: '1px solid rgba(255,255,255,0.14)' }">
            <div
                v-if="ck.summary"
                aria-live="polite"
                :style="{ display: 'flex', alignItems: 'flex-start', justifyContent: 'space-between', gap: '14px', padding: '0 6px' }"
            >
                <span :style="{ minWidth: 0, fontFamily: 'var(--font-ui)', fontSize: '14.5px', fontWeight: 'var(--fw-semibold)', lineHeight: 1.4, color: 'var(--isla-sobre)', textWrap: 'pretty' }">{{ ck.summary }}</span>
                <span
                    v-if="ck.total"
                    :style="{ display: 'flex', flexDirection: 'column', alignItems: 'flex-end', gap: '3px', flex: '0 0 auto' }"
                >
                    <b :style="{ fontFamily: 'var(--font-display)', fontWeight: 'var(--fw-black)', fontSize: '22px', lineHeight: 1, letterSpacing: '-0.02em', color: 'var(--isla-sobre)', whiteSpace: 'nowrap', fontVariantNumeric: 'tabular-nums' }">{{ ck.total }}</b>
                    <small
                        v-if="ck.today"
                        :style="{ fontFamily: 'var(--font-ui)', fontSize: '14px', fontWeight: 'var(--fw-bold)', color: 'var(--isla-vivo)', whiteSpace: 'nowrap' }"
                    >{{ ck.today }}</small>
                </span>
            </div>
            <div
                v-if="ck.note"
                :style="{ display: 'flex', alignItems: 'center', gap: '8px', padding: '0 6px', fontFamily: 'var(--font-ui)', fontSize: '14px', fontWeight: 'var(--fw-semibold)', color: 'var(--isla-foco)' }"
            ><IconoLucide
                name="clock"
                :size="16"
            />{{ ck.note }}</div>
            <BotonAccion
                v-if="ck.action"
                big
                :label="ck.action.label"
                :pulsar="ck.action.onClick"
                :disabled="Boolean(ck.action.disabled)"
                :loading="ck.action.loading || false"
            />
            <slot name="junto" />
        </div>
    </div>
</template>
