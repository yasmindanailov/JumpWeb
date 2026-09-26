<script setup>
/**
 * **Mi cuenta, el inicio** (`paginas/mi-cuenta/cuenta.jsx`, la vista `inicio`; §4.13): arriba, las tres respuestas en
 * tres segundos —«Hola, Ana», la próxima en una línea (baja a su bloque) y, con tareas, «Siguiente: …» (baja a Antes de
 * venir, T5c)—; después los bloques en su orden. **Tu QR**: compacto, con «Enseñar mi QR» (`PmcQrMini`), o grande de
 * entrada si la reserva es HOY, que es lo que va a hacer (T5b). **Tu próxima reserva**, **Antes de venir** (T5c) y
 * **Otras reservas** con su historial (T5b), **Quién viene contigo** (T5d) y los **Ajustes** plegados con «Cerrar
 * sesión» (T5e). Reservar otra vez se suma aquí en la T5f.
 *
 * Pinta y avisa: qué se enseña lo decide `useSeccionCuenta.js` (y `reservas.js`).
 */
import { useTextos } from '../piezas/textos.js';
import { CUENTA } from './estilos.js';
import AvisoCuenta from './AvisoCuenta.vue';
import BloqueQr from './BloqueQr.vue';
import BloqueReserva from './BloqueReserva.vue';
import BloqueAntes from './BloqueAntes.vue';
import BloqueOtras from './BloqueOtras.vue';
import { defineAsyncComponent } from 'vue';
import BloqueQuien from './BloqueQuien.vue';
import IconoLucide from '../ui/IconoLucide.vue';
import BotonSistema from '../ui/BotonSistema.vue';
import PaseQr from '../ui/PaseQr.vue';
import EsqueletoCarga from '../ui/EsqueletoCarga.vue';

defineProps({
    nombre: { type: String, default: '' },
    linea: { type: String, default: '' },
    qr: { type: Object, required: true },
    aviso: { type: String, default: '' },
    avisoTono: { type: String, default: 'success' },
    hoy: { type: Boolean, default: false },
    renovar: { type: Boolean, default: false },
    renovando: { type: Boolean, default: false },
    sinQr: { type: String, default: '' },
    proxima: { type: Object, default: null },
    esperandoProxima: { type: Boolean, default: false },
    antes: { type: Object, default: null },
    chip: { type: String, default: '' },
    otras: { type: Object, required: true },
    quien: { type: Object, required: true },
    ajustes: { type: Object, default: null },
    saliendo: { type: Boolean, default: false },
});
const emit = defineEmits([
    'qr', 'bloque', 'cambiar', 'abrir', 'mas', 'guardar', 'preguntar', 'renovar', 'tarea', 'hijos', 'hijo',
    'alternar', 'dato', 'guardar-datos', 'paso', 'vincular', 'interruptor', 'descargar', 'mas-recibos', 'salir',
]);
const { t, tp } = useTextos();

// Ajustes (T5e), en su trozo: va plegado al final («nada esencial vive aquí») y Mi cuenta pinta sin esperarlo. Dentro
// medía +30,8 KiB (el bloque, el acordeón, el selector y el interruptor), un tercio de Mi cuenta.
const BloqueAjustes = defineAsyncComponent(() => import('./BloqueAjustes.vue'));
</script>

<template>
    <div :style="CUENTA.columna">
        <AvisoCuenta
            v-if="aviso"
            :texto="aviso"
            :tono="avisoTono"
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
            <button
                v-if="chip"
                type="button"
                :style="{ display: 'inline-flex', alignItems: 'center', gap: '8px', justifySelf: 'start', minHeight: '36px', padding: '0 14px', borderRadius: 'var(--r-pill)', border: '1px solid var(--notice-warn-border)', background: 'var(--notice-warn-bg)', cursor: 'pointer', fontFamily: 'var(--font-ui)', fontSize: 'var(--fs-caption)', fontWeight: 'var(--fw-bold)', color: 'var(--text-strong)' }"
                @click="emit('bloque', 'antes')"
            >
                <span
                    aria-hidden="true"
                    :style="{ width: '8px', height: '8px', borderRadius: '50%', background: 'var(--notice-warn-fg)' }"
                />{{ chip }}
            </button>
        </header>
        <BloqueQr
            v-if="hoy"
            :qr="qr"
            :renovar="renovar"
            :renovando="renovando"
            :sin-qr="sinQr"
            @guardar="emit('guardar')"
            @preguntar="(si) => emit('preguntar', si)"
            @renovar="emit('renovar')"
        />
        <section
            v-else
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
        <BloqueReserva
            v-if="proxima"
            :titulo="t('mi_cuenta.proxima.titulo')"
            v-bind="proxima"
            @cambiar="emit('cambiar')"
        />
        <EsqueletoCarga
            v-else-if="esperandoProxima"
            kind="card"
            height="168px"
        />
        <BloqueAntes
            v-if="antes"
            :antes="antes"
            @tarea="(a) => emit('tarea', a)"
        />
        <BloqueOtras
            v-bind="otras"
            @abrir="(id) => emit('abrir', id)"
            @mas="emit('mas')"
        />
        <BloqueQuien
            v-bind="quien"
            @anadir="emit('hijos')"
            @abrir="(id) => emit('hijo', id)"
        />
        <BloqueAjustes
            v-if="ajustes"
            :ajustes="ajustes"
            :saliendo="saliendo"
            @alternar="(id) => emit('alternar', id)"
            @dato="(campo, valor) => emit('dato', campo, valor)"
            @guardar="emit('guardar-datos')"
            @paso="(v) => emit('paso', v)"
            @vincular="emit('vincular')"
            @interruptor="(nombre, valor) => emit('interruptor', nombre, valor)"
            @descargar="emit('descargar')"
            @mas="emit('mas-recibos')"
            @salir="emit('salir')"
        />
    </div>
</template>
