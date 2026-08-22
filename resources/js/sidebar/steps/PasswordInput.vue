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
            <svg v-show="! revealed" class="pwd-input__icon" width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <path d="M2.036 12.322a1 1 0 0 1 0-.644C3.423 7.512 7.36 4.5 12 4.5s8.577 3.012 9.964 7.178a1 1 0 0 1 0 .644C20.577 16.488 16.64 19.5 12 19.5s-8.577-3.012-9.964-7.178Z"
                      stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
                <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.7" />
            </svg>
            <svg v-show="revealed" class="pwd-input__icon" width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <path d="M3 3l18 18M10.584 10.587a2 2 0 0 0 2.828 2.83M9.363 5.365A9.466 9.466 0 0 1 12 5c4.64 0 8.577 3.012 9.964 7.178a1 1 0 0 1 0 .644 9.46 9.46 0 0 1-3.07 4.385M6.61 6.61C4.547 7.97 2.999 9.984 2.036 12.178a1 1 0 0 0 0 .644C3.423 16.988 7.36 19.5 12 19.5a9.46 9.46 0 0 0 5.39-1.61"
                      stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
        </button>
    </div>
</template>
