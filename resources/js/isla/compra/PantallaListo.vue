<script setup>
/**
 * «Listo» (`PjcListo` del diseño): el único momento con movimiento de la compra. La cabecera celebra; el QR, en
 * grande, con «Guardar en el móvil» y a dónde se ha enviado; «Antes de venir», una tarjeta por tarea, todas
 * opcionales; y, solo tras la primera compra, que la cuenta ya está creada. Lo de debajo sube escalonado.
 *
 * `tareas` llegan hechas: `{ id, icon, title, steps, texto, botones }` —menores, adultos, lo de la instalación
 * (sus calcetines) y la fiesta—. El QR real es el carné (`qrSrc`); `codigo`, lo que se lee debajo.
 */
import { useTextos } from '../piezas/textos.js';
import { PASO, subir } from './estilos.js';
import IconoLucide from '../ui/IconoLucide.vue';
import BotonSistema from '../ui/BotonSistema.vue';
import PaseQr from '../ui/PaseQr.vue';
import TarjetaTarea from '../ui/TarjetaTarea.vue';
import CabeceraDesenlace from '../ui/CabeceraDesenlace.vue';

defineProps({
    fiesta: { type: Boolean, default: false },
    linea: { type: String, required: true },
    codigo: { type: String, required: true },
    qrSrc: { type: String, default: '' },
    correo: { type: String, required: true },
    whatsapp: { type: Boolean, default: false },
    tareas: { type: Array, default: () => [] },
    cuentaNueva: { type: Boolean, default: false },
});
const emit = defineEmits(['guardar', 'tarea']);
const { t, tp } = useTextos();
</script>

<template>
    <div :style="PASO.paso">
        <CabeceraDesenlace
            kind="success"
            :title="fiesta ? t('compra.listo.titular_fiesta') : t('compra.listo.titular')"
        >
            {{ linea }}
        </CabeceraDesenlace>

        <div :style="[{ display: 'grid', gap: '12px', justifyItems: 'center', textAlign: 'center', padding: '18px 14px', borderRadius: 'var(--r-lg)', background: 'var(--surface-card)' }, subir(300)]">
            <PaseQr
                :code="codigo"
                size="lg"
                :src="qrSrc"
            />
            <p :style="[PASO.pregunta, { textWrap: 'balance' }]">{{ t('compra.listo.qr') }}</p>
            <BotonSistema
                variant="quiet"
                full
                :style="{ maxWidth: '320px' }"
                @click="emit('guardar')"
            >
                <template #icono-izquierda><IconoLucide
                    name="download"
                    :size="18"
                /></template>{{ t('compra.listo.guardar') }}
            </BotonSistema>
            <p :style="PASO.pista">{{ tp('compra.listo.enviado', { correo }) + (whatsapp ? ` ${t('compra.listo.whatsapp')}` : '') }}</p>
        </div>

        <section :style="[{ display: 'grid', gap: '10px' }, subir(380)]">
            <h2 :style="PASO.pregunta">{{ t('compra.listo.antes') }}</h2>
            <TarjetaTarea
                v-for="tarea in tareas"
                :key="tarea.id"
                :icon="tarea.icon"
                :title="tarea.title || ''"
                :steps="tarea.steps || null"
            >
                <template
                    v-if="tarea.botones"
                    #acciones
                >
                    <BotonSistema
                        v-for="b in tarea.botones"
                        :key="b"
                        variant="quiet"
                        :style="{ flex: '1 1 180px' }"
                        @click="emit('tarea', tarea.id, b)"
                    >
                        {{ b }}
                    </BotonSistema>
                </template>
                <template
                    v-if="tarea.texto"
                    #default
                >
                    {{ tarea.texto }}
                </template>
            </TarjetaTarea>
        </section>

        <p
            v-if="cuentaNueva"
            :style="[PASO.cuerpo, { display: 'flex', gap: '10px' }, subir(420)]"
        ><span :style="{ flex: '0 0 auto', marginTop: '2px', color: 'var(--icon-accent)' }"><IconoLucide
            name="circle-user-round"
            :size="18"
        /></span>{{ t('compra.listo.cuenta') }}</p>
    </div>
</template>
