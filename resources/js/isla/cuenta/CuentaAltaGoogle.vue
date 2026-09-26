<script setup>
/**
 * **Completar el alta que vuelve de Google, en la isla** (T5a de §4.13; `specs/auth-con-google.md` §7). El mockup no
 * la dibuja y el owner pidió dibujarla CON EL SISTEMA (`#773`·d): un paso de la capa con sus piezas (campo, casilla,
 * enlace, aviso) y los textos de siempre de esta pantalla (`account.google.*` y los del alta, `account.register.*`).
 * La secuencia es la del cajón (`account/google.js`); aquí solo se pinta.
 *
 *   · **El correo se ENSEÑA, no se pide**: es la identidad que Google acaba de verificar.
 *   · **El nombre es lo único que se teclea**: Google a veces trae «Ana G.», y ese nombre va a la reserva y a la firma.
 *   · **La privacidad no es casilla**: se informa, con su enlace; el descargo, sí, si hay texto que firmar.
 *   · **Sin nada que completar** (caducó, ya se hizo), la única salida es empezar otra vez: un enlace a Google.
 */
import { PASO } from '../compra/estilos.js';
import PasoCompra from '../compra/PasoCompra.vue';
import CampoSistema from '../ui/CampoSistema.vue';
import CasillaSistema from '../ui/CasillaSistema.vue';
import EnlaceSistema from '../ui/EnlaceSistema.vue';
import AvisoDestacado from '../ui/AvisoDestacado.vue';
import BotonSistema from '../ui/BotonSistema.vue';
import IconoLucide from '../ui/IconoLucide.vue';

defineProps({
    pantalla: { type: Object, required: true },
    rotulos: { type: Object, required: true },
    firma: { type: Boolean, default: false },
    urls: { type: Object, default: () => ({}) },
});
const emit = defineEmits(['cambiar', 'descargo']);
</script>

<template>
    <PasoCompra
        v-if="pantalla.expired"
        :titulo="rotulos.titulo"
    >
        <p
            role="status"
            :style="PASO.cuerpo"
        >{{ rotulos.caducada }}</p>
        <BotonSistema
            v-if="urls.google"
            variant="quiet"
            :href="urls.google"
        >{{ rotulos.empezar }}</BotonSistema>
    </PasoCompra>
    <PasoCompra
        v-else-if="pantalla.pending"
        :titulo="rotulos.titulo"
    >
        <p :style="PASO.cuerpo">{{ rotulos.intro }}</p>
        <AvisoDestacado
            v-if="pantalla.errors.summary.length"
            tone="danger"
            size="sm"
            role="alert"
            :title="rotulos.revisa"
        >
            <template #icono><IconoLucide
                name="circle-alert"
                :size="18"
            /></template>
            <p
                v-for="(mensaje, i) in pantalla.errors.summary"
                :key="i"
                :style="{ margin: 0 }"
            >{{ mensaje }}</p>
        </AvisoDestacado>
        <div :style="{ display: 'grid', gap: '4px' }">
            <span :style="PASO.pregunta">{{ rotulos.correo }}</span>
            <p :style="[PASO.cuerpo, { color: 'var(--text-strong)', overflowWrap: 'anywhere' }]">{{ pantalla.pending.email }}</p>
            <p :style="PASO.pista">{{ rotulos.correoPista }}</p>
        </div>
        <CampoSistema
            id="mc-google-nombre"
            :label="rotulos.nombre"
            autocomplete="name"
            :model-value="pantalla.nombre"
            :error="pantalla.errors.fields.name || ''"
            @update:model-value="emit('cambiar', 'nombre', $event)"
        />
        <div :style="{ display: 'grid', gap: '4px' }">
            <p :style="PASO.pista">{{ rotulos.privacidad }}</p>
            <EnlaceSistema
                v-if="urls.privacy"
                :href="urls.privacy"
                target="_blank"
                rel="noopener"
                :style="{ justifySelf: 'start' }"
            >{{ rotulos.leerPrivacidad }}</EnlaceSistema>
        </div>
        <CasillaSistema
            v-if="firma"
            id="mc-google-descargo"
            :label="rotulos.casilla"
            :model-value="pantalla.descargo"
            :error="pantalla.errors.fields.accept_waiver || pantalla.errors.fields.waiver_document_id || ''"
            @update:model-value="emit('cambiar', 'descargo', $event)"
        >
            <EnlaceSistema
                :style="{ justifySelf: 'start' }"
                @click="emit('descargo')"
            >{{ rotulos.leerDescargo }}</EnlaceSistema>
        </CasillaSistema>
    </PasoCompra>
</template>
