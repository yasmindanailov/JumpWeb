import { test, describe } from 'node:test';
import assert from 'node:assert/strict';
import { registerErrors, runRegister, signupRequiresCaptcha, CONTEXT_PURCHASE, CONTEXT_STANDALONE } from './register.js';

/**
 * Fase 4 · paso 4.4b·1 — la red del alta embebida (criterio CE-6).
 *
 * Lo que se fija aquí es **la secuencia y el reparto de los avisos**. Que los literales coincidan con
 * los del servidor lo comparaba `SidebarRegisterParityTest` contra `__()` real; ese fichero se retiró
 * con el modal (`DECISIONES #122`) y hoy los literales los fija `Api\V1\AuthRegistrationTest`, que es
 * quien los publica.
 *
 * ⚠️ El caso central es `el 201 no dice si hubo cuenta`: la respuesta del alta es **idéntica** para un
 * alta real y para un señuelo que actuó —si no lo fuera, un bot distinguiría las dos de un vistazo—,
 * así que el destino lo decide `GET /me`. Quitar esa segunda pregunta manda al pago a alguien que no
 * tiene sesión.
 */

const MESSAGES = { errors: { try_later: 'Demasiados intentos seguidos. Espera un minuto antes de volver a intentarlo.' } };
const AUTH = { throttle: 'Demasiados intentos. Inténtalo de nuevo en :seconds segundos.' };

const ok = (data = null, status = 200) => ({ ok: true, status, data, error: null, offline: false });
const fail = (status, error) => ({ ok: false, status, data: { error }, error, offline: false });
const offline = () => ({ ok: false, status: 0, data: null, error: null, offline: true });

const errorsOf = (response) => registerErrors(response, { messages: MESSAGES, auth: AUTH });

const FORM = {
    name: 'Mara', email: 'mara@jumpweb.test', phone: '600111222', password: 'Un4-C0ntraseña-Larga',
    accept_privacy: true, accept_terms: true, marketing: false, website: '',
};

describe('el reparto de los avisos', () => {
    test('un alta que sale bien no deja ningún aviso', () => {
        assert.deepEqual(errorsOf(ok(null, 201)), { summary: [], fields: {} });
    });

    /**
     * ⚠️ **El banner y el campo, las DOS cosas.** La web pinta todos los mensajes juntos arriba (A11y
     * de formulario largo) **y** cada uno bajo su input. Quedarse con una sola mitad rompe la paridad
     * de un árbol que el diff sí compara: el banner tiene un `<li>` por aviso.
     */
    test('la validación llena el banner Y cada campo', () => {
        const errors = errorsOf(fail(422, {
            code: 'validation_failed',
            message: 'Revisa los datos que has enviado.',
            fields: {
                name: ['El campo nombre es obligatorio.'],
                email: ['El campo email es obligatorio.'],
            },
        }));

        assert.equal(errors.fields.name, 'El campo nombre es obligatorio.');
        assert.equal(errors.fields.email, 'El campo email es obligatorio.');
        assert.deepEqual(errors.summary, ['El campo nombre es obligatorio.', 'El campo email es obligatorio.']);
    });

    /**
     * ⚠️ El ORDEN del banner es el de las REGLAS, no el del JSON. Son `<li>` hermanos y el diff los
     * cuenta en orden; que el sobre los serialice de otra forma no puede reordenar la lista.
     */
    test('el banner sigue el orden de las reglas, no el del sobre', () => {
        const errors = errorsOf(fail(422, {
            code: 'validation_failed',
            message: 'x',
            fields: { password: ['pass'], name: ['nombre'], accept_terms: ['términos'], email: ['correo'] },
        }));

        assert.deepEqual(errors.summary, ['nombre', 'correo', 'pass', 'términos']);
    });

    /** Un campo que el cliente no conoce se pinta igual: el servidor manda, y el banner lo lista. */
    test('un campo desconocido no se pierde', () => {
        const errors = errorsOf(fail(422, {
            code: 'validation_failed', message: 'x', fields: { campo_nuevo: ['algo'] },
        }));

        assert.deepEqual(errors.summary, ['algo']);
        assert.equal(errors.fields.campo_nuevo, 'algo');
    });

    /**
     * ⚠️ **El limitador va bajo el campo EMAIL, al revés que en el login.** No es un descuido de la
     * web: `Register::reportSignup()` lanza `auth.throttle` en la clave `email` y `Login` en `_global`.
     * Se transcribe como está.
     */
    test('el limitador va bajo el campo email, no en un banner suelto', () => {
        const errors = errorsOf(fail(429, { code: 'too_many_requests', message: 'x', params: { retry_after: 42 } }));

        assert.equal(errors.fields.email, 'Demasiados intentos. Inténtalo de nuevo en 42 segundos.');
        assert.deepEqual(errors.summary, ['Demasiados intentos. Inténtalo de nuevo en 42 segundos.']);
    });

    /**
     * Los literales de negocio —correo ya registrado, pendiente de verificar, anti-bot— los manda el
     * SERVIDOR en `fields.email` y se pintan tal cual. Es lo contrario que en el login, y en los dos
     * sitios es correcto: aquí el servidor publica exactamente los mismos literales que pinta el Blade.
     */
    test('el literal del servidor se pinta tal cual', () => {
        const yaExiste = 'Ese correo ya tiene cuenta. Inicia sesión.';
        const errors = errorsOf(fail(422, { code: 'validation_failed', message: 'x', fields: { email: [yaExiste] } }));

        assert.equal(errors.fields.email, yaExiste);
    });

    test('sin red se avisa con el genérico y sin culpar a ningún campo', () => {
        const errors = errorsOf(offline());

        assert.deepEqual(errors.summary, [MESSAGES.errors.try_later]);
        assert.deepEqual(errors.fields, {});
    });

    test('un 500 y un código desconocido caen en el mismo sitio', () => {
        assert.deepEqual(errorsOf(fail(500, { code: 'server_error', message: 'x' })).summary, [MESSAGES.errors.try_later]);
        assert.deepEqual(errorsOf(fail(409, { code: 'algo_nuevo', message: 'x' })).summary, [MESSAGES.errors.try_later]);
    });
});

describe('la guarda del anti-bot', () => {
    /**
     * ⚠️ Desde 4.4b·2 este bit decide **si montar el widget en el cajón**, ya no «si delegar en el
     * modal de Livewire» (esa delegación se retiró). Lo que fija sigue siendo lo mismo y sigue siendo
     * lo que importa: la equivalencia **clave publicada ⟺ anti-bot COMPLETO**. Con la clave a medias
     * se pintaría un formulario que rechaza a todo el mundo con «no eres un robot», sin correo y sin
     * una sola línea de log — `Turnstile::verify('')` corta antes del POST y antes de su `Log::warning`.
     */
    test('con clave publicada, el alta exige captcha', () => {
        assert.equal(signupRequiresCaptcha({ turnstile_site_key: '0x4AAAAAAA' }), true);
    });

    test('sin anti-bot configurado, el cajón se basta solo', () => {
        assert.equal(signupRequiresCaptcha({ turnstile_site_key: null }), false);
        assert.equal(signupRequiresCaptcha({}), false);
        assert.equal(signupRequiresCaptcha(null), false);
        assert.equal(signupRequiresCaptcha(undefined), false);
    });

    /**
     * ⚠️ La cadena VACÍA no es una clave. Un `''` publicado por error activaría la degradación en una
     * instalación sin anti-bot y mandaría al modal a todo el que quisiera crear cuenta.
     */
    test('una clave vacía no cuenta como anti-bot', () => {
        assert.equal(signupRequiresCaptcha({ turnstile_site_key: '' }), false);
    });
});

describe('el token del anti-bot en el envío', () => {
    /**
     * ⚠️ **Esta es la única red que ve este cableado.** Se intentó cubrirlo con un centinela de
     * `SidebarBundleBudgetTest` y NO discrimina: al quitar la línea del payload, `turnstile_token`
     * baja de 6 a 4 ocurrencias en el chunk —el identificador vive también en `emptyForm`, en el
     * `v-model` del paso y en el vaciado tras un fallo—, así que aquel caso seguía en verde con el
     * token sin mandarse. Medido reconstruyendo, no razonado.
     */
    function apiSpy() {
        const calls = [];

        return { calls, post: async (path, body) => { calls.push({ path, body }); return ok(null, 201); }, get: async () => ok({ id: 7 }) };
    }

    test('manda el token del anti-bot', async () => {
        const api = apiSpy();

        await runRegister({ form: { email: 'a@b.c', turnstile_token: 'tok-de-cloudflare' }, api });

        assert.equal(api.calls[0].body.turnstile_token, 'tok-de-cloudflare');
    });

    test('sin token manda cadena vacía, NUNCA null ni undefined', async () => {
        // `openapi/v1.yaml` declara `turnstile_token` como `type: string` sin `nullable`, y
        // `RegisterRequest` es `additionalProperties: false`: Spectator valida también la PETICIÓN,
        // así que un `null` haría fallar por esquema y el rojo hablaría de otra cosa.
        const api = apiSpy();

        await runRegister({ form: { email: 'a@b.c' }, api });

        assert.equal(api.calls[0].body.turnstile_token, '');
        assert.notEqual(api.calls[0].body.turnstile_token, null);
    });
});

describe('el envío', () => {
    function apiDouble({ register = ok(null, 201), me = ok({ id: 7 }) } = {}) {
        const calls = [];

        return {
            calls,
            post: async (path, body) => {
                calls.push({ method: 'POST', path, body });

                return register;
            },
            get: async (path) => {
                calls.push({ method: 'GET', path });

                return me;
            },
        };
    }

    /**
     * ⚠️ **El contexto lo decide QUIEN LLAMA, y `purchase` es lo que activa el pay-first**: sin correo
     * de verificación y con sesión abierta, porque el pago la sustituye.
     *
     * Hasta el 2026-08-23 este módulo lo mandaba QUEMADO, y este caso lo daba por bueno porque su
     * único cliente era el embudo. Con el alta también en el área de cliente, eso habría convertido el
     * alta suelta en pay-first sin que nadie lo decidiera (`specs/auth-en-cajon.md` §4.3).
     */
    test('manda el contexto que le pasan, y `purchase` es el que activa el pay-first', async () => {
        const api = apiDouble();

        await runRegister({ form: FORM, api, messages: MESSAGES, auth: AUTH, context: CONTEXT_PURCHASE });

        assert.equal(api.calls[0].path, '/auth/register');
        assert.equal(api.calls[0].body.context, 'purchase');
    });

    test('y el alta SUELTA manda el suyo, que es el que sí manda el correo de verificación', async () => {
        const api = apiDouble();

        await runRegister({ form: FORM, api, messages: MESSAGES, auth: AUTH, context: CONTEXT_STANDALONE });

        assert.equal(api.calls[0].body.context, 'standalone');
    });

    /**
     * ⚠️⚠️ **El default es el CONSERVADOR, y se eligió por seguridad.** Quien olvide pasar el contexto
     * en el embudo verá al cliente parado en «revisa tu correo» —visible, y se arregla—; el default
     * contrario habría saltado la verificación de correo **en silencio**, que es un cambio de política
     * que ninguna pantalla delata. Es además el mismo que aplica el servidor si el campo no viaja.
     */
    test('sin contexto se queda el conservador, no el pay-first', async () => {
        const api = apiDouble();

        await runRegister({ form: FORM, api, messages: MESSAGES, auth: AUTH });

        assert.equal(api.calls[0].body.context, 'standalone');
        assert.notEqual(api.calls[0].body.context, 'purchase', 'el default NUNCA puede ser el que salta la verificación');
    });

    /** El señuelo viaja SIEMPRE, vacío o no: si no viajara, el servidor no podría usarlo. */
    test('el señuelo viaja en el cuerpo', async () => {
        const api = apiDouble();

        await runRegister({ form: { ...FORM, website: 'soy-un-bot' }, api, messages: MESSAGES, auth: AUTH });

        assert.equal(api.calls[0].body.website, 'soy-un-bot');

        const limpio = apiDouble();
        await runRegister({ form: FORM, api: limpio, messages: MESSAGES, auth: AUTH });
        assert.equal(limpio.calls[0].body.website, '');
    });

    test('las dos casillas legales viajan como booleanos', async () => {
        const api = apiDouble();

        await runRegister({ form: { ...FORM, accept_privacy: true, accept_terms: false }, api, messages: MESSAGES, auth: AUTH });

        assert.equal(api.calls[0].body.accept_privacy, true);
        assert.equal(api.calls[0].body.accept_terms, false);
        assert.equal(api.calls[0].body.marketing, false);
    });

    /**
     * ⚠️ **EL caso del paso.** El 201 es idéntico para un alta real y para un señuelo que actuó, así
     * que hay que preguntar quién eres. Con sesión, la compra sigue.
     */
    test('un alta real deja sesión, y se comprueba preguntando', async () => {
        const api = apiDouble({ me: ok({ id: 42 }) });

        const result = await runRegister({ form: FORM, api, messages: MESSAGES, auth: AUTH });

        assert.deepEqual(api.calls.map((c) => c.path), ['/auth/register', '/me']);
        assert.equal(result.ok, true);
        assert.equal(result.identified, true);
        assert.equal(result.me.data.id, 42);
    });

    /** Y sin sesión, el señuelo actuó: misma respuesta del servidor, otro destino. */
    test('un señuelo devuelve el MISMO 201 y se distingue por la sesión', async () => {
        const api = apiDouble({ register: ok(null, 201), me: { ok: false, status: 401, data: null, error: null, offline: false } });

        const result = await runRegister({ form: { ...FORM, website: 'soy-un-bot' }, api, messages: MESSAGES, auth: AUTH });

        assert.equal(result.ok, true, 'el servidor finge que salió bien: es lo que hace útil al señuelo');
        assert.equal(result.identified, false, 'pero no hay sesión, así que no hubo cuenta');
    });

    /** Un fallo del alta no pregunta por la identidad: no hay nada que preguntar. */
    test('si el alta falla no se pregunta quién eres', async () => {
        const api = apiDouble({ register: fail(422, { code: 'validation_failed', message: 'x', fields: { email: ['mal'] } }) });

        const result = await runRegister({ form: FORM, api, messages: MESSAGES, auth: AUTH });

        assert.deepEqual(api.calls.map((c) => c.path), ['/auth/register']);
        assert.equal(result.ok, false);
        assert.equal(result.identified, false);
        assert.equal(result.errors.fields.email, 'mal');
    });

    /**
     * ⚠️ Un fallo de red al preguntar por la identidad **no** puede leerse como «hay sesión»: llevaría
     * al pago a alguien que no ha entrado. Ante la duda, la pantalla de «revisa tu correo».
     */
    test('si no se puede preguntar quién eres, no se da por identificado', async () => {
        const api = apiDouble({ me: offline() });

        const result = await runRegister({ form: FORM, api, messages: MESSAGES, auth: AUTH });

        assert.equal(result.identified, false);
    });
});
