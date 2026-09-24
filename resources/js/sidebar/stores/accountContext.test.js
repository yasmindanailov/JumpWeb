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
        delete: async (url) => {
            llamadas.push(`DELETE ${url}`);

            return respuestas[`DELETE ${url}`] ?? { ok: false, status: 500, data: null };
        },
    };
}

/** T3a·4 · el aviso de que la navegación puede vincularse a la cuenta, tal como viaja en el contexto. */
const AVISO = { text: 'Novedad: tu navegación puede vincularse a tu cuenta.', dismiss: 'Entendido' };

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

    /**
     * T3a·4 de la analítica (`specs/analitica.md` §4.3): el aviso a las cuentas que existían antes de la v3 de
     * la política viaja en el contexto mientras está pendiente, y despedirlo es un `DELETE` que el servidor
     * confirma con 204. Solo entonces desaparece del contexto: un fallo lo deja pintado, que es lo honesto.
     */
    describe('el aviso del enlace con la analítica', () => {
        test('despedirlo pide el DELETE y, confirmado, lo quita del contexto', async () => {
            const store = useAccountContextStore();
            store.seed({ ...CONTEXTO, analytics_notice: AVISO });
            const api = fakeApi({ 'DELETE /me/analytics-notice': { ok: true, status: 204, data: null } });

            assert.equal(await store.dismissAnalyticsNotice({ api }), true);

            assert.deepEqual(api.llamadas, ['DELETE /me/analytics-notice']);
            assert.equal(store.context.analytics_notice, null);
            assert.equal(store.context.first_name, 'Ada', 'el resto del contexto no se toca');
        });

        test('si el servidor no lo confirma, el aviso sigue en pie', async () => {
            const store = useAccountContextStore();
            store.seed({ ...CONTEXTO, analytics_notice: AVISO });

            assert.equal(await store.dismissAnalyticsNotice({ api: fakeApi({}) }), false); // 500

            assert.deepEqual(store.context.analytics_notice, AVISO);
        });

        test('sin aviso pendiente no se llama a nadie', async () => {
            const store = useAccountContextStore();
            store.seed({ ...CONTEXTO, analytics_notice: null });
            const api = fakeApi({ 'DELETE /me/analytics-notice': { ok: true, status: 204, data: null } });

            assert.equal(await store.dismissAnalyticsNotice({ api }), false);

            assert.deepEqual(api.llamadas, []);
        });
    });
});
