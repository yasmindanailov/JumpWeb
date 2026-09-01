import { test, describe } from 'node:test';
import assert from 'node:assert/strict';
import { shouldRefresh, watchTabReturn, MIN_GAP_MS } from './tab-return.js';

/**
 * Un `window` de mentira con su `document`: registra los oyentes y deja dispararlos a mano, para
 * poder probar el cableado sin navegador (`CE-6`).
 */
function ventana({ hidden = false } = {}) {
    const oyentes = { win: {}, doc: {} };

    const registrar = (bolsa) => ({
        addEventListener: (evento, fn) => { (bolsa[evento] ??= []).push(fn); },
    });

    return {
        oyentes,
        document: { get hidden() { return hidden; }, ...registrar(oyentes.doc) },
        ...registrar(oyentes.win),
        ocultar(valor) { hidden = valor; },
        disparar(donde, evento) { (oyentes[donde][evento] ?? []).forEach((fn) => fn()); },
    };
}

function contexto({ identified = true } = {}) {
    const llamadas = [];

    return { identified, refresh: () => llamadas.push('refresh'), llamadas };
}

describe('la decisión de releer', () => {
    const base = { identified: true, hidden: false, lastAt: 0, now: MIN_GAP_MS };

    test('relee al volver si hay titular y ha pasado el intervalo', () => {
        assert.equal(shouldRefresh(base), true);
    });

    /**
     * ⚠️ La puerta que evita convertir cada página pública en un sondeo: sin titular no hay contexto
     * que releer, y el servidor solo contestaría `null`.
     */
    test('NO relee sin titular, aunque haya pasado el intervalo', () => {
        assert.equal(shouldRefresh({ ...base, identified: false }), false);
    });

    /** Volver es hacerse VISIBLE; recibir el foco con la pestaña oculta no es volver. */
    test('NO relee si la pestaña sigue oculta', () => {
        assert.equal(shouldRefresh({ ...base, hidden: true }), false);
    });

    test('NO relee dos veces seguidas dentro del intervalo mínimo', () => {
        assert.equal(shouldRefresh({ ...base, now: MIN_GAP_MS - 1 }), false);
    });

    /** El límite es inclusivo: justo en el intervalo ya toca. */
    test('en el límite exacto del intervalo, relee', () => {
        assert.equal(shouldRefresh({ ...base, now: MIN_GAP_MS }), true);
    });
});

describe('el cableado a la pestaña', () => {
    test('relee cuando la pestaña vuelve a ser visible', () => {
        const win = ventana();
        const ctx = contexto();
        let reloj = 0;

        watchTabReturn({ context: ctx, win, now: () => reloj });

        reloj = MIN_GAP_MS;
        win.disparar('doc', 'visibilitychange');

        assert.deepEqual(ctx.llamadas, ['refresh']);
    });

    /**
     * ⚠️ Se escuchan los DOS eventos porque ninguno cubre solo todos los casos, así que el cableado
     * tiene que responder también al `focus`.
     */
    test('relee también con `focus`', () => {
        const win = ventana();
        const ctx = contexto();
        let reloj = 0;

        watchTabReturn({ context: ctx, win, now: () => reloj });

        reloj = MIN_GAP_MS;
        win.disparar('win', 'focus');

        assert.deepEqual(ctx.llamadas, ['refresh']);
    });

    /**
     * ⚠️⚠️ **El caso que justifica escuchar los dos sin duplicar**: los navegadores disparan
     * `visibilitychange` y `focus` casi a la vez al volver. Si el sello no se pusiera ANTES de pedir,
     * saldrían dos peticiones por cada vuelta.
     */
    test('los dos eventos juntos producen UNA sola relectura', () => {
        const win = ventana();
        const ctx = contexto();
        let reloj = 0;

        watchTabReturn({ context: ctx, win, now: () => reloj });

        reloj = MIN_GAP_MS;
        win.disparar('doc', 'visibilitychange');
        win.disparar('win', 'focus');

        assert.deepEqual(ctx.llamadas, ['refresh'], 'la segunda cae dentro del intervalo mínimo');
    });

    /** Nada más arrancar no hay nada que releer: el servidor acaba de sembrar el contexto. */
    test('no relee nada más cablearse', () => {
        const win = ventana();
        const ctx = contexto();

        watchTabReturn({ context: ctx, win, now: () => 0 });
        win.disparar('win', 'focus');

        assert.deepEqual(ctx.llamadas, []);
    });

    test('sin titular no pide nada por muchas vueltas que dé', () => {
        const win = ventana();
        const ctx = contexto({ identified: false });
        let reloj = 0;

        watchTabReturn({ context: ctx, win, now: () => reloj });

        for (let i = 1; i <= 5; i++) {
            reloj = i * MIN_GAP_MS * 2;
            win.disparar('win', 'focus');
        }

        assert.deepEqual(ctx.llamadas, []);
    });

    /**
     * ⚠️ **La rama que el caso puro no cubre**: `shouldRefresh` recibe `hidden`, pero quien lo LEE del
     * `document` es el cableado. Un `focus` con la pestaña todavía oculta —una ventana emergente que
     * se cierra— no es volver, y sin este caso ese `doc.hidden` no lo miraría nadie.
     */
    test('un `focus` con la pestaña aún oculta no relee', () => {
        const win = ventana({ hidden: true });
        const ctx = contexto();
        let reloj = 0;

        watchTabReturn({ context: ctx, win, now: () => reloj });

        reloj = MIN_GAP_MS * 5;
        win.disparar('win', 'focus');
        assert.deepEqual(ctx.llamadas, [], 'oculta todavía');

        win.ocultar(false);
        win.disparar('win', 'focus');
        assert.deepEqual(ctx.llamadas, ['refresh'], 'y al hacerse visible, sí');
    });

    /** Vueltas separadas en el tiempo SÍ vuelven a leer: el intervalo acota, no apaga. */
    test('dos vueltas separadas releen las dos veces', () => {
        const win = ventana();
        const ctx = contexto();
        let reloj = 0;

        watchTabReturn({ context: ctx, win, now: () => reloj });

        reloj = MIN_GAP_MS;
        win.disparar('win', 'focus');
        reloj = MIN_GAP_MS * 3;
        win.disparar('win', 'focus');

        assert.deepEqual(ctx.llamadas, ['refresh', 'refresh']);
    });
});
