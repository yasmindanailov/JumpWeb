<script setup>
/**
 * **El botón de entrar con Google** (`specs/auth-con-google.md` §21.1, tanda T5).
 *
 * ⚠️ **Es un `<a>`, no un botón con JS**, y eso es el diseño entero: el flujo empieza con una
 * redirección del SERVIDOR (§6.4), así que aquí no hay nada que orquestar. Funciona sin JavaScript y
 * no hay estado que se pueda quedar a medias.
 *
 * ⚠️ **Sin `href` no se pinta nada.** La ruta la compone el servidor y **solo viaja si la instalación
 * tiene las dos claves** (`layout.blade.php`), así que su ausencia ES el interruptor: el hueco falla
 * hacia invisible, como el logotipo o el kit del cliente.
 *
 * ⚠️⚠️ **ESTE BOTÓN SIGUE EL SISTEMA DE GOOGLE, NO EL NUESTRO** (`[DECIDIDO owner, 2026-09-02]`,
 * variante clara y píldora). Hasta hoy era un `.btn--zone` con el color de marca de la instalación y
 * el rótulo como única mención a Google. Ahora lleva su «G» a cuatro colores, su blanco, su borde y
 * su tipo de letra — y **eso es deliberado, no una incoherencia**: sus directrices prohíben
 * recolorear la marca, y un botón de un tercero que se disfraza del anfitrión es exactamente lo que
 * hace que la gente no reconozca con qué está entrando.
 *
 * ⚠️⚠️ **El logotipo es un `<img>` a un FICHERO, y hay tres razones que se refuerzan.**
 *  1. Es lo que `DEUDA.md` dejó escrito como salida: *asset por proveedor*, como `client-logo.svg`.
 *  2. **En línea sería un dibujo inventado para `SidebarIconParityTest`**, cuya lista `DRAWER_OWN`
 *     está vacía a propósito y **solo encoge**; y como componente `<x-icons.*>` rompería
 *     `IconSetAnatomyTest` (`currentColor` y rejilla 24) — que es justo lo que `#343` midió al
 *     decidir que no entraba.
 *  3. Dentro de un `<img>` un SVG es **inerte** (`#254`), y además no hay forma de recolorearlo
 *     desde el CSS: la única defensa real contra que alguien lo «adapte al tema».
 *
 * ⚠️ **`alt=""` a propósito**: el nombre accesible lo da el rótulo («Continuar con Google»). Con
 * `alt="Google"` un lector de pantalla diría *«Google, Continuar con Google»*.
 */
defineProps({
    /** La ida a Google (`route('auth.google.redirect')`). Vacío = esta instalación no lo ofrece. */
    href: { type: String, default: '' },
    /** El rótulo, del grupo `account` (`register.google_cta`). Una de las tres cadenas que su guía admite. */
    label: { type: String, default: '' },
});

/**
 * La marca, servida desde `public/`.
 *
 * ⚠️ **Ruta RAÍZ-RELATIVA y no `asset()`**: una URL absoluta saldría con el host del contenedor, que
 * es la trampa que `#286` ya pagó con el `<use>` externo.
 *
 * ⚠️⚠️ **Y va ENLAZADA (`:src`) y no como atributo literal**, aunque el valor sea constante: con
 * `src="/images/…"` Vite lo trata como un IMPORT a resolver desde la raíz del proyecto y **el build
 * SSR falla** (`UNRESOLVED_IMPORT`, medido). Un fichero de `public/` no pasa por el empaquetador.
 */
const MARK = '/images/providers/google.svg';
</script>

<template>
    <a v-if="href" class="btn auth__google" :href="href">
        <img class="auth__google-mark" :src="MARK" alt="" width="18" height="18">
        <span>{{ label }}</span>
    </a>
</template>
