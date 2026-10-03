<script setup>
/**
 * «Entra», en la compra y en Mi cuenta sin sesión (`usePjEntrar` del diseño, `paginas/compra/entrar.jsx`; la Z6g·1 de
 * `specs/isla-y-landing-nueva.md` §4.27). Al entrar, la compra vuelve a «Tus datos» con todo relleno.
 *
 * ▶ **Con un CÓDIGO al correo, sin contraseña** (A3 de `specs/acceso-con-codigo.md`, `#848`/`#849`): `paso: 'id'` pide el
 * correo; `paso: 'codigo'`, el que le acaba de llegar, en sus seis casillas (`CampoCodigo`): a quién se envió con «Cambiar»
 * —vuelve al correo, como la flecha—, quién lo manda y «Pedir otro código». Con la sexta cifra avisa (`completo`): quien
 * la pinta hace lo que haría «Entrar». Un correo sin cuenta no llega al código: va a darse de alta (`#849`).
 *
 * ⚠️ **Solo CORREO** (`#695`, `[DECIDIDO owner]`), con su texto del `lang` y `type="email"`. Google y Apple ARRIBA, con
 * «— o —» antes del correo (`#857`, el owner), aunque el zip (6) los dibuje debajo. La casilla de recordar, `#858` (el
 * owner), y no el «un año» del diseño. Con el motor (T3e·4), `social`, `apple` y `marcaGoogle` como en «Tus datos».
 * ▶ **La puerta de la compra** (M3 de `#880`): sin sesión, «Tus datos» empieza aquí, como Mi cuenta (`cuenta`): «Entra o
 * crea tu cuenta» y el texto del owner («…Si no tienes cuenta, la creas en 1 minuto…»). `aviso`, el «no» que traiga la
 * compra (una vuelta de Google que no salió), arriba, como en «Tus datos».
 */
import { useTextos } from '../piezas/textos.js';
import { PASO } from './estilos.js';
import PasoCompra from './PasoCompra.vue';
import CampoSistema from '../ui/CampoSistema.vue';
import CampoCodigo from '../ui/CampoCodigo.vue';
import CasillaSistema from '../ui/CasillaSistema.vue';
import EnlaceSistema from '../ui/EnlaceSistema.vue';
import AccesoSocial from '../ui/AccesoSocial.vue';
import TextoConCorreo from '../ui/TextoConCorreo.vue';
import AvisoDestacado from '../ui/AvisoDestacado.vue';
import IconoLucide from '../ui/IconoLucide.vue';

defineProps({
    paso: { type: String, default: 'id' },
    valor: { type: String, default: '' },
    codigo: { type: String, default: '' },
    // «Mantener la sesión iniciada en este dispositivo» (`#858`, `[DECIDIDO owner]`): SIN marcar de serie.
    recordar: { type: Boolean, default: false },
    error: { type: String, default: '' },
    // Cuántos códigos se han pedido OTRA vez: con uno o más, el texto dice «otro» y que el anterior ya no vale.
    reenvios: { type: Number, default: 0 },
    // Comprobando el código o pidiendo otro: las casillas y «Pedir otro código» esperan.
    ocupado: { type: Boolean, default: false },
    // Mi cuenta: «Entra o crea tu cuenta» (el mismo camino entra o da de alta: el correo decide).
    cuenta: { type: Boolean, default: false },
    enApp: { type: Boolean, default: null },
    social: { type: Boolean, default: true },
    apple: { type: Boolean, default: true },
    marcaGoogle: { type: String, default: '' },
    aviso: { type: String, default: '' },
});
const emit = defineEmits(['cambiar', 'otro', 'proveedor', 'completo', 'correo']);
const { t } = useTextos();
</script>

<template>
    <PasoCompra :titulo="t(paso === 'codigo' ? 'compra.entrar.codigo_titular' : cuenta ? 'compra.entrar.titular_cuenta' : 'compra.entrar.titular')">
        <AvisoDestacado
            v-if="aviso"
            tone="danger"
            size="sm"
            role="alert"
            :title="aviso"
        >
            <template #icono><IconoLucide
                name="circle-alert"
                :size="18"
            /></template>
        </AvisoDestacado>
        <template v-if="paso === 'codigo'">
            <p :style="PASO.cuerpo"><TextoConCorreo
                :texto="t(reenvios ? 'compra.entrar.codigo_otro' : 'compra.entrar.codigo_texto')"
                :correo="valor"
            />{{ ' ' }}<EnlaceSistema
                underline="always"
                @click="emit('correo')"
            >{{ t('resumen.cambiar') }}</EnlaceSistema></p>
            <CampoCodigo
                id="pjc-ent-codigo"
                :label="t('compra.datos.codigo')"
                :hint="t('compra.entrar.codigo_pista')"
                :model-value="codigo"
                :error="error"
                :disabled="ocupado"
                :otro="t('compra.datos.otro_codigo')"
                :ocupado="ocupado"
                @update:model-value="emit('cambiar', 'codigo', $event)"
                @completo="emit('completo')"
                @otro="emit('otro')"
            />
            <!-- Recordar el dispositivo solo si se pide (`#858`): la cookie persistente no está exenta de consentimiento. -->
            <CasillaSistema
                id="pjc-ent-recordar"
                :label="t('compra.datos.recordar')"
                :model-value="recordar"
                @update:model-value="emit('cambiar', 'recordar', $event)"
            />
        </template>
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
            <p :style="PASO.cuerpo">{{ t(cuenta ? 'compra.entrar.texto_cuenta' : 'compra.entrar.texto') }}</p>
            <CampoSistema
                id="pjc-ent"
                :label="t('compra.entrar.correo')"
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
