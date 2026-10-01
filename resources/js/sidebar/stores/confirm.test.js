import { test, describe, beforeEach } from 'node:test';
import assert from 'node:assert/strict';
import { createPinia, setActivePinia } from 'pinia';
import { NEW_EMAIL, useConfirmStore } from './confirm.js';
import { useProfileStore } from './profile.js';

/**
 * La red del código que CONFIRMA lo sensible de Mi cuenta en el cajón (A4b de `docs/specs/acceso-con-codigo.md` §4.11,
 * `#813`): el primer toque lo pide, el segundo —o la sexta cifra— lo usa, y el «no» vuelve bajo el campo. Lo que el servidor
 * responde lo fijan `Api\V1\MeConfirmationCodeTest` y `MePendingEmailCodeTest`.
 */

const TEXTS = {
    account: { login: { code_wait: 'Espera :n segundos para pedir otro código.' } },
    messages: { errors: { try_later: 'Espera un minuto.' } },
    auth: { throttle: 'Espera :seconds segundos.' },
};

const ACCEPTED = { ok: true, status: 202, data: null, error: null, offline: false };
const TOO_SOON = { ok: false, status: 429, data: null, offline: false, error: { code: 'too_many_requests', params: { retry_after: 41 } } };

function fakeApi(respuestas = {}) {
    const llamadas = [];
    const responder = (method) => async (url, body) => {
        llamadas.push({ method, url, body });

        return respuestas[method + ' ' + url] ?? respuestas[url] ?? ACCEPTED;
    };

    return { llamadas, get: responder('GET'), post: responder('POST') };
}

const ctx = (api) => ({ api, ...TEXTS });

/** El store de un formulario (`runForm` deja ahí los errores por campo). */
const owner = (fields = {}) => ({ fields });

describe('el primer toque PIDE el código; el segundo lo USA', () => {
    beforeEach(() => setActivePinia(createPinia()));

    test('sin pedir: nada a la vista, el botón no está listo', () => {
        const c = useConfirmStore();

        assert.equal(c.isShown('close_sessions'), false);
        assert.equal(c.ready, false);
    });

    test('el primer toque pide el código de ESA acción y enseña el campo, sin hacer la acción', async () => {
        const c = useConfirmStore();
        const api = fakeApi();
        let hecha = false;

        const done = await c.act('close_sessions', owner(), async () => { hecha = true; return true; }, ctx(api));

        assert.equal(done, false);
        assert.equal(hecha, false, 'pedir el código no puede cerrar ya las sesiones');
        assert.deepEqual(api.llamadas, [{ method: 'POST', url: '/me/confirm-code', body: { action: 'close_sessions' } }]);
        assert.equal(c.isShown('close_sessions'), true);
        assert.equal(c.isShown('delete_account'), false, 'el código pedido es de una acción, no de todas');
    });

    test('con el código a medias no se hace nada: el botón espera a las seis', async () => {
        const c = useConfirmStore();
        await c.request('close_sessions', ctx(fakeApi()));
        c.code = '48291';
        let hecha = false;

        assert.equal(await c.act('close_sessions', owner(), async () => { hecha = true; return true; }, ctx(fakeApi())), false);
        assert.equal(hecha, false);
    });

    test('con las seis la hace con el código LIMPIO, y al salir bien todo vuelve a empezar', async () => {
        const c = useConfirmStore();
        await c.request('delete_account', ctx(fakeApi()));
        c.code = '482 913';
        let usado = null;

        const done = await c.act('delete_account', owner(), async (code) => { usado = code; return true; }, ctx(fakeApi()));

        assert.equal(done, true);
        assert.equal(usado, '482913', 'el espacio que pega el móvil no viaja');
        assert.equal(c.isShown('delete_account'), false);
        assert.equal(c.code, '');
    });

    test('el código que no vale: su «no» bajo el campo, el campo VACÍO y a la vista', async () => {
        const c = useConfirmStore();
        await c.request('unlink_google', ctx(fakeApi()));
        c.code = '000000';
        const form = owner({ code: ['El código no es correcto o ha caducado. Pide otro.'] });

        const done = await c.act('unlink_google', form, async () => false, ctx(fakeApi()));

        assert.equal(done, false);
        assert.equal(c.error, 'El código no es correcto o ha caducado. Pide otro.');
        assert.equal(c.code, '', 'el `CodeInput` se vacía tras un «no», como en la puerta');
        assert.equal(c.isShown('unlink_google'), true);
    });

    test('un «no» que no es del código (la red, el límite) deja lo escrito: ese código sigue valiendo', async () => {
        const c = useConfirmStore();
        await c.request('close_sessions', ctx(fakeApi()));
        c.code = '482913';

        await c.act('close_sessions', owner({}), async () => false, ctx(fakeApi()));

        assert.equal(c.code, '482913');
        assert.equal(c.error, '');
    });

    test('un intento nuevo borra el «no» del anterior antes de llamar', async () => {
        const c = useConfirmStore();
        await c.request('close_sessions', ctx(fakeApi()));
        c.code = '000000';
        await c.act('close_sessions', owner({ code: ['Mal.'] }), async () => false, ctx(fakeApi()));
        c.code = '482913';
        let durante = null;

        await c.act('close_sessions', owner(), async () => { durante = c.error; return true; }, ctx(fakeApi()));

        assert.equal(durante, '', 'el «no» viejo seguía a la vista mientras se probaba el código nuevo');
    });
});

describe('borrar la cuenta: el código y DESPUÉS la pregunta (`ask`)', () => {
    beforeEach(() => setActivePinia(createPinia()));

    test('sin el código pedido, lo pide y NO pregunta todavía', async () => {
        const c = useConfirmStore();
        const api = fakeApi();
        let preguntado = false;

        await c.ask('delete_account', () => { preguntado = true; }, ctx(api));

        assert.equal(preguntado, false);
        assert.deepEqual(api.llamadas[0].body, { action: 'delete_account' });
        assert.equal(c.isShown('delete_account'), true);
    });

    test('con el código a medias no pregunta, ni pide otro', async () => {
        const c = useConfirmStore();
        await c.request('delete_account', ctx(fakeApi()));
        c.code = '482';
        const api = fakeApi();
        let preguntado = false;

        await c.ask('delete_account', () => { preguntado = true; }, ctx(api));

        assert.equal(preguntado, false);
        assert.equal(api.llamadas.length, 0);
    });

    /** ⚠️ La sexta cifra (o el botón) abre la PREGUNTA: borrar es lo único irreversible del producto y no va solo. */
    test('con las seis, pregunta, sin pedir nada ni borrar', async () => {
        const c = useConfirmStore();
        await c.request('delete_account', ctx(fakeApi()));
        c.code = '482913';
        const api = fakeApi();
        let preguntado = false;

        await c.ask('delete_account', () => { preguntado = true; }, ctx(api));

        assert.equal(preguntado, true);
        assert.equal(api.llamadas.length, 0);
        assert.equal(c.code, '482913', 'el código escrito sobrevive a la pregunta');
    });
});

describe('pedir otro, y pedir el de OTRA acción', () => {
    beforeEach(() => setActivePinia(createPinia()));

    test('«Pedir otro código» de la misma acción: cuenta como «otro» y vacía lo escrito', async () => {
        const c = useConfirmStore();
        const api = fakeApi();
        await c.request('close_sessions', ctx(api));
        c.code = '1234';

        assert.equal(await c.again(ctx(api)), true);
        assert.equal(c.resends, 1);
        assert.equal(c.code, '');
        assert.deepEqual(api.llamadas[1].body, { action: 'close_sessions' });
    });

    /** ⚠️ Como en la puerta (`#812`): si es pronto no sale ninguno, y el que hay sigue valiendo. */
    test('antes del minuto: NO cuenta como «otro», dice cuánto esperar y deja lo escrito', async () => {
        const c = useConfirmStore();
        await c.request('close_sessions', ctx(fakeApi()));
        c.code = '4829';

        assert.equal(await c.again(ctx(fakeApi({ '/me/confirm-code': TOO_SOON }))), false);
        assert.equal(c.resends, 0);
        assert.equal(c.code, '4829');
        assert.equal(c.error, 'Espera 41 segundos para pedir otro código.');
        assert.equal(c.isShown('close_sessions'), true);
    });

    test('el tope en el PRIMER toque enseña el campo igual (hay uno recién enviado) con su espera', async () => {
        const c = useConfirmStore();

        await c.request('delete_account', ctx(fakeApi({ '/me/confirm-code': TOO_SOON })));

        assert.equal(c.isShown('delete_account'), true);
        assert.equal(c.error, 'Espera 41 segundos para pedir otro código.');
    });

    test('pedir el de otra acción la sustituye: su propio contador y el campo vacío', async () => {
        const c = useConfirmStore();
        const api = fakeApi();
        await c.request('close_sessions', ctx(api));
        await c.again(ctx(api));
        c.code = '482913';

        await c.request('unlink_google', ctx(api));

        assert.equal(c.isShown('unlink_google'), true);
        assert.equal(c.isShown('close_sessions'), false, 'el código anterior ya no vale: no puede seguir a la vista');
        assert.equal(c.resends, 0);
        assert.equal(c.code, '');
    });

    test('sin conexión: el campo sigue escondido y el aviso arriba', async () => {
        const c = useConfirmStore();

        await c.request('close_sessions', ctx(fakeApi({ '/me/confirm-code': { ok: false, status: 0, data: null, error: null, offline: true } })));

        assert.equal(c.isShown('close_sessions'), false);
        assert.equal(c.notice, 'Espera un minuto.');
    });

    test('la sesión caducó por el camino: se dice, sin campo', async () => {
        const c = useConfirmStore();

        await c.request('close_sessions', ctx(fakeApi({ '/me/confirm-code': { ok: false, status: 401, data: null, error: null, offline: false } })));

        assert.equal(c.expired, true);
        assert.equal(c.isShown('close_sessions'), false);
    });

    test('dos toques seguidos no piden dos códigos', async () => {
        const c = useConfirmStore();
        const api = fakeApi();
        c.busy = true;

        assert.equal(await c.request('close_sessions', ctx(api)), false);
        assert.equal(api.llamadas.length, 0);
    });
});

describe('el código del correo NUEVO', () => {
    beforeEach(() => setActivePinia(createPinia()));

    test('ya salió con el cambio: su campo se ve sin pedir nada', () => {
        const c = useConfirmStore();

        c.showNewEmail();

        assert.equal(c.isShown(NEW_EMAIL), true);
    });

    test('volver a enseñarlo NO borra lo que se está escribiendo', () => {
        const c = useConfirmStore();
        c.showNewEmail();
        c.code = '4829';

        c.showNewEmail();

        assert.equal(c.code, '4829');
    });

    test('nunca se pide por el endpoint de confirmar: sin su campo, el botón no hace nada', async () => {
        const c = useConfirmStore();
        const api = fakeApi();

        assert.equal(await c.act(NEW_EMAIL, owner(), async () => true, ctx(api)), false);
        assert.equal(api.llamadas.length, 0);
    });

    test('«Pedir otro código» va por su camino y relee el perfil (resella la caducidad)', async () => {
        const c = useConfirmStore();
        const api = fakeApi({ 'GET /me': { ok: true, status: 200, data: { email: 'ana@x.test', pending_email: 'nueva@x.test' }, error: null } });
        c.showNewEmail();

        assert.equal(await c.again(ctx(api)), true);
        assert.deepEqual(api.llamadas.map((l) => `${l.method} ${l.url}`), ['POST /me/pending-email/resend', 'GET /me']);
        assert.equal(useProfileStore().user.pending_email, 'nueva@x.test');
        assert.equal(c.resends, 1);
    });

    /**
     * ⚠️⚠️ **Confirmar el cambio de correo deja pendiente el NUEVO**, y «Tus datos» enseña su código (`showNewEmail`) ANTES
     * de que `act` vuelva: vaciar al salir bien lo escondería, y la persona se quedaría sin dónde escribir el segundo código.
     */
    test('salir bien NO vacía si la zona ya enseña el código de otra acción', async () => {
        const c = useConfirmStore();
        await c.request('change_email', ctx(fakeApi()));
        c.code = '482913';

        await c.act('change_email', owner(), async () => { c.showNewEmail(); return true; }, ctx(fakeApi()));

        assert.equal(c.isShown(NEW_EMAIL), true);
    });
});
