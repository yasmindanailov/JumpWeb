<script setup>
/**
 * Paso 1 de la compra, «Tus datos» (`PjcDatos` del diseño, `paginas/compra/pasos-1-2.jsx`). Sin sesión: «¿Ya has
 * venido? Entra», Google o Apple ANTES de los campos (después ya no ahorran nada), nombre, correo, teléfono y
 * contraseña, y la casilla del descargo. Con sesión, solo el saludo y lo que de verdad falta: el teléfono en un
 * cumpleaños si entró con Google o Apple, y la casilla si esa cuenta nunca la firmó. «Esta cuenta ya existe» pide
 * su contraseña; sale al ENVIAR, no al teclear (`#688`). Con errores, un resumen arriba que se lee primero.
 *
 * Pinta y avisa (`cambiar(campo, valor)`, `entrar(modo)`, `descargo`, `proveedor(via)`, `hora(valor)`): quién es,
 * qué falta y si la hora se llenó lo decide quien lleva la compra.
 */
import { computed } from 'vue';
import { useTextos } from '../piezas/textos.js';
import { PASO } from './estilos.js';
import PasoCompra from './PasoCompra.vue';
import IconoLucide from '../ui/IconoLucide.vue';
import EnlaceSistema from '../ui/EnlaceSistema.vue';
import CampoSistema from '../ui/CampoSistema.vue';
import CasillaSistema from '../ui/CasillaSistema.vue';
import AvisoDestacado from '../ui/AvisoDestacado.vue';
import SelectorHoras from '../ui/SelectorHoras.vue';
import AccesoSocial from '../ui/AccesoSocial.vue';

const props = defineProps({
    cuenta: { type: String, default: 'nueva' },
    nombrePila: { type: String, default: '' },
    valores: { type: Object, required: true },
    firmado: { type: Boolean, default: false },
    pedirTelefono: { type: Boolean, default: false },
    errores: { type: Object, default: () => ({}) },
    lineaMenores: { type: Boolean, default: false },
    llena: { type: Boolean, default: false },
    cercanas: { type: Array, default: () => [] },
    horaNueva: { type: String, default: null },
    enApp: { type: Boolean, default: null },
});
const emit = defineEmits(['cambiar', 'entrar', 'descargo', 'proveedor', 'hora']);
const { t, tp } = useTextos();

const fallos = computed(() => Object.values(props.errores).filter(Boolean).length);
const revisa = computed(() => (fallos.value === 1 ? t('compra.datos.revisa_uno') : tp('compra.datos.revisa', { n: fallos.value })));
const cambiar = (campo) => (valor) => emit('cambiar', campo, valor);
</script>

<template>
    <PasoCompra :titulo="cuenta === 'dentro' ? tp('compra.datos.hola', { nombre: nombrePila }) : t('compra.datos.titular')">
        <template
            v-if="cuenta !== 'dentro'"
            #antes
        >
            <p :style="PASO.cuerpo">{{ `${t('compra.datos.ya')} ` }}<EnlaceSistema
                underline="always"
                @click="emit('entrar', 'id')"
            >{{ t('compra.datos.entra') }}</EnlaceSistema></p>
        </template>
        <AvisoDestacado
            v-if="fallos"
            tone="danger"
            size="sm"
            role="alert"
            :title="revisa"
        >
            <template #icono><IconoLucide
                name="circle-alert"
                :size="18"
            /></template>
        </AvisoDestacado>
        <template v-if="cuenta !== 'dentro'">
            <AccesoSocial
                :in-app="enApp"
                :labels="{ google: t('compra.datos.google'), apple: t('compra.datos.apple') }"
                @google="emit('proveedor', 'google')"
                @apple="emit('proveedor', 'apple')"
            />
            <div :style="{ display: 'grid', gap: '16px' }">
                <CampoSistema
                    id="pjc-nombre"
                    :label="t('compra.datos.nombre')"
                    autocomplete="name"
                    :model-value="valores.nombre"
                    :error="errores.nombre || ''"
                    @update:model-value="cambiar('nombre')($event)"
                />
                <CampoSistema
                    id="pjc-correo"
                    :label="t('compra.datos.correo')"
                    type="email"
                    inputmode="email"
                    autocomplete="email"
                    :model-value="valores.correo"
                    :error="errores.correo || ''"
                    @update:model-value="cambiar('correo')($event)"
                />
                <AvisoDestacado
                    v-if="cuenta === 'existe'"
                    tone="info"
                    size="sm"
                    role="status"
                    :title="t('compra.datos.existe')"
                >
                    <template #icono><IconoLucide
                        name="circle-user-round"
                        :size="18"
                    /></template>
                    <div :style="{ display: 'grid', gap: '4px', marginTop: '10px' }">
                        <CampoSistema
                            id="pjc-clave-e"
                            :label="t('compra.datos.contrasena')"
                            type="password"
                            autocomplete="current-password"
                            :model-value="valores.contrasena"
                            :error="errores.contrasena || ''"
                            @update:model-value="cambiar('contrasena')($event)"
                        />
                        <EnlaceSistema
                            :style="{ justifySelf: 'start' }"
                            @click="emit('entrar', 'olvido')"
                        >{{ t('compra.datos.olvido') }}</EnlaceSistema>
                    </div>
                </AvisoDestacado>
                <template v-else>
                    <CampoSistema
                        id="pjc-tel"
                        :label="t('compra.datos.telefono')"
                        type="tel"
                        inputmode="tel"
                        autocomplete="tel"
                        :model-value="valores.telefono"
                        :hint="t('compra.datos.pista_telefono')"
                        :error="errores.telefono || ''"
                        @update:model-value="cambiar('telefono')($event)"
                    />
                    <CampoSistema
                        id="pjc-clave"
                        :label="t('compra.datos.contrasena')"
                        type="password"
                        autocomplete="new-password"
                        :model-value="valores.contrasena"
                        :hint="t('compra.datos.pista_contrasena')"
                        :error="errores.contrasena || ''"
                        @update:model-value="cambiar('contrasena')($event)"
                    />
                </template>
            </div>
        </template>
        <CampoSistema
            v-else-if="pedirTelefono"
            id="pjc-tel"
            :label="t('compra.datos.telefono')"
            type="tel"
            inputmode="tel"
            autocomplete="tel"
            :model-value="valores.telefono"
            :hint="t('compra.datos.pista_telefono')"
            :error="errores.telefono || ''"
            @update:model-value="cambiar('telefono')($event)"
        />
        <CasillaSistema
            v-if="!firmado && cuenta !== 'existe'"
            id="pjc-descargo"
            :label="t('compra.datos.casilla')"
            :model-value="Boolean(valores.descargo)"
            :error="errores.descargo || ''"
            @update:model-value="cambiar('descargo')($event)"
        >
            <EnlaceSistema
                :style="{ justifySelf: 'start' }"
                @click="emit('descargo')"
            >{{ t('compra.datos.leer') }}</EnlaceSistema>
            <p :style="PASO.pista">{{ t('compra.datos.pista_descargo') }}</p>
        </CasillaSistema>
        <AvisoDestacado
            v-if="lineaMenores"
            tone="neutral"
            size="sm"
        >
            <template #icono><IconoLucide
                name="users"
                :size="18"
            /></template>{{ t('compra.datos.linea') }}
        </AvisoDestacado>
        <AvisoDestacado
            v-if="llena"
            id="pjc-llena"
            tone="warn"
            size="sm"
            role="alert"
            :title="t('compra.datos.llena')"
        >
            <template #icono><IconoLucide
                name="clock-alert"
                :size="18"
            /></template>
            <SelectorHoras
                size="sm"
                :style="{ marginTop: '8px' }"
                :slots="cercanas"
                :model-value="horaNueva"
                counts="low"
                :low-threshold="8"
                @update:model-value="emit('hora', $event)"
            />
        </AvisoDestacado>
    </PasoCompra>
</template>
