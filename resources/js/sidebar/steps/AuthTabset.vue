<script setup>
/**
 * **La barra de «Entrar / Crear cuenta», UNA vez** (`DECISIONES #561`).
 *
 * ⚠️⚠️ **Nace porque estaba escrita DOS veces y hubo que sincronizarla a mano.** El paso 5 del embudo
 * y la barra del área de cuenta pintaban el mismo marcado en dos ficheros, y el docblock de
 * `AuthTabs.vue` ya decía que compartían clases «a propósito, es el mismo widget» — pero nada impedía
 * que divergieran. Al cambiar la pieza de `.zone-tabs` a `.tabset` hubo que tocar los dos, y ahí se ve
 * el coste: *dos copias del mismo control no divergen el día que se escriben, sino el día que alguien
 * arregla una.* ▶ De paso poda el chunk, que es lo que la norma pide antes de subir un techo.
 *
 * **No decide nada**: recibe cuál está activa y emite cuál se ha pulsado. Quién es «activa» y qué pasa
 * al pulsar son cosas distintas en sus dos sitios —el embudo cambia un modo interno, el área NAVEGA
 * entre zonas con `replace()`—, y esa diferencia se queda en quien lo monta.
 *
 * ⚠️ **`aria-pressed`, no `aria-selected`.** Esto no es un `tablist`: no hay paneles hermanos que se
 * conmuten —el formulario se sustituye entero— así que anunciarlo como pestañas ARIA le prometería al
 * lector de pantalla una navegación con flechas que no existe. Dos botones donde uno queda hundido es
 * exactamente lo que `aria-pressed` describe.
 */
defineProps({
    /** El rótulo de cada cara, ya traducido. Se emiten las dos siempre. */
    loginLabel: { type: String, default: '' },
    registerLabel: { type: String, default: '' },

    /** `'login'` o `'register'`: cuál lleva el estado activo. */
    active: { type: String, default: 'login' },
});

defineEmits(['select']);
</script>

<template>
    <!-- ❗❗ **La pestaña del SISTEMA (`.tabset`), no la de las ZONAS** (`#561`, `[DECIDIDO owner]`):
         aquí no se mira una zona del parque, se elige entre entrar y registrarse. La de zonas iba en
         mayúsculas a 13 con espaciado —un rótulo de CATEGORÍA— sobre pista blanca con borde; ésta
         levanta la activa a blanco sobre pista gris, y es la misma de `/precios`. -->
    <div class="tabset purchase__authtabs">
        <button type="button" class="tabset__tab" :class="{ 'is-active': active === 'login' }"
                :aria-pressed="active === 'login' ? 'true' : 'false'"
                @click="$emit('select', 'login')">{{ loginLabel }}</button>
        <button type="button" class="tabset__tab" :class="{ 'is-active': active === 'register' }"
                :aria-pressed="active === 'register' ? 'true' : 'false'"
                @click="$emit('select', 'register')">{{ registerLabel }}</button>
    </div>
</template>
