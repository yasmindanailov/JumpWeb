<script setup>
/**
 * El menú de la isla (`IslandMenu` del diseño): la única superficie secundaria. Aquí viven la cuenta, Mi QR,
 * las páginas, el teléfono, WhatsApp, el idioma y las cookies; la barra se queda con dos cosas.
 */
import { computed } from 'vue';
import FilaMenu from './FilaMenu.vue';
import EnlaceIsla from './EnlaceIsla.vue';
import { useTextos } from './textos.js';

const props = defineProps({
    items: { type: Array, default: () => [] },
    homeLabel: { type: String, default: null },
    contact: { type: Object, default: () => ({}) },
    lang: { type: String, default: '' },
    account: { type: Object, default: () => ({ state: 'guest' }) },
    bookingToday: { type: Object, default: null },
    help: { type: Object, default: null },
    cookies: { type: Object, default: null },
});
const emit = defineEmits(['abrir', 'capa', 'navegar']);
const { t } = useTextos();

const sesion = computed(() => props.account.state === 'session');
const portada = computed(() => props.homeLabel || t('menu.portada'));
const notaQr = computed(() => (props.bookingToday
    ? props.bookingToday.text + (props.bookingToday.extra ? ` · ${props.bookingToday.extra}` : '')
    : t('menu.qr_nota')));
</script>

<template>
    <div>
        <FilaMenu
            v-if="sesion"
            icon="qr-code"
            :title="t('menu.qr')"
            :note="notaQr"
            :tone="bookingToday ? 'live' : undefined"
            chevron
            @click="account.onQr ? emit('capa', account.onQr) : emit('abrir', 'qr')"
        />
        <FilaMenu
            :icon="sesion ? 'user-round' : 'log-in'"
            :title="sesion ? t('menu.cuenta') : t('menu.entrar')"
            :note="sesion ? (account.pendingText || t('menu.cuenta_nota')) : t('menu.entrar_nota')"
            :tone="sesion && account.pendingText ? 'alert' : undefined"
            chevron
            @click="account.onClick ? emit('capa', account.onClick) : emit('abrir', 'cuenta')"
        />
        <div :style="{ height: '1px', background: 'rgba(255,255,255,0.12)', margin: '8px 12px' }" />
        <FilaMenu
            :title="portada"
            icon="house"
            href="/"
            @click="emit('navegar', { label: portada, href: '/' }, $event)"
        />
        <FilaMenu
            v-for="it in items"
            :key="it.href || it.label"
            :title="it.label"
            :href="it.href"
            :active="it.active"
            @click="emit('navegar', it, $event)"
        />
        <div :style="{ height: '1px', background: 'rgba(255,255,255,0.12)', margin: '8px 12px' }" />
        <FilaMenu
            v-if="contact.phone"
            icon="phone"
            :title="contact.phone"
            :href="`tel:${contact.phone.replace(/\s/g, '')}`"
        />
        <FilaMenu
            v-if="help || contact.whatsapp"
            icon="message-circle"
            :title="contact.whatsapp || t('menu.whatsapp')"
            :note="help ? (help.inHours ? t('menu.ayuda_en_horario') : t('menu.ayuda_fuera')) : null"
            :chevron="Boolean(help)"
            @click="help && emit('abrir', 'help')"
        />
        <div :style="{ display: 'flex', gap: '6px', padding: '4px 6px 2px' }">
            <EnlaceIsla :label="lang" />
            <span :style="{ color: 'rgba(255,255,255,0.3)' }">·</span>
            <EnlaceIsla
                :label="t('menu.cookies')"
                :pulsar="cookies ? cookies.onConfigure : null"
            />
        </div>
    </div>
</template>
