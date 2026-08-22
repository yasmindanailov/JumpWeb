<script setup>
import { ZONES } from '../navigation.js';
import { t as translate } from '../../i18n.js';

/**
 * **El índice del área de cliente**: desde aquí se llega a todo lo demás
 * (`docs/specs/area-cliente.md` §4.2). Espeja `/mi-cuenta`, que hoy es una rejilla de tarjetas.
 *
 * ⚠️ **Hoy tiene UNA entrada, y eso no es un descuido: es el alcance.** La tanda 1 es solo lectura
 * (`DECISIONES #120(b)`); perfil, contraseña, sesiones y privacidad llegan con la tanda 2, cuando
 * existan sus endpoints — hoy no hay ninguno. Pintar aquí cuatro filas que no llevan a nada sería
 * peor que no pintarlas.
 *
 * ⚠️ Reutiliza el marcado del catálogo (`.catalog` / `.catalog__item` / `.catalog__go svg`) porque
 * **ese CSS ya existe dentro del panel y es estructural**: `.catalog__go svg` es un selector que
 * depende del tipo de elemento, no de la clase (§4.2 de `sidebar-spa.md`). Inventar clases nuevas
 * habría significado escribir CSS que replica el que ya hay.
 */
defineProps({
    /** El grupo `account` podado: de ahí salen los rótulos. */
    account: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['go']);
</script>

<template>
    <div class="catalog">
        <button type="button" class="catalog__item" @click="emit('go', ZONES.ORDERS)">
            <span class="catalog__name">{{ translate(account, 'orders.title') }}</span>
            <span class="catalog__go" aria-hidden="true">
                <svg class="arrow-ico" width="16" height="16" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"
                     aria-hidden="true" focusable="false">
                    <line x1="5" y1="12" x2="19" y2="12" />
                    <polyline points="12 5 19 12 12 19" />
                </svg>
            </span>
        </button>
    </div>
</template>
