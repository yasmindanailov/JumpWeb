/**
 * **Cerrar sesión desde el cajón** (`docs/specs/account-context-vue.md` §4.8).
 *
 * ⚠️⚠️ **No pinta un `<form>` con `@csrf`, y esa es la decisión entera de este fichero.**
 * `AuthSessionController::login()` llama a `session()->regenerate()`, que **rota el `_token`**. Hasta
 * hoy no se notaba porque el bloque era Livewire y su `@csrf` se re-renderizaba en servidor; un
 * formulario pintado por Vue leería el `<meta name="csrf-token">` de la página, que tras entrar en el
 * paso 5 del embudo **ya está caducado** → **419**. Y ninguna guarda de árbol lo vería: un `<form>`
 * con un token viejo es idéntico a uno con un token bueno.
 *
 * ▶ Por eso se pide por la API, que toma el token de la **cookie** —refrescada por la respuesta del
 * propio login— y ya reintenta el 419 por su cuenta (`api.js`).
 *
 * ⚠️ **Después hay que NAVEGAR, no basta con vaciar el estado.** El HTML de la página se pintó con
 * sesión: el nav lleva el nombre del titular, y el `data-boot` lleva su contexto y sus textos. Una
 * carga nueva es lo único que devuelve la página entera al estado de invitado. Se va a la HOME y no
 * se recarga la actual, por lo mismo que hace `LogoutController` en la web: la ruta actual puede ser
 * una PUERTA (`/mi-cuenta`), y recargarla reabriría el cajón en una zona que ya no se puede ver.
 *
 * Módulo PLANO con el `window` **por parámetro** (`CE-6`, mismo patrón que `account/after-auth.js`).
 */

import { api as httpClient } from '../api.js';

/**
 * Cierra la sesión y se lleva al cliente a la home. Devuelve si se cerró.
 *
 * ⚠️⚠️ **Solo navega si el servidor lo confirma —o si ya no había sesión (401)—**, y la asimetría es
 * deliberada. Navegar «pase lo que pase» convertiría un fallo en algo que **parece** haber
 * funcionado: el cliente aterrizaría en la home todavía identificado y sin nada que se lo diga. Es
 * literalmente la familia de `DECISIONES #117`, con el agravante de que aquí se trata de creerse
 * fuera de una sesión que sigue abierta — en un dispositivo compartido eso es un problema de
 * seguridad, no de interfaz.
 *
 * ▶ Cuando falla no se navega, se deja rastro en consola y quien llama vuelve a habilitar el botón:
 * el cliente puede pulsar otra vez, que es lo honesto.
 *
 * @param {{api?: object, urls?: {home?: string}, win?: Window}} deps
 * @returns {Promise<boolean>} `true` si la sesión quedó cerrada y se navegó
 */
export async function signOut({ api = httpClient, urls = {}, win = window } = {}) {
    let response;

    try {
        response = await api.post('/auth/logout', {});
    } catch (e) {
        // La red se cayó a mitad. No se navega: no sabemos si la sesión murió.
        console.error('[sidebar] no se pudo cerrar la sesión', e);

        return false;
    }

    // Un 401 significa que ya no había sesión que cerrar: el resultado que el cliente pidió, ya
    // conseguido. Tratarlo como fallo le dejaría pulsando un botón que nunca va a «funcionar».
    if (! response.ok && response.status !== 401) {
        console.error('[sidebar] el servidor no cerró la sesión', response.status);

        return false;
    }

    // ⚠️ Sin `urls.home` NO se compone una ruta a mano: sería quemar el enrutador de Laravel en el
    // cliente, y en una instalación con prefijo de idioma llevaría a un 404. Recargar es la
    // degradación honesta — la sesión ya está cerrada, así que el servidor repinta como invitado.
    if (typeof urls?.home === 'string' && urls.home !== '') {
        win.location.assign(urls.home);
    } else {
        win.location.reload();
    }

    return true;
}
