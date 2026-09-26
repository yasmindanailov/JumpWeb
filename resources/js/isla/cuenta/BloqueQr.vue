<script setup>
/**
 * **Tu QR, entero** (`paginas/mi-cuenta/bloques.jsx`, `PmcQr`): el QR grande sobre blanco (la PNG del servidor,
 * `identidad-qr-puerta.md` §0), «Guardar en el móvil» (esa imagen), el código para dictar y «Renovar mi QR», que
 * pregunta en el sitio. Dos formas: en su VISTA de la capa (`vista`, sin tarjeta y con el título en la banda) o en su
 * TARJETA del inicio, que es como sale de entrada cuando la reserva es HOY (T5b). [Wallet], apagado (`#773`·c).
 *
 * ⚠️ Un carné que el servidor no puede dibujar (su clave rotó) no pinta un hueco: dice por qué y ofrece renovarlo.
 */
import { useTextos } from '../piezas/textos.js';
import { CUENTA } from './estilos.js';
import IconoLucide from '../ui/IconoLucide.vue';
import BotonSistema from '../ui/BotonSistema.vue';
import EnlaceSistema from '../ui/EnlaceSistema.vue';
import AvisoDestacado from '../ui/AvisoDestacado.vue';
import PaseQr from '../ui/PaseQr.vue';
import EsqueletoCarga from '../ui/EsqueletoCarga.vue';

defineProps({
    qr: { type: Object, required: true },
    vista: { type: Boolean, default: false },
    renovar: { type: Boolean, default: false },
    renovando: { type: Boolean, default: false },
    sinQr: { type: String, default: '' },
});
const emit = defineEmits(['guardar', 'preguntar', 'renovar']);
const { t, tp } = useTextos();
</script>

<template>
    <section
        id="mi-qr"
        aria-labelledby="mi-qr-t"
        :style="[{ display: 'grid', gap: '16px', justifyItems: 'center', textAlign: 'center', scrollMarginTop: '16px' }, vista ? { padding: '8px 0 0' } : { padding: '20px 16px 16px', borderRadius: 'var(--r-xl)', border: '1px solid var(--border-subtle)', background: 'var(--surface-card)' }]"
    >
        <h2
            id="mi-qr-t"
            :style="vista ? CUENTA.oculto : [CUENTA.h2, { color: 'var(--text-muted)' }]"
        >{{ t('mi_cuenta.qr.titulo') }}</h2>
        <div
            v-if="qr.cargando"
            :style="{ width: '248px' }"
        >
            <EsqueletoCarga
                kind="block"
                aspect="1"
                radius="var(--r-lg)"
            />
        </div>
        <PaseQr
            v-else-if="qr.dibujable"
            :code="qr.codigo"
            :src="qr.src"
            size="lg"
            :show-code="false"
            :label="tp('mi_cuenta.qr.de', { codigo: qr.codigo })"
        />
        <AvisoDestacado
            v-else
            tone="warn"
            size="sm"
            :title="sinQr"
            :style="{ width: '100%', textAlign: 'left' }"
        >
            <template #icono><IconoLucide
                name="triangle-alert"
                :size="18"
            /></template>
        </AvisoDestacado>
        <template v-if="qr.dibujable">
            <p :style="[CUENTA.cuerpo, { color: 'var(--text-strong)', fontWeight: 'var(--fw-bold)', fontSize: 'var(--fs-body)' }]">{{ t('mi_cuenta.qr.texto') }}</p>
            <div :style="{ display: 'grid', gap: '8px', width: '100%', maxWidth: '340px' }">
                <BotonSistema
                    variant="inverse"
                    size="lg"
                    full
                    :href="qr.src"
                    download="mi-qr.png"
                    @click="emit('guardar')"
                >
                    <template #icono-izquierda><IconoLucide
                        name="download"
                        :size="19"
                    /></template>{{ t('mi_cuenta.qr.guardar') }}
                </BotonSistema>
            </div>
            <p :style="CUENTA.pista">{{ `${t('mi_cuenta.qr.dicta')} ` }}<b :style="{ font: 'var(--fw-medium) 1.0625rem/1 var(--font-mono)', letterSpacing: '0.08em', color: 'var(--text-strong)', whiteSpace: 'nowrap' }">{{ qr.codigo }}</b>.</p>
        </template>
        <AvisoDestacado
            v-if="renovar"
            tone="warn"
            size="sm"
            role="alertdialog"
            :title="t('mi_cuenta.qr.renovar')"
            :style="{ width: '100%', textAlign: 'left' }"
        >
            <template #icono><IconoLucide
                name="triangle-alert"
                :size="18"
            /></template>
            <div :style="{ display: 'grid', gap: '10px' }">
                <span>{{ t('mi_cuenta.qr.renovar_aviso') }}</span>
                <div :style="{ display: 'flex', flexWrap: 'wrap', gap: '8px 18px', alignItems: 'center' }">
                    <BotonSistema
                        variant="quiet"
                        size="sm"
                        :loading="renovando"
                        @click="emit('renovar')"
                    >{{ t('mi_cuenta.qr.renovar_si') }}</BotonSistema>
                    <EnlaceSistema @click="emit('preguntar', false)">{{ t('mi_cuenta.qr.renovar_no') }}</EnlaceSistema>
                </div>
            </div>
        </AvisoDestacado>
        <EnlaceSistema
            v-else-if="! qr.cargando"
            variant="quiet"
            size="sm"
            @click="emit('preguntar', true)"
        >{{ t('mi_cuenta.qr.renovar') }}</EnlaceSistema>
        <p
            v-if="qr.fallo"
            role="alert"
            :style="[CUENTA.pista, { color: 'var(--isla-alerta-texto)' }]"
        >{{ qr.fallo }}</p>
    </section>
</template>
