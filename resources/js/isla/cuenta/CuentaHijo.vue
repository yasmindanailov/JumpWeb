<script setup>
/**
 * **La ficha de un hijo** (T5d de §4.13, `#777`): el mockup no la dibuja y se hace con las piezas del sistema (`#773`·d).
 * Lo que el cajón ya permitía y Mi cuenta no puede perder: ver su exención (firmada, con su PDF; de una versión anterior;
 * sin firmar; o pendiente de confirmar el correo), FIRMAR en su nombre —la acción de la capa, con su casilla y «Leer el
 * descargo»— y QUITARLO de la cuenta, tras preguntar en el sitio (como «Renovar mi QR»).
 *
 * Pinta y avisa: qué se ofrece lo decide `hijos.js` (con `sidebar/account/dependents.js`).
 */
import { useTextos } from '../piezas/textos.js';
import { PASO } from '../compra/estilos.js';
import PasoCompra from '../compra/PasoCompra.vue';
import AvisoCuenta from './AvisoCuenta.vue';
import CasillaSistema from '../ui/CasillaSistema.vue';
import EnlaceSistema from '../ui/EnlaceSistema.vue';
import BotonSistema from '../ui/BotonSistema.vue';
import AvisoDestacado from '../ui/AvisoDestacado.vue';
import IconoLucide from '../ui/IconoLucide.vue';

defineProps({
    ficha: { type: Object, required: true },
});
const emit = defineEmits(['casilla', 'descargo', 'preguntar', 'quitar']);
const { t, tp } = useTextos();
</script>

<template>
    <PasoCompra :titulo="ficha.completo">
        <AvisoCuenta
            v-if="ficha.aviso"
            :texto="ficha.aviso"
        />
        <p :style="PASO.cuerpo">{{ [ficha.datos, ficha.relacion].filter(Boolean).join(' · ') }}</p>
        <AvisoDestacado
            v-if="ficha.adulto"
            tone="info"
            size="sm"
            :title="ficha.adulto"
        >
            <template #icono><IconoLucide
                name="info"
                :size="18"
            /></template>
        </AvisoDestacado>
        <template v-if="ficha.firma">
            <p
                :style="[PASO.cuerpo, { display: 'flex', gap: '8px', alignItems: 'flex-start', color: ficha.firma.estado === 'firmada' ? 'var(--text-positive)' : 'var(--text-body)' }]"
            >
                <IconoLucide
                    :name="ficha.firma.estado === 'firmada' ? 'circle-check' : 'file-signature'"
                    :size="18"
                    aria-hidden="true"
                />
                <span>{{ ficha.firma.texto }}</span>
            </p>
            <EnlaceSistema
                v-if="ficha.firma.pdf"
                :href="ficha.firma.pdf"
                :style="{ justifySelf: 'start' }"
            >
                <template #icono><IconoLucide
                    name="download"
                    :size="16"
                /></template>{{ t('mi_cuenta.hijo.pdf') }}
            </EnlaceSistema>
        </template>
        <AvisoDestacado
            v-if="ficha.verificar"
            tone="warn"
            size="sm"
            :title="ficha.verificar"
        >
            <template #icono><IconoLucide
                name="mail-warning"
                :size="18"
            /></template>
        </AvisoDestacado>
        <CasillaSistema
            v-if="ficha.firmar"
            id="pmc-hijo-descargo"
            :label="t('mi_cuenta.hijos.casilla')"
            :model-value="ficha.casilla"
            :error="ficha.error"
            @update:model-value="emit('casilla', $event)"
        >
            <EnlaceSistema
                :style="{ justifySelf: 'start' }"
                @click="emit('descargo')"
            >{{ t('mi_cuenta.hijos.leer') }}</EnlaceSistema>
        </CasillaSistema>
        <AvisoDestacado
            v-if="ficha.preguntar"
            tone="warn"
            size="sm"
            role="alertdialog"
            :title="tp('mi_cuenta.hijo.pregunta', { nombre: ficha.nombre })"
        >
            <template #icono><IconoLucide
                name="triangle-alert"
                :size="18"
            /></template>
            <div :style="{ display: 'grid', gap: '10px' }">
                <span>{{ t('mi_cuenta.hijo.pregunta_texto') }}</span>
                <div :style="{ display: 'flex', flexWrap: 'wrap', gap: '8px 18px', alignItems: 'center' }">
                    <BotonSistema
                        variant="quiet"
                        size="sm"
                        :loading="ficha.quitando"
                        @click="emit('quitar')"
                    >{{ t('mi_cuenta.hijo.quitar_si') }}</BotonSistema>
                    <EnlaceSistema @click="emit('preguntar', false)">{{ t('mi_cuenta.hijo.quitar_no') }}</EnlaceSistema>
                </div>
            </div>
        </AvisoDestacado>
        <EnlaceSistema
            v-else
            variant="quiet"
            size="sm"
            :style="{ justifySelf: 'start' }"
            @click="emit('preguntar', true)"
        >{{ t('mi_cuenta.hijo.quitar') }}</EnlaceSistema>
    </PasoCompra>
</template>
