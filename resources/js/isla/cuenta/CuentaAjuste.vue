<script setup>
/**
 * **Los pasos de Ajustes** (`paginas/mi-cuenta/cuenta.jsx`, las vistas `correo` y `borrar`; T5e de §4.13, `#778`): lo que
 * necesita más de un campo o una confirmación se abre en la misma capa, con su flecha a Mi cuenta y su acción abajo (la
 * banda la decide `vista.js`). Los del mockup y los que la verdad añade con las piezas del sistema (`#773`·d): cerrar las
 * otras sesiones y desvincular Google (el mockup lo hacía con un toque) y firmar TU descargo (el cajón, en Privacidad).
 *
 * ▶ **Lo sensible se confirma con un CÓDIGO al correo** (A3b del acceso con código, `#857`; antes, la contraseña):
 * `CampoCodigoConfirmar`. El CORREO en tres tiempos, en la misma pantalla: el nuevo y el código que confirma que eres tú
 * (al de ahora); después, ya pendiente, el código que llegó al NUEVO. «Cambiar la contraseña» ya no existe aquí: en la isla
 * nadie entra con ella (`#848`).
 *
 * Pinta y avisa: lo que se valida y lo que se pide, en `useAjustesCuenta.js`.
 */
import './iconos-ajustes.js';
import { useTextos } from '../piezas/textos.js';
import { PASO } from '../compra/estilos.js';
import { VISTA } from './vista.js';
import PasoCompra from '../compra/PasoCompra.vue';
import AvisoCuenta from './AvisoCuenta.vue';
import CampoCodigoConfirmar from './CampoCodigoConfirmar.vue';
import CampoSistema from '../ui/CampoSistema.vue';
import CasillaSistema from '../ui/CasillaSistema.vue';
import BotonSistema from '../ui/BotonSistema.vue';
import EnlaceSistema from '../ui/EnlaceSistema.vue';
import AvisoDestacado from '../ui/AvisoDestacado.vue';
import IconoLucide from '../ui/IconoLucide.vue';

defineProps({
    vista: { type: String, required: true },
    paso: { type: Object, required: true },
});
// `completo`: la sexta cifra de un código, que hace la acción del paso (Z6g·1). Borrar no la avisa: va con su casilla y su
// botón, lo único irreversible (`#813`).
const emit = defineEmits(['cambiar', 'otro', 'completo', 'cancelar', 'descargo', 'borrar', 'volver']);
const { t, tp } = useTextos();
const cambiar = (campo, valor) => emit('cambiar', campo, valor);
</script>

<template>
    <PasoCompra :titulo="t(`mi_cuenta.${vista === VISTA.OTRAS ? 'otras_sesiones' : vista === VISTA.FIRMA ? 'descargo' : vista}.titulo`)">
        <AvisoCuenta
            v-if="paso.aviso"
            :texto="paso.aviso.texto"
            :tono="paso.aviso.tono"
        />
        <AvisoDestacado
            v-if="paso.fallo"
            tone="danger"
            size="sm"
            role="alert"
            :title="paso.fallo"
        >
            <template #icono><IconoLucide
                name="circle-alert"
                :size="18"
            /></template>
        </AvisoDestacado>

        <!-- El correo YA PEDIDO: el código que llegó al buzón NUEVO; hasta confirmarlo se sigue entrando con el de siempre. -->
        <template v-if="vista === VISTA.CORREO && paso.correo?.pendiente">
            <p :style="PASO.cuerpo">{{ paso.correo.pendiente.texto }} {{ paso.correo.pendiente.caduca }}</p>
            <CampoCodigoConfirmar
                id="mc-correo-codigo-nuevo"
                enviado
                :reenvios="paso.codigo.reenvios"
                :correo="paso.correo.pendiente.correo"
                :ocupado="paso.ocupado"
                :model-value="paso.f.codigo"
                :error="paso.errores.codigo"
                @update:model-value="(v) => cambiar('codigo', v)"
                @otro="emit('otro')"
                @completo="emit('completo')"
            />
            <EnlaceSistema
                variant="quiet"
                :style="{ justifySelf: 'start' }"
                :disabled="paso.ocupado"
                @click="emit('cancelar')"
            >{{ t('mi_cuenta.correo.cancelar') }}</EnlaceSistema>
        </template>

        <!-- El correo: el nuevo, y el código que confirma que eres tú (al de ahora). -->
        <template v-else-if="vista === VISTA.CORREO">
            <p :style="PASO.cuerpo">{{ t('mi_cuenta.correo.texto') }}</p>
            <CampoSistema
                id="mc-correo-nuevo"
                :label="t('mi_cuenta.correo.nuevo')"
                type="email"
                inputmode="email"
                autocomplete="email"
                :model-value="paso.f.correo"
                :error="paso.errores.correo"
                @update:model-value="(v) => cambiar('correo', v)"
            />
            <CampoCodigoConfirmar
                id="mc-correo-codigo"
                v-bind="paso.codigo"
                :ocupado="paso.ocupado"
                :model-value="paso.f.codigo"
                :error="paso.errores.codigo"
                @update:model-value="(v) => cambiar('codigo', v)"
                @otro="emit('otro')"
                @completo="emit('completo')"
            />
        </template>

        <template v-else-if="vista === VISTA.OTRAS">
            <p :style="PASO.cuerpo">{{ t('mi_cuenta.otras_sesiones.texto') }}</p>
            <CampoCodigoConfirmar
                id="mc-otras-codigo"
                v-bind="paso.codigo"
                :ocupado="paso.ocupado"
                :model-value="paso.f.codigo"
                :error="paso.errores.codigo"
                @update:model-value="(v) => cambiar('codigo', v)"
                @otro="emit('otro')"
                @completo="emit('completo')"
            />
        </template>

        <template v-else-if="vista === VISTA.DESVINCULAR">
            <p :style="PASO.cuerpo">{{ tp('mi_cuenta.desvincular.texto', { correo: paso.google?.correo ?? '' }) }}</p>
            <CampoCodigoConfirmar
                id="mc-desvincular-codigo"
                v-bind="paso.codigo"
                :ocupado="paso.ocupado"
                :model-value="paso.f.codigo"
                :error="paso.errores.codigo"
                @update:model-value="(v) => cambiar('codigo', v)"
                @otro="emit('otro')"
                @completo="emit('completo')"
            />
        </template>

        <!-- Tu descargo: el texto vigente, «Leer el descargo» y la casilla (el mismo gesto que la ficha de un hijo). -->
        <template v-else-if="vista === VISTA.FIRMA">
            <p :style="PASO.cuerpo">{{ t('compra.datos.pista_descargo') }}</p>
            <CasillaSistema
                v-if="paso.documento"
                id="mc-firma-casilla"
                :label="t('compra.datos.casilla')"
                :model-value="paso.f.casilla"
                :error="paso.errores.casilla"
                @update:model-value="(v) => cambiar('casilla', v)"
            >
                <EnlaceSistema
                    :style="{ justifySelf: 'start' }"
                    @click="emit('descargo')"
                >{{ t('compra.datos.leer') }}</EnlaceSistema>
            </CasillaSistema>
        </template>

        <!--
          Borrar tu cuenta: qué se pierde, la reserva que lo impide y la confirmación con su casilla. El botón vive AQUÍ,
          no en la isla: lo destructivo no va en el naranja de «seguir» (el diseño). ⚠️ Con una reserva por celebrar el
          servidor lo niega: se dice, y no se ofrece un botón que solo puede fallar.
        -->
        <template v-else-if="vista === VISTA.BORRAR">
            <AvisoDestacado
                tone="danger"
                size="sm"
                :title="t('mi_cuenta.borrar.texto')"
            >
                <template #icono><IconoLucide
                    name="triangle-alert"
                    :size="18"
                /></template>
            </AvisoDestacado>
            <AvisoDestacado
                v-if="paso.reserva"
                tone="warn"
                size="sm"
                :title="paso.reserva"
            >
                <template #icono><IconoLucide
                    name="calendar-x"
                    :size="18"
                /></template>
            </AvisoDestacado>
            <template v-else>
                <CasillaSistema
                    id="mc-borrar-casilla"
                    :label="t('mi_cuenta.borrar.casilla')"
                    :model-value="paso.f.entiendo"
                    @update:model-value="(v) => cambiar('entiendo', v)"
                />
                <CampoCodigoConfirmar
                    id="mc-borrar-codigo"
                    v-bind="paso.codigo"
                    :ocupado="paso.ocupado"
                    :model-value="paso.f.codigo"
                    :error="paso.errores.codigo"
                    @update:model-value="(v) => cambiar('codigo', v)"
                    @otro="emit('otro')"
                />
                <!-- Sin el código pedido, el botón lo pide; con él, borra. -->
                <BotonSistema
                    variant="secondary"
                    full
                    :disabled="! paso.f.entiendo"
                    :loading="paso.ocupado"
                    :loading-label="t(paso.codigo.enviado ? 'mi_cuenta.borrar.borrando' : 'compra.entrar.enviando')"
                    @click="emit('borrar')"
                >{{ t(paso.codigo.enviado ? 'mi_cuenta.borrar.boton' : 'mi_cuenta.codigo.enviar') }}</BotonSistema>
            </template>
            <EnlaceSistema
                :style="{ justifySelf: 'center' }"
                @click="emit('volver')"
            >{{ t('mi_cuenta.borrar.no') }}</EnlaceSistema>
        </template>
    </PasoCompra>
</template>
