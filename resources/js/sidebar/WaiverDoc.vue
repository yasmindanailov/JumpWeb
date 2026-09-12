<script setup>
/**
 * **EL TEXTO DEL DESCARGO, Y LA FILA QUE LO ABRE** (`#566`, grieta 13 del canvas).
 *
 * ⚠️⚠️ **Existe porque el mismo bloque estaba escrito CINCO veces** — las dos altas (la del embudo y
 * la de Google), «Privacidad y datos», la tarjeta de un menor y el alta de un menor—, idéntico hasta
 * el `v-for`. Es el motivo por el que nacieron `PasswordInput` (`#125`) y `ConfirmInline` (`#565`):
 * *dos copias del mismo control no divergen el día que alguien las escribe, divergen el día que
 * alguien toca una* — y aquí eran cinco.
 *
 * ▶ **Qué cambia respecto a lo que había**: el `<summary>` era texto gris de 15 px con el triángulo
 * por defecto del navegador y medía **20 px de alto** contra el suelo táctil de **48** que declara el
 * producto. Ahora es la MISMA fila que «Ver más fechas» y «Leer las condiciones» —receta compartida,
 * no copiada (`#557`, `#562`)—, con el chevron del set porque **despliega AQUÍ**; la flecha se
 * reserva para lo que SALE a otra página.
 *
 * ⚠️ **El `<details>` no estrena clase a propósito**: `SidebarStyleWiringTest` exige que toda clase
 * emitida tenga regla, y aquí no hace falta ninguna — el estado abierto se lee del atributo `[open]`,
 * que es el estado REAL del elemento (una clase la lee solo el CSS, la lección de `#562`).
 */
defineProps({
    /** El documento firmable: `{ sections: [{ h, p }] }`. Sin él no se pinta nada. */
    document: { type: Object, required: true },

    /**
     * El rótulo de la fila.
     *
     * ⚠️ Llega por prop y no se resuelve aquí: el grupo `account` viaja por otra rama del payload en
     * cada llamante, y un `t()` mal apuntado devuelve CADENA VACÍA sin fallar (`#333`) — o sea una
     * fila muda. Que el texto lo ponga quien ya lo tiene evita estrenar ese hueco.
     */
    label: { type: String, required: true },
});
</script>

<template>
    <details class="form__hint">
        <summary class="cal-more legal-more">
            <span>{{ label }}</span>
            <!-- `chevron-down` del sistema de diseño, copiado byte a byte de «Ver más fechas»
                 (`SidebarIconParityTest`). Gira 180° con `[open]`. -->
            <svg class="cal-more__chev" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                 stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                <path d="M5.5 9 12 15.5 18.5 9" />
            </svg>
        </summary>
        <p v-for="(section, i) in document.sections" :key="i">
            <strong v-if="section.h">{{ section.h }}</strong> {{ section.p }}
        </p>
    </details>
</template>
