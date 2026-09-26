<script setup>
/**
 * **Mi cuenta sin sesión: Entra** (`paginas/mi-cuenta/cuenta.jsx`, la vista `entrar`; T5a de §4.13). Es LA MISMA
 * pantalla que la compra (`compra/PantallaEntrar.vue`: solo correo, `#695`; su contraseña, su olvido y Google), y
 * debajo, «¿Es tu primera vez? Crea tu cuenta». En el olvido ya enviado, solo su confirmación.
 */
import { useTextos } from '../piezas/textos.js';
import { CUENTA } from './estilos.js';
import PantallaEntrar from '../compra/PantallaEntrar.vue';
import EnlaceSistema from '../ui/EnlaceSistema.vue';

const props = defineProps({
    pantalla: { type: Object, required: true },
});
const emit = defineEmits(['cambiar', 'olvido', 'proveedor', 'crear']);
const { t } = useTextos();
</script>

<template>
    <div :style="{ display: 'grid', gap: '24px' }">
        <PantallaEntrar
            v-bind="props.pantalla"
            @cambiar="(campo, valor) => emit('cambiar', campo, valor)"
            @olvido="emit('olvido')"
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
