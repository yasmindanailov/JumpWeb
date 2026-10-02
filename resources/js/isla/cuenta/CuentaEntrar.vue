<script setup>
/**
 * **Mi cuenta sin sesión: «Entra o crea tu cuenta»** (`paginas/mi-cuenta/cuenta.jsx`, la vista `entrar`; T5a de §4.13 y la
 * Z6g·1 de §4.27). Es LA MISMA pantalla que la compra (`compra/PantallaEntrar.vue`: solo correo, `#695`; su código al
 * correo —A3 del acceso con código, `#849`— y Google). Sin «¿Es tu primera vez?» desde el zip (6): el mismo camino sirve
 * para las dos cosas —un correo sin cuenta sigue a «Crea tu cuenta»—. Arriba, el aviso de una vuelta de Google que no
 * salió (T5e·2, `#779`) o el del correo que ya tenía cuenta, con su tono.
 */
import AvisoCuenta from './AvisoCuenta.vue';
import PantallaEntrar from '../compra/PantallaEntrar.vue';

const props = defineProps({
    pantalla: { type: Object, required: true },
    aviso: { type: Object, default: null },
});
const emit = defineEmits(['cambiar', 'otro', 'proveedor', 'completo', 'correo']);
</script>

<template>
    <div :style="{ display: 'grid', gap: '24px' }">
        <AvisoCuenta
            v-if="props.aviso"
            :texto="props.aviso.texto"
            :tono="props.aviso.tono"
            :style="{ maxWidth: '520px', width: '100%', margin: '0 auto' }"
        />
        <PantallaEntrar
            v-bind="props.pantalla"
            cuenta
            @cambiar="(campo, valor) => emit('cambiar', campo, valor)"
            @otro="emit('otro')"
            @proveedor="(via) => emit('proveedor', via)"
            @completo="emit('completo')"
            @correo="emit('correo')"
        />
    </div>
</template>
