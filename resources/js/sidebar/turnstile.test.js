import { test, describe } from 'node:test';
import assert from 'node:assert/strict';
import { mountTurnstile, TURNSTILE_SCRIPT } from './turnstile.js';

/**
 * Fase 4 · paso 4.4b·2 — la red del widget anti-bot del cajón (criterio CE-6).
 *
 * ⚠️ **El caso central es `no corta por el guard ajeno`**, y no es teórico: el modal de auth de la
 * cabecera se renderiza *eager* en toda página pública, así que cuando el cajón se abre
 * `window.__cfTurnstileLoading` **ya vale `true`**. Un módulo que lo usara para decidir «ya está
 * cargado, no hago nada» dejaría el widget del cajón sin pintar SIEMPRE, y el síntoma sería un 422
 * «no eres un robot» sin una sola línea en los logs del servidor. Este fichero es el único sitio que
 * puede ver esa diferencia: en local `window.turnstile` ya está cargado cuando abres el cajón a mano,
 * así que **el camino del sondeo no se ejercita nunca probando a ojo**.
 *
 * El segundo que importa es `guarda el widgetId`: es lo único que el motor Livewire descarta, y sin él
 * no hay `reset()`. El token de Turnstile es de un solo uso y el servidor lo quema antes de comprobar
 * si el correo ya existe, así que sin reset un usuario que se equivoca de correo queda encerrado.
 */

/** Doble mínimo de `window`, con relojes controlados a mano (nada de temporizadores reales). */
function fakeWin({ turnstile = null } = {}) {
    const timers = new Map();
    let nextId = 1;

    return {
        turnstile,
        __cfTurnstileLoading: undefined,
        console: { warn: () => {} },
        setInterval(fn) { const id = nextId++; timers.set(id, fn); return id; },
        clearInterval(id) { timers.delete(id); },
        /** Avanza el sondeo N veces. */
        tick(times = 1) { for (let i = 0; i < times; i++) [...timers.values()].forEach((fn) => fn()); },
        get liveTimers() { return timers.size; },
    };
}

function fakeDoc() {
    const appended = [];

    return { appended, createElement: () => ({}), head: { appendChild: (node) => appended.push(node) } };
}

/** Doble de la API de Cloudflare: registra las opciones con que la llaman. */
function fakeTurnstile() {
    const calls = { render: [], reset: [], remove: [] };
    let nextId = 100;

    return {
        calls,
        render(el, opts) { calls.render.push({ el, opts }); return `widget-${nextId++}`; },
        reset(id) { calls.reset.push(id); },
        remove(id) { calls.remove.push(id); },
    };
}

const EL = { tag: 'div' };

describe('la carga del script', () => {
    test('lo inyecta UNA vez y marca el guard compartido', () => {
        const win = fakeWin();
        const doc = fakeDoc();

        mountTurnstile(EL, { sitekey: 'k', onToken: () => {}, win, doc });

        assert.equal(doc.appended.length, 1);
        assert.equal(doc.appended[0].src, TURNSTILE_SCRIPT);
        assert.equal(doc.appended[0].async, true);
        assert.equal(doc.appended[0].defer, true);
        assert.equal(win.__cfTurnstileLoading, true);
    });

    test('no corta por el guard ajeno: no reinyecta, pero SIGUE sondeando hasta que aparece la API', () => {
        // El escenario real: el modal de la cabecera ya pidió `api.js` y aún no ha cargado.
        const win = fakeWin();
        win.__cfTurnstileLoading = true;
        const doc = fakeDoc();
        const api = fakeTurnstile();

        const widget = mountTurnstile(EL, { sitekey: 'k', onToken: () => {}, win, doc });

        assert.equal(doc.appended.length, 0, 'no debe reinyectar el script: ya lo pidió el otro widget');
        assert.equal(api.calls.render.length, 0);

        win.turnstile = api;   // llega la API
        win.tick();

        assert.equal(api.calls.render.length, 1, 'con el guard ajeno puesto, el widget DEBE pintarse igual');
        widget.destroy();
    });

    test('si la API ya está lista, pinta sin sondear ni tocar el script', () => {
        const win = fakeWin({ turnstile: fakeTurnstile() });
        const doc = fakeDoc();

        mountTurnstile(EL, { sitekey: 'k', onToken: () => {}, win, doc });

        assert.equal(doc.appended.length, 0);
        assert.equal(win.liveTimers, 0, 'no debe quedar ningún sondeo vivo');
        assert.equal(win.turnstile.calls.render.length, 1);
    });
});

describe('el render', () => {
    test('pasa EXACTAMENTE las cuatro opciones del motor Livewire', () => {
        const win = fakeWin({ turnstile: fakeTurnstile() });

        mountTurnstile(EL, { sitekey: 'mi-clave', onToken: () => {}, win, doc: fakeDoc() });

        const { el, opts } = win.turnstile.calls.render[0];

        assert.equal(el, EL, 'debe pintar sobre el nodo recibido, no sobre uno buscado por selector');
        assert.deepEqual(
            Object.keys(opts).sort(),
            ['callback', 'error-callback', 'expired-callback', 'sitekey'],
            'una opción de más es una diferencia de comportamiento entre motores que ninguna paridad ve',
        );
        assert.equal(opts.sitekey, 'mi-clave');
    });

    test('el token llega por el callback, y se vacía al caducar o fallar', () => {
        const win = fakeWin({ turnstile: fakeTurnstile() });
        const seen = [];

        mountTurnstile(EL, { sitekey: 'k', onToken: (t) => seen.push(t), win, doc: fakeDoc() });

        const { opts } = win.turnstile.calls.render[0];
        opts.callback('tok-1');
        opts['expired-callback']();
        opts['error-callback']();

        assert.deepEqual(seen, ['tok-1', '', '']);
    });

    test('un callback sin token emite cadena vacía, nunca null ni undefined', () => {
        // El contrato declara `turnstile_token` como `type: string` SIN `nullable`.
        const win = fakeWin({ turnstile: fakeTurnstile() });
        const seen = [];

        mountTurnstile(EL, { sitekey: 'k', onToken: (t) => seen.push(t), win, doc: fakeDoc() });
        win.turnstile.calls.render[0].opts.callback(undefined);

        assert.deepEqual(seen, ['']);
    });

    test('el sondeo no puede pintar dos veces: se para ANTES de pintar', () => {
        // ⚠️ Este caso fija el mecanismo REAL (el sondeo se detiene antes de renderizar), no la guarda
        // `widgetId !== null` de `render()`. Medido mutando el 2026-08-19: quitar esa guarda NO pone
        // nada en rojo, porque hoy es inalcanzable — y un caso que no muerde da confianza falsa. La
        // guarda se queda como cinturón por si alguien añade un tercer sitio que llame a `render()`,
        // y aquí se deja escrito que quien lo haga tendrá que traer su propio caso.
        const win = fakeWin();
        const api = fakeTurnstile();

        mountTurnstile(EL, { sitekey: 'k', onToken: () => {}, win, doc: fakeDoc() });
        win.turnstile = api;
        win.tick(5);

        assert.equal(api.calls.render.length, 1);
        assert.equal(win.liveTimers, 0, 'el sondeo debe pararse en cuanto pinta');
    });
});

describe('el sondeo que se agota', () => {
    test('avisa y fuerza el token vacío en vez de rendirse en silencio', () => {
        const win = fakeWin();
        const seen = [];
        const avisos = [];

        mountTurnstile(EL, {
            sitekey: 'k', onToken: (t) => seen.push(t), win, doc: fakeDoc(),
            warn: (m) => avisos.push(m), maxTries: 3,
        });

        win.tick(4);

        assert.deepEqual(seen, [''], 'sin esto, el fallo no deja rastro en NINGÚN sitio');
        assert.equal(avisos.length, 1);
        assert.match(avisos[0], /turnstile/i);
        assert.equal(win.liveTimers, 0, 'el sondeo agotado debe pararse');
    });
});

describe('reset y destroy', () => {
    test('reset usa el widgetId devuelto por render y vacía el token', () => {
        // Es lo que el motor Livewire NO puede hacer, porque descarta el id.
        const win = fakeWin({ turnstile: fakeTurnstile() });
        const seen = [];

        const widget = mountTurnstile(EL, { sitekey: 'k', onToken: (t) => seen.push(t), win, doc: fakeDoc() });
        widget.reset();

        assert.deepEqual(win.turnstile.calls.reset, ['widget-100']);
        assert.deepEqual(seen, ['']);
    });

    test('destroy desmonta el widget en Cloudflare y para el sondeo', () => {
        const win = fakeWin({ turnstile: fakeTurnstile() });

        const widget = mountTurnstile(EL, { sitekey: 'k', onToken: () => {}, win, doc: fakeDoc() });
        widget.destroy();

        assert.deepEqual(win.turnstile.calls.remove, ['widget-100']);
        assert.equal(win.liveTimers, 0);
    });

    test('destroy antes de que la API cargue no revienta y para el sondeo', () => {
        const win = fakeWin();

        const widget = mountTurnstile(EL, { sitekey: 'k', onToken: () => {}, win, doc: fakeDoc() });
        widget.destroy();

        assert.equal(win.liveTimers, 0);
    });
});

describe('las salidas sin efecto', () => {
    test('sin clave pública no monta nada', () => {
        // Es el caso NORMAL: el anti-bot está apagado en la mayoría de instalaciones.
        const win = fakeWin({ turnstile: fakeTurnstile() });
        const doc = fakeDoc();

        const widget = mountTurnstile(EL, { sitekey: '', onToken: () => {}, win, doc });

        assert.equal(win.turnstile.calls.render.length, 0);
        assert.equal(doc.appended.length, 0);
        assert.doesNotThrow(() => { widget.reset(); widget.destroy(); });
    });

    test('sin nodo, sin window o sin document devuelve un apaño inerte y no lanza', () => {
        // El render SSR del diff de árbol corre en Node puro: aquí no puede explotar nada.
        for (const args of [
            [null, { sitekey: 'k', win: fakeWin(), doc: fakeDoc() }],
            [EL, { sitekey: 'k', win: null, doc: fakeDoc() }],
            [EL, { sitekey: 'k', win: fakeWin(), doc: null }],
        ]) {
            const widget = mountTurnstile(args[0], { onToken: () => {}, ...args[1] });
            assert.doesNotThrow(() => { widget.reset(); widget.destroy(); });
        }
    });
});

/**
 * `DECISIONES #169` · `DEUDA.md` (Alta): en `/registro` el cajón nace abierto y `RegisterForm` se monta
 * ANTES de que `GET /config` traiga la clave. Con valores, `mountTurnstile` devolvía el apaño inerte y
 * nadie volvía a llamar: el contenedor se pintaba vacío y el servidor respondía «no eres un robot» a
 * todo el mundo. Con FUNCIONES, se espera a que existan clave y nodo — y hasta entonces no se carga el
 * script de Cloudflare, porque con el anti-bot apagado la clave no llega nunca.
 */
describe('la clave y el nodo que llegan DESPUÉS: el alta suelta de /registro', () => {
    test('espera SIN inyectar el script, y monta cuando clave y nodo existen', () => {
        const win = fakeWin();
        const doc = fakeDoc();
        const api = fakeTurnstile();
        const seen = [];
        let key = '';
        let el = null;

        mountTurnstile(() => el, { sitekey: () => key, onToken: (t) => seen.push(t), win, doc });
        win.tick(3);

        assert.equal(doc.appended.length, 0, 'sin clave no se carga el script de Cloudflare');
        assert.equal(win.liveTimers, 1, 'sigue esperando');

        key = 'k';
        el = EL; // llega /config y Vue pinta el contenedor
        win.tick(1);

        assert.equal(doc.appended.length, 1, 'ahora sí: el script, una vez');

        win.turnstile = api; // la API carga
        win.tick(1);

        assert.equal(api.calls.render.length, 1);
        assert.equal(api.calls.render[0].el, EL, 'pinta en el nodo que EXISTE ahora, no en el null de antes');
        assert.equal(api.calls.render[0].opts.sitekey, 'k');
        assert.equal(win.liveTimers, 0);
        assert.deepEqual(seen, [], 'no se emite ningún token vacío por esperar');
    });

    test('si la clave no llega nunca (anti-bot apagado) se rinde en SILENCIO: sin script, sin aviso, sin token', () => {
        const win = fakeWin();
        const doc = fakeDoc();
        const avisos = [];
        const seen = [];

        mountTurnstile(() => EL, {
            sitekey: () => '', onToken: (t) => seen.push(t), win, doc,
            warn: (m) => avisos.push(m), waitMaxTries: 3,
        });
        win.tick(5);

        assert.equal(doc.appended.length, 0);
        assert.equal(avisos.length, 0, 'no hay nada que avisar: el anti-bot puede estar apagado');
        assert.deepEqual(seen, []);
        assert.equal(win.liveTimers, 0, 'la espera también se agota');
    });

    test('la espera NO consume los intentos de carga: el sondeo agotado sigue avisando después', () => {
        const win = fakeWin();
        const avisos = [];
        const seen = [];
        let key = '';

        mountTurnstile(() => EL, {
            sitekey: () => key, onToken: (t) => seen.push(t), win, doc: fakeDoc(),
            warn: (m) => avisos.push(m), maxTries: 2, waitMaxTries: 10,
        });
        win.tick(5); // esperando la clave

        assert.equal(avisos.length, 0, 'esperar no es fallar');
        assert.equal(win.liveTimers, 1);

        key = 'k';
        win.tick(4); // inyecta y agota los 2 intentos

        assert.equal(avisos.length, 1);
        assert.deepEqual(seen, ['']);
        assert.equal(win.liveTimers, 0);
    });

    test('destroy durante la espera para el sondeo y no monta aunque la clave llegue después', () => {
        const win = fakeWin();
        const api = fakeTurnstile();
        let key = '';

        const handle = mountTurnstile(() => EL, { sitekey: () => key, onToken: () => {}, win, doc: fakeDoc() });
        handle.destroy();
        key = 'k';
        win.turnstile = api;
        win.tick(3);

        assert.equal(win.liveTimers, 0);
        assert.equal(api.calls.render.length, 0);
    });

    test('con funciones que ya resuelven se comporta como con valores: pinta sin sondear si la API está', () => {
        const win = fakeWin({ turnstile: fakeTurnstile() });
        const doc = fakeDoc();

        mountTurnstile(() => EL, { sitekey: () => 'k', onToken: () => {}, win, doc });

        assert.equal(win.turnstile.calls.render.length, 1);
        assert.equal(doc.appended.length, 0);
        assert.equal(win.liveTimers, 0);
    });
});
