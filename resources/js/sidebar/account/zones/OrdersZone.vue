<script setup>
import { t as translate } from '../../i18n.js';

/**
 * **«Mis reservas»**: el historial de pedidos del cliente (`docs/specs/area-cliente.md` §4.2).
 * Espeja `/mi-cuenta/pedidos`, que de cara al cliente se titula así — medido en `lang/`:
 * `account.orders.title` es literalmente «Mis reservas», no «Mis pedidos».
 *
 * ⚠️ **Paso 2 de la tanda: aquí está la NAVEGACIÓN, no los datos.** La zona pinta hoy su estado
 * vacío; `GET /me/orders` entra en el paso 3, con su módulo plano y su paridad contra la API. El
 * corte es por dependencia y no por pantalla, que es la regla que ha ordenado la Fase 4 entera: un
 * fallo de navegación y uno de pintado que llegan juntos no se pueden separar.
 */
defineProps({
    /** El grupo `account` podado. */
    account: { type: Object, default: () => ({}) },
});
</script>

<template>
    <p class="purchase__empty">{{ translate(account, 'orders.empty') }}</p>
</template>
