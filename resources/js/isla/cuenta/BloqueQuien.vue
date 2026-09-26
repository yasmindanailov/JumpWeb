<script setup>
/**
 * **Quién viene contigo** (`paginas/mi-cuenta/bloques-2.jsx`, `PmcQuien`; T5d de §4.13, `#777`): cada hijo con su inicial,
 * su nombre, su edad y «firmado», en fila como chips; «Añadir»; y que los adultos se registran ellos. Cada chip abre la
 * ficha de ese hijo —firmar por él, quitarlo—, que el mockup no dibuja (`#773`·d); sin firma, el chip lo dice.
 *
 * Pinta y avisa (`anadir`, `abrir`): qué dice cada chip lo decide `hijos.js`.
 */
import { useTextos } from '../piezas/textos.js';
import { CUENTA } from './estilos.js';
import BotonSistema from '../ui/BotonSistema.vue';
import IconoLucide from '../ui/IconoLucide.vue';

defineProps({
    hijos: { type: Array, default: () => [] },
    cargando: { type: Boolean, default: false },
});
const emit = defineEmits(['anadir', 'abrir']);
const { t } = useTextos();
</script>

<template>
    <section
        id="quien"
        aria-labelledby="quien-t"
        :style="CUENTA.bloque"
    >
        <h2
            id="quien-t"
            :style="CUENTA.h2"
        >{{ t('mi_cuenta.quien.titulo') }}</h2>
        <div :style="{ display: 'grid', gap: '8px' }">
            <h3 :style="[CUENTA.cuerpo, { fontWeight: 'var(--fw-bold)', color: 'var(--text-strong)' }]">{{ t('mi_cuenta.quien.hijos') }}</h3>
            <ul
                v-if="hijos.length"
                :style="{ listStyle: 'none', margin: 0, padding: 0, display: 'flex', flexWrap: 'wrap', gap: '8px' }"
            >
                <li
                    v-for="h in hijos"
                    :key="h.id"
                >
                    <button
                        type="button"
                        :aria-label="h.aria"
                        :style="{ display: 'inline-flex', alignItems: 'center', gap: '8px', minHeight: '40px', padding: '4px 12px 4px 4px', borderRadius: 'var(--r-pill)', border: `1px solid ${h.pendiente ? 'var(--notice-warn-border)' : 'var(--border-subtle)'}`, background: 'var(--surface-card)', cursor: 'pointer', font: 'inherit', color: 'inherit' }"
                        @click="emit('abrir', h.id)"
                    >
                        <span
                            aria-hidden="true"
                            :style="{ display: 'inline-flex', alignItems: 'center', justifyContent: 'center', width: '32px', height: '32px', borderRadius: '50%', background: 'var(--notice-info-bg)', color: 'var(--notice-info-fg)', fontFamily: 'var(--font-display)', fontWeight: 'var(--fw-black)', fontSize: '0.9375rem' }"
                        >{{ h.inicial }}</span>
                        <span
                            aria-hidden="true"
                            :style="{ fontFamily: 'var(--font-ui)', fontSize: 'var(--fs-body-sm)', color: 'var(--text-strong)' }"
                        ><b>{{ h.nombre }}</b>{{ h.edad ? `, ${h.edad}` : '' }}</span>
                        <span
                            v-if="h.firmado"
                            aria-hidden="true"
                            :style="{ display: 'inline-flex', alignItems: 'center', gap: '4px', fontFamily: 'var(--font-ui)', fontSize: 'var(--fs-caption)', fontWeight: 'var(--fw-bold)', color: 'var(--text-positive)' }"
                        ><IconoLucide
                            name="check"
                            :size="14"
                        />{{ t('mi_cuenta.quien.firmado') }}</span>
                        <span
                            v-else-if="h.pendiente"
                            aria-hidden="true"
                            :style="{ fontFamily: 'var(--font-ui)', fontSize: 'var(--fs-caption)', fontWeight: 'var(--fw-bold)', color: 'var(--notice-warn-fg)' }"
                        >{{ t('mi_cuenta.quien.falta_firma') }}</span>
                    </button>
                </li>
            </ul>
            <BotonSistema
                variant="outline"
                :disabled="cargando"
                :style="{ justifySelf: 'start' }"
                @click="emit('anadir')"
            >
                <template #icono-izquierda><IconoLucide
                    name="user-round-plus"
                    :size="18"
                /></template>{{ t('mi_cuenta.quien.anadir') }}
            </BotonSistema>
        </div>
        <p :style="CUENTA.pista">{{ t('mi_cuenta.quien.adultos') }}</p>
    </section>
</template>
