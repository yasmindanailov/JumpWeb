<script setup>
/**
 * **Añade a tus hijos** (`paginas/mi-cuenta/pantallas.jsx`, `PmcHijos`; T5d de §4.13, `#777`): por hijo, su nombre, su
 * fecha de nacimiento (teclado de números y las barras solas) y «Soy su» con las CINCO relaciones del catálogo, ninguna
 * marcada (`#773`·b); «Añadir otro hijo»; y una sola casilla del descargo para todos, con «Leer el descargo», si la
 * instalación lo firma dentro. Sin apellidos (`#773`·a). La acción, «Guardar», es de la capa.
 *
 * Pinta y avisa: qué falta y qué dijo el servidor lo decide `hijos.js`.
 */
import { useTextos } from '../piezas/textos.js';
import { PASO } from '../compra/estilos.js';
import PasoCompra from '../compra/PasoCompra.vue';
import CampoSistema from '../ui/CampoSistema.vue';
import TarjetasOpcion from '../ui/TarjetasOpcion.vue';
import CasillaSistema from '../ui/CasillaSistema.vue';
import EnlaceSistema from '../ui/EnlaceSistema.vue';
import AvisoDestacado from '../ui/AvisoDestacado.vue';
import IconoLucide from '../ui/IconoLucide.vue';

defineProps({
    pantalla: { type: Object, required: true },
});
const emit = defineEmits(['cambiar', 'otro', 'quitar', 'casilla', 'descargo']);
const { t } = useTextos();
</script>

<template>
    <PasoCompra :titulo="t('mi_cuenta.hijos.titulo')">
        <AvisoDestacado
            v-if="pantalla.aviso"
            tone="danger"
            size="sm"
            role="alert"
            :title="pantalla.aviso"
        >
            <template #icono><IconoLucide
                name="circle-alert"
                :size="18"
            /></template>
        </AvisoDestacado>
        <fieldset
            v-for="(f, i) in pantalla.fichas"
            :key="f.id"
            :style="{ border: 'none', margin: 0, padding: 0, display: 'grid', gap: '14px', minWidth: 0 }"
        >
            <legend
                v-if="pantalla.fichas.length > 1"
                :style="{ display: 'flex', width: '100%', alignItems: 'center', justifyContent: 'space-between', padding: 0, marginBottom: '10px' }"
            >
                <span :style="PASO.pregunta">{{ f.titulo }}</span>
                <EnlaceSistema @click="emit('quitar', i)">{{ t('mi_cuenta.hijos.quitar') }}</EnlaceSistema>
            </legend>
            <CampoSistema
                :id="`pmc-n-${f.id}`"
                :label="t('mi_cuenta.hijos.nombre')"
                autocomplete="off"
                autocapitalize="words"
                :model-value="f.nombre"
                :error="pantalla.errores.lista?.[i]?.nombre || ''"
                @update:model-value="emit('cambiar', i, 'nombre', $event)"
            />
            <CampoSistema
                :id="`pmc-f-${f.id}`"
                :label="t('mi_cuenta.hijos.nacimiento')"
                inputmode="numeric"
                autocomplete="off"
                :placeholder="t('mi_cuenta.hijos.pista_fecha')"
                maxlength="10"
                :hint="f.pista"
                :model-value="f.fecha"
                :error="pantalla.errores.lista?.[i]?.fecha || ''"
                @update:model-value="emit('cambiar', i, 'fecha', $event)"
            />
            <TarjetasOpcion
                :name="`pmc-r-${f.id}`"
                :label="t('mi_cuenta.hijos.soy_su')"
                columns="2"
                :items="pantalla.relaciones"
                :model-value="f.rel"
                :error="pantalla.errores.lista?.[i]?.rel || ''"
                @update:model-value="emit('cambiar', i, 'rel', $event)"
            />
        </fieldset>
        <EnlaceSistema
            :style="{ justifySelf: 'start', marginTop: '-8px' }"
            @click="emit('otro')"
        >
            <template #icono><IconoLucide
                name="plus"
                :size="18"
            /></template>{{ t('mi_cuenta.hijos.otro') }}
        </EnlaceSistema>
        <CasillaSistema
            v-if="pantalla.firma"
            id="pmc-descargo"
            :label="t('mi_cuenta.hijos.casilla')"
            :model-value="pantalla.descargo"
            :error="pantalla.errores.descargo || ''"
            @update:model-value="emit('casilla', $event)"
        >
            <EnlaceSistema
                :style="{ justifySelf: 'start' }"
                @click="emit('descargo')"
            >{{ t('mi_cuenta.hijos.leer') }}</EnlaceSistema>
        </CasillaSistema>
    </PasoCompra>
</template>
