import { test } from 'node:test';
import assert from 'node:assert/strict';
import { altoEnReposo, estiloIsla, estiloMedida, estiloRaiz, publicaAlto, tamano, transicionIsla } from './forma.js';

const caja = (w, h, cap = 0) => ({ w, h, cap });

test('la isla no anima hasta la primera medida: el primer tamaño no es una transición desde cero', () => {
    const antes = estiloIsla({ row: true, box: caja(0, 0), alert: false, grown: false, animate: false, calm: false });
    assert.equal(antes.transition, 'none');
    assert.equal(antes.width, 'max-content');
    assert.equal(antes.height, 'auto');

    const medida = estiloIsla({ row: true, box: caja(312.5, 64), alert: false, grown: false, animate: true, calm: false });
    assert.equal(medida.width, '314.5px', 'lo medido más el borde (1px por lado): con `border-box`, sin él se recortaban 2px');
    assert.equal(medida.height, '66px');
    assert.match(medida.transition, /^width var\(--dur-island\) var\(--ease-island\)/, 'el morph de la isla');
});

test('el aire de dentro, igual en los cuatro lados (Z6b, el owner): 7px del medidor y 1 de borde, fuera de lo medido', () => {
    const medidor = estiloMedida({ row: true, top: true, isOpen: false, cap: 1200, maxWidth: 760 });
    assert.equal(medidor.padding, '7px');
    // La caja de fuera mide lo de siempre: el contenido, 7 + 7 de aire y 1 + 1 de borde (antes, 8 + 8 y el borde DENTRO).
    const contenido = 300;
    const isla = estiloIsla({ row: true, box: caja(contenido + 14, 46 + 14), alert: false, grown: false, animate: true, calm: false });
    assert.deepEqual([isla.width, isla.height], [`${contenido + 16}px`, `${46 + 16}px`]);
});

test('la capa grande va sin rebote, y una isla en columna ocupa el ancho entero', () => {
    const capa = estiloIsla({ row: false, box: caja(300, 400), alert: false, grown: true, animate: true, calm: true, inCheckout: true });
    assert.equal(capa.width, '100%');
    assert.equal(capa.borderRadius, 'var(--r-lg)');
    assert.doesNotMatch(capa.transition, /--ease-island/);
});

test('es cristal (Z6a): `--surface-glass-ink-float` en reposo y con un panel; solo la capa grande va en tinta', () => {
    const base = { row: true, box: caja(300, 64), alert: false, grown: false, animate: true, calm: false };
    assert.equal(estiloIsla(base).background, 'var(--surface-glass-ink-float)');
    assert.equal(estiloIsla({ ...base, grown: true }).background, 'var(--surface-glass-ink-float)', 'abierta, también cristal');
    assert.equal(estiloIsla({ ...base, lee: true }).background, 'var(--ink-surface)');
    assert.equal(estiloIsla(base).backdropFilter, 'var(--blur-island)');
});

test('dos velocidades (Z6a): lo que se toca, `--dur-island`; lo que trae el scroll, en calma también para lo que se cruza dentro', () => {
    const base = { row: true, box: caja(300, 64), alert: false, grown: false, animate: true, calm: false };
    assert.equal(estiloIsla({ ...base, rapido: true })['--dur-island'], undefined);
    assert.equal(estiloIsla({ ...base, rapido: false })['--dur-island'], 'var(--dur-island-calma)');
    assert.match(transicionIsla({ calm: false, rapido: true }), /^width var\(--dur-island\) var\(--ease-island\), height var\(--dur-island\) var\(--ease-island\)/);
    assert.match(transicionIsla({ calm: false, rapido: false }), /^width var\(--dur-island-calma\) var\(--ease-island\)/);
});

test('la caja: la capa grande abre en calma y cierra más corto; el fondo cambia en `--dur-slow`; hundida, en un instante', () => {
    // Abrir la capa en calma; cerrarla, más corto: la página vuelve enseguida.
    assert.match(transicionIsla({ calm: true, inCheckout: true }), /^width var\(--dur-slow\) var\(--ease-out\)/);
    assert.match(transicionIsla({ calm: true, inCheckout: false }), /^width var\(--dur-close\) var\(--ease-out\)/);
    assert.match(transicionIsla({ calm: false }), /background-color var\(--dur-slow\) var\(--ease-out\)/);
    // Hundida en un instante; al soltar, con el muelle (y el último `transform` de la lista es el que vale).
    assert.match(transicionIsla({ calm: false, hundida: true }), /transform var\(--dur-instant\) var\(--ease-out\)$/);
    assert.match(transicionIsla({ calm: false, hundida: false }), /transform 260ms var\(--ease-spring\)$/);
});

test('hundida, la isla escala a `--scale-press`; y el nombre de la transición entre páginas solo si lo lleva', () => {
    const base = { row: true, box: caja(300, 64), alert: false, grown: false, animate: true, calm: false };
    assert.equal(estiloIsla({ ...base, hundida: true }).transform, 'scale(var(--scale-press))');
    assert.equal(estiloIsla(base).transform, 'none');
    assert.equal(estiloIsla({ ...base, nombre: 'isla' }).viewTransitionName, 'isla');
    assert.equal(estiloIsla(base).viewTransitionName, undefined);
});

test('el pago fallido cambia el borde a su rol de alerta', () => {
    const fallo = estiloIsla({ row: true, box: caja(300, 64), alert: true, grown: false, animate: true, calm: false });
    assert.equal(fallo.border, '1px solid var(--isla-borde-alerta)');
});

test('abierta arriba, el mínimo va en PÍXELES (un % perseguía a la isla que se anima) y nunca pasa de 390', () => {
    assert.equal(estiloMedida({ row: false, top: true, isOpen: true, cap: 1200, maxWidth: 760 }).minWidth, '390px');
    assert.equal(estiloMedida({ row: false, top: true, isOpen: true, cap: 320, maxWidth: 760 }).minWidth, '320px');
    assert.equal(estiloMedida({ row: false, top: true, isOpen: true, cap: 0, maxWidth: 360 }).minWidth, '360px');
    assert.equal(estiloMedida({ row: true, top: true, isOpen: false, cap: 1200, maxWidth: 760 }).minWidth, undefined);
    assert.equal(estiloMedida({ row: true, top: false, isOpen: true, cap: 390, maxWidth: 760 }).minWidth, undefined);
});

test('el techo del medidor: el contenedor o `maxWidth` arriba; abajo, la pantalla menos sus márgenes', () => {
    assert.equal(estiloMedida({ row: true, top: true, isOpen: false, cap: 1200, maxWidth: 760 }).maxWidth, '760px');
    assert.equal(estiloMedida({ row: true, top: true, isOpen: false, cap: 0, maxWidth: 760 }).maxWidth, '760px');
    assert.equal(estiloMedida({ row: true, top: false, isOpen: false, cap: 390, maxWidth: 760 }).maxWidth, 'calc(100vw - 2 * var(--gutter))');
    assert.equal(estiloMedida({ row: false, top: false, isOpen: false, cap: 390, maxWidth: 760 }).maxWidth, '100%');
});

test('en la compra la raíz deja de ir pegada: fija sobre la página, con aire arriba o a pantalla completa abajo', () => {
    const reposo = estiloRaiz({ gutter: '16px', top: false });
    assert.equal(reposo.position, 'sticky');
    assert.equal(reposo.pointerEvents, 'none');

    const arriba = estiloRaiz({ gutter: '16px', top: true, inCheckout: true });
    assert.deepEqual([arriba.position, arriba.alignItems, arriba.paddingTop, arriba.pointerEvents], ['fixed', 'flex-start', 'max(16px, 4vh)', 'auto']);

    const abajo = estiloRaiz({ gutter: '16px', top: false, inCheckout: true });
    assert.deepEqual([abajo.position, abajo.alignItems, abajo.paddingLeft, abajo.bottom], ['fixed', 'flex-end', '8px', 0]);
    // Abajo, tocar fuera no la cierra: la raíz sigue sin recoger punteros, y el velo es de la isla.
    assert.equal(abajo.pointerEvents, 'none');
});

test('abajo, el hueco de la isla es fijo y ella lo desborda hacia arriba; en la compra no hay hueco', () => {
    assert.equal(estiloRaiz({ gutter: '16px', top: false }).height, undefined, 'antes de medir, el alto de siempre');
    const abajo = estiloRaiz({ gutter: '16px', top: false, reservado: 64 });
    assert.deepEqual([abajo.height, abajo.alignItems], ['calc(64px + max(var(--gutter), env(safe-area-inset-bottom)))', 'flex-end']);
    assert.equal(estiloRaiz({ gutter: '16px', top: false, reservado: 64, inCheckout: true }).height, undefined);
});

test('la primera pantalla: arriba, la banda es FIJA —aire más el alto en reposo— y lo que crece se abre encima', () => {
    // Con o sin lo medido, el mismo alto: las cookies o un panel no empujan la cabecera.
    for (const reservado of [0, 60, 240]) {
        const arriba = estiloRaiz({ gutter: 'var(--gutter)', top: true, reservado });
        assert.deepEqual([arriba.paddingTop, arriba.height, arriba.alignItems], [
            'max(var(--island-inset), env(safe-area-inset-top))',
            'calc(max(var(--island-inset), env(safe-area-inset-top)) + var(--island-h))',
            'flex-start',
        ]);
    }
    // Abajo, el mismo aire que a los lados; y el margen lateral es el que se le da (el de la página, por defecto).
    const abajo = estiloRaiz({ gutter: 'var(--gutter)', top: false });
    assert.deepEqual([abajo.paddingBottom, abajo.paddingLeft, abajo.paddingRight], ['max(var(--gutter), env(safe-area-inset-bottom))', 'var(--gutter)', 'var(--gutter)']);
    // En la compra, arriba, la banda deja de medir su alto fijo: ocupa la pantalla.
    assert.equal(estiloRaiz({ gutter: 'var(--gutter)', top: true, inCheckout: true }).height, 'auto');
});

test('el teclado del móvil (§4.16): en la compra, la raíz se ciñe a la ventana visible; sin él o arriba, como siempre', () => {
    const kb = { h: 400, top: 12 };
    const con = estiloRaiz({ gutter: 'var(--gutter)', top: false, inCheckout: true, kb });
    assert.deepEqual([con.position, con.top, con.bottom, con.height, con.paddingTop, con.paddingBottom], ['fixed', '12px', 'auto', '400px', '8px', '8px']);
    const sin = estiloRaiz({ gutter: 'var(--gutter)', top: false, inCheckout: true, kb: null });
    assert.deepEqual([sin.top, sin.bottom, sin.height], [0, 0, undefined], 'sin teclado: toda la pantalla');
    assert.equal(estiloRaiz({ gutter: 'var(--gutter)', top: true, inCheckout: true, kb }).top, 0, 'arriba (escritorio), el teclado no cuenta');
    assert.equal(estiloRaiz({ gutter: 'var(--gutter)', top: false, inCheckout: false, kb }).height, undefined, 'fuera de la compra, tampoco');
});

test('el alto en reposo que publica: la fila, desde la línea si va encima, más los 16px del medidor', () => {
    // En fila (arriba en escritorio): 46 de fila + 16 = 62, el valor por defecto de la hoja.
    assert.equal(altoEnReposo({ fila: { top: 24, bottom: 70 }, linea: null, row: true }), 62);
    // Con la línea ENCIMA (abajo, en móvil): desde la línea hasta el final de la fila.
    assert.equal(altoEnReposo({ fila: { top: 700, bottom: 746 }, linea: { top: 671 }, row: false }), 91);
    // En fila, la línea no cuenta aunque exista (va DENTRO de la fila).
    assert.equal(altoEnReposo({ fila: { top: 700, bottom: 746 }, linea: { top: 671 }, row: true }), 62);
    // Sin fila, o una medida absurda (montándose, sin caja): nada que publicar.
    assert.equal(altoEnReposo({ fila: null, row: true }), 0);
    assert.equal(altoEnReposo({ fila: { top: 0, bottom: 0 }, row: true }), 0);
});

test('publica en reposo; abierta, en la compra o con el pago fallido, no (ya no hay compacta, Z6a)', () => {
    const reposo = { isOpen: false, inCheckout: false, extra: null };
    assert.equal(publicaAlto(reposo), true);
    assert.equal(publicaAlto({ ...reposo, isOpen: true }), false);
    assert.equal(publicaAlto({ ...reposo, inCheckout: true }), false);
    assert.equal(publicaAlto({ ...reposo, extra: { kind: 'fallo' } }), false);
});

test('en la compra el medidor mide 600px como mucho arriba y el ancho entero abajo, aunque sea fila', () => {
    assert.equal(estiloMedida({ row: true, top: true, isOpen: true, cap: 1200, maxWidth: 760, inCheckout: true }).width, 'min(600px, calc(100vw - 32px))');
    assert.equal(estiloMedida({ row: false, top: false, isOpen: true, cap: 390, maxWidth: 760, inCheckout: true }).width, '100%');
});

test('el tamaño que declara la raíz, de más a menos: compra, panel, aviso, reposo', () => {
    assert.equal(tamano({ inCheckout: true, isOpen: true, notice: 'x' }), 'compra');
    assert.equal(tamano({ inCheckout: false, isOpen: true, notice: 'x' }), 'abierta');
    assert.equal(tamano({ inCheckout: false, isOpen: false, notice: 'x' }), 'aviso');
    assert.equal(tamano({ inCheckout: false, isOpen: false, notice: null }), 'reposo');
});
