<script setup>
import { useAccountStore } from '../../stores/account.js';
import { ZONES } from '../navigation.js';
import { t as translate } from '../../i18n.js';

/**
 * **Las dos pestañas de auth: entrar y crear cuenta** (`specs/auth-en-cajon.md` §3.2).
 *
 * ⚠️ **Navegan entre ZONAS, no cambian un modo interno**, y esa fue la decisión de §3.2: con una zona
 * por pantalla, el mapa `ruta → zona` de `Http\Sidebar\AccountDoor` sigue siendo plano —`/login` y
 * `/registro` llevan cada una a la suya— y no hace falta una segunda señal para decir qué pestaña
 * abrir. El paso 5 del embudo sí usa un modo interno, porque allí no hay rutas que servir.
 *
 * ⚠️⚠️ **Pero conmutan con `replace()`, no con `go()`** (2026-08-23, `DECISIONES #125`). Que sean dos
 * zonas es cómo se sirven; para quien mira son **dos caras de UNA pantalla**, y apilarlas hacía que
 * «Volver» deshiciera la pestaña en vez de salir del área: mismo armazón, misma barra, otro
 * formulario — un botón que aparentaba no hacer nada. La regla vive en `navigation.js::replace()`,
 * con su `node --test`; aquí solo se elige el verbo correcto.
 *
 * ⚠️ Reutiliza las clases del paso 5 (`zone-tabs` + `purchase__authtabs`) **a propósito**: es el mismo
 * widget y darle clases propias habría duplicado su CSS en un segundo sitio, que es justo lo que
 * `specs/area-cliente.md` §4.9 evitó al reutilizar `Shell.vue`.
 */
const props = defineProps({
    /** El grupo `account`: los rótulos de las dos pestañas. */
    account: { type: Object, default: () => ({}) },

    /** La zona activa. Decide cuál lleva `active`; las dos se emiten siempre. */
    active: { type: String, default: '' },
});

const nav = useAccountStore();

const a = (key) => translate(props.account, key);
</script>

<template>
    <div class="zone-tabs purchase__authtabs">
        <button type="button" class="zone-tab" :class="{ active: active === ZONES.LOGIN }"
                @click="nav.replace(ZONES.LOGIN)">{{ a('login.cta') }}</button>
        <button type="button" class="zone-tab" :class="{ active: active === ZONES.REGISTER }"
                @click="nav.replace(ZONES.REGISTER)">{{ a('register.cta') }}</button>
    </div>
</template>
