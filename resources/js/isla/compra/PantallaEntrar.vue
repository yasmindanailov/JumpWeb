<script setup>
/**
 * «Entra», dentro de la compra (`PjcEntrar` del diseño): el correo y la contraseña, o Google o Apple. Al entrar
 * vuelve a «Tus datos» con todo relleno. `paso: 'olvido'` es la confirmación de «¿Has olvidado tu contraseña?», que
 * por la regla de siempre no dice si el correo existe (`SEC-06`).
 *
 * ⚠️ **Solo CORREO** (`#695`, `[DECIDIDO owner]`): el diseño decía «correo o teléfono» y el acceso del producto solo
 * admite correo; el texto es del `lang` y el campo, `type="email"`. Con el motor (T3e·4), `social`, `apple` y
 * `marcaGoogle` como en «Tus datos»; sin ellos, el diseño.
 */
import { useTextos } from '../piezas/textos.js';
import { PASO } from './estilos.js';
import PasoCompra from './PasoCompra.vue';
import CampoSistema from '../ui/CampoSistema.vue';
import EnlaceSistema from '../ui/EnlaceSistema.vue';
import AccesoSocial from '../ui/AccesoSocial.vue';

defineProps({
    paso: { type: String, default: 'id' },
    valor: { type: String, default: '' },
    clave: { type: String, default: '' },
    error: { type: String, default: '' },
    enApp: { type: Boolean, default: null },
    social: { type: Boolean, default: true },
    apple: { type: Boolean, default: true },
    marcaGoogle: { type: String, default: '' },
});
const emit = defineEmits(['cambiar', 'olvido', 'proveedor']);
const { t } = useTextos();
</script>

<template>
    <PasoCompra
        v-if="paso === 'olvido'"
        :titulo="t('compra.datos.olvido')"
    >
        <p
            role="status"
            :style="PASO.cuerpo"
        >{{ t('compra.entrar.olvido_texto') }}</p>
    </PasoCompra>
    <PasoCompra
        v-else
        :titulo="t('compra.entrar.titular')"
    >
        <div :style="{ display: 'grid', gap: '16px' }">
            <CampoSistema
                id="pjc-ent"
                :label="t('compra.entrar.texto')"
                type="email"
                inputmode="email"
                autocomplete="username"
                :model-value="valor"
                @update:model-value="emit('cambiar', 'valor', $event)"
            />
            <div :style="{ display: 'grid', gap: '4px' }">
                <CampoSistema
                    id="pjc-ent-clave"
                    :label="t('compra.datos.contrasena')"
                    type="password"
                    autocomplete="current-password"
                    :model-value="clave"
                    :error="error"
                    @update:model-value="emit('cambiar', 'clave', $event)"
                />
                <EnlaceSistema
                    :style="{ justifySelf: 'start' }"
                    @click="emit('olvido')"
                >{{ t('compra.datos.olvido') }}</EnlaceSistema>
            </div>
        </div>
        <AccesoSocial
            v-if="social"
            mode="signin"
            :in-app="enApp"
            :apple="apple"
            :marca="marcaGoogle"
            :labels="{ google: t('compra.entrar.google'), apple: t('compra.entrar.apple') }"
            @google="emit('proveedor', 'google')"
            @apple="emit('proveedor', 'apple')"
        />
    </PasoCompra>
</template>
