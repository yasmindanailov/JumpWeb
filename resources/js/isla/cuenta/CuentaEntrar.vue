<script setup>
/**
 * **Mi cuenta sin sesión: Entra** (`paginas/mi-cuenta/cuenta.jsx`, la vista `entrar`; T5a de §4.13). Es LA MISMA
 * pantalla que la compra (`compra/PantallaEntrar.vue`: solo correo, `#695`; su código al correo —A3 del acceso con
 * código, `#849`— y Google), y debajo del correo, «¿Es tu primera vez? Crea tu cuenta». Arriba, el aviso de una vuelta
 * de Google que no salió (T5e·2, `#779`) o el del correo que ya tenía cuenta, con su tono.
 */
import { useTextos } from '../piezas/textos.js';
import { CUENTA } from './estilos.js';
import PantallaEntrar from '../compra/PantallaEntrar.vue';
import AvisoCuenta from './AvisoCuenta.vue';
import EnlaceSistema from '../ui/EnlaceSistema.vue';

const props = defineProps({
    pantalla: { type: Object, required: true },
    aviso: { type: Object, default: null },
});
const emit = defineEmits(['cambiar', 'otro', 'proveedor', 'crear']);
const { t } = useTextos();
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
            @cambiar="(campo, valor) => emit('cambiar', campo, valor)"
            @otro="emit('otro')"
            @proveedor="(via) => emit('proveedor', via)"
        />
        <p
            v-if="props.pantalla.paso === 'id'"
            :style="[CUENTA.cuerpo, { maxWidth: '520px', width: '100%', margin: '0 auto' }]"
        >{{ `${t('mi_cuenta_alta.primera_vez')} ` }}<EnlaceSistema
            underline="always"
            @click="emit('crear')"
        >{{ t('mi_cuenta_alta.crear_enlace') }}</EnlaceSistema></p>
    </div>
</template>
