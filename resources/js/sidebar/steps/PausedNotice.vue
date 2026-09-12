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
        <!-- La tercera pegatina del artboard: AVISO. Aquí no hay error ni éxito — las reservas
             están pausadas—, y era la otra pantalla del cajón que no enseñaba nada (`#258`).
             `warning` del sistema de diseño, copiado byte a byte (`SidebarIconParityTest`). -->
        <div class="purchase__party" aria-hidden="true">
            <span class="state-badge state-badge--attn">
                <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M10.6 4.4a1.6 1.6 0 0 1 2.8 0l8.4 14.6a1.6 1.6 0 0 1-1.4 2.4H3.6a1.6 1.6 0 0 1-1.4-2.4zM12 8.9a1.4 1.4 0 0 0-1.4 1.4v3.6a1.4 1.4 0 0 0 2.8 0v-3.6A1.4 1.4 0 0 0 12 8.9zm0 8.7a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3z" />
                </svg>
            </span>
        </div>
        <h3 class="wiz__title">{{ notice.title }}</h3>
        <p class="purchase__maint-body">{{ notice.message }}</p>
        <div class="purchase__maint-ctas">
            <!--
              Los canales van A LA VEZ, no en cascada: si hay teléfono y WhatsApp se pintan los dos.
              Solo el de llamar lleva `btn--ink` (es el canal principal).

              ❗❗❗ **DEFECTO PREEXISTENTE, ARREGLADO EN `#551` Y LO DESTAPÓ EL MANIFIESTO**: el que NO
              era principal se quedaba en `.btn` pelado, o sea en el **relleno de ACCIÓN**, mientras el
              principal llevaba la marca. Resultado: en una pantalla donde **no se puede comprar** —las
              ventas están pausadas— el único naranja era el canal SECUNDARIO, y pesaba más que el
              primero. Ahora el secundario es fantasma, que es lo que el sistema declara para el que
              baja de jerarquía. ▶ *Pintar «lo demás» con la clase base es heredar el rol que la base
              tenga, y la base de esta familia ES la acción.*
            -->
            <a v-for="cta in notice.ctas" :key="cta.key"
               :href="cta.href"
               class="btn btn--lg"
               :class="cta.primary ? 'btn--ink' : 'btn--ghost'"
               :target="cta.external ? '_blank' : null"
               :rel="cta.external ? 'noopener' : null">{{ cta.label }}</a>
        </div>
    </div>
</template>
