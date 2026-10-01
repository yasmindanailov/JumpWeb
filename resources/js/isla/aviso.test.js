import { test, mock } from 'node:test';
import assert from 'node:assert/strict';
import { effectScope, nextTick, reactive, ref } from 'vue';
import { AVISO_MS, useAviso } from './useAviso.js';
import { estiloBarra, partesDelAviso } from './piezas/aviso-isla.js';

/**
 * Z6b·2 (zip (6), «el aviso, a isla entera»): el aviso crece un momento y se va solo; su reloj se para con un panel, con el
 * ratón o con el foco, y sigue donde iba. Se prueba fuera de un componente, en un `effectScope`, con el reloj falso de node.
 */
function montar({ notice = null, cookies = null } = {}) {
    let ahora = 1_000_000;
    const props = reactive({ notice, cookies });
    const isOpen = ref(false);
    const inCheckout = ref(false);
    const scope = effectScope();
    const a = scope.run(() => useAviso(props, { isOpen, inCheckout }, { ahora: () => ahora }));
    const tick = (ms) => { ahora += ms; mock.timers.tick(ms); };

    return { a, props, isOpen, inCheckout, scope, tick };
}

function conReloj(fn) {
    return async () => {
        mock.timers.enable({ apis: ['setTimeout'] });
        try { await fn(); } finally { mock.timers.reset(); }
    };
}

test('se va solo a los 4,2 s, una vez por mensaje: el mismo texto no vuelve hasta que se vacía', conReloj(async () => {
    const { a, props, scope, tick } = montar();
    props.notice = 'Guardado';
    await nextTick();
    assert.equal(a.shownNotice.value, 'Guardado');
    assert.equal(a.entero.value, true, 'sin panel ni cookies, a isla entera');
    tick(AVISO_MS - 1);
    assert.equal(a.shownNotice.value, 'Guardado');
    tick(1);
    assert.equal(a.shownNotice.value, null);
    props.notice = 'Guardado';
    await nextTick();
    assert.equal(a.shownNotice.value, null, 'el mismo texto otra vez no se repite');
    props.notice = null;
    await nextTick();
    props.notice = 'Guardado';
    await nextTick();
    assert.equal(a.shownNotice.value, 'Guardado', 'vaciado antes (`avisar()` de la página), vuelve');
    scope.stop();
}));

test('un panel abierto lo para y, al cerrarlo, SIGUE DONDE IBA: antes volvía a empezar', conReloj(async () => {
    const { a, props, isOpen, scope, tick } = montar();
    props.notice = 'Tu cuenta ha sido eliminada. Esperamos verte de nuevo.';
    await nextTick();
    tick(1000);
    isOpen.value = true;
    await nextTick();
    assert.equal(a.entero.value, false, 'con un panel, la tira');
    tick(60_000);
    assert.notEqual(a.shownNotice.value, null, 'parado mientras el panel está abierto');
    isOpen.value = false;
    await nextTick();
    assert.equal(a.entero.value, true);
    assert.equal(a.transcurrido(), 1000, 'la barra vuelve donde se quedó, no llena');
    tick(AVISO_MS - 1000 - 1);
    assert.notEqual(a.shownNotice.value, null);
    tick(1);
    assert.equal(a.shownNotice.value, null, 'se va con lo que le quedaba, no con 4,2 s nuevos');
    scope.stop();
}));

test('el ratón o el foco encima lo paran, y sigue donde iba al quitarlos', conReloj(async () => {
    const { a, props, scope, tick } = montar({ notice: 'Datos actualizados.' });
    await nextTick();
    tick(1500);
    a.pausa.value = true;
    await nextTick();
    tick(60_000);
    assert.equal(a.shownNotice.value, 'Datos actualizados.', 'parado con el ratón encima');
    assert.equal(a.transcurrido(), 1500, 'parado, lo corrido no avanza');
    a.pausa.value = false;
    await nextTick();
    tick(AVISO_MS - 1500 - 1);
    assert.equal(a.shownNotice.value, 'Datos actualizados.');
    tick(1);
    assert.equal(a.shownNotice.value, null);
    props.notice = null;
    scope.stop();
}));

test('a isla entera solo sin panel, sin la compra y sin las cookies; con las cookies, la tira y el reloj sigue', conReloj(async () => {
    const { a, props, inCheckout, scope, tick } = montar({ cookies: { text: 'Usamos cookies…' } });
    props.notice = 'Guardado';
    await nextTick();
    assert.equal(a.entero.value, false, 'con las cookies, la tira de siempre');
    tick(2000);
    assert.equal(a.transcurrido(), 2000, 'el reloj corre con la tira');
    props.cookies = null;
    await nextTick();
    assert.equal(a.entero.value, true, 'decididas las cookies, a isla entera');
    assert.equal(a.transcurrido(), 2000, 'y su barra empieza en lo ya corrido');
    inCheckout.value = true;
    await nextTick();
    assert.equal(a.entero.value, false, 'en la compra, nunca');
    scope.stop();
}));

test('una pausa no sobrevive al aviso que se deja de ver: el siguiente no nace parado', conReloj(async () => {
    const { a, props, scope, tick } = montar({ notice: 'Uno.' });
    await nextTick();
    a.pausa.value = true;
    await nextTick();
    a.quitar();
    await nextTick();
    assert.equal(a.shownNotice.value, null, 'tocarlo lo quita al momento');
    assert.equal(a.pausa.value, false, 'el ratón que estaba encima ya no cuenta: el nodo se fue sin avisar');
    props.notice = null;
    await nextTick();
    props.notice = 'Dos.';
    await nextTick();
    tick(AVISO_MS);
    assert.equal(a.shownNotice.value, null, 'el siguiente se va solo');
    scope.stop();
}));

test('un aviso nuevo empieza con sus 4,2 s enteros, aunque el anterior estuviera a medias', conReloj(async () => {
    const { a, props, scope, tick } = montar({ notice: 'Uno.' });
    await nextTick();
    tick(3000);
    props.notice = 'Dos.';
    await nextTick();
    assert.equal(a.transcurrido(), 0);
    tick(AVISO_MS - 1);
    assert.equal(a.shownNotice.value, 'Dos.');
    tick(1);
    assert.equal(a.shownNotice.value, null);
    scope.stop();
}));

test('el hecho y el matiz: tras la primera frase, también la que acaba en «!», y el resto unido por un espacio', () => {
    assert.deepEqual(partesDelAviso('Tu cuenta ha sido eliminada. Esperamos verte de nuevo.'), { hecho: 'Tu cuenta ha sido eliminada.', matiz: 'Esperamos verte de nuevo.' });
    assert.deepEqual(partesDelAviso('¡Email confirmado! Tu cuenta ya está activa.'), { hecho: '¡Email confirmado!', matiz: 'Tu cuenta ya está activa.' });
    assert.deepEqual(partesDelAviso('Formulario guardado. ¡Gracias!   Puedes volver a editarlo.'), { hecho: 'Formulario guardado.', matiz: '¡Gracias! Puedes volver a editarlo.' });
    assert.deepEqual(partesDelAviso('Guardado'), { hecho: 'Guardado', matiz: '' }, 'una frase: sin matiz');
    assert.deepEqual(partesDelAviso('Abrimos a las 16.30 hoy.'), { hecho: 'Abrimos a las 16.30 hoy.', matiz: '' }, 'un punto sin espacio no corta');
});

test('la barra se vacía en 4,2 s desde lo corrido, se para con el reloj y no se pinta sin movimiento', () => {
    const barra = estiloBarra({ transcurrido: 1500, pausado: false, sinMovimiento: false });
    assert.equal(barra.animation, `isla-aviso-resto ${AVISO_MS}ms linear -1500ms both`, 'un retraso negativo la empieza a medias');
    assert.equal(barra.animationPlayState, 'running');
    assert.equal(estiloBarra({ transcurrido: 1500, pausado: true, sinMovimiento: false }).animationPlayState, 'paused');
    assert.equal(estiloBarra({ transcurrido: 0, sinMovimiento: false }).animation, `isla-aviso-resto ${AVISO_MS}ms linear 0ms both`, 'nuevo, llena');
    assert.equal(estiloBarra({ transcurrido: 0, sinMovimiento: true }), null, 'con «reducir movimiento», sin barra');
});
