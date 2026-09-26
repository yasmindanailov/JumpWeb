<script setup>
/**
 * **Mi cuenta, el inicio** (`paginas/mi-cuenta/cuenta.jsx`, la vista `inicio`; T5a de §4.13): arriba, las tres
 * respuestas en tres segundos —«Hola, Ana», la próxima en una línea (baja a su bloque) y, con tareas, «Siguiente: …»
 * (T5c)—; después los bloques en su orden. En la T5a, el primero: **Tu QR**, compacto, con «Enseñar mi QR», que lo
 * abre en grande (`PmcQrMini`). Los demás (la próxima, Antes de venir, Otras reservas, Reservar otra vez, Quién viene
 * contigo y los Ajustes) se suman aquí en las tandas que los traen.
 *
 * Pinta y avisa (`qr`, `bloque`): qué se enseña lo decide `useSeccionCuenta.js`.
 */
import { useTextos } from '../piezas/textos.js';
import { CUENTA } from './estilos.js';
import AvisoCuenta from './AvisoCuenta.vue';
import IconoLucide from '../ui/IconoLucide.vue';
import BotonSistema from '../ui/BotonSistema.vue';
import PaseQr from '../ui/PaseQr.vue';

defineProps({
    nombre: { type: String, default: '' },
    linea: { type: String, default: '' },
    qr: { type: Object, required: true },
    aviso: { type: String, default: '' },
});
const emit = defineEmits(['qr', 'bloque']);
const { t, tp } = useTextos();
</script>

<template>
    <div :style="CUENTA.columna">
        <AvisoCuenta
            v-if="aviso"
            :texto="aviso"
        />
        <header :style="{ display: 'grid', gap: '10px' }">
            <h1
                tabindex="-1"
                :style="CUENTA.h1"
            >{{ tp('mi_cuenta.hola', { nombre }) }}</h1>
            <button
                v-if="linea"
                type="button"
                :style="{ display: 'flex', alignItems: 'center', gap: '10px', justifySelf: 'start', maxWidth: '100%', minHeight: '44px', padding: 0, border: 'none', background: 'none', textAlign: 'left', cursor: 'pointer', fontFamily: 'var(--font-ui)', fontSize: 'var(--fs-body-sm)', fontWeight: 'var(--fw-semibold)', color: 'var(--text-body)' }"
                @click="emit('bloque', 'proxima')"
            >
                <IconoLucide
                    name="calendar-check"
                    :size="18"
                    color="var(--icon-accent)"
                />
                <span :style="{ minWidth: 0 }">{{ linea }}</span>
            </button>
        </header>
        <section
            id="mi-qr"
            aria-labelledby="mi-qr-t"
            :style="{ display: 'grid', gridTemplateColumns: 'auto minmax(0,1fr)', alignItems: 'center', gap: '14px', padding: '12px', borderRadius: 'var(--r-xl)', border: '1px solid var(--border-subtle)', background: 'var(--surface-card)', scrollMarginTop: '16px' }"
        >
            <button
                type="button"
                :aria-label="t('mi_cuenta.qr.ensenar')"
                :style="{ padding: 0, border: 'none', background: 'none', cursor: 'pointer', borderRadius: 'var(--r-md)' }"
                @click="emit('qr')"
            >
                <PaseQr
                    :code="qr.codigo"
                    :src="qr.src"
                    size="sm"
                />
            </button>
            <div :style="{ display: 'grid', gap: '8px', minWidth: 0 }">
                <h2
                    id="mi-qr-t"
                    :style="CUENTA.h2"
                >{{ t('mi_cuenta.qr.mini') }}</h2>
                <BotonSistema
                    variant="inverse"
                    full
                    @click="emit('qr')"
                >
                    <template #icono-izquierda><IconoLucide
                        name="maximize-2"
                        :size="18"
                    /></template>{{ t('mi_cuenta.qr.ensenar') }}
                </BotonSistema>
            </div>
        </section>
    </div>
</template>
