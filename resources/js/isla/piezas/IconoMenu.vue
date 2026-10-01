<script setup>
/**
 * Un icono del pie del menú (Z6a; `MenuIcon` del diseño): sin fondo —es una utilidad, no un control de la barra—, 44px
 * de toque y su nombre para el lector y al pasar el ratón. Sin acción no se toca: se lee (el idioma, mientras la web
 * no lo cambie desde aquí).
 */
import { computed, ref } from 'vue';
import IconoLucide from '../ui/IconoLucide.vue';

const props = defineProps({
    icon: { type: String, required: true },
    label: { type: String, required: true },
    href: { type: String, default: undefined },
    pulsar: { type: Function, default: null },
    external: { type: Boolean, default: false },
});

const hover = ref(false);
const ring = ref(false);
const activo = computed(() => Boolean(props.href || props.pulsar));
const etiqueta = computed(() => (props.href ? 'a' : activo.value ? 'button' : 'span'));
</script>

<template>
    <component
        :is="etiqueta"
        :href="href"
        :type="etiqueta === 'button' ? 'button' : undefined"
        :target="href && external ? '_blank' : undefined"
        :rel="href && external ? 'noopener' : undefined"
        :aria-label="label"
        :title="label"
        :role="activo ? undefined : 'img'"
        :style="{
            display: 'inline-flex', alignItems: 'center', justifyContent: 'center', flex: '0 0 auto', width: '44px', height: '44px', padding: 0,
            border: 'none', borderRadius: 'var(--r-pill)', textDecoration: 'none',
            background: activo && hover ? 'rgba(255,255,255,0.1)' : 'transparent',
            color: activo ? 'var(--isla-sobre)' : 'var(--text-muted)', cursor: activo ? 'pointer' : 'default',
            transition: 'var(--t-hover)', boxShadow: ring ? 'inset 0 0 0 2px var(--isla-foco)' : 'none',
        }"
        @click="pulsar && pulsar($event)"
        @mouseenter="hover = true"
        @mouseleave="hover = false"
        @focus="ring = $event.currentTarget.matches(':focus-visible')"
        @blur="ring = false"
    >
        <IconoLucide
            :name="icon"
            :size="20"
        />
    </component>
</template>
