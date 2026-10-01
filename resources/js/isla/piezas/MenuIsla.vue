<script setup>
/**
 * El menú de la isla (`IslandMenu` del diseño), desde la Z6a (zip (6)): el nombre del parque —la primera fila, con la
 * letra de la marca—, las páginas y, al pie, una fila de iconos: llamar y WhatsApp | idioma y cookies, separados por una
 * línea vertical. Mi QR y la cuenta ya no están aquí: viven en la barra. Las páginas llevan su nombre: ningún icono
 * dice «Kids» ni «Colegios».
 */
import { computed } from 'vue';
import FilaMenu from './FilaMenu.vue';
import IconoMenu from './IconoMenu.vue';
import { useTextos } from './textos.js';
import './iconos-menu.js';

const props = defineProps({
    items: { type: Array, default: () => [] },
    homeLabel: { type: String, default: null },
    contact: { type: Object, default: () => ({}) },
    lang: { type: String, default: '' },
    /** El idioma: su icono lo abre. Sin él, el icono solo se lee (`IconoMenu` sin acción). */
    onLanguage: { type: Function, default: null },
    help: { type: Object, default: null },
    cookies: { type: Object, default: null },
    // Con la segunda capa en la página (T4e), «Cookies» la abre SIEMPRE, también ya decidido: retirar el consentimiento
    // tiene que ser tan fácil como darlo. Sin ella, el `onConfigure` del aviso, como el diseño.
    preferencias: { type: Object, default: null },
});
const emit = defineEmits(['abrir', 'navegar']);
const { t, tp } = useTextos();
const configurar = computed(() => (props.preferencias ? () => emit('abrir', 'cookies') : (props.cookies ? props.cookies.onConfigure : null)));

const portada = computed(() => props.homeLabel || t('menu.portada'));
// WhatsApp: con la ayuda, el icono la abre (situación 12); sin ella, el número abre WhatsApp fuera.
const whatsapp = computed(() => (props.contact.whatsapp && /^\+?[\d\s]{6,}$/.test(props.contact.whatsapp)
    ? `https://wa.me/${props.contact.whatsapp.replace(/\D/g, '')}` : null));
const contacto = computed(() => Boolean(props.contact.phone || props.help || whatsapp.value));
const etiquetaWhatsapp = computed(() => t('menu.whatsapp')
    + (props.help ? ` · ${props.help.inHours ? t('menu.ayuda_en_horario') : t('menu.ayuda_fuera')}` : ''));
</script>

<template>
    <div>
        <FilaMenu
            brand
            :title="portada"
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
        <div :style="{ display: 'flex', alignItems: 'center', gap: '2px', padding: '2px 4px' }">
            <IconoMenu
                v-if="contact.phone"
                icon="phone"
                :label="tp('menu.llamar', { tel: contact.phone })"
                :href="`tel:${contact.phone.replace(/\s/g, '')}`"
            />
            <IconoMenu
                v-if="help || whatsapp"
                icon="message-circle"
                :label="etiquetaWhatsapp"
                :pulsar="help ? () => emit('abrir', 'help') : null"
                :href="help ? undefined : whatsapp"
                :external="! help"
            />
            <!-- La línea vertical separa el contacto de las preferencias. -->
            <i
                v-if="contacto"
                aria-hidden="true"
                :style="{ flex: '0 0 auto', width: '1px', height: '20px', margin: '0 8px', background: 'var(--ink-surface-border)' }"
            />
            <IconoMenu
                icon="languages"
                :label="tp('menu.idioma', { idioma: lang })"
                :pulsar="onLanguage"
            />
            <IconoMenu
                icon="cookie"
                :label="t('menu.cookies')"
                :pulsar="configurar"
            />
        </div>
    </div>
</template>
