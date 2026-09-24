import { test, describe, beforeEach } from 'node:test';
import assert from 'node:assert/strict';
import { createCajonController } from './controller.js';

/**
 * F4 · T2 — el controlador del cajón SIN framework (`docs/specs/cajon-empaquetable.md` §4.2).
 *
 * Lo que aquí se fija es que la lógica que ERA el store de Alpine funciona sin Alpine: ni `window.Alpine`, ni
 * proxy, ni `$store`. Es la mitad de «una página ajena puede abrir el cajón»; la otra mitad —que el panel se
 * vea— es la carcasa, que llega con la T3.
 */

let eventos;
let recargas;
let llaves;

function montar({ dataset = {}, hueco = null } = {}) {
    eventos = [];
    recargas = 0;
    llaves = [];

    globalThis.document = {
        body: { dataset },
        dispatchEvent: (evento) => eventos.push({ tipo: evento.type, detalle: evento.detail }),
        getElementById: (id) => (id === 'sidecart-spa' ? hueco : null),
    };
    globalThis.window = { location: { reload: () => { recargas += 1; } } };

    return createCajonController({
        scrollLock: {
            lock: (llave) => llaves.push(`+${llave}`),
            unlock: (llave) => llaves.push(`-${llave}`),
        },
    });
}

describe('el cajón sin framework', () => {
    beforeEach(() => montar());

    test('abrir pide SU llave del cerrojo y lo anuncia a la página', () => {
        const cajon = montar();

        cajon.open();

        assert.equal(cajon.isOpen, true);
        assert.deepEqual(llaves, ['+sidecart']);
        assert.deepEqual(eventos, [{ tipo: 'jw:cajon:open', detalle: { reason: 'user', product: undefined, surface: 'cajon' } }]);
    });

    test('cerrar suelta la llave, lo anuncia y NO recarga si la sesión no cambió', () => {
        const cajon = montar();
        cajon.open();

        cajon.close();

        assert.equal(cajon.isOpen, false);
        assert.deepEqual(llaves, ['+sidecart', '-sidecart']);
        assert.deepEqual(eventos.at(-1), { tipo: 'jw:cajon:close', detalle: { reloading: false } });
        assert.equal(recargas, 0);
    });

    /** Quien entra DENTRO del cajón deja la página pintada como invitado: cerrar la recarga, y se avisa antes. */
    test('cerrar recarga la página si hubo login dentro, y el aviso sale ANTES', () => {
        const cajon = montar();
        cajon.open();
        cajon.authChanged = true;

        cajon.close();

        assert.equal(recargas, 1);
        assert.deepEqual(eventos.at(-1), { tipo: 'jw:cajon:close', detalle: { reloading: true } });
    });

    /**
     * ⚠️ La costura de intención: el motor se trae con `import()`, así que entre el clic y el montaje la
     * intención ESPERA. Si se aplicara sin adaptador, o se repitiera, el cliente acabaría en el catálogo raíz
     * (medido en staging con el adaptador de Livewire, `DECISIONES #111`).
     */
    test('una intención espera al motor, se aplica UNA vez y se consume', () => {
        const cajon = montar();
        const aplicadas = [];

        cajon.openWith({ type: 'zone', slug: 'kids' });
        assert.deepEqual(cajon.intent, { type: 'zone', slug: 'kids' }, 'sin adaptador, la intención se CONSERVA');

        cajon.useIntentAdapter((intencion) => aplicadas.push(intencion));
        cajon.flushIntent();

        assert.deepEqual(aplicadas, [{ type: 'zone', slug: 'kids' }]);
        assert.equal(cajon.intent, null);
    });

    test('el estado con el que NACE lo dice el servidor en el <body>', () => {
        const cajon = montar({ dataset: { purchaseOpen: '1', accountZone: 'orders' } });

        assert.equal(cajon.isOpen, true);
        assert.equal(cajon.accountZone, 'orders');
    });

    /**
     * T1b de la analítica (`docs/specs/analitica.md` §4.2): el cajón que NACE abierto no pasa por `open()`, así
     * que `start()` anuncia la apertura con su motivo — y el motivo lo dice lo que el servidor dejó en la página.
     */
    test('nacer abierto se anuncia con su motivo: enlace profundo, puerta de cuenta o vuelta de la pasarela', () => {
        montar({ dataset: { purchaseOpen: '1' } }).start();
        assert.deepEqual(eventos.at(-1), { tipo: 'jw:cajon:open', detalle: { reason: 'deeplink', surface: 'cajon' } });

        montar({ dataset: { purchaseOpen: '1', accountZone: 'orders' } }).start();
        assert.deepEqual(eventos.at(-1), { tipo: 'jw:cajon:open', detalle: { reason: 'door', surface: 'cajon' } });

        montar({ dataset: { purchaseOpen: '1' }, hueco: { dataset: { boot: JSON.stringify({ outcome: 'confirmed' }) } } }).start();
        assert.deepEqual(eventos.at(-1), { tipo: 'jw:cajon:open', detalle: { reason: 'return', surface: 'cajon' } });

        eventos = [];
        montar().start();
        assert.deepEqual(eventos, [], 'cerrado al nacer: nada que anunciar');
    });

    /**
     * T3e·4 (`sidebar/reanudar.js`): la compra que salió a Google y vuelve a su página nace abierta porque lo dice el
     * servidor (`?compra=reanudar`), y la analítica lo sabe distinguir de un enlace profundo. El desenlace del pago y
     * la puerta de cuenta mandan sobre ella.
     */
    test('la vuelta de Google se anuncia como `resume`; el desenlace y la puerta mandan', () => {
        montar({ dataset: { purchaseOpen: '1', purchaseResume: '1' } }).start();
        assert.deepEqual(eventos.at(-1), { tipo: 'jw:cajon:open', detalle: { reason: 'resume', surface: 'cajon' } });

        montar({ dataset: { purchaseOpen: '1', purchaseResume: '1', accountZone: 'orders' } }).start();
        assert.equal(eventos.at(-1).detalle.reason, 'door');

        montar({ dataset: { purchaseOpen: '1', purchaseResume: '1' }, hueco: { dataset: { boot: JSON.stringify({ outcome: 'failed' }) } } }).start();
        assert.equal(eventos.at(-1).detalle.reason, 'return');
    });

    test('abrir EN un producto lo lleva en el anuncio; abrir en una zona, no', () => {
        const cajon = montar();

        cajon.openWith({ type: 'product', id: 395 });
        assert.deepEqual(eventos.at(-1).detalle, { reason: 'user', product: 395, surface: 'cajon' });

        cajon.openWith({ type: 'zone', slug: 'kids' });
        assert.deepEqual(eventos.at(-1).detalle, { reason: 'user', product: undefined, surface: 'cajon' });
    });

    /**
     * **La SUPERFICIE de cada apertura** (T3e·2, `DECISIONES #682`): con la isla como carcasa, la compra se abre
     * en la isla y la cuenta en el lateral (hasta la T5). El anuncio lo dice, y es lo que oyen la carcasa del
     * lateral y la compra de la isla para saber cuál de las dos se enseña.
     */
    test('con la isla como carcasa, la compra se abre en la isla y la cuenta en el lateral', () => {
        const cajon = montar({ hueco: { dataset: { boot: JSON.stringify({ shell: 'isla' }) } } });

        cajon.openWith({ type: 'zone', slug: 'kids' });
        assert.equal(cajon.surface, 'isla');
        assert.equal(eventos.at(-1).detalle.surface, 'isla');

        cajon.openAccount({ preventDefault() {} }, 'orders');
        assert.equal(cajon.surface, 'cajon');
        assert.equal(eventos.at(-1).detalle.surface, 'cajon');

        cajon.close();
        assert.equal(cajon.surface, null, 'cerrado no está en ninguna');
    });

    test('nacer abierto con la isla: una compra (la vuelta del banco, `/entradas`) en la isla; una puerta de cuenta, en el lateral', () => {
        const isla = JSON.stringify({ shell: 'isla', outcome: 'failed' });

        const vuelta = montar({ dataset: { purchaseOpen: '1' }, hueco: { dataset: { boot: isla } } });
        vuelta.bootSpaEngine = async () => null;
        vuelta.start();
        assert.equal(vuelta.surface, 'isla');
        assert.deepEqual(eventos.at(-1).detalle, { reason: 'return', surface: 'isla' });

        const puerta = montar({ dataset: { purchaseOpen: '1', accountZone: 'orders' }, hueco: { dataset: { boot: isla } } });
        puerta.bootSpaEngine = async () => null;
        puerta.start();
        assert.equal(puerta.surface, 'cajon');
    });

    /**
     * ⚠️ En una página ajena no hay `data-boot`: la carcasa la dirá el arranque de la API. Hasta entonces se abre
     * en el lateral, pero NO se da por sabida: guardarla aquí dejaría la isla de esa instalación sin abrirse nunca.
     */
    test('sin arranque en la página, la carcasa aún no se sabe y no se da por sabida', () => {
        const cajon = montar();

        assert.equal(cajon.carcasaActual(), 'cajon');
        assert.equal(cajon.carcasa, null);
    });

    test('el motor cuenta cada paso al anfitrión y éste lo anuncia', () => {
        const cajon = montar();

        cajon.enteredStep(1, 2);

        assert.deepEqual(eventos, [{ tipo: 'jw:cajon:step', detalle: { from: 1, to: 2 } }]);
    });

    /** La zona de cuenta tiene UN consumidor y se vacía: si no, cerrar y reabrir devolvería siempre a ella. */
    test('la zona de cuenta se aplica al motor y se consume', () => {
        const cajon = montar({ dataset: { accountZone: 'orders' } });
        const vistas = [];
        const motor = { showAccount: (zona) => vistas.push(zona) };

        cajon.applyAccountZone(motor);
        cajon.applyAccountZone(motor);

        assert.deepEqual(vistas, ['orders']);
        assert.equal(cajon.accountZone, '');
    });

    test('el puente de la cabecera previene el clic, abre y guarda la zona para el motor', () => {
        const cajon = montar();
        let prevenido = false;

        cajon.openAccount({ preventDefault: () => { prevenido = true; } }, 'login');

        assert.equal(prevenido, true);
        assert.equal(cajon.isOpen, true);
        assert.equal(cajon.accountZone, 'login', 'sin motor montado, la zona ESPERA: aplicarla ahora sería aplicarla sobre nada');
    });

    test('sin hueco del motor en la página, abrir no revienta', async () => {
        const cajon = montar();

        cajon.open();

        assert.equal(await cajon.bootSpaEngine(), null);
    });

    /** El modo lo publica el MOTOR, entra por una sola puerta, y se ANUNCIA: la carcasa lo pinta en el panel. */
    test('el modo entra por setMode, vuelve a «catalog» si llega vacío, y se anuncia', () => {
        const cajon = montar();

        cajon.setMode('booking');
        assert.equal(cajon.mode, 'booking');

        cajon.setMode('');
        assert.equal(cajon.mode, 'catalog');

        assert.deepEqual(eventos, [
            { tipo: 'jw:cajon:mode', detalle: { mode: 'booking' } },
            { tipo: 'jw:cajon:mode', detalle: { mode: 'catalog' } },
        ]);
    });

    /**
     * **La compra confirmada se cuenta UNA vez** (F4 · T3b). La pantalla de confirmación se repinta —el
     * resumen llega después, el sondeo resuelve—, y una landing que contara conversiones contaría de más.
     */
    test('`purchased()` anuncia una vez por pedido, y nunca sin código', () => {
        const cajon = montar();

        cajon.purchased('');
        cajon.purchased(null);
        assert.deepEqual(eventos, [], 'sin código no hay nada que contar');

        cajon.purchased('JW-1');
        cajon.purchased('JW-1');
        cajon.purchased('JW-2');

        assert.deepEqual(eventos, [
            { tipo: 'jw:cajon:purchased', detalle: { orderCode: 'JW-1' } },
            { tipo: 'jw:cajon:purchased', detalle: { orderCode: 'JW-2' } },
        ]);
    });

    /**
     * **El cajón que NACE abierto** (`/entradas`, una puerta de cuenta, la vuelta de la pasarela): por ahí
     * `open()` no se llama NUNCA. Sin `start()`, el panel aparece con el hueco vacío y la página de detrás
     * sigue rodando bajo él — el fallo de `#59(b)`, que vivía suelto en `app.js` y solo cubría al producto.
     */
    test('`start()` solo hace algo si el cajón nace abierto: pide la llave y arranca el motor', async () => {
        const cerrado = montar();

        assert.equal(cerrado.start(), null);
        assert.deepEqual(llaves, []);

        const abierto = montar({ dataset: { purchaseOpen: '1' } });
        // ⚠️ Se ESPÍA el arranque del motor en vez de mirar lo que devuelve: sin hueco, `bootSpaEngine()`
        // devuelve `null` igual que no llamarlo, así que comprobar el valor no distingue las dos cosas — y
        // esa era la primera versión de este caso, que una mutación atravesó sin despeinarse.
        let arrancado = 0;
        abierto.bootSpaEngine = async () => { arrancado += 1; return null; };

        await abierto.start();

        assert.deepEqual(llaves, ['+sidecart'], 'sin la llave, la página de detrás sigue rodando bajo el panel');
        assert.equal(arrancado, 1, 'sin esto el cajón aparece abierto y con el hueco VACÍO (`#59(b)`)');
    });

    /** Escape llega a `close()` esté como esté el cajón: cerrar uno CERRADO no es un cierre y no se anuncia. */
    test('cerrar un cajón que ya estaba cerrado no anuncia nada', () => {
        const cajon = montar();

        cajon.close();

        assert.deepEqual(eventos, []);
    });
});
