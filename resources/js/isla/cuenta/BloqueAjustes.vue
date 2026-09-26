<script setup>
/**
 * **Ajustes, plegados al final** (`paginas/mi-cuenta/bloques-2.jsx`, `PmcAjustes`; T5e de §4.13, `#778`): «nada esencial
 * vive aquí». Cuatro plegables —Tus datos, Acceso, Privacidad, Recibos— y «Cerrar sesión» debajo. Lo que se aplica al
 * momento se confirma arriba; lo que necesita más de un campo o una confirmación abre un paso en la misma capa.
 *
 * Lo que el mockup no dibuja y la verdad pide, con las piezas del sistema (`#773`·d): el correo pendiente de confirmar
 * (reenviar, cancelar), desvincular Google, los interruptores de la analítica y la encuesta junto a «Novedades» (retirar
 * tan fácil como dar), el descargo que falta firmar, y sin Apple ni «Descargar el recibo» (`#773`·c, `#683`).
 *
 * Pinta y avisa: qué dice cada fila lo decide `ajustes.js`, y lo que hace, `useAjustesCuenta.js`.
 */
import { computed } from 'vue';
import './iconos-ajustes.js';
import { useTextos } from '../piezas/textos.js';
import { CUENTA } from './estilos.js';
import { PLEGABLES } from './ajustes.js';
import FilaAjuste from './FilaAjuste.vue';
import AcordeonSistema from '../ui/AcordeonSistema.vue';
import CampoSistema from '../ui/CampoSistema.vue';
import SelectorSistema from '../ui/SelectorSistema.vue';
import InterruptorSistema from '../ui/InterruptorSistema.vue';
import BotonSistema from '../ui/BotonSistema.vue';
import EnlaceSistema from '../ui/EnlaceSistema.vue';
import ResumenPrecio from '../ui/ResumenPrecio.vue';
import EsqueletoCarga from '../ui/EsqueletoCarga.vue';
import IconoLucide from '../ui/IconoLucide.vue';

const props = defineProps({
    ajustes: { type: Object, required: true },
    saliendo: { type: Boolean, default: false },
});
const emit = defineEmits(['alternar', 'dato', 'guardar', 'paso', 'vincular', 'interruptor', 'descargar', 'mas', 'salir']);
const { t, tp } = useTextos();

const items = computed(() => PLEGABLES.map((p) => ({ id: p.id, question: t(`mi_cuenta.ajustes.${p.id}`) })));
const iconoDe = Object.fromEntries(PLEGABLES.map((p) => [p.id, p.icono]));
const correoSub = computed(() => {
    const c = props.ajustes.correo;

    return c?.pendiente ? tp('mi_cuenta.ajustes.correo_pendiente', { correo: c.pendiente.correo }) : (c?.actual ?? '');
});
</script>

<template>
    <section
        id="ajustes"
        aria-labelledby="ajustes-t"
        :style="CUENTA.bloque"
    >
        <h2
            id="ajustes-t"
            :style="CUENTA.h2"
        >{{ t('mi_cuenta.ajustes.titulo') }}</h2>
        <AcordeonSistema
            :items="items"
            :abiertos="ajustes.abiertos"
            :heading-level="3"
            @alternar="(id) => emit('alternar', id)"
        >
            <template
                v-for="p in PLEGABLES"
                :key="p.id"
                #[`icono-${p.id}`]
            >
                <IconoLucide
                    :name="iconoDe[p.id]"
                    :size="18"
                />
            </template>

            <template #datos>
                <EsqueletoCarga
                    v-if="ajustes.cargandoPerfil"
                    kind="card"
                    height="200px"
                />
                <div
                    v-else
                    :style="{ display: 'grid', gap: '14px', paddingTop: '4px' }"
                >
                    <CampoSistema
                        id="mc-aj-nombre"
                        :label="t('mi_cuenta.ajustes.nombre')"
                        autocomplete="name"
                        :model-value="ajustes.datos.nombre"
                        :error="ajustes.datos.errores.nombre"
                        @update:model-value="(v) => emit('dato', 'nombre', v)"
                    />
                    <CampoSistema
                        id="mc-aj-telefono"
                        :label="t('mi_cuenta.ajustes.telefono')"
                        type="tel"
                        inputmode="tel"
                        autocomplete="tel"
                        :model-value="ajustes.datos.telefono"
                        :error="ajustes.datos.errores.telefono"
                        @update:model-value="(v) => emit('dato', 'telefono', v)"
                    />
                    <SelectorSistema
                        id="mc-aj-idioma"
                        :label="t('mi_cuenta.ajustes.idioma')"
                        :hint="t('mi_cuenta.ajustes.idioma_pista')"
                        :options="ajustes.idiomas"
                        :model-value="ajustes.datos.idioma"
                        :error="ajustes.datos.errores.idioma"
                        @update:model-value="(v) => emit('dato', 'idioma', v)"
                    />
                    <BotonSistema
                        v-if="ajustes.datos.cambiado"
                        variant="secondary"
                        :loading="ajustes.datos.guardando"
                        :loading-label="t('mi_cuenta.ajustes.guardando')"
                        :style="{ justifySelf: 'start' }"
                        @click="emit('guardar')"
                    >{{ t('mi_cuenta.ajustes.guardar') }}</BotonSistema>
                    <!-- Los enlaces cortos de las filas («Cambiar», «Cerrar», «Descargar») llevan su nombre ENTERO: dentro
                         de la capa hay otro «Cerrar» (la X) y dos «Descargar», y el que lee con lector de pantalla no ve la
                         fila. El nombre contiene lo que se ve (WCAG 2.5.3). -->
                    <FilaAjuste
                        :label="t('mi_cuenta.ajustes.correo')"
                        :sub="correoSub"
                    >
                        <EnlaceSistema
                            :aria-label="t('mi_cuenta.ajustes.cambiar_correo')"
                            @click="emit('paso', 'correo')"
                        >{{ t('mi_cuenta.ajustes.cambiar') }}</EnlaceSistema>
                    </FilaAjuste>
                    <p :style="CUENTA.pista">{{ t('mi_cuenta.ajustes.correo_pista') }}</p>
                </div>
            </template>

            <template #acceso>
                <div :style="{ display: 'grid', paddingTop: '4px' }">
                    <FilaAjuste
                        :label="t('mi_cuenta.ajustes.clave')"
                        abre
                        @click="emit('paso', 'clave')"
                    />
                    <FilaAjuste
                        v-if="ajustes.google"
                        :label="ajustes.google.vinculada ? t('mi_cuenta.ajustes.google_vinculado') : t('mi_cuenta.ajustes.google_vincular')"
                        :sub="ajustes.google.correo ?? ''"
                    >
                        <template v-if="ajustes.google.vinculada">
                            <IconoLucide
                                name="check"
                                :size="18"
                                color="var(--text-positive)"
                            />
                            <EnlaceSistema
                                :aria-label="t('mi_cuenta.desvincular.titulo')"
                                @click="emit('paso', 'desvincular')"
                            >{{ t('mi_cuenta.ajustes.desvincular') }}</EnlaceSistema>
                        </template>
                        <EnlaceSistema
                            v-else
                            :aria-label="t('mi_cuenta.ajustes.google_vincular')"
                            @click="emit('vincular')"
                        >{{ t('mi_cuenta.ajustes.vincular') }}</EnlaceSistema>
                    </FilaAjuste>
                    <FilaAjuste :label="t('mi_cuenta.ajustes.otras')">
                        <EnlaceSistema
                            :aria-label="t('mi_cuenta.ajustes.otras')"
                            @click="emit('paso', 'otras-sesiones')"
                        >{{ t('mi_cuenta.ajustes.cerrar_corto') }}</EnlaceSistema>
                    </FilaAjuste>
                </div>
            </template>

            <template #privacidad>
                <EsqueletoCarga
                    v-if="ajustes.cargandoPerfil"
                    kind="card"
                    height="160px"
                />
                <div
                    v-else
                    :style="{ display: 'grid', paddingTop: '4px' }"
                >
                    <InterruptorSistema
                        id="mc-aj-novedades"
                        :label="t('mi_cuenta.ajustes.novedades')"
                        :checked="ajustes.interruptores?.novedades ?? false"
                        :disabled="Boolean(ajustes.cambiando)"
                        :on-text="t('mi_cuenta.ajustes.si')"
                        :off-text="t('mi_cuenta.ajustes.no')"
                        :style="{ borderBottom: '1px solid var(--border-subtle)' }"
                        @cambiar="(v) => emit('interruptor', 'novedades', v)"
                    />
                    <InterruptorSistema
                        id="mc-aj-analitica"
                        :label="t('mi_cuenta.ajustes.analitica')"
                        :description="t('mi_cuenta.ajustes.analitica_pista')"
                        :checked="ajustes.interruptores?.analitica ?? false"
                        :disabled="Boolean(ajustes.cambiando)"
                        :on-text="t('mi_cuenta.ajustes.si')"
                        :off-text="t('mi_cuenta.ajustes.no')"
                        :style="{ borderBottom: '1px solid var(--border-subtle)' }"
                        @cambiar="(v) => emit('interruptor', 'analitica', v)"
                    />
                    <InterruptorSistema
                        id="mc-aj-encuestas"
                        :label="t('mi_cuenta.ajustes.encuestas')"
                        :description="t('mi_cuenta.ajustes.encuestas_pista')"
                        :checked="ajustes.interruptores?.encuestas ?? false"
                        :disabled="Boolean(ajustes.cambiando)"
                        :on-text="t('mi_cuenta.ajustes.si')"
                        :off-text="t('mi_cuenta.ajustes.no')"
                        :style="{ borderBottom: '1px solid var(--border-subtle)' }"
                        @cambiar="(v) => emit('interruptor', 'encuestas', v)"
                    />
                    <FilaAjuste
                        v-if="ajustes.descargo"
                        :label="t('mi_cuenta.ajustes.descargo')"
                        :sub="ajustes.descargo.texto"
                    >
                        <EnlaceSistema
                            v-if="ajustes.descargo.firmar"
                            :aria-label="t('mi_cuenta.ajustes.firmar_descargo')"
                            @click="emit('paso', 'firma')"
                        >{{ t('mi_cuenta.ajustes.firmar') }}</EnlaceSistema>
                        <EnlaceSistema
                            v-else-if="ajustes.descargo.pdf"
                            :href="ajustes.descargo.pdf"
                            :aria-label="t('mi_cuenta.ajustes.descargar_descargo')"
                        >
                            <template #icono><IconoLucide
                                name="download"
                                :size="16"
                            /></template>{{ t('mi_cuenta.ajustes.descargar') }}
                        </EnlaceSistema>
                    </FilaAjuste>
                    <FilaAjuste :label="t('mi_cuenta.ajustes.mis_datos')">
                        <EnlaceSistema
                            :disabled="ajustes.exportando"
                            :aria-label="t('mi_cuenta.ajustes.mis_datos')"
                            @click="emit('descargar')"
                        >
                            <template #icono><IconoLucide
                                name="download"
                                :size="16"
                            /></template>{{ t('mi_cuenta.ajustes.descargar') }}
                        </EnlaceSistema>
                    </FilaAjuste>
                    <div :style="{ paddingTop: '10px' }">
                        <EnlaceSistema
                            variant="quiet"
                            @click="emit('paso', 'borrar')"
                        >{{ t('mi_cuenta.ajustes.borrar') }}</EnlaceSistema>
                    </div>
                </div>
            </template>

            <template #recibos>
                <div :style="{ display: 'grid', gap: '12px', paddingTop: '4px' }">
                    <EsqueletoCarga
                        v-if="ajustes.recibos === null"
                        kind="card"
                        height="120px"
                    />
                    <p
                        v-else-if="! ajustes.recibos.length"
                        :style="CUENTA.pista"
                    >{{ t('mi_cuenta.ajustes.sin_recibos') }}</p>
                    <ResumenPrecio
                        v-for="r in ajustes.recibos ?? []"
                        :key="r.code"
                        size="md"
                        :selection="r.selection"
                        :lines="r.lines"
                        :total="r.total"
                        :total-label="t('mi_cuenta.proxima.total')"
                        :note="r.note"
                    />
                    <EnlaceSistema
                        v-if="ajustes.recibos?.length && ajustes.hayMasRecibos"
                        :disabled="ajustes.cargandoRecibos"
                        :style="{ justifySelf: 'start' }"
                        @click="emit('mas')"
                    >{{ t('mi_cuenta.otras.mas') }}</EnlaceSistema>
                </div>
            </template>
        </AcordeonSistema>
        <BotonSistema
            variant="ghost"
            :loading="saliendo"
            :loading-label="t('mi_cuenta.ajustes.cerrar')"
            :style="{ justifySelf: 'start' }"
            @click="emit('salir')"
        >
            <template #icono-izquierda><IconoLucide
                name="log-out"
                :size="18"
            /></template>{{ t('mi_cuenta.ajustes.cerrar') }}
        </BotonSistema>
    </section>
</template>
