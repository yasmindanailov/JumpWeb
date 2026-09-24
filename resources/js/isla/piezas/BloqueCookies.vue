<script setup>
/**
 * El aviso de cookies dentro de la isla (situación 1): encima de la acción, sin taparla. «Aceptar» y
 * «Rechazar» van al mismo nivel, con el mismo tamaño y formato, y ninguno con la cara del botón que vende.
 * ⚠️ En la T2 solo se PINTA: conectarlo al consentimiento real es de la T3 de la analítica (carril del SPA).
 */
import BotonTranquilo from './BotonTranquilo.vue';
import EnlaceIsla from './EnlaceIsla.vue';
import { useTextos } from './textos.js';

defineProps({
    cookies: { type: Object, required: true },
    top: { type: Boolean, default: false },
});
const { t } = useTextos();
</script>

<template>
    <div :style="{ padding: '12px 10px', margin: top ? '10px 0 0' : '0 0 10px', borderTop: top ? '1px solid rgba(255,255,255,0.14)' : 'none', borderBottom: top ? 'none' : '1px solid rgba(255,255,255,0.14)' }">
        <p :style="{ margin: '0 0 10px', fontFamily: 'var(--font-ui)', fontSize: 'var(--fs-caption)', lineHeight: 1.45, color: 'var(--text-body)' }">{{ cookies.text || t('cookies.texto') }}</p>
        <div :style="{ display: 'flex', gap: '8px' }">
            <BotonTranquilo
                :label="t('cookies.aceptar')"
                :pulsar="cookies.onAccept"
            />
            <BotonTranquilo
                :label="t('cookies.rechazar')"
                :pulsar="cookies.onReject"
            />
        </div>
        <div :style="{ display: 'flex', justifyContent: 'center', gap: '16px', marginTop: '8px' }">
            <EnlaceIsla
                :label="t('cookies.configurar')"
                :pulsar="cookies.onConfigure"
            />
            <EnlaceIsla
                :label="t('cookies.politica')"
                :pulsar="cookies.onPolicy"
            />
        </div>
    </div>
</template>
