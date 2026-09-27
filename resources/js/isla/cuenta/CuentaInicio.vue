<script setup>
/**
 * **Mi cuenta, el inicio** (`paginas/mi-cuenta/cuenta.jsx`, la vista `inicio`; §4.13): arriba, las tres respuestas en
 * tres segundos —«Hola, Ana», la próxima en una línea (baja a su bloque) y, con tareas, «Siguiente: …» (baja a Antes de
 * venir, T5c)—; después los bloques en su orden. **Tu QR**: compacto, con «Enseñar mi QR» (`PmcQrMini`), o grande de
 * entrada si la reserva es HOY, que es lo que va a hacer (T5b). **Tu próxima reserva**, **Antes de venir** (T5c) y
 * **Otras reservas** con su historial (T5b), **Quién viene contigo** (T5d) y los **Ajustes** plegados con «Cerrar
 * sesión» (T5e). Bajo la cabecera, los **avisos de la cuenta** (T5e·2). Y la T5f: **Reservar otra vez** tras «Otras
 * reservas» y, con la cuenta aún sin ninguna reserva, su **bienvenida** («Tu cuenta está lista»), con una sola acción.
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
import BloqueOtraVez from './BloqueOtraVez.vue';
import BloqueQuien from './BloqueQuien.vue';
import BloqueSeguro from './BloqueSeguro.vue';
import AvisosCuenta from './AvisosCuenta.vue';
import IconoLucide from '../ui/IconoLucide.vue';
import BotonSistema from '../ui/BotonSistema.vue';
import PaseQr from '../ui/PaseQr.vue';
import EsqueletoCarga from '../ui/EsqueletoCarga.vue';

// `tuQr` es lo que pinta Tu QR —`qr` con `renovar`, `renovando` y `sinQr`, las props de `BloqueQr`—, y `aviso`, la
// confirmación de arriba (`{ texto, tono }`, las de `AvisoCuenta`): como la vista de Tu QR, en un objeto (T5f).
defineProps({
    nombre: { type: String, default: '' },
    linea: { type: String, default: '' },
    tuQr: { type: Object, required: true },
    aviso: { type: Object, default: null },
    hoy: { type: Boolean, default: false },
    proxima: { type: Object, default: null },
    esperandoProxima: { type: Boolean, default: false },
    antes: { type: Object, default: null },
    chip: { type: String, default: '' },
    otras: { type: Object, required: true },
    otraVez: { type: Object, default: null },
    bienvenida: { type: Boolean, default: false },
    quien: { type: Object, required: true },
    ajustes: { type: Object, default: null },
    saliendo: { type: Boolean, default: false },
    avisos: { type: Object, default: () => ({}) },
});
const emit = defineEmits(['qr', 'bloque', 'cambiar', 'abrir', 'mas', 'guardar', 'preguntar', 'renovar', 'tarea', 'hijos', 'hijo',
    'alternar', 'dato', 'guardar-datos', 'paso', 'vincular', 'interruptor', 'descargar', 'mas-recibos', 'salir', 'aviso', 'analitica',
    'otra-vez', 'primera']);
const { t, tp } = useTextos();

// Ajustes (T5e), en su trozo: va plegado al final («nada esencial vive aquí») y Mi cuenta pinta sin esperarlo. Dentro
// medía +30,8 KiB (el bloque, el acordeón, el selector y el interruptor), un tercio de Mi cuenta.
const BloqueAjustes = defineAsyncComponent(() => import('./BloqueAjustes.vue'));
</script>

<template>
    <div :style="CUENTA.columna">
        <AvisoCuenta
            v-if="aviso"
            v-bind="aviso"
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
        <!-- Los avisos de la cuenta (T5e·2), arriba, con «qué le falta». -->
        <AvisosCuenta
            v-bind="avisos"
            @aviso="(hace) => emit('aviso', hace)"
            @analitica="(que) => emit('analitica', que)"
        />
        <!-- La bienvenida (T5f): la cuenta está lista y el siguiente paso es obvio. Una sola acción. -->
        <section
            v-if="bienvenida"
            aria-labelledby="bienvenida-t"
            :style="{ display: 'grid', gap: '12px', padding: '18px', borderRadius: 'var(--r-xl)', border: '1px solid var(--notice-success-border)', background: 'var(--notice-success-bg)' }"
        >
            <h2
                id="bienvenida-t"
                :style="[CUENTA.h2, { display: 'flex', alignItems: 'center', gap: '10px' }]"
            >
                <IconoLucide
                    name="party-popper"
                    :size="20"
                    color="var(--text-positive)"
                />{{ t('mi_cuenta.bienvenida.titulo') }}
            </h2>
            <p :style="CUENTA.cuerpo">{{ t('mi_cuenta.bienvenida.texto') }}</p>
            <BotonSistema
                variant="inverse"
                full
                @click="emit('primera')"
            >
                {{ t('mi_cuenta.bienvenida.boton') }}<template #icono-derecha><IconoLucide
                    name="arrow-right"
                    :size="18"
                /></template>
            </BotonSistema>
        </section>
        <!-- Cada bloque, protegido (T5f): si sus datos o su pintura fallan, deja su hueco y el resto sigue. -->
        <BloqueSeguro
            :nombre="t('mi_cuenta.qr.titulo')"
            :datos="tuQr.qr"
        >
            <BloqueQr
                v-if="hoy"
                v-bind="tuQr"
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
                        :code="tuQr.qr.codigo"
                        :src="tuQr.qr.src"
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
        </BloqueSeguro>
        <BloqueSeguro
            v-if="proxima"
            :nombre="t('mi_cuenta.proxima.titulo')"
            :datos="proxima"
        >
            <BloqueReserva
                :titulo="t('mi_cuenta.proxima.titulo')"
                v-bind="proxima"
                @cambiar="emit('cambiar')"
            />
        </BloqueSeguro>
        <EsqueletoCarga
            v-else-if="esperandoProxima"
            kind="card"
            height="168px"
        />
        <BloqueSeguro
            v-if="antes"
            :nombre="t('mi_cuenta.antes.titulo')"
            :datos="antes"
        >
            <BloqueAntes
                :antes="antes"
                @tarea="(a) => emit('tarea', a)"
            />
        </BloqueSeguro>
        <BloqueSeguro
            :nombre="t('mi_cuenta.otras.titulo')"
            :datos="otras"
        >
            <BloqueOtras
                v-bind="otras"
                @abrir="(id) => emit('abrir', id)"
                @mas="emit('mas')"
            />
        </BloqueSeguro>
        <BloqueSeguro
            v-if="otraVez"
            :nombre="t('mi_cuenta.otra_vez.titulo')"
            :datos="otraVez"
        >
            <BloqueOtraVez
                :texto="otraVez.texto"
                @elegir="emit('otra-vez')"
            />
        </BloqueSeguro>
        <BloqueSeguro
            :nombre="t('mi_cuenta.quien.titulo')"
            :datos="quien"
        >
            <BloqueQuien
                v-bind="quien"
                @anadir="emit('hijos')"
                @abrir="(id) => emit('hijo', id)"
            />
        </BloqueSeguro>
        <BloqueSeguro
            v-if="ajustes"
            :nombre="t('mi_cuenta.ajustes.titulo')"
            :datos="ajustes"
        >
            <BloqueAjustes
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
        </BloqueSeguro>
    </div>
</template>
