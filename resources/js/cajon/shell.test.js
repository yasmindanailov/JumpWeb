import { test, describe } from 'node:test';
import assert from 'node:assert/strict';
import { installShell, modeClass, trapTarget } from './shell.js';

/**
 * F4 · T3a — la carcasa del cajón con un solo dueño y sin framework.
 *
 * Aquí se prueba la LÓGICA con un DOM de mentira. Que la carcasa se vea abrir, que el foco entre y que Tab no
 * escape son cosas de un navegador de verdad: `scripts/sonda-cajon-apertura.mjs`.
 */

function elemento(nombre) {
    const clases = new Set();
    const oyentes = {};

    return {
        nombre,
        oyentes,
        enfocado: 0,
        offsetParent: {},
        // La marca de idempotencia de `installShell` vive aquí: en una página ajena se instala dos veces.
        dataset: {},
        classList: {
            add: (c) => clases.add(c),
            remove: (c) => clases.delete(c),
            toggle: (c, on) => (on ? clases.add(c) : clases.delete(c)),
            contains: (c) => clases.has(c),
        },
        clases: () => [...clases].sort(),
        addEventListener(tipo, fn) { (oyentes[tipo] ??= []).push(fn); },
        disparar(tipo, evento = {}) { (oyentes[tipo] ?? []).forEach((fn) => fn(evento)); },
        focus() { this.enfocado += 1; },
    };
}

function montar({ cajon } = {}) {
    const root = elemento('sidecart');
    const panel = elemento('panel');
    const telon = elemento('backdrop');
    const cierre = elemento('close');
    const enfocables = [cierre, elemento('campo'), elemento('boton')];

    root.querySelector = (sel) => ({
        '.sidecart__panel': panel, '.sidecart__backdrop': telon, '.sidecart__close': cierre,
    }[sel] ?? enfocables[0]);
    root.querySelectorAll = () => enfocables;

    const doc = elemento('document');
    doc.querySelector = (sel) => (sel === '.sidecart' ? root : null);
    doc.activeElement = null;
    doc.defaultView = { requestAnimationFrame: (fn) => fn() };

    const estado = cajon ?? { isOpen: false, mode: 'catalog', cierres: 0, close() { this.cierres += 1; this.isOpen = false; } };

    installShell(() => estado, doc);

    return { root, panel, telon, cierre, enfocables, doc, estado };
}

describe('la carcasa sin framework', () => {
    test('nace cerrada y en el modo del controlador', () => {
        const { root, panel } = montar();

        assert.deepEqual(root.clases(), []);
        assert.deepEqual(panel.clases(), ['is-catalog']);
    });

    /**
     * `/entradas`, una puerta de cuenta o la vuelta de la pasarela: el cajón NACE abierto y nadie llama a `open()`.
     *
     * ⚠️ **Y el foco entra también aquí** (`[DECIDIDO owner]`, `DECISIONES #634`): si no, quien llega a esas
     * páginas con teclado o lector de pantalla se encuentra el panel delante y el foco fuera, detrás del telón.
     */
    test('si el cajón nace abierto, la carcasa nace abierta y con el foco dentro', () => {
        const { root, cierre } = montar({ cajon: { isOpen: true, mode: 'catalog', close() {} } });

        assert.deepEqual(root.clases(), ['is-open']);
        assert.equal(cierre.enfocado, 1);
    });

    test('se abre y se cierra por los eventos del controlador, y al abrir mete el foco', () => {
        const { root, doc, cierre } = montar();

        doc.disparar('jw:cajon:open');
        assert.deepEqual(root.clases(), ['is-open']);
        assert.equal(cierre.enfocado, 1);

        doc.disparar('jw:cajon:close');
        assert.deepEqual(root.clases(), []);
    });

    /**
     * **Solo para SU superficie** (T3e·2, `DECISIONES #682`): con la isla como carcasa, una compra se abre en la
     * isla y este lateral no se mueve; si estaba enseñando la cuenta, se cierra. Sin superficie en el evento —un
     * anfitrión anterior a la T3e— se abre, que es la conducta de siempre.
     */
    test('una apertura en la isla no abre el lateral, y lo cierra si estaba abierto', () => {
        const { root, doc } = montar();

        doc.disparar('jw:cajon:open', { detail: { surface: 'isla' } });
        assert.deepEqual(root.clases(), []);

        doc.disparar('jw:cajon:open', { detail: { surface: 'cajon' } });
        assert.deepEqual(root.clases(), ['is-open']);

        doc.disparar('jw:cajon:open', { detail: { surface: 'isla' } });
        assert.deepEqual(root.clases(), [], 'de la cuenta a la compra: el lateral se cierra y abre la isla');
    });

    test('un cajón que nace abierto EN LA ISLA no pinta abierto el lateral', () => {
        const { root, cierre } = montar({ cajon: { isOpen: true, surface: 'isla', mode: 'catalog', close() {} } });

        assert.deepEqual(root.clases(), []);
        assert.equal(cierre.enfocado, 0);
    });

    /**
     * ⚠️⚠️ El puente del MODO (`DECISIONES #118`): `is-{modo}` es lo que colapsa el bloque de cuenta. Si se
     * pierde, el panel se queda en `is-catalog` para siempre y NADA falla. Y la clase anterior se RETIRA: dos
     * modos a la vez es peor que ninguno.
     */
    test('el modo que publica el motor sustituye a la clase anterior del panel', () => {
        const { panel, doc } = montar();

        doc.disparar('jw:cajon:mode', { detail: { mode: 'booking' } });
        assert.deepEqual(panel.clases(), ['is-booking']);

        doc.disparar('jw:cajon:mode', { detail: { mode: 'account' } });
        assert.deepEqual(panel.clases(), ['is-account']);
    });

    test('el telón y la × cierran por el controlador', () => {
        const { telon, cierre, estado } = montar();

        telon.disparar('click');
        cierre.disparar('click');

        assert.equal(estado.cierres, 2);
    });

    /** Escape cerraba SIEMPRE, también un cajón cerrado: soltaba una llave que no tenía y anunciaba un cierre falso. */
    test('Escape cierra solo si está abierto', () => {
        const { doc, estado } = montar();

        doc.disparar('keydown', { key: 'Escape' });
        assert.equal(estado.cierres, 0);

        estado.isOpen = true;
        doc.disparar('keydown', { key: 'Escape' });
        doc.disparar('keydown', { key: 'Enter' });
        assert.equal(estado.cierres, 1);
    });

    test('Tab no escapa del panel: del último salta al primero, y con Shift al revés', () => {
        const { root, doc, enfocables } = montar();
        const [primero, , ultimo] = enfocables;
        let prevenidos = 0;
        const tab = (shiftKey) => ({ key: 'Tab', shiftKey, preventDefault: () => { prevenidos += 1; } });

        doc.activeElement = ultimo;
        root.disparar('keydown', tab(false));
        assert.equal(primero.enfocado, 1);

        doc.activeElement = primero;
        root.disparar('keydown', tab(true));
        assert.equal(ultimo.enfocado, 1);

        doc.activeElement = enfocables[1];
        root.disparar('keydown', tab(false));
        assert.equal(prevenidos, 2, 'en mitad del panel el navegador sigue solo');
    });

    /**
     * ⚠️⚠️ Medido en navegador: justo al montar el motor, tras el botón de cerrar quedan controles CON caja y
     * `visibility: hidden`. El filtro viejo (`offsetParent`) los contaba, la trampa creía no estar en el último
     * y el foco se iba al banner de cookies. «Visible» es que se puede enfocar.
     */
    test('un control con caja pero `visibility: hidden` no cuenta: desde el último DE VERDAD se da la vuelta', () => {
        const { root, doc, enfocables } = montar();
        const [primero, medio, oculto] = enfocables;
        doc.defaultView.getComputedStyle = (el) => ({ visibility: el === oculto ? 'hidden' : 'visible' });

        // Se reinstala con esa vista: `installShell` lee `defaultView` al instalarse.
        const otroRoot = { ...root, oyentes: {}, dataset: {}, addEventListener(tipo, fn) { (this.oyentes[tipo] ??= []).push(fn); } };
        doc.querySelector = (sel) => (sel === '.sidecart' ? otroRoot : null);
        installShell(() => ({ isOpen: false, mode: 'catalog', close() {} }), doc);

        let prevenido = false;
        doc.activeElement = medio;
        otroRoot.oyentes.keydown.forEach((fn) => fn({ key: 'Tab', shiftKey: false, preventDefault: () => { prevenido = true; } }));

        assert.equal(prevenido, true, '`medio` es el último enfocable de verdad: Tab tiene que dar la vuelta');
        assert.equal(primero.enfocado, 1);
    });

    test('los enfocables OCULTOS no cuentan para el ciclo', () => {
        const items = [{ id: 1 }, { id: 2 }];

        assert.equal(trapTarget({ key: 'Tab', shiftKey: false }, items, items[1]), items[0]);
        assert.equal(trapTarget({ key: 'Tab', shiftKey: false }, [], null), null);
        assert.equal(trapTarget({ key: 'a', shiftKey: false }, items, items[1]), null);
    });

    test('sin carcasa en la página, y sin arranque con que construirla, no hace nada', () => {
        const doc = { querySelector: () => null };

        assert.equal(installShell(() => null, doc), null);
        assert.equal(modeClass(''), '');
    });
});

// ── F4 · T3b — instalar una carcasa CONSTRUIDA (la construye `standalone.js`, y allí se prueba) ─

describe('instalar una carcasa construida', () => {
    /** Un documento sin carcasa, donde se pueda colgar una y volver a buscarla. */
    function docVacio() {
        let colgada = null;
        const doc = {
            oyentes: {},
            activeElement: null,
            body: { hijos: [], append(nodo) { this.hijos.push(nodo); colgada = nodo; } },
            querySelector: (sel) => (sel === '.sidecart' ? colgada : null),
            addEventListener(tipo, fn) { (this.oyentes[tipo] ??= []).push(fn); },
            defaultView: { requestAnimationFrame: (fn) => fn(), getComputedStyle: () => ({ visibility: 'visible' }) },
        };

        return doc;
    }

    test('la cuelga del documento y la cablea', () => {
        const doc = docVacio();
        const construida = elemento('sidecart');
        const cierre = elemento('close');

        construida.querySelector = (sel) => (sel === '.sidecart__close' ? cierre : elemento('otro'));
        construida.querySelectorAll = () => [];

        const cajon = { isOpen: false, mode: 'catalog', cierres: 0, close() { this.cierres += 1; } };
        const instalada = installShell(() => cajon, doc, construida);

        assert.equal(instalada.root, construida);
        assert.deepEqual(doc.body.hijos, [construida], 'la carcasa construida se cuelga del body');
        assert.equal(construida.dataset.jwShell, 'on', 'queda marcada para no cablearse dos veces');

        cierre.disparar('click');
        assert.equal(cajon.cierres, 1, 'la × de la carcasa construida cierra el cajón');
    });

    /**
     * ⚠️⚠️ En una página ajena `installShell` se llama DOS veces —al cargar, que no encuentra nada, y al abrir,
     * que ya trae una construida—: sin la marca, cada gesto tendría dos oyentes y Escape cerraría dos veces.
     */
    test('la segunda pasada no vuelve a colgar ni a cablear', () => {
        const doc = docVacio();
        const construida = elemento('sidecart');

        construida.querySelector = () => elemento('otro');
        construida.querySelectorAll = () => [];

        installShell(() => ({ isOpen: false, mode: 'catalog', close() {} }), doc, construida);
        installShell(() => ({ isOpen: false, mode: 'catalog', close() {} }), doc, construida);

        assert.equal(doc.body.hijos.length, 1);
        assert.equal(doc.oyentes.keydown.length, 1);
    });

    test('sin carcasa que adoptar y sin una construida, no hace nada', () => {
        assert.equal(installShell(() => null, { querySelector: () => null }), null);
    });
});
