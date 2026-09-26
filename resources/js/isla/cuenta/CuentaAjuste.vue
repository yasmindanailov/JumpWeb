<script setup>
/**
 * **Los pasos de Ajustes** (`paginas/mi-cuenta/cuenta.jsx`, las vistas `clave`, `correo` y `borrar`; T5e de §4.13, `#778`):
 * lo que necesita más de un campo o una confirmación se abre en la misma capa, con su flecha a Mi cuenta y su acción
 * abajo (la banda la decide `vista.js`). Los tres del mockup y los tres que la verdad añade con las piezas del sistema
 * (`#773`·d): cerrar las otras sesiones y desvincular Google (piden la contraseña: el mockup lo hacía con un toque) y
 * firmar TU descargo (el cajón lo hacía en Privacidad).
 *
 * Pinta y avisa: lo que se valida y lo que se pide, en `useAjustesCuenta.js`.
 */
import './iconos-ajustes.js';
import { useTextos } from '../piezas/textos.js';
import { PASO } from '../compra/estilos.js';
import { VISTA } from './vista.js';
import PasoCompra from '../compra/PasoCompra.vue';
import AvisoCuenta from './AvisoCuenta.vue';
import CampoClaveActual from './CampoClaveActual.vue';
import CampoSistema from '../ui/CampoSistema.vue';
import CasillaSistema from '../ui/CasillaSistema.vue';
import BotonSistema from '../ui/BotonSistema.vue';
import EnlaceSistema from '../ui/EnlaceSistema.vue';
import AvisoDestacado from '../ui/AvisoDestacado.vue';
import CabeceraDesenlace from '../ui/CabeceraDesenlace.vue';
import IconoLucide from '../ui/IconoLucide.vue';

defineProps({
    vista: { type: String, required: true },
    paso: { type: Object, required: true },
});
const emit = defineEmits(['cambiar', 'enlace', 'reenviar', 'cancelar', 'descargo', 'borrar', 'volver']);
const { t, tp } = useTextos();
const cambiar = (campo, valor) => emit('cambiar', campo, valor);
</script>

<template>
    <!-- El correo ya pedido: el desenlace del diseño, con lo que hace falta para seguir (reenviar, o cancelar el cambio). -->
    <CabeceraDesenlace
        v-if="vista === VISTA.CORREO && paso.correo?.pendiente"
        kind="success"
        :celebrate="false"
        :title="t('mi_cuenta.correo.titulo')"
        :style="{ padding: '24px 0' }"
    >
        <div :style="{ display: 'grid', gap: '12px', justifyItems: 'center' }">
            <span>{{ paso.correo.pendiente.texto }} {{ paso.correo.pendiente.caduca }}</span>
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
            />
            <div :style="{ display: 'flex', flexWrap: 'wrap', gap: '8px 18px', justifyContent: 'center' }">
                <EnlaceSistema
                    :disabled="paso.ocupado"
                    @click="emit('reenviar')"
                >{{ t('mi_cuenta.correo.reenviar') }}</EnlaceSistema>
                <EnlaceSistema
                    variant="quiet"
                    :disabled="paso.ocupado"
                    @click="emit('cancelar')"
                >{{ t('mi_cuenta.correo.cancelar') }}</EnlaceSistema>
            </div>
        </div>
    </CabeceraDesenlace>

    <PasoCompra
        v-else
        :titulo="t(`mi_cuenta.${vista === VISTA.OTRAS ? 'otras_sesiones' : vista === VISTA.FIRMA ? 'descargo' : vista}.titulo`)"
    >
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

        <!-- Cambiar la contraseña: la actual y la nueva (el mockup; con «ver» dentro, sin repetirla). -->
        <div
            v-if="vista === VISTA.CLAVE"
            :style="{ display: 'grid', gap: '16px' }"
        >
            <CampoClaveActual
                id="mc-clave-actual"
                :model-value="paso.f.actual"
                :error="paso.errores.actual"
                @update:model-value="(v) => cambiar('actual', v)"
                @enlace="emit('enlace')"
            />
            <CampoSistema
                id="mc-clave-nueva"
                :label="t('mi_cuenta.clave.nueva')"
                type="password"
                autocomplete="new-password"
                :hint="t('compra.datos.pista_contrasena')"
                :model-value="paso.f.nueva"
                :error="paso.errores.nueva"
                @update:model-value="(v) => cambiar('nueva', v)"
            />
        </div>

        <!-- El correo: con un enlace al buzón nuevo; hasta abrirlo, se sigue entrando con el de siempre. -->
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
            <CampoClaveActual
                id="mc-correo-clave"
                :model-value="paso.f.clave"
                :error="paso.errores.clave"
                @update:model-value="(v) => cambiar('clave', v)"
                @enlace="emit('enlace')"
            />
        </template>

        <template v-else-if="vista === VISTA.OTRAS">
            <p :style="PASO.cuerpo">{{ t('mi_cuenta.otras_sesiones.texto') }}</p>
            <CampoClaveActual
                id="mc-otras-clave"
                :model-value="paso.f.clave"
                :error="paso.errores.clave"
                @update:model-value="(v) => cambiar('clave', v)"
                @enlace="emit('enlace')"
            />
        </template>

        <template v-else-if="vista === VISTA.DESVINCULAR">
            <p :style="PASO.cuerpo">{{ tp('mi_cuenta.desvincular.texto', { correo: paso.google?.correo ?? '' }) }}</p>
            <CampoClaveActual
                id="mc-desvincular-clave"
                :model-value="paso.f.clave"
                :error="paso.errores.clave"
                @update:model-value="(v) => cambiar('clave', v)"
                @enlace="emit('enlace')"
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
                <CampoClaveActual
                    id="mc-borrar-clave"
                    :model-value="paso.f.clave"
                    :error="paso.errores.clave"
                    @update:model-value="(v) => cambiar('clave', v)"
                    @enlace="emit('enlace')"
                />
                <CasillaSistema
                    id="mc-borrar-casilla"
                    :label="t('mi_cuenta.borrar.casilla')"
                    :model-value="paso.f.entiendo"
                    @update:model-value="(v) => cambiar('entiendo', v)"
                />
                <BotonSistema
                    variant="secondary"
                    full
                    :disabled="! paso.f.entiendo"
                    :loading="paso.ocupado"
                    :loading-label="t('mi_cuenta.borrar.borrando')"
                    @click="emit('borrar')"
                >{{ t('mi_cuenta.borrar.boton') }}</BotonSistema>
            </template>
            <EnlaceSistema
                :style="{ justifySelf: 'center' }"
                @click="emit('volver')"
            >{{ t('mi_cuenta.borrar.no') }}</EnlaceSistema>
        </template>
    </PasoCompra>
</template>
