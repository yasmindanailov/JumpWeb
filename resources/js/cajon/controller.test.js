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
        assert.deepEqual(eventos, [{ tipo: 'jw:cajon:open', detalle: {} }]);
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

    /** El modo lo publica el MOTOR y entra por una sola puerta. */
    test('el modo entra por setMode y vuelve a «catalog» si llega vacío', () => {
        const cajon = montar();

        cajon.setMode('booking');
        assert.equal(cajon.mode, 'booking');

        cajon.setMode('');
        assert.equal(cajon.mode, 'catalog');
    });
});
