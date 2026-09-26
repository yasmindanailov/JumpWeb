<script setup>
/**
 * **Tu QR** (`paginas/mi-cuenta/bloques.jsx`, `PmcQr` en su vista; T5a de §4.13): la única vista de QR de la web —Mi QR
 * del menú, «Enseñar mi QR» y «Ir a Mi QR» de Listo llevan aquí—. El QR grande, siempre sobre blanco (lo DIBUJA el
 * servidor, `identidad-qr-puerta.md` §0: aquí solo se enseña su PNG), «Guardar en el móvil» (esa misma imagen), el
 * código para dictar si la cámara falla y «Renovar mi QR», que pregunta en el sitio. Llegando de fuera, «Ir a mi
 * cuenta». [Apple Wallet] y [Google Wallet], APAGADOS en la v2.0.0 (`#773`·c): corchetes sin hueco.
 *
 * ⚠️ Un carné que el servidor no puede dibujar (su clave rotó) no pinta un hueco: dice por qué y ofrece renovarlo.
 */
import { useTextos } from '../piezas/textos.js';
import { CUENTA } from './estilos.js';
import AvisoCuenta from './AvisoCuenta.vue';
import IconoLucide from '../ui/IconoLucide.vue';
import BotonSistema from '../ui/BotonSistema.vue';
import EnlaceSistema from '../ui/EnlaceSistema.vue';
import AvisoDestacado from '../ui/AvisoDestacado.vue';
import PaseQr from '../ui/PaseQr.vue';
import EsqueletoCarga from '../ui/EsqueletoCarga.vue';

defineProps({
    qr: { type: Object, required: true },
    renovar: { type: Boolean, default: false },
    renovando: { type: Boolean, default: false },
    irCuenta: { type: Boolean, default: false },
    aviso: { type: String, default: '' },
    sinQr: { type: String, default: '' },
});
const emit = defineEmits(['guardar', 'preguntar', 'renovar', 'cuenta']);
const { t, tp } = useTextos();
</script>

<template>
    <div :style="{ display: 'grid', gap: '16px', maxWidth: '520px', margin: '0 auto' }">
        <AvisoCuenta
            v-if="aviso"
            :texto="aviso"
        />
        <section
            id="mi-qr"
            aria-labelledby="mi-qr-t"
            :style="{ display: 'grid', gap: '16px', justifyItems: 'center', textAlign: 'center', scrollMarginTop: '16px', padding: '8px 0 0' }"
        >
            <h2
                id="mi-qr-t"
                :style="CUENTA.oculto"
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
        <EnlaceSistema
            v-if="irCuenta"
            :style="{ justifySelf: 'center' }"
            @click="emit('cuenta')"
        >
            <template #icono><IconoLucide
                name="user-round"
                :size="17"
            /></template>{{ t('cuenta.ir') }}
        </EnlaceSistema>
    </div>
</template>
