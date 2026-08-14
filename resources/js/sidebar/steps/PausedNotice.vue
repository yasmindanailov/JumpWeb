<script setup>
/**
 * El aviso de RESERVAS EN PAUSA (Fase 4 · paso 4.3·3).
 *
 * Sustituye el flujo de compra entero mientras el interruptor está puesto. **No decide nada**: el
 * texto y los canales llegan compuestos por `paused.js` a partir de `GET /booking/status`.
 *
 * ⚠️ **El contenedor `.purchase__maint-ctas` se emite SIEMPRE**, aunque el condicional esté en cada
 * enlace: es el mismo patrón que `.cart__lines`, y de él cuelga el único selector estructural del
 * bloque —`.purchase__maint-ctas .btn`, que es lo que da a los botones el ancho completo y el texto
 * centrado—. Un `v-if` sobre el contenedor los deja del ancho del texto con las clases correctas.
 *
 * ⚠️ **Y el `text-align: center` lo hereda todo el bloque de `.purchase__maint`**: sacar el título o
 * el cuerpo de ese contenedor los descentra sin que falte una sola clase.
 */
defineProps({
    /** `{title, message, ctas:[{key, href, label, primary, external}]}`, compuesto por `paused.js`. */
    notice: { type: Object, required: true },
});
</script>

<template>
    <div class="purchase__maint">
        <h3 class="wiz__title">{{ notice.title }}</h3>
        <p class="purchase__maint-body">{{ notice.message }}</p>
        <div class="purchase__maint-ctas">
            <!--
              Los canales van A LA VEZ, no en cascada: si hay teléfono y WhatsApp se pintan los dos.
              Solo el de llamar lleva `btn--zone` (es el canal principal).
            -->
            <a v-for="cta in notice.ctas" :key="cta.key"
               :href="cta.href"
               class="btn btn--lg"
               :class="cta.primary ? 'btn--zone' : ''"
               :target="cta.external ? '_blank' : null"
               :rel="cta.external ? 'noopener' : null">{{ cta.label }}</a>
        </div>
    </div>
</template>
