<script setup>
/**
 * **El botón de entrar con Google** (`specs/auth-con-google.md`, tanda T2).
 *
 * ⚠️ **Es un `<a>`, no un botón con JS**, y eso es el diseño entero: el flujo empieza con una
 * redirección del SERVIDOR (§6.4), así que aquí no hay nada que orquestar. Funciona sin JavaScript y
 * no hay estado que se pueda quedar a medias.
 *
 * ⚠️ **Sin `href` no se pinta nada.** La ruta la compone el servidor y **solo viaja si la instalación
 * tiene las dos claves** (`layout.blade.php`), así que su ausencia ES el interruptor: el hueco falla
 * hacia invisible, como el logotipo o el kit del cliente.
 *
 * ⚠️⚠️ **No lleva el logotipo tetracolor de Google, y es una decisión con su motivo**: el set de
 * iconos de este producto exige `currentColor` y rejilla 24 (`IconSetAnatomyTest`), y un glifo con
 * colores tecleados dentro rompería esa anatomía — que es lo que hace que la web entera se vea de un
 * solo idioma visual. La marca queda nombrada en el rótulo. Ficha en `DEUDA.md` por si el owner
 * quiere el botón oficial de Google, que exige tratar su logo como ASSET y no como icono.
 */
defineProps({
    /** La ida a Google (`route('auth.google.redirect')`). Vacío = esta instalación no lo ofrece. */
    href: { type: String, default: '' },
    /** El rótulo, del grupo `account` (`register.google_cta`). */
    label: { type: String, default: '' },
});
</script>

<template>
    <a v-if="href" class="btn btn--zone auth__google" :href="href">{{ label }}</a>
</template>
