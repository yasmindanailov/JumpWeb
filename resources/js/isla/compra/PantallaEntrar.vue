<script setup>
/**
 * «Entra», dentro de la compra (`PjcEntrar` del diseño): Google o Apple arriba, «— o —», y el correo (el owner, 30-09).
 * Al entrar vuelve a «Tus datos» con todo relleno.
 *
 * ▶ **Con un CÓDIGO al correo, sin contraseña** (A3 de `specs/acceso-con-codigo.md`, `#848`/`#849`; construido con las
 * piezas de la isla a la espera del diseño del owner, `#857`): `paso: 'id'` pide el correo; `paso: 'codigo'`, el que le
 * acaba de llegar, con «Pedir otro código». Un correo sin cuenta no llega aquí: va a «Tus datos» (quien lleva la compra).
 *
 * ⚠️ **Solo CORREO** (`#695`, `[DECIDIDO owner]`): el diseño decía «correo o teléfono» y el acceso del producto solo
 * admite correo; el texto es del `lang` y el campo, `type="email"`. Con el motor (T3e·4), `social`, `apple` y
 * `marcaGoogle` como en «Tus datos»; sin ellos, el diseño.
 */
import { useTextos } from '../piezas/textos.js';
import PasoCompra from './PasoCompra.vue';
import CampoSistema from '../ui/CampoSistema.vue';
import EnlaceSistema from '../ui/EnlaceSistema.vue';
import AccesoSocial from '../ui/AccesoSocial.vue';

defineProps({
    paso: { type: String, default: 'id' },
    valor: { type: String, default: '' },
    codigo: { type: String, default: '' },
    error: { type: String, default: '' },
    // Cuántos códigos se han pedido OTRA vez: con uno o más, la pista dice «otro».
    reenvios: { type: Number, default: 0 },
    enApp: { type: Boolean, default: null },
    social: { type: Boolean, default: true },
    apple: { type: Boolean, default: true },
    marcaGoogle: { type: String, default: '' },
});
const emit = defineEmits(['cambiar', 'otro', 'proveedor']);
const { t, tp } = useTextos();
</script>

<template>
    <PasoCompra :titulo="t('compra.entrar.titular')">
        <div
            v-if="paso === 'codigo'"
            :style="{ display: 'grid', gap: '4px' }"
        >
            <CampoSistema
                id="pjc-ent-codigo"
                :label="t('compra.datos.codigo')"
                inputmode="numeric"
                autocomplete="one-time-code"
                maxlength="7"
                :hint="tp(reenvios ? 'compra.datos.codigo_otro_enviado' : 'compra.datos.codigo_enviado', { correo: valor })"
                :model-value="codigo"
                :error="error"
                @update:model-value="emit('cambiar', 'codigo', $event)"
            />
            <EnlaceSistema
                :style="{ justifySelf: 'start' }"
                @click="emit('otro')"
            >{{ t('compra.datos.otro_codigo') }}</EnlaceSistema>
        </div>
        <template v-else>
            <!-- Google y Apple ARRIBA, y «— o —» antes del correo (el owner, 30-09, `#857`): el botón de Google no es el
                 que envía el correo de debajo; el suyo es «Continuar», en la acción. -->
            <AccesoSocial
                v-if="social"
                mode="signin"
                separador
                :in-app="enApp"
                :apple="apple"
                :marca="marcaGoogle"
                :labels="{ google: t('compra.entrar.google'), apple: t('compra.entrar.apple') }"
                @google="emit('proveedor', 'google')"
                @apple="emit('proveedor', 'apple')"
            />
            <CampoSistema
                id="pjc-ent"
                :label="t('compra.entrar.texto')"
                type="email"
                inputmode="email"
                autocomplete="username"
                :model-value="valor"
                :error="error"
                @update:model-value="emit('cambiar', 'valor', $event)"
            />
        </template>
    </PasoCompra>
</template>
