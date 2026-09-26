<script setup>
/**
 * **MI CUENTA EN LA ISLA** (T5 de `specs/isla-y-landing-nueva.md` §4.13, `DECISIONES #773`): con la isla como carcasa
 * de la instalación, ocupa el sitio de la sección de cuenta del cajón (`Sidebar.vue`) y se enseña en la CAPA GRANDE
 * de la isla —la de la compra: su contenedor, su flecha, su X, su acción—, teletransportada a `<body>`, cuando el
 * controlador abre la cuenta. Pinta (`CE-6`): lo que decide está en `cuenta/vista.js` y lo que hace, en
 * `cuenta/useSeccionCuenta.js`.
 *
 * Viaja en su propio trozo y se trae a la primera entrada: quien solo compra no lo descarga.
 * ⚠️ `bloquea-pagina` apagado: la página la deja quieta el controlador, dueño único del cerrojo de scroll.
 */
import { defineAsyncComponent } from 'vue';
import './isla.css';
import './cuenta/iconos.js';
import IslaFlotante from './IslaFlotante.vue';
import { PROPS_MOTOR } from '../sidebar/props.js';
import { VISTA } from './cuenta/vista.js';
import { useSeccionCuenta } from './cuenta/useSeccionCuenta.js';
import CuentaInicio from './cuenta/CuentaInicio.vue';
import CuentaQr from './cuenta/CuentaQr.vue';
import CuentaEntrar from './cuenta/CuentaEntrar.vue';
import CuentaAltaGoogle from './cuenta/CuentaAltaGoogle.vue';
import CuentaCambiar from './cuenta/CuentaCambiar.vue';
import BloqueReserva from './cuenta/BloqueReserva.vue';
import BloqueAntes from './cuenta/BloqueAntes.vue';
import CabeceraDesenlace from './ui/CabeceraDesenlace.vue';
import PantallaDatos from './compra/PantallaDatos.vue';
import PantallaDescargo from './compra/PantallaDescargo.vue';
import CajaAntiBot from './compra/CajaAntiBot.vue';

// Añade a tus hijos y la ficha de un hijo (T5d), en su trozo: se abren a demanda, y Mi cuenta pinta sin esperarlas.
const CuentaHijos = defineAsyncComponent(() => import('./cuenta/CuentaHijos.vue'));
const CuentaHijo = defineAsyncComponent(() => import('./cuenta/CuentaHijo.vue'));
// Los pasos de Ajustes (T5e: la contraseña, el correo, las otras sesiones, desvincular, tu descargo, borrar), igual.
const CuentaAjuste = defineAsyncComponent(() => import('./cuenta/CuentaAjuste.vue'));
const PASOS_DE_AJUSTES = [VISTA.CLAVE, VISTA.CORREO, VISTA.OTRAS, VISTA.DESVINCULAR, VISTA.FIRMA, VISTA.BORRAR];

// Las MISMAS props que la raíz le pasa con `v-bind="props"`: ninguna acaba de atributo en el DOM.
defineOptions({ inheritAttrs: false });
const props = defineProps(PROPS_MOTOR);
const {
    abierta, textos, e, ck, inicio, vistaQr, social, firma, authStore, waiverStore, rotulosGoogle, tx, sinQr,
    pantallaEntrar, google, abrirQr, aInicio, renovarQr, olvido, aGoogle, guardarQr, pedirRenovar, cambiarEntrada,
    cambiarAlta, aCrear, leerDescargo, cambiarGoogle, irAlBloque, abrirReserva, aCambiar, masHistorial, reservaAbierta,
    cambiarVista, antesAbierta, hacerTarea, pantallaHijos, fichaHijo, abrirHijos, abrirHijo, cambiarHijo, otroHijo,
    quitarFicha, casillaHijos, casillaHijo, preguntarQuitar, quitarHijo, alternarAjuste, datoAjuste, guardarDatos, pasoAjuste,
    vincular, interruptor, descargarDatos, masRecibos, salir, pasoDeAjuste, cambiarPaso, enlaceClave, reenviarCorreo,
    cancelarCorreo, borrarCuenta,
} = useSeccionCuenta(props);
const proveedor = (via) => via === 'google' && aGoogle();
</script>

<template>
    <Teleport to="body">
        <IslaFlotante
            v-if="abierta"
            :textos="textos"
            :checkout="ck"
            :bloquea-pagina="false"
        >
            <PantallaDescargo
                v-if="e.subpaso === 'descargo'"
                :secciones="waiverStore.document?.sections ?? []"
            />
            <CuentaInicio
                v-else-if="e.vista === VISTA.INICIO"
                v-bind="inicio"
                @qr="abrirQr"
                @bloque="irAlBloque"
                @cambiar="aCambiar(false)"
                @abrir="abrirReserva"
                @mas="masHistorial"
                @guardar="guardarQr"
                @preguntar="pedirRenovar"
                @renovar="renovarQr"
                @tarea="hacerTarea"
                @hijos="abrirHijos"
                @hijo="abrirHijo"
                @alternar="alternarAjuste"
                @dato="datoAjuste"
                @guardar-datos="guardarDatos"
                @paso="pasoAjuste"
                @vincular="vincular"
                @interruptor="interruptor"
                @descargar="descargarDatos"
                @mas-recibos="masRecibos"
                @salir="salir"
            />
            <CuentaAjuste
                v-else-if="PASOS_DE_AJUSTES.includes(e.vista) && pasoDeAjuste"
                :vista="e.vista"
                :paso="pasoDeAjuste"
                @cambiar="cambiarPaso"
                @enlace="enlaceClave"
                @reenviar="reenviarCorreo"
                @cancelar="cancelarCorreo"
                @descargo="leerDescargo"
                @borrar="borrarCuenta"
                @volver="aInicio"
            />
            <CuentaHijos
                v-else-if="e.vista === VISTA.HIJOS"
                :pantalla="pantallaHijos"
                @cambiar="cambiarHijo"
                @otro="otroHijo"
                @quitar="quitarFicha"
                @casilla="casillaHijos"
                @descargo="leerDescargo"
            />
            <CabeceraDesenlace
                v-else-if="e.vista === VISTA.HIJOS_LISTO"
                kind="success"
                :celebrate="false"
                :title="tx('mi_cuenta.hijos.listo')"
                :style="{ padding: '24px 0' }"
            />
            <CuentaHijo
                v-else-if="e.vista === VISTA.HIJO && fichaHijo.nombre"
                :ficha="fichaHijo"
                @casilla="casillaHijo"
                @descargo="leerDescargo"
                @preguntar="preguntarQuitar"
                @quitar="quitarHijo"
            />
            <div
                v-else-if="e.vista === VISTA.RESERVA && reservaAbierta"
                :style="{ display: 'grid', gap: '28px', maxWidth: '520px', margin: '0 auto' }"
            >
                <BloqueReserva
                    id-bloque="reserva"
                    :titulo="tx('mi_cuenta.reserva.titulo')"
                    oculto
                    v-bind="reservaAbierta"
                    @cambiar="aCambiar(true)"
                />
                <BloqueAntes
                    v-if="antesAbierta"
                    id-bloque="reserva-antes"
                    :antes="antesAbierta"
                    @tarea="hacerTarea"
                />
            </div>
            <CuentaCambiar
                v-else-if="e.vista === VISTA.CAMBIAR && cambiarVista"
                :cambiar="cambiarVista"
            />
            <CuentaQr
                v-else-if="e.vista === VISTA.QR"
                v-bind="vistaQr"
                :sin-qr="sinQr"
                @guardar="guardarQr"
                @preguntar="pedirRenovar"
                @renovar="renovarQr"
                @cuenta="aInicio"
            />
            <CuentaEntrar
                v-else-if="e.vista === VISTA.ENTRAR"
                :pantalla="pantallaEntrar"
                @cambiar="cambiarEntrada"
                @olvido="olvido"
                @proveedor="proveedor"
                @crear="aCrear"
            />
            <PantallaDatos
                v-else-if="e.vista === VISTA.CREAR"
                cuenta="nueva"
                :titulo="tx('mi_cuenta_alta.crear_titulo')"
                :entrar="false"
                :valores="e.f"
                :firmado="! firma"
                :errores="e.errores"
                :aviso="e.avisoAlta"
                v-bind="social"
                @cambiar="cambiarAlta"
                @descargo="leerDescargo"
                @proveedor="proveedor"
            >
                <template #antibot>
                    <CajaAntiBot
                        v-if="authStore.signupSiteKey"
                        v-model:token="e.token"
                        :sitekey="authStore.signupSiteKey"
                    />
                </template>
            </PantallaDatos>
            <CuentaAltaGoogle
                v-else-if="e.vista === VISTA.ALTA_GOOGLE"
                :pantalla="google"
                :rotulos="rotulosGoogle"
                :firma="waiverStore.document !== null"
                :urls="props.urls"
                @cambiar="cambiarGoogle"
                @descargo="leerDescargo"
            />
        </IslaFlotante>
    </Teleport>
</template>
