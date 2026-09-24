<script setup>
/**
 * **Los pasos de la compra de la isla DESPUÉS de la pantalla 0** (T3e·3): «Tus datos» (con el descargo y el olvido
 * dentro), «Pagar», la salida al banco y los desenlaces. Viajan en su propio trozo (`pasos-diferidos.js`), que
 * `SeccionCompra.vue` pide al montarse: quien abre la compra ve la pantalla 0 sin esperarlos, y cuando pulsa
 * «Continuar» ya están. Pinta (`CE-6`) lo que le da `useSeccionCompra.js` por `inject`.
 */
import { inject } from 'vue';
import { COMPRA } from './useSeccionCompra.js';
import PantallaDatos from './PantallaDatos.vue';
import PantallaDescargo from './PantallaDescargo.vue';
import PantallaEntrar from './PantallaEntrar.vue';
import PantallaPagar from './PantallaPagar.vue';
import PantallaSaliendo from './PantallaSaliendo.vue';
import PantallaFallido from './PantallaFallido.vue';
import PantallaVerificando from './PantallaVerificando.vue';
import PantallaPerdida from './PantallaPerdida.vue';
import PantallaListo from './PantallaListo.vue';
import CajaAntiBot from './CajaAntiBot.vue';
import FormularioPasarela from './FormularioPasarela.vue';
import CabeceraDesenlace from '../ui/CabeceraDesenlace.vue';

const {
    paso, esperando, authStore, outcomeStore, datos, pantallaDatos, pantallaEntrar, aGoogle, pago, listo, recibo, fallido,
    perdida, elegirNueva,
} = inject(COMPRA);
// Apple sigue de corchete apagado (`#683`): su botón no se pinta, así que solo Google llega aquí.
const proveedor = (via) => via === 'google' && aGoogle();
</script>

<template>
    <template v-if="paso === 'datos'">
        <PantallaDescargo
            v-if="datos.estado.vista === 'descargo'"
            :secciones="datos.waiverStore.document?.sections ?? []"
        />
        <PantallaEntrar
            v-else-if="datos.estado.vista === 'entrar'"
            v-bind="pantallaEntrar"
            @cambiar="datos.cambiarEntrada"
            @olvido="datos.olvido({ correo: datos.estado.ent.valor, solo: false })"
            @proveedor="proveedor"
        />
        <PantallaDatos
            v-else
            v-bind="pantallaDatos"
            @cambiar="datos.cambiar"
            @descargo="datos.estado.vista = 'descargo'"
            @entrar="datos.abrirEntrar"
            @proveedor="proveedor"
        >
            <template
                v-if="authStore.signupSiteKey && pantallaDatos.cuenta === 'nueva'"
                #antibot
            >
                <CajaAntiBot
                    v-model:token="datos.estado.token"
                    :sitekey="authStore.signupSiteKey"
                />
            </template>
        </PantallaDatos>
    </template>
    <PantallaPagar
        v-else-if="paso === 'pagar'"
        v-bind="recibo"
        @cantidad="pago.cantidad"
        @calcetines="pago.calcetines"
    />
    <template v-else-if="paso === 'banco'">
        <PantallaSaliendo />
        <FormularioPasarela
            v-if="outcomeStore.gateway"
            :form="outcomeStore.gateway"
            :intentos="pago.salidas.value"
        />
    </template>
    <CabeceraDesenlace
        v-else-if="esperando"
        kind="pending"
        :style="{ padding: '32px 0' }"
    />
    <PantallaFallido
        v-else-if="paso === 'fallido'"
        v-bind="fallido"
    />
    <PantallaVerificando v-else-if="paso === 'verificando'" />
    <PantallaPerdida
        v-else-if="paso === 'perdida'"
        v-bind="perdida"
        @hora="elegirNueva"
    />
    <PantallaListo
        v-else-if="paso === 'listo'"
        v-bind="listo"
        @guardar="pago.guardarQr"
        @tarea="pago.tarea"
    />
</template>
