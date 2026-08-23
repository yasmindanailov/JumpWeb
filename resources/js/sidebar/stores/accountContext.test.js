import { test, describe, beforeEach } from 'node:test';
import assert from 'node:assert/strict';
import { createPinia, setActivePinia } from 'pinia';
import { useAccountContextStore } from './accountContext.js';

function fakeApi(respuestas) {
    const llamadas = [];

    return {
        llamadas,
        get: async (url) => {
            llamadas.push(url);

            return respuestas[url] ?? { ok: false, status: 500, data: null };
        },
    };
}

const CONTEXTO = {
    first_name: 'Ada',
    upcoming_count: 2,
    next_reservation: { date: '2026-09-05', date_label: 'Sáb. 5 sep.', time_window: null, product_name: 'Cumple' },
    pending_forms: [],
    pending_forms_count: 0,
};

describe('el contexto de cuenta', () => {
    beforeEach(() => setActivePinia(createPinia()));

    test('nace sin titular', () => {
        const store = useAccountContextStore();

        assert.equal(store.context, null);
        assert.equal(store.identified, false);
    });

    test('la semilla del montaje lo deja pintado sin pedir nada', () => {
        const store = useAccountContextStore();

        store.seed(CONTEXTO);

        assert.equal(store.identified, true);
        assert.equal(store.context.first_name, 'Ada');
    });

    /**
     * ⚠️ El montaje anónimo lleva la clave con `null` **a propósito** para que este store no tenga que
     * distinguir «no está» de «está vacío». Aun así se acepta `undefined`: un montaje sin la clave
     * degrada a «sin sesión», que es la lectura honesta.
     */
    test('sembrar nada es sembrar «sin sesión»', () => {
        const store = useAccountContextStore();

        store.seed(null);
        assert.equal(store.identified, false);

        store.seed(undefined);
        assert.equal(store.identified, false);
    });

    test('el refresco pregunta al servidor y publica lo que le diga', async () => {
        const store = useAccountContextStore();
        const api = fakeApi({ '/me/account-context': { ok: true, status: 200, data: CONTEXTO } });

        await store.refresh({ api });

        assert.deepEqual(api.llamadas, ['/me/account-context']);
        assert.equal(store.identified, true);
        assert.equal(store.context.upcoming_count, 2);
    });

    /**
     * ⚠️⚠️ **El caso que justifica el endpoint entero**: quien entra en el paso 5 del embudo **no
     * recarga**, así que su semilla es la del invitado (`null`). Sin refresco, el bloque seguiría
     * ofreciendo «Entrar» a alguien que acaba de entrar.
     */
    test('tras entrar sin recargar, el refresco convierte al invitado en titular', async () => {
        const store = useAccountContextStore();
        store.seed(null);

        assert.equal(store.identified, false, 'la semilla de quien no había entrado');

        await store.refresh({ api: fakeApi({ '/me/account-context': { ok: true, status: 200, data: CONTEXTO } }) });

        assert.equal(store.identified, true);
        assert.equal(store.context.first_name, 'Ada');
    });

    /**
     * ⚠️ **Un fallo NO se anuncia y NO borra lo que había.** Es cortesía de interfaz, no el contenido
     * de una pantalla: el servidor hace lo mismo —`CustomerAccountContext` envuelve su carga en un
     * `try`— porque romper el panel por no poder saludar con la próxima reserva sería un mal negocio.
     */
    test('un fallo del servidor deja el contexto anterior en pie y no lanza', async () => {
        const store = useAccountContextStore();
        store.seed(CONTEXTO);

        await store.refresh({ api: fakeApi({}) }); // 500

        assert.equal(store.identified, true, 'lo que ya se pintaba sigue en pie');
        assert.equal(store.context.first_name, 'Ada');
    });

    /**
     * ⚠️⚠️ **Pero un 401 SÍ lo vacía**, y la asimetría con el 500 es deliberada. Un 500 es «no he
     * podido preguntar»; un 401 es «ya no hay sesión» —la que caducó, o la que otro cerró en un
     * dispositivo compartido—. Seguir saludando por el nombre del anterior sería peor que no saludar.
     */
    test('un 401 vacía el contexto: la sesión ya no existe', async () => {
        const store = useAccountContextStore();
        store.seed(CONTEXTO);

        await store.refresh({
            api: fakeApi({ '/me/account-context': { ok: false, status: 401, data: null } }),
        });

        assert.equal(store.identified, false);
        assert.equal(store.context, null);
    });

    /** Dos refrescos a la vez no se pisan: el segundo sale por la guarda de reentrada. */
    test('no se solapa consigo mismo', async () => {
        const store = useAccountContextStore();
        const api = fakeApi({ '/me/account-context': { ok: true, status: 200, data: CONTEXTO } });

        await Promise.all([store.refresh({ api }), store.refresh({ api })]);

        assert.equal(api.llamadas.length, 1, 'la segunda llamada tiene que salir por la guarda');
    });
});
