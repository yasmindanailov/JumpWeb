<script setup>
/** El aviso (tamaño «aviso»): crece un momento para confirmar algo que la persona acaba de hacer, y vuelve sola. */
import IconoLucide from '../ui/IconoLucide.vue';

defineProps({
    texto: { type: String, required: true },
    top: { type: Boolean, default: false },
});
</script>

<template>
    <div :style="{ display: 'flex', alignItems: 'center', gap: '9px', padding: '10px 12px', margin: top ? '8px 0 0' : '0 0 8px', background: 'var(--isla-aviso-fondo)', border: '1px solid var(--isla-aviso-borde)', borderRadius: 'var(--r-sm)', fontFamily: 'var(--font-ui)', fontSize: '13px', fontWeight: 'var(--fw-semibold)', color: 'var(--isla-sobre)', animation: 'isla-swap var(--dur-base) var(--ease-island) both' }">
        <IconoLucide
            name="check"
            :size="16"
            color="var(--isla-vivo)"
        />{{ texto }}
    </div>
</template>
