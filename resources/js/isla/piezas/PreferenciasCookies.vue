<script setup>
/**
 * **LA SEGUNDA CAPA DE LAS COOKIES, dentro de la isla** («Tus cookies»; el mockup solo dibuja la primera, y el owner la
 * encargó, 25-09, con el sistema que ya hay). Las mismas piezas que la primera capa (`BloqueCookies`): los dos botones
 * tranquilos AL MISMO NIVEL —aceptar y rechazar, igual de fáciles (guía de la AEPD)— y sus enlaces; cada finalidad con
 * su interruptor del sistema, que se aplica AL MOMENTO (la regla de `Switch`: sin «Guardar»; la isla lo confirma con su
 * aviso). Las necesarias no se pueden apagar: su etiqueta lo dice. Los textos de cada finalidad son los legales de
 * siempre (`lang/<idioma>/cookies.php`, su grupo `panel`). PINTA (`CE-6`): lo que hace cada cosa llega en `preferencias`.
 */
import BotonTranquilo from './BotonTranquilo.vue';
import EnlaceIsla from './EnlaceIsla.vue';
import InterruptorSistema from '../ui/InterruptorSistema.vue';

defineProps({
    preferencias: { type: Object, required: true },
});
const raya = { height: '1px', background: 'rgba(255,255,255,0.12)', margin: '2px 0' };
</script>

<template>
    <div :style="{ display: 'grid', gap: '2px', padding: '0 6px 4px' }">
        <div :style="{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: '16px', minHeight: '44px' }">
            <span :style="{ display: 'flex', flexDirection: 'column', gap: '2px', minWidth: 0 }">
                <span :style="{ fontFamily: 'var(--font-ui)', fontSize: 'var(--fs-body-sm)', fontWeight: 'var(--fw-semibold)', color: 'var(--isla-sobre)' }">{{ preferencias.necesarias.titulo }}</span>
                <span :style="{ fontFamily: 'var(--font-ui)', fontSize: 'var(--fs-caption)', lineHeight: 1.45, color: 'rgba(255,255,255,0.7)' }">{{ preferencias.necesarias.texto }}</span>
            </span>
            <span :style="{ flex: '0 0 auto', padding: '4px 10px', borderRadius: 'var(--r-pill)', background: 'rgba(255,255,255,0.12)', fontFamily: 'var(--font-ui)', fontSize: 'var(--fs-caption)', fontWeight: 'var(--fw-bold)', color: 'var(--isla-sobre)', whiteSpace: 'nowrap' }">{{ preferencias.necesarias.etiqueta }}</span>
        </div>
        <template
            v-for="c in preferencias.categorias"
            :key="c.id"
        >
            <div :style="raya" />
            <InterruptorSistema
                :id="`isla-cookie-${c.id}`"
                tono="tinta"
                :label="c.titulo"
                :description="c.texto"
                :checked="c.activa"
                :on-text="preferencias.textos.si"
                :off-text="preferencias.textos.no"
                @cambiar="(activa) => preferencias.onCambiar(c.id, activa)"
            />
        </template>
        <div :style="{ display: 'flex', gap: '8px', marginTop: '12px' }">
            <BotonTranquilo
                :label="preferencias.textos.aceptar"
                :pulsar="preferencias.onAceptarTodas"
            />
            <BotonTranquilo
                :label="preferencias.textos.rechazar"
                :pulsar="preferencias.onRechazarTodas"
            />
        </div>
        <div :style="{ display: 'flex', justifyContent: 'center', marginTop: '8px' }">
            <EnlaceIsla
                :label="preferencias.textos.politica"
                :pulsar="preferencias.onPolitica"
            />
        </div>
    </div>
</template>
