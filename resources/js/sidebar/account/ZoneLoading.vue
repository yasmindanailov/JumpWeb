<script setup>
/**
 * **El spinner de una zona del área mientras trae sus datos por primera vez**
 * (`docs/sistemas/UI-SPINNER.md`, nivel 2).
 *
 * ⚠️⚠️ **Nace de un hueco visible**: cuatro zonas piden datos al montarse —el índice, «Mis reservas»,
 * «Tus datos» y privacidad— y hasta el 2026-08-23 **no pintaban nada mientras llegaban**. La zona
 * salía en blanco, que es lo que el principio nº 1 de ese doc prohíbe: *ninguna petición al servidor
 * deja al usuario sin respuesta visual*. Y lo peor de una zona vacía no es que no informe, es que
 * **informa mal**: se lee como «no tienes nada», que es justo lo contrario de «aún no lo sé».
 *
 * ⚠️ **Va en el FLUJO del contenido y no como velo absoluto**, a diferencia del de `Shell.vue`.
 * Aquél cubre el panel entero —incluidos el título y el botón de volver— y eso en el embudo es lo
 * que se quiere; aquí dejaría al cliente **sin salida** durante una carga lenta, que es peor que la
 * espera. Lo que está vacío es el contenido, y es el contenido lo que se rellena.
 *
 * ⚠️ **Solo en la PRIMERA carga.** Quien lo usa pregunta «estoy cargando **y** aún no tengo datos»:
 * pintarlo en cada refresco haría parpadear la pantalla en cada acción, que es exactamente lo que el
 * §1 de ese doc descarta para las acciones rápidas.
 *
 * ⚠️ Accesible por construcción (regla obligatoria del §6): el `role="status"` y el texto para lector
 * de pantalla los trae el propio componente de marca; aquí solo se le da sitio y su rótulo.
 */
defineProps({
    /** El rótulo del grupo `ui`, el mismo que usa el velo del armazón. */
    ui: { type: Object, default: () => ({}) },
});
</script>

<template>
    <p class="purchase__empty">
        <span class="jj-spinner-with-label">
            <span class="jj-spinner jj-spinner--sm" role="status">
                <span class="jj-spinner__sr">{{ ui.loading ?? '' }}</span>
            </span>
            <span class="jj-spinner-label" aria-hidden="true">{{ ui.loading ?? '' }}</span>
        </span>
    </p>
</template>
