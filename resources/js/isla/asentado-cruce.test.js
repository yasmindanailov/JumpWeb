import { test, mock } from 'node:test';
import assert from 'node:assert/strict';
import { effectScope, nextTick, ref } from 'vue';
import { useAsentado } from './useAsentado.js';
import { useCruce } from './useCruce.js';

/**
 * Z6a (zip (6)): lo que trae el scroll se ASIENTA antes de mandar, y lo que cambia se CRUZA (lo viejo encima, quieto,
 * mientras lo nuevo llega). Se prueban fuera de un componente, en un `effectScope`, con el reloj falso de node.
 */

test('asentado: con espera 0 manda al momento; con espera, solo tras ese tiempo quieto', () => {
    mock.timers.enable({ apis: ['setTimeout'] });
    const valor = ref('desde');
    const espera = ref(250);
    const scope = effectScope();
    const s = scope.run(() => useAsentado(() => valor.value, () => valor.value, () => espera.value));
    try {
        assert.equal(s.value, 'desde');
        valor.value = 'hoy';
        assert.equal(s.value, 'desde', 'aún no lleva 250ms quieto');
        mock.timers.tick(249);
        assert.equal(s.value, 'desde');
        mock.timers.tick(1);
        assert.equal(s.value, 'hoy');
        // Bajando deprisa: si llega otra clave antes de asentarse, el reloj vuelve a empezar y salta a la última.
        valor.value = 'oferta';
        mock.timers.tick(200);
        valor.value = 'miedo';
        mock.timers.tick(200);
        assert.equal(s.value, 'hoy', 'la de en medio no llega a mandar');
        mock.timers.tick(50);
        assert.equal(s.value, 'miedo');
        // Lo que provoca la persona (espera 0), al momento.
        espera.value = 0;
        valor.value = 'compra';
        assert.equal(s.value, 'compra');
    } finally {
        scope.stop();
        mock.timers.reset();
    }
});

test('asentado: con la misma clave que ya manda, el valor nuevo entra al momento (los manejadores no se quedan viejos)', () => {
    mock.timers.enable({ apis: ['setTimeout'] });
    const sit = ref({ id: 'desde', n: 1 });
    const scope = effectScope();
    const s = scope.run(() => useAsentado(() => sit.value, () => sit.value.id, () => 250));
    try {
        sit.value = { id: 'desde', n: 2 };
        assert.equal(s.value.n, 2);
    } finally {
        scope.stop();
        mock.timers.reset();
    }
});

test('cruce: la llegada no se mueve; después, la frase y la etiqueta que se van se quedan 480ms encima', async () => {
    mock.timers.enable({ apis: ['setTimeout'] });
    const s = ref({ id: 'desde', line: 'Desde 8 €', note: null });
    const acc = ref('Reservar');
    const animate = ref(false);
    const scope = effectScope();
    const cruce = scope.run(() => useCruce({ s, accionKey: acc, animate }));
    try {
        // Llegando (aún no anima): cambia, pero no hay nada que cruzar.
        s.value = { id: 'hoy', line: 'Hoy abrimos de 16:30 a 21:30.', note: null };
        await nextTick();
        assert.deepEqual([cruce.value.lineaSale, cruce.value.nL], [null, 0]);

        animate.value = true;
        s.value = { id: 'oferta', line: '−20 % hasta el 30', note: null };
        await nextTick();
        assert.equal(cruce.value.lineaSale.line, 'Hoy abrimos de 16:30 a 21:30.', 'se va la de antes, no la nueva');
        assert.equal(cruce.value.nL, 1);

        acc.value = 'Reservar para hoy';
        await nextTick();
        assert.deepEqual([cruce.value.accSale, cruce.value.nA], ['Reservar', 1]);

        mock.timers.tick(480);
        assert.deepEqual([cruce.value.lineaSale, cruce.value.accSale], [null, null], 'se quitan solas');
        assert.deepEqual([cruce.value.nL, cruce.value.nA], [1, 1], 'lo que entra sigue animando a partir de aquí');
    } finally {
        scope.stop();
        mock.timers.reset();
    }
});
