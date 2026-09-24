import { test, describe } from 'node:test';
import assert from 'node:assert/strict';
import { landOnAccount } from './after-auth.js';
import { CLAVE_REANUDAR, VIGENCIA_MS } from '../marca-compra.js';

/**
 * La red del ATERRIZAJE tras conseguir sesión dentro del cajón (`specs/auth-en-cajon.md` §3.3).
 *
 * ⚠️ Lo que se fija aquí es que **se navega de verdad** y que la degradación sin URL no inventa una
 * ruta. El porqué —los textos del área viajan solo con sesión— está en el módulo; esto solo impide
 * que alguien lo «simplifique» a un `reload()` incondicional, que dejaría al cliente en la página en
 * la que estaba en vez de en su cuenta.
 */

/** Un `window` de mentira que apunta lo que le piden en vez de navegar. */
function fakeWindow() {
    const acciones = [];

    return {
        acciones,
        location: {
            assign: (url) => acciones.push({ tipo: 'assign', url }),
            reload: () => acciones.push({ tipo: 'reload' }),
        },
    };
}

describe('el aterrizaje', () => {
    test('navega a la PUERTA del índice que compone el servidor', () => {
        const win = fakeWindow();

        const destino = landOnAccount({ urls: { account: '/mi-cuenta' }, win });

        assert.equal(destino, '/mi-cuenta');
        assert.deepEqual(win.acciones, [{ tipo: 'assign', url: '/mi-cuenta' }]);
    });

    /**
     * ⚠️ **Es una NAVEGACIÓN, no un `reload()`.** Recargar dejaría al cliente identificado pero en la
     * página en la que estaba, sin llegar nunca a su cuenta — que es lo que §3.3 promete. La
     * diferencia no la ve ningún test de árbol, y por eso está aquí.
     */
    test('y no se conforma con recargar cuando SÍ hay a dónde ir', () => {
        const win = fakeWindow();

        landOnAccount({ urls: { account: '/mi-cuenta' }, win });

        assert.equal(win.acciones.some((a) => a.tipo === 'reload'), false);
    });

    /**
     * ⚠️ Sin URL **no se compone `/mi-cuenta` a mano**: sería quemar el enrutador de Laravel en el
     * cliente, y en una instalación con otro prefijo llevaría a un 404. Recargar es la degradación
     * honesta — la sesión ya está puesta.
     */
    test('sin la URL del servidor recarga, y NO se inventa una ruta', () => {
        const win = fakeWindow();

        const destino = landOnAccount({ urls: {}, win });

        assert.equal(destino, '');
        assert.deepEqual(win.acciones, [{ tipo: 'reload' }]);
    });

    test('una URL que no es una cadena se trata como si no estuviera', () => {
        for (const basura of [null, 42, {}, undefined]) {
            const win = fakeWindow();

            landOnAccount({ urls: { account: basura }, win });

            assert.deepEqual(win.acciones, [{ tipo: 'reload' }], `«${String(basura)}» no puede acabar en la barra de direcciones`);
        }
    });

    test('sin argumentos no revienta: cae en la recarga', () => {
        const win = fakeWindow();

        assert.equal(landOnAccount({ win }), '');
        assert.deepEqual(win.acciones, [{ tipo: 'reload' }]);
    });
});

/**
 * T3e·4 (`sidebar/reanudar.js`): la cuenta nueva con Google que salió de una compra vuelve a ELLA. Solo cuando lo
 * pide el alta con Google, y solo con una marca viva: el resto de aterrizajes sigue yendo a «Mi cuenta».
 */
describe('la vuelta a la compra tras un alta con Google', () => {
    const conMarca = (marca) => {
        const win = fakeWindow();
        const datos = new Map(marca ? [[CLAVE_REANUDAR, JSON.stringify(marca)]] : []);

        win.sessionStorage = { getItem: (k) => datos.get(k) ?? null, setItem: (k, v) => datos.set(k, v), removeItem: (k) => datos.delete(k) };

        return win;
    };

    test('con la marca viva, a la vuelta de la compra (que el servidor sirve abierta), no a la cuenta', () => {
        const win = conMarca({ vuelta: '/kids?compra=reanudar', en: Date.now() - 60_000 });

        assert.equal(landOnAccount({ urls: { account: '/mi-cuenta' }, win, reanudar: true }), '/kids?compra=reanudar');
        assert.deepEqual(win.acciones, [{ tipo: 'assign', url: '/kids?compra=reanudar' }]);
    });

    test('sin pedirlo, o con la marca caducada o ajena, a la cuenta como siempre', () => {
        const viva = { vuelta: '/kids', en: Date.now() };

        for (const [marca, reanudar] of [[viva, false], [{ vuelta: '/kids', en: Date.now() - VIGENCIA_MS - 1000 }, true], [{ vuelta: '//evil.test', en: Date.now() }, true], [null, true]]) {
            const win = conMarca(marca);

            landOnAccount({ urls: { account: '/mi-cuenta' }, win, reanudar });
            assert.deepEqual(win.acciones, [{ tipo: 'assign', url: '/mi-cuenta' }]);
        }
    });
});
