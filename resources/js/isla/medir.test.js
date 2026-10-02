import { test, beforeEach } from 'node:test';
import assert from 'node:assert/strict';
import { CLAVE_EXPERIMENTO, exponerExperimento, medir, otraCarga } from './medir.js';

/**
 * Lo que la isla mide (la Z6c, el experimento B3; la forma, acordada con el SPA): la exposición, UNA vez por carga y solo
 * con variante asignada en el `<html>`; los gestos, cada vez. Todo por `JumpWeb.track`, resuelto en cada llamada.
 */
function ventana({ asignada = null, conTrack = true } = {}) {
    const llamadas = [];
    const win = {
        document: { documentElement: { getAttribute: (a) => (a === 'data-isla-experimento' ? asignada : null) } },
        JumpWeb: conTrack ? { track: (nombre, datos) => llamadas.push([nombre, datos]) } : {},
    };

    return { win, llamadas };
}

beforeEach(() => otraCarga());

test('con variante asignada, la exposición se cuenta UNA vez por carga, con la variante que le tocó', () => {
    const { win, llamadas } = ventana({ asignada: 'b3' });
    assert.equal(exponerExperimento(win), true);
    assert.deepEqual(llamadas, [['experiment_exposed', { key: CLAVE_EXPERIMENTO, variant: 'b3' }]]);
    assert.equal(exponerExperimento(win), false, 'la isla se vuelve a montar al cerrarse la compra: no cuenta dos veces');
    assert.equal(llamadas.length, 1);
    otraCarga();
    assert.equal(exponerExperimento(win), true, 'otra carga de la página, otra exposición');
});

test('sin variante asignada (sin experimento vivo, o la vista previa), no hay exposición', () => {
    const { win, llamadas } = ventana();
    assert.equal(exponerExperimento(win), false);
    assert.deepEqual(llamadas, []);
});

test('sin `JumpWeb.track` (una página sin el cajón) no cuenta, y no se da por contada: si llega después, cuenta', () => {
    const { win, llamadas } = ventana({ asignada: 'hoy', conTrack: false });
    assert.equal(exponerExperimento(win), false);
    win.JumpWeb.track = (nombre, datos) => llamadas.push([nombre, datos]);
    assert.equal(exponerExperimento(win), true);
    assert.deepEqual(llamadas, [['experiment_exposed', { key: 'isla', variant: 'hoy' }]]);
});

test('los gestos, cada vez y por el `JumpWeb.track` de ese momento (el buzón del cajón se sustituye al llegar el tracker)', () => {
    const { win, llamadas } = ventana();
    medir('isla_accion', { situacion: 'hoy', cara: 'barra', variante: 'b3' }, win);
    const delTracker = [];
    win.JumpWeb.track = (nombre, datos) => delTracker.push([nombre, datos]);
    medir('isla_panel', { panel: 'menu', variante: 'b3' }, win);
    assert.deepEqual(llamadas, [['isla_accion', { situacion: 'hoy', cara: 'barra', variante: 'b3' }]]);
    assert.deepEqual(delTracker, [['isla_panel', { panel: 'menu', variante: 'b3' }]]);
    assert.doesNotThrow(() => medir('isla_razon', {}, {}), 'sin `JumpWeb`, nada');
});
