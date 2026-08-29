<script setup>
import { ref } from 'vue';

/**
 * **Un campo de contraseña con su botón de mostrar/ocultar.**
 *
 * ⚠️ **Existe porque el mismo bloque estaba ESCRITO DOS VECES** —`LoginForm` y `RegisterForm`— y la
 * tanda 2 iba a añadir dos más (la contraseña actual y la nueva, en la zona de cambiarla). Cuatro
 * copias de dieciséis líneas con dos `<svg>` dentro: la clase de duplicación que nadie arregla entera
 * el día que el icono cambia.
 *
 * ⚠️⚠️ **El árbol que emite es EXACTAMENTE el de antes**, y eso no es una aspiración: lo verifican
 * `SidebarDomContractTest` y las dos paridades de auth, que comparan el árbol renderizado contra un
 * manifiesto congelado. Un nodo de más aquí las pone en rojo — que es justamente lo que hace seguro
 * este movimiento. Por eso el root sigue siendo `.pwd-input` y no hay envoltorio nuevo.
 *
 * Lo único que cambia entre los usos es el `id` y el `autocomplete`; los dos entran por prop.
 */
defineProps({
    /** El `id` del input, para que su `<label for>` siga apuntando a él. */
    id: { type: String, required: true },

    /**
     * ⚠️ `current-password` al identificarse y `new-password` al elegir una: no es cosmético — es lo
     * que hace que el gestor de contraseñas del navegador ofrezca guardar en vez de rellenar.
     */
    autocomplete: { type: String, default: 'current-password' },
});

// ⚠️ `defineModel()` DEVUELVE la variable escribible; usar la prop `modelValue` directamente en el
// `v-model` no compila —«local prop bindings are not writable»— y es el error que dio el build. No lo
// vio `npm run test:js`, que no compila componentes: por eso el build va en el gate y va ANTES.
const model = defineModel({ type: String, default: '' });

const revealed = ref(false);
</script>

<template>
    <div class="pwd-input">
        <input :id="id" v-model="model" :type="revealed ? 'text' : 'password'"
               :autocomplete="autocomplete" required>
        <button type="button" class="pwd-input__toggle" tabindex="-1" @click="revealed = ! revealed">
            <!-- `eye` y `eye-off` del sistema de diseño, copiados byte a byte
                 (`SidebarIconParityTest`). Eran Heroicons a trazo 1,7 — otra librería y otro idioma
                 dentro del mismo campo—, y **el primero de los dos llevaba sin comprobarse desde
                 que se escribió el docblock de arriba**: la guarda arrancaba en el «`<svg>`» que ese
                 comentario cita y se lo tragaba entero (`#258`). -->
            <svg v-show="! revealed" class="pwd-input__icon" width="18" height="18" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M2.6 12s3.5-6.6 9.4-6.6S21.4 12 21.4 12s-3.5 6.6-9.4 6.6S2.6 12 2.6 12z" />
                <circle cx="12" cy="12" r="1.6" fill="currentColor" stroke="none" />
            </svg>
            <svg v-show="revealed" class="pwd-input__icon" width="18" height="18" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M2.6 12s3.5-6.6 9.4-6.6S21.4 12 21.4 12s-3.5 6.6-9.4 6.6S2.6 12 2.6 12z" />
                <circle cx="12" cy="12" r="1.6" fill="currentColor" stroke="none" />
                <path d="M4.4 4.4 19.6 19.6" />
            </svg>
        </button>
    </div>
</template>
