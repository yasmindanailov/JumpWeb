import { test, describe } from 'node:test';
import assert from 'node:assert/strict';
import { signOut } from './sign-out.js';

/** El `window` va por parámetro (`CE-6`), así que se dobla con lo único que este módulo le pide. */
function fakeWin() {
    const acciones = [];

    return {
        acciones,
        location: {
            assign: (url) => acciones.push(['assign', url]),
            reload: () => acciones.push(['reload']),
        },
    };
}

function fakeApi(respuesta, { lanza = false } = {}) {
    const llamadas = [];

    return {
        llamadas,
        post: async (url, body) => {
            llamadas.push([url, body]);

            if (lanza) throw new Error('red caída');

            return respuesta;
        },
    };
}

const URLS = { home: 'https://parque.test/' };

describe('cerrar sesión desde el cajón', () => {
    /**
     * ⚠️ Se pide por la API y no con un `<form>`: el `_token` de la página está CADUCADO en cuanto
     * alguien entra en el paso 5 del embudo (`session()->regenerate()` lo rota), y `api.js` toma el
     * suyo de la cookie.
     */
    test('pide el cierre a la API y se lleva al cliente a la home', async () => {
        const api = fakeApi({ ok: true, status: 204 });
        const win = fakeWin();

        assert.equal(await signOut({ api, urls: URLS, win }), true);
        assert.deepEqual(api.llamadas, [['/auth/logout', {}]]);
        assert.deepEqual(win.acciones, [['assign', 'https://parque.test/']]);
    });

    /**
     * ⚠️ **A la HOME, no a la página actual**, por lo mismo que hace `LogoutController` en la web: la
     * ruta actual puede ser una PUERTA (`/mi-cuenta`) y recargarla reabriría el cajón en una zona que
     * ya no se puede ver.
     */
    test('sin `urls.home` recarga, pero no se inventa una ruta', async () => {
        const win = fakeWin();

        await signOut({ api: fakeApi({ ok: true, status: 204 }), urls: {}, win });

        assert.deepEqual(win.acciones, [['reload']], 'componer «/» a mano quemaría el enrutador en el cliente');
    });

    /** Un 401 es «ya no había sesión»: el resultado que se pedía, ya conseguido. */
    test('un 401 cuenta como cerrada', async () => {
        const win = fakeWin();

        assert.equal(await signOut({ api: fakeApi({ ok: false, status: 401 }), urls: URLS, win }), true);
        assert.deepEqual(win.acciones, [['assign', 'https://parque.test/']]);
    });

    /**
     * ⚠️⚠️ **Si el servidor no lo confirma, NO se navega.** Navegar «pase lo que pase» dejaría al
     * cliente en la home **todavía identificado** y creyéndose fuera: en un dispositivo compartido eso
     * es un problema de seguridad, no de interfaz. Es la familia de `DECISIONES #117` —algo que no
     * falla y no hace lo que dice— en su versión cara.
     */
    test('un fallo del servidor NO navega: nadie debe creerse fuera sin estarlo', async () => {
        const win = fakeWin();

        assert.equal(await signOut({ api: fakeApi({ ok: false, status: 500 }), urls: URLS, win }), false);
        assert.deepEqual(win.acciones, [], 'no se navega: no sabemos si la sesión murió');
    });

    test('la red caída tampoco navega, y no lanza hacia fuera', async () => {
        const win = fakeWin();

        assert.equal(await signOut({ api: fakeApi(null, { lanza: true }), urls: URLS, win }), false);
        assert.deepEqual(win.acciones, []);
    });
});
