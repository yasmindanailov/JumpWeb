import { test, mock } from 'node:test';
import assert from 'node:assert/strict';
import { effectScope, nextTick, ref } from 'vue';
import { CALMA, DESPLAZADO, ESPERA_DECISION, ESPERA_ELEGIDO, useSinSaturar } from './useSinSaturar.js';
import { useHueco, RELEVO_MS } from './useHueco.js';

/**
 * Z6b (zip (6), «Sin saturar, por prioridad», 29-09): la razón de la pieza y su frase tienen presupuesto. Se prueban fuera
 * de un componente, en un `effectScope`, con el reloj falso de node y una ventana de mentira (su `scrollY` y su evento).
 */
function ventana(scrollY = 0) {
    const oyentes = new Set();

    return {
        scrollY,
        addEventListener: (tipo, fn) => { if (tipo === 'scroll') oyentes.add(fn); },
        removeEventListener: (tipo, fn) => { if (tipo === 'scroll') oyentes.delete(fn); },
        bajar(y) { this.scrollY = y; oyentes.forEach((fn) => fn()); },
        oyentes,
    };
}

function montar({ scrollY = 0 } = {}) {
    // El reloj de la visita, lejos de cero como `Date.now()`: sin nada dicho, la calma es la mínima (1,2 s).
    let ahora = 1_000_000;
    const reason = ref(null);
    const reassurance = ref(null);
    const chosen = ref(null);
    const win = ventana(scrollY);
    const scope = effectScope();
    const r = scope.run(() => useSinSaturar(
        { reason: () => reason.value, reassurance: () => reassurance.value, chosen: () => chosen.value },
        { win, ahora: () => ahora },
    ));
    const tick = (ms) => { ahora += ms; mock.timers.tick(ms); };

    return { r, reason, reassurance, chosen, win, scope, tick };
}

const razon = (text, decision = false) => ({ type: 'razon', text, decision });

test('nada al llegar: sin desplazarse no hay razón, aunque la pieza la tenga y sea de decisión', async () => {
    mock.timers.enable({ apis: ['setTimeout'] });
    const { r, reason, win, scope, tick } = montar();
    try {
        reason.value = razon('Hoy solo pagas 50 €', true);
        await nextTick();
        tick(5000);
        assert.equal(r.razon.value, null, 'arriba del todo manda la cabecera');
        win.bajar(DESPLAZADO);
        await nextTick();
        tick(5000);
        assert.equal(r.razon.value, null, `${DESPLAZADO} px aún no es desplazarse`);
        win.bajar(DESPLAZADO + 1);
        await nextTick();
        tick(ESPERA_DECISION - 1);
        assert.equal(r.razon.value, null, 'la de decisión espera 1,2 s con el botón a la vista');
        tick(1);
        assert.equal(r.razon.value.text, 'Hoy solo pagas 50 €');
        assert.equal(win.oyentes.size, 0, 'movida la página, ya no escucha el scroll');
    } finally {
        scope.stop();
        mock.timers.reset();
    }
});

test('las de decisión, siempre; el resto, UNA razón por visita, lo visto no vuelve y 6 s de calma', async () => {
    mock.timers.enable({ apis: ['setTimeout'] });
    const { r, reason, scope, tick } = montar({ scrollY: 500 });
    try {
        // La primera de las que no son de decisión: tras la calma mínima (1,2 s; nada se ha dicho aún).
        reason.value = razon('Su zona, a su medida');
        await nextTick();
        tick(ESPERA_DECISION);
        assert.equal(r.razon.value.text, 'Su zona, a su medida');
        // Se va el botón y vuelve: ya vista, no vuelve.
        reason.value = null;
        await nextTick();
        assert.equal(r.razon.value, null);
        reason.value = razon('Su zona, a su medida');
        await nextTick();
        tick(CALMA * 2);
        assert.equal(r.razon.value, null, 'lo visto no vuelve');
        // Otra que no es de decisión: el presupuesto (una por visita) ya se gastó.
        reason.value = razon('A cubierto y climatizado');
        await nextTick();
        tick(CALMA * 2);
        assert.equal(r.razon.value, null, 'una sola por visita');
        // Una de decisión, siempre, y cuantas veces vuelva.
        reason.value = razon('Tú no pagas entrada', true);
        await nextTick();
        tick(ESPERA_DECISION);
        assert.equal(r.razon.value.text, 'Tú no pagas entrada');
        reason.value = null;
        await nextTick();
        reason.value = razon('Tú no pagas entrada', true);
        await nextTick();
        tick(ESPERA_DECISION);
        assert.equal(r.razon.value.text, 'Tú no pagas entrada', 'la de decisión vuelve');
    } finally {
        scope.stop();
        mock.timers.reset();
    }
});

test('la calma: tras decir algo, lo siguiente que no es de decisión espera a que pasen 6 s desde lo último', async () => {
    mock.timers.enable({ apis: ['setTimeout'] });
    const { r, reason, reassurance, scope, tick } = montar({ scrollY: 500 });
    try {
        reason.value = razon('Tú no pagas entrada', true);
        await nextTick();
        tick(ESPERA_DECISION);
        assert.ok(r.razon.value, 'dicha a los 1,2 s: la última, ahora');
        // La primera frase que no es de decisión, justo después: espera a cumplir los 6 s desde la razón.
        reassurance.value = 'Reservas con 50 €.';
        await nextTick();
        tick(CALMA - 1);
        assert.equal(r.frase.value, null);
        tick(1);
        assert.equal(r.frase.value, 'Reservas con 50 €.');
    } finally {
        scope.stop();
        mock.timers.reset();
    }
});

test('con día y hora elegidos, la razón del pago sale a los 0,6 s, también si no es de decisión', async () => {
    mock.timers.enable({ apis: ['setTimeout'] });
    const { r, reason, chosen, scope, tick } = montar({ scrollY: 500 });
    try {
        chosen.value = { text: 'Sáb 4 · 17:00 · 10 niños' };
        reason.value = razon('Tú no pagas entrada');
        await nextTick();
        tick(ESPERA_ELEGIDO - 1);
        assert.equal(r.razon.value, null);
        tick(1);
        assert.equal(r.razon.value.text, 'Tú no pagas entrada');
    } finally {
        scope.stop();
        mock.timers.reset();
    }
});

test('bajando deprisa, la que se queda atrás no llega a salir; manda la de la pieza en la que se para', async () => {
    mock.timers.enable({ apis: ['setTimeout'] });
    const { r, reason, scope, tick } = montar({ scrollY: 500 });
    try {
        reason.value = razon('Hoy solo pagas 50 €', true);
        await nextTick();
        tick(800);
        reason.value = razon('Tú solo traes a los invitados', true);
        await nextTick();
        tick(ESPERA_DECISION - 1);
        assert.equal(r.razon.value, null, 'la primera no llegó a decirse');
        tick(1);
        assert.equal(r.razon.value.text, 'Tú solo traes a los invitados');
    } finally {
        scope.stop();
        mock.timers.reset();
    }
});

test('la frase: las de decisión siempre; el resto una por visita; la ÚLTIMA dicha vuelve al momento (como el diseño)', async () => {
    mock.timers.enable({ apis: ['setTimeout'] });
    const { r, reassurance, scope, tick } = montar();
    try {
        reassurance.value = { text: 'Reservas con 50 €.', decision: false };
        await nextTick();
        tick(ESPERA_DECISION);
        assert.equal(r.frase.value, 'Reservas con 50 €.', 'la frase no espera al desplazamiento: su pieza ya no es la cabecera');
        reassurance.value = null;
        await nextTick();
        assert.equal(r.frase.value, null);
        reassurance.value = { text: 'Reservas con 50 €.', decision: false };
        await nextTick();
        assert.equal(r.frase.value, 'Reservas con 50 €.', 'la última dicha vuelve sin esperar');
        reassurance.value = { text: 'Otra que no es de decisión.', decision: false };
        await nextTick();
        tick(CALMA * 2);
        assert.equal(r.frase.value, null, 'una por visita');
        reassurance.value = { text: 'Cambias o cancelas hasta 3 días antes.', decision: true };
        await nextTick();
        tick(ESPERA_DECISION);
        assert.equal(r.frase.value, 'Cambias o cancelas hasta 3 días antes.');
        reassurance.value = { text: 'Reservas con 50 €.', decision: false };
        await nextTick();
        tick(CALMA * 2);
        assert.equal(r.frase.value, null, 'ya no era la última: vista, no vuelve');
    } finally {
        scope.stop();
        mock.timers.reset();
    }
});

test('el relevo del hueco: lo que se va es lo último que había, 260 ms; un cambio de etiqueta de la acción no es relevo', () => {
    mock.timers.enable({ apis: ['setTimeout'] });
    const contenido = ref({ clave: 'act', accion: { label: 'Reservar Kids', calm: true } });
    const scope = effectScope();
    const sale = scope.run(() => useHueco(() => contenido.value));
    try {
        contenido.value = { clave: 'act', accion: { label: 'Reservar para hoy', calm: true } };
        assert.equal(sale.value, null, 'la etiqueta la cruza el botón');
        contenido.value = { clave: 'bn|razon|Tú no pagas entrada', bn: { type: 'razon', text: 'Tú no pagas entrada' } };
        assert.deepEqual(sale.value, { clave: 'act', accion: { label: 'Reservar para hoy', calm: true } });
        mock.timers.tick(RELEVO_MS - 1);
        assert.ok(sale.value);
        mock.timers.tick(1);
        assert.equal(sale.value, null);
        contenido.value = null;
        assert.equal(sale.value.bn.text, 'Tú no pagas entrada', 'sin nada en el hueco, lo que había también se va');
    } finally {
        scope.stop();
        mock.timers.reset();
    }
});
