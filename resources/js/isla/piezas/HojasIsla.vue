<script setup>
/**
 * Las hojas pequeñas de la isla (`SummarySheet`, `AccountSheet` y `HelpSheet` del diseño): el resumen del
 * cálculo, la cuenta sin sesión y la ayuda por WhatsApp. Mi QR (`QrSheet`) llega con Mi cuenta (T5), que es
 * cuando hay un carné que enseñar.
 */
import BotonTranquilo from './BotonTranquilo.vue';
import IconoLucide from '../ui/IconoLucide.vue';
import { useTextos } from './textos.js';

defineProps({
    vista: { type: String, required: true },
    quote: { type: Object, default: null },
    account: { type: Object, default: () => ({ state: 'guest' }) },
    help: { type: Object, default: null },
});
const { t, tp } = useTextos();
</script>

<template>
    <div
        v-if="vista === 'resumen' && quote"
        :style="{ display: 'grid', gap: '8px', padding: '0 6px 4px' }"
    >
        <div
            v-for="r in quote.detail || []"
            :key="r.label"
            :style="{ display: 'flex', justifyContent: 'space-between', gap: '12px', fontFamily: 'var(--font-ui)', fontSize: '13.5px', color: 'rgba(255,255,255,0.8)' }"
        >
            <span>{{ r.label }}</span>
            <strong :style="{ color: 'var(--isla-sobre)', fontFamily: 'var(--font-mono)', fontWeight: 500 }">{{ r.value }}</strong>
        </div>
        <BotonTranquilo
            v-if="quote.onChange"
            :label="t('resumen.cambiar')"
            :pulsar="quote.onChange"
        />
    </div>
    <div
        v-else-if="vista === 'cuenta'"
        :style="{ padding: '0 6px 4px' }"
    >
        <p :style="{ margin: '0 0 10px', fontFamily: 'var(--font-ui)', fontSize: '13.5px', lineHeight: 1.45, color: 'rgba(255,255,255,0.8)' }">{{ account.pendingText || t('cuenta.texto') }}</p>
        <BotonTranquilo
            :label="account.state === 'session' ? t('cuenta.ir') : t('cuenta.entrar')"
            :pulsar="() => account.onClick && account.onClick({ from: 'menu' })"
        />
    </div>
    <div
        v-else-if="vista === 'help' && help"
        :style="{ padding: '0 6px 2px' }"
    >
        <p :style="{ margin: '0 0 10px', fontFamily: 'var(--font-ui)', fontSize: '13.5px', color: 'rgba(255,255,255,0.8)', lineHeight: 1.45 }">{{ help.inHours ? t('ayuda.en_horario') : tp('ayuda.fuera', { hora: help.opensAt || '' }) }}</p>
        <button
            type="button"
            :style="{ display: 'flex', alignItems: 'center', justifyContent: 'center', gap: '9px', width: '100%', height: '46px', borderRadius: 'var(--r-pill)', border: 'none', background: 'var(--isla-vivo)', color: 'var(--isla-tinta)', fontFamily: 'var(--font-ui)', fontWeight: 'var(--fw-bold)', fontSize: '15px', cursor: 'pointer' }"
            @click="help.onWhatsApp && help.onWhatsApp($event)"
        >
            <IconoLucide
                name="message-circle"
                :size="18"
            />{{ t('ayuda.whatsapp') }}
        </button>
    </div>
</template>
