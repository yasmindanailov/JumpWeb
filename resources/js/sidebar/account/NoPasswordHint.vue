<script setup>
import { useAccountStore } from '../stores/account.js';
import { ZONES } from './navigation.js';
import { t as translate } from '../i18n.js';

/**
 * **La salida para quien entró con Google y no tiene contraseña** (`specs/auth-con-google.md` §8,
 * `[DECIDIDO owner, 2026-09-02]`).
 *
 * ⚠️⚠️ **Cierra un obstáculo del art. 12.2, y el owner eligió esta forma sobre el ticket de
 * re-autenticación de la spec**: cuatro acciones exigen la contraseña —cambiarla, cerrar las demás
 * sesiones, cambiar el correo y borrar la cuenta— y una cuenta nacida con Google no tiene ninguna.
 * Como esa persona **ya controla su buzón verificado**, crear una contraseña es un paso, no un muro:
 * lo que faltaba no era un camino nuevo de autenticación, era **decírselo donde se topa con la
 * pared**.
 *
 * ⚠️ **Se pinta SIEMPRE, no solo a quien no tiene contraseña**, y es deliberado: el servidor no puede
 * distinguir un hash aleatorio de uno elegido, así que detectarlo exigiría una columna nueva y
 * mantenerla en los siete sitios que escriben contraseñas. Para quien sí la tiene la frase sigue
 * siendo verdad y útil —también se olvidan contraseñas—; para quien no, es la única puerta.
 * ▶ La alternativa medida queda en `DEUDA.md` por si algún día compensa.
 *
 * ⚠️ **El rótulo del botón se REUTILIZA** (`forgot.title`), que ya viaja en TODAS las páginas por ser
 * texto de invitado: el rótulo más barato es el que ya está en el payload.
 */
defineProps({
    /** El grupo `account`, del que salen la frase y el rótulo del botón. */
    account: { type: Object, default: () => ({}) },
});

const nav = useAccountStore();
</script>

<template>
    <p class="form__hint">
        {{ translate(account, 'account.no_password') }}
        <button type="button" @click="nav.go(ZONES.FORGOT)">{{ translate(account, 'forgot.title') }}</button>
    </p>
</template>
