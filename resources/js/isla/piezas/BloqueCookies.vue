<script setup>
/**
 * El aviso de cookies dentro de la isla (situación 1): encima de la acción, sin taparla. «Aceptar» y
 * «Rechazar» van al mismo nivel, con el mismo tamaño y formato, y ninguno con la cara del botón que vende.
 * **Abajo (móvil), compacto** (zip del 27-09, `isla-y-landing-nueva.md` §4.15): el aviso entero medía ~170px y tapaba el
 * botón de la cabecera a todo el que llega por primera vez. Las mismas cuatro salidas: una frase corta con «Configurar» y
 * «Política» dentro, y Aceptar y Rechazar iguales y a un toque. Arriba (escritorio) sigue el aviso entero, que se abre
 * encima de la cabecera sin empujarla.
 * ⚠️ En la T2 solo se PINTA: conectarlo al consentimiento real es de la T3 de la analítica (carril del SPA).
 */
import BotonTranquilo from './BotonTranquilo.vue';
import EnlaceIsla from './EnlaceIsla.vue';
import EnlaceEnFrase from './EnlaceEnFrase.vue';
import { useTextos } from './textos.js';

defineProps({
    cookies: { type: Object, required: true },
    top: { type: Boolean, default: false },
});
const { t } = useTextos();
</script>

<template>
    <div
        v-if="top"
        :style="{ padding: '12px 10px', margin: '10px 0 0', borderTop: '1px solid rgba(255,255,255,0.14)' }"
    >
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
    <div
        v-else
        :style="{ padding: '8px 6px 10px', margin: '0 0 10px', borderBottom: '1px solid rgba(255,255,255,0.14)', display: 'grid', gap: '8px' }"
    >
        <p :style="{ margin: 0, fontFamily: 'var(--font-ui)', fontSize: 'var(--fs-caption)', lineHeight: 1.45, color: 'var(--text-body)' }">
            {{ cookies.shortText || t('cookies.texto_corto') }}{{ ' ' }}<EnlaceEnFrase
                :label="t('cookies.configurar')"
                :pulsar="cookies.onConfigure"
            />{{ ' · ' }}<EnlaceEnFrase
                :label="t('cookies.politica_corta')"
                :pulsar="cookies.onPolicy"
            />
        </p>
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
    </div>
</template>
