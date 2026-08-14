import { test, describe } from 'node:test';
import assert from 'node:assert/strict';
import { buildProgress, shortDate } from './progress.js';

/**
 * Fase 4 · paso 4.3·1 — la red de la banda de progreso (criterio CE-6).
 *
 * Que estos estados coincidan con los de `Purchase::bookingProgress()` lo comprueba
 * `SidebarProgressParityTest` contra el componente real; aquí se fija la conducta y, sobre todo, los
 * casos frontera que un test contra el servidor no alcanza (el huso del navegador).
 */

const MESSAGES = {
    phase_date: 'Fecha',
    phase_time: 'Hora',
    phase_extras: 'Extras',
    phase_details: 'Datos',
};

const base = {
    isPack: false,
    productName: 'Entrada 1 hora',
    date: null,
    time: null,
    messages: MESSAGES,
    locale: 'es',
};

describe('en qué pasos existe la banda', () => {
    /**
     * ⚠️ La banda es EXCLUSIVA del modo «booking». En el carrito no la hay —y el paso 4 tampoco tiene
     * «Volver» propio—, así que emitirla ahí sería un nodo de más que el diff de árbol vería.
     */
    test('solo los pasos 2 y 3 la llevan', () => {
        assert.equal(buildProgress({ ...base, step: 1 }), null);
        assert.equal(buildProgress({ ...base, step: 4 }), null);
        assert.equal(buildProgress({ ...base, step: 8 }), null);
        assert.notEqual(buildProgress({ ...base, step: 2 }), null);
        assert.notEqual(buildProgress({ ...base, step: 3 }), null);
    });
});

describe('avance dentro del flujo', () => {
    test('el paso de fecha es la fase 1 y las otras dos están por hacer', () => {
        const progress = buildProgress({ ...base, step: 2 });

        assert.equal(progress.active, 1);
        assert.equal(progress.total, 3);
        assert.deepEqual(progress.steps.map((s) => s.state), ['current', 'todo', 'todo']);
    });

    /** El progreso avanza DENTRO del paso 3: elegir hora mueve la fase activa de la 2 a la 3. */
    test('el paso de hora avanza al elegir la hora', () => {
        const sinHora = buildProgress({ ...base, step: 3, date: '2026-09-05' });
        const conHora = buildProgress({ ...base, step: 3, date: '2026-09-05', time: '10:00:00' });

        assert.equal(sinHora.active, 2);
        assert.deepEqual(sinHora.steps.map((s) => s.state), ['done', 'current', 'todo']);

        assert.equal(conHora.active, 3);
        assert.deepEqual(conHora.steps.map((s) => s.state), ['done', 'done', 'current']);
    });

    /**
     * La tercera fase cambia de nombre según el producto: «Extras» en una entrada, «Datos» en un pack
     * (que reúne los datos del cumpleaños). Es lo que el cliente espera encontrar al llegar.
     */
    test('la tercera fase se llama distinto en un pack', () => {
        assert.equal(buildProgress({ ...base, step: 2 }).steps[2].label, 'Extras');
        assert.equal(buildProgress({ ...base, step: 2, isPack: true }).steps[2].label, 'Datos');
    });
});

describe('la línea de contexto', () => {
    test('se va llenando conforme el cliente elige', () => {
        assert.equal(buildProgress({ ...base, step: 2 }).context, 'Entrada 1 hora');
        assert.equal(
            buildProgress({ ...base, step: 3, date: '2026-09-05' }).context,
            'Entrada 1 hora · Sáb 5 sept'
        );
        assert.equal(
            buildProgress({ ...base, step: 3, date: '2026-09-05', time: '10:00:00' }).context,
            'Entrada 1 hora · Sáb 5 sept · 10:00'
        );
    });

    /** Sin tramos que enseñar no puede quedar un separador suelto: el servidor los filtra igual. */
    test('los tramos vacíos no dejan separadores sueltos', () => {
        assert.equal(buildProgress({ ...base, step: 2, productName: '' }).context, '');
        assert.equal(
            buildProgress({ ...base, step: 3, productName: '', date: '2026-09-05' }).context,
            'Sáb 5 sept'
        );
    });

    /**
     * ⚠️ **El huso del navegador no puede mover la fecha.** `new Date('2026-09-05')` se interpreta
     * como medianoche UTC y al oeste de Greenwich es la víspera: el contexto diría «Vie 4 sept». Es
     * el mismo agujero que el calendario ya cerró; aquí se comprueba forzando el huso del proceso.
     *
     * El caso se ejecuta de verdad solo cuando el runner corre con `TZ` puesto —lo hace el test de
     * paridad—, pero la aserción de abajo ya fija la conducta con la fecha construida por partes.
     */
    test('la fecha se construye por PARTES, no parseando la cadena', () => {
        // Si el módulo usara `new Date('2026-09-01')`, en un huso al oeste esto sería «31 ago».
        assert.match(shortDate('2026-09-01', 'es'), /1 sept$/);
        assert.match(shortDate('2026-01-01', 'es'), /1 ene$/);
    });

    test('una fecha ilegible no rompe el contexto', () => {
        assert.equal(shortDate('', 'es'), '');
        assert.equal(shortDate('no-es-fecha', 'es'), '');
    });

    /**
     * ⚠️ Medido: con el patrón fijado por nosotros, inglés y francés coinciden EXACTAMENTE con
     * Carbon. Si alguien «limpia» los puntos de la abreviatura para acercar el español, rompe el
     * francés, que hoy está bien. El caso está aquí para que ese cambio no pase inadvertido.
     */
    test('el francés conserva los puntos de abreviatura, que es como los pinta el servidor', () => {
        assert.equal(shortDate('2026-09-05', 'fr'), 'sam. 5 sept.');
        assert.equal(shortDate('2026-09-05', 'en'), 'Sat 5 Sep');
    });
});
