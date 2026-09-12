<script setup>
import { useAccountStore } from '../../stores/account.js';
import { ZONES } from '../navigation.js';
import { t as translate } from '../../i18n.js';
import AuthTabset from '../../steps/AuthTabset.vue';

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
    <!-- ⚠️ El marcado vive en `steps/AuthTabset.vue`, UNA vez (`#561`): estaba escrito aquí y en el
         paso 5, y al cambiar la pieza hubo que tocar los dos a mano. Lo que esta capa añade es lo
         suyo —que conmutar sea NAVEGAR entre zonas con `replace()`—, que es justo lo que el embudo no
         hace. -->
    <AuthTabset :active="active === ZONES.REGISTER ? 'register' : 'login'"
                :login-label="a('login.cta')" :register-label="a('register.cta')"
                @select="nav.replace($event === 'register' ? ZONES.REGISTER : ZONES.LOGIN)" />
</template>
