<script setup>
/**
 * **Reservar otra vez** (`paginas/mi-cuenta/bloques-2.jsx`, `PmcOtraVez`; T5f de §4.13): la última visita que se puede
 * repetir —qué y cuántos— y «Elegir día», que abre la compra ya situada en ese producto y esa gente. Lo que se ofrece lo
 * decide `reservas.js::otraVezDe`. ⚠️ Sin «Tu vale de 1 hora»: los vales son de JumpPoints, sin empezar (`#773`·c).
 *
 * Pinta y avisa (`elegir`).
 */
import { useTextos } from '../piezas/textos.js';
import { CUENTA } from './estilos.js';
import BotonSistema from '../ui/BotonSistema.vue';
import IconoLucide from '../ui/IconoLucide.vue';

defineProps({
    texto: { type: String, required: true },
});
const emit = defineEmits(['elegir']);
const { t } = useTextos();
</script>

<template>
    <section
        id="otra-vez"
        aria-labelledby="otra-vez-t"
        :style="CUENTA.bloque"
    >
        <h2
            id="otra-vez-t"
            :style="CUENTA.h2"
        >{{ t('mi_cuenta.otra_vez.titulo') }}</h2>
        <div :style="{ display: 'flex', flexWrap: 'wrap', alignItems: 'center', justifyContent: 'space-between', gap: '12px 16px', padding: '16px 18px', borderRadius: 'var(--r-lg)', border: '1px solid var(--border-subtle)', background: 'var(--surface-card)' }">
            <p :style="[CUENTA.cuerpo, { display: 'flex', alignItems: 'center', gap: '10px', color: 'var(--text-strong)', fontWeight: 'var(--fw-bold)', fontSize: 'var(--fs-body)' }]">
                <span
                    aria-hidden="true"
                    :style="{ color: 'var(--icon-accent)', display: 'inline-flex' }"
                ><IconoLucide
                    name="rotate-ccw"
                    :size="18"
                /></span>{{ texto }}
            </p>
            <BotonSistema
                variant="outline"
                @click="emit('elegir')"
            >
                {{ t('mi_cuenta.otra_vez.boton') }}<template #icono-derecha><IconoLucide
                    name="arrow-right"
                    :size="17"
                /></template>
            </BotonSistema>
        </div>
    </section>
</template>
