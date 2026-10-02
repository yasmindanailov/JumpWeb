import { test, mock } from 'node:test';
import assert from 'node:assert/strict';
import { effectScope } from 'vue';
import { CLAVE, EVENTO, VIGENCIA_MS, anunciar, dejar, loQueDeja, tomar } from './compra-cerrada.js';
import { UMBRAL_PREPARANDO, useCompraCerrada } from './useCompraCerrada.js';

/**
 * `#867` — lo que la compra deja al cerrarse y la isla de la página lo dice: «Sigue con tu reserva», «¡Reservado!» y
 * «Preparando tu reserva». El almacén de la pestaña y la ventana son de mentira; los relojes, los falsos de node.
 */
function almacen({ rota = false } = {}) {
    const datos = new Map();
    const lanza = () => { throw new Error('SecurityError'); };

    return {
        datos,
        getItem: (k) => (rota ? lanza() : (datos.has(k) ? datos.get(k) : null)),
        setItem: (k, v) => (rota ? lanza() : datos.set(k, String(v))),
        removeItem: (k) => (rota ? lanza() : datos.delete(k)),
    };
}

function ventana({ ruta = '/kids', pestana = almacen() } = {}) {
    const oyentes = new Map();

    return {
        sessionStorage: pestana,
        location: { pathname: ruta },
        addEventListener: (tipo, fn) => oyentes.set(tipo, fn),
        removeEventListener: (tipo) => oyentes.delete(tipo),
        dispatchEvent: (ev) => { oyentes.get(ev.type)?.(ev); return true; },
        CustomEvent: class { constructor(type, init = {}) { this.type = type; this.detail = init.detail; } },
        setTimeout: (fn, ms) => setTimeout(fn, ms),
        clearTimeout: (id) => clearTimeout(id),
        oyentes,
    };
}

const AHORA = 1_000_000;
const pedido = { n: 2 };

test('lo que deja: a medias en «Tus datos», «Pagar» o la hora perdida, con pedido; hecha en «Listo»; si no, nada', () => {
    for (const paso of ['datos', 'pagar', 'perdida']) {
        assert.deepEqual(loQueDeja({ paso, pedido, linea: 'Kids · sáb 4, 17:00' }), { estado: 'a-medias', linea: 'Kids · sáb 4, 17:00' }, paso);
    }
    assert.equal(loQueDeja({ paso: 'pagar', pedido: null, linea: 'x' }), null, 'sin pedido no hay nada a medias');
    assert.deepEqual(loQueDeja({ paso: 'datos', pedido, linea: null }), { estado: 'a-medias', linea: '' });
    assert.deepEqual(loQueDeja({ paso: 'listo', pedido: null, fiesta: true }), { estado: 'hecho', fiesta: true }, 'tras la vuelta del banco no hay pedido de la isla');
    assert.deepEqual(loQueDeja({ paso: 'listo', pedido }), { estado: 'hecho', fiesta: false });
    for (const paso of ['cuando', 'banco', 'fallido', 'verificando']) assert.equal(loQueDeja({ paso, pedido }), null, paso);
});

test('se deja en la pestaña para UNA lectura, solo en su página y dos minutos', () => {
    const pestana = almacen();
    const hecho = { estado: 'hecho', fiesta: true };

    assert.equal(dejar(pestana, hecho, { ruta: '/kids', ahora: AHORA }), true);
    assert.deepEqual(tomar(pestana, { ruta: '/kids', ahora: AHORA + VIGENCIA_MS }), hecho);
    assert.equal(tomar(pestana, { ruta: '/kids', ahora: AHORA }), null, 'tomado una vez, ya no está');

    dejar(pestana, hecho, { ruta: '/kids', ahora: AHORA });
    assert.equal(tomar(pestana, { ruta: '/cumpleanos', ahora: AHORA }), null, 'en otra página no vale');
    assert.equal(pestana.datos.has(CLAVE), false, 'y se borra igual');

    dejar(pestana, hecho, { ruta: '/kids', ahora: AHORA });
    assert.equal(tomar(pestana, { ruta: '/kids', ahora: AHORA + VIGENCIA_MS + 1 }), null, 'caducado');
    dejar(pestana, hecho, { ruta: '/kids', ahora: AHORA });
    assert.equal(tomar(pestana, { ruta: '/kids', ahora: AHORA - 1 }), null, 'del futuro, tampoco');
});

test('a medias lleva su vuelta, y una que no sea del sitio no vale', () => {
    const pestana = almacen();
    const lo = { estado: 'a-medias', linea: 'Kids · sáb 4, 17:00' };

    dejar(pestana, lo, { ruta: '/kids', ahora: AHORA, vuelta: '/kids?compra=reanudar' });
    assert.deepEqual(tomar(pestana, { ruta: '/kids', ahora: AHORA }), { ...lo, vuelta: '/kids?compra=reanudar' });
    dejar(pestana, lo, { ruta: '/kids', ahora: AHORA, vuelta: '//otro.example/kids' });
    assert.equal(tomar(pestana, { ruta: '/kids', ahora: AHORA }).vuelta, null, 'la isla navega a ella: nunca fuera del sitio');
    pestana.setItem(CLAVE, JSON.stringify({ estado: 'otra-cosa', ruta: '/kids', en: AHORA }));
    assert.equal(tomar(pestana, { ruta: '/kids', ahora: AHORA }), null, 'solo lo que la compra puede dejar');
    pestana.setItem(CLAVE, '{roto');
    assert.equal(tomar(pestana, { ruta: '/kids', ahora: AHORA }), null);
});

test('sin almacén, o con el que lanza (cookies bloqueadas), ni se deja ni se toma, y nada revienta', () => {
    assert.equal(dejar(null, { estado: 'hecho' }, { ruta: '/kids', ahora: AHORA }), false);
    assert.equal(dejar(almacen({ rota: true }), { estado: 'hecho' }, { ruta: '/kids', ahora: AHORA }), false);
    assert.equal(dejar(almacen(), null, { ruta: '/kids', ahora: AHORA }), false, 'sin nada que dejar');
    assert.equal(tomar(almacen({ rota: true }), { ruta: '/kids', ahora: AHORA }), null);
    assert.equal(tomar(null, { ruta: '/kids', ahora: AHORA }), null);
});

function montar({ ruta = '/kids', pestana = almacen() } = {}) {
    const win = ventana({ ruta, pestana });
    const scope = effectScope();
    const c = scope.run(() => useCompraCerrada({ win, ahora: () => AHORA }));

    return { c, win, scope };
}

test('la isla oye lo que deja la compra al cerrarse, y reabrirla se lo lleva; abrir Mi cuenta, no', () => {
    const { c, win, scope } = montar();
    const lo = { estado: 'a-medias', linea: 'Kids · sáb 4, 17:00' };

    anunciar(lo, win);
    assert.deepEqual(c.deja.value, lo);
    c.alAbrir({ cuenta: true, enLaIsla: true });
    assert.deepEqual(c.deja.value, lo, 'Mi cuenta no es la compra');
    c.alAbrir({ enLaIsla: true });
    assert.equal(c.deja.value, null, 'la compra, al cerrarse, lo dirá otra vez');
    anunciar({ estado: 'hecho', fiesta: false }, win);
    c.gastar();
    assert.equal(c.deja.value, null, 'lo hecho se gasta al tocarlo');
    anunciar(null, win);
    assert.equal(c.deja.value, null);
    scope.stop();
    assert.equal(win.oyentes.has(EVENTO), false, 'al irse, deja de oír');
});

test('lo dejado antes de una recarga se toma al nacer la isla, una sola vez', () => {
    const pestana = almacen();
    dejar(pestana, { estado: 'hecho', fiesta: true }, { ruta: '/kids', ahora: AHORA });
    const { c, scope } = montar({ pestana });
    assert.deepEqual(c.deja.value, { estado: 'hecho', fiesta: true });
    scope.stop();
    const otra = montar({ pestana });
    assert.equal(otra.c.deja.value, null, 'la siguiente carga de la página ya no lo encuentra');
    otra.scope.stop();
});

test('«Preparando tu reserva» solo si la compra en la isla tarda más del umbral; nunca con el cajón lateral ni Mi cuenta', () => {
    mock.timers.enable({ apis: ['setTimeout'] });
    try {
        const { c, scope } = montar();
        c.alAbrir({ enLaIsla: true });
        mock.timers.tick(UMBRAL_PREPARANDO - 1);
        assert.equal(c.preparando.value, false, 'rápida, no se ve');
        mock.timers.tick(1);
        assert.equal(c.preparando.value, true);
        c.listo();
        assert.equal(c.preparando.value, false, 'la relevó su capa (o se cerró)');

        c.alAbrir({ enLaIsla: true });
        mock.timers.tick(UMBRAL_PREPARANDO - 1);
        c.listo();
        mock.timers.tick(10_000);
        assert.equal(c.preparando.value, false, 'relevada a tiempo, el reloj se va con ella');

        c.alAbrir({ enLaIsla: false });
        c.alAbrir({ cuenta: true, enLaIsla: true });
        mock.timers.tick(10_000);
        assert.equal(c.preparando.value, false);

        c.alAbrir({ enLaIsla: true });
        scope.stop();
        mock.timers.tick(10_000);
        assert.equal(c.preparando.value, false, 'al irse la isla, su reloj también');
    } finally {
        mock.timers.reset();
    }
});
