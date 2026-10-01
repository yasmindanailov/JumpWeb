import { test, describe } from 'node:test';
import assert from 'node:assert/strict';
import { resolverSituacion, reparto } from './situacion.js';

/**
 * La tabla de prioridades del diseño (`ParkIsland.jsx`), portada a `situacion.js` (T2b), al día con la Z6a (zip (6),
 * «tres huecos»): la acción no se va nunca —con un botón de la página a la vista, baja a secundaria (`calm`)— y la tarea
 * y la reserva de hoy ya no mandan en la barra.
 *
 * Los textos son los del grupo `isla` de `lang/es` (copiados aquí los que se prueban): en español, la isla dice
 * letra a letra lo que dice el diseño, porque se juzga contra él píxel a píxel.
 */
const m = {
    accion: { reservar: 'Reservar', reservar_hoy: 'Reservar para hoy', seguir: 'Sigue con tu reserva',
        pagar_senal: 'Reservar y pagar la señal', pagar_bizum: 'Pagar con Bizum', reintentar_tarjeta: 'Volver a intentar con tarjeta',
        manual: 'O lo reservamos nosotros y pagas por Bizum' },
    hoy: { antes: 'Hoy abrimos a las :hora.', antes_con_huecos: 'Hoy abrimos a las :hora. Quedan huecos esta tarde.',
        antes_de_a: 'Hoy abrimos de :abre a :cierra.', antes_de_a_con_huecos: 'Hoy abrimos de :abre a :cierra. Quedan huecos esta tarde.',
        abierto: 'Abierto hasta las :hora.', abierto_con_huecos: 'Abierto hasta las :hora. Quedan huecos.',
        completo: 'Hoy está completo. Mira mañana.', cerrado: 'Abrimos mañana a las :hora.', apoyo: 'Hoy abrimos de :abre a :cierra' },
    pago: { no_cobrado: 'No se ha cobrado nada.' },
};

const pagina = { kind: 'producto', product: 'kids', action: { label: 'Reservar Kids', href: '#precio' }, from: 'Desde 8 €' };
const hoy = (state, slots = true) => ({ state, opensAt: '16:30', closesAt: '21:30', slots });

describe('qué dice la isla', () => {
    test('sin nada más, el «desde» de la página y su acción, en principal', () => {
        const s = resolverSituacion({ page: pagina }, m);
        assert.equal(s.id, 'desde');
        assert.equal(s.line, 'Desde 8 €');
        assert.deepEqual(s.action, pagina.action);
        assert.equal(s.tone, 'neutral');
        assert.equal(s.calm, false);
    });

    test('sin acción de página, «Reservar» del grupo de textos', () => {
        assert.deepEqual(resolverSituacion({ page: { kind: 'portada' } }, m).action, { label: 'Reservar' });
    });

    test('hoy, antes de abrir y con huecos: la frase ENTERA de la cabecera, tono vivo y «Reservar para hoy» sin perder el destino', () => {
        const s = resolverSituacion({ page: pagina, today: hoy('antes') }, m);
        assert.equal(s.id, 'hoy');
        assert.equal(s.line, 'Hoy abrimos de 16:30 a 21:30. Quedan huecos esta tarde.', 'Z6a: la cabecera ya no lo dice; lo dice la isla.');
        assert.equal(s.tone, 'live');
        assert.deepEqual(s.action, { label: 'Reservar para hoy', href: '#precio' });
        // Sin hora de cierre, la frase de siempre.
        const sinCierre = resolverSituacion({ page: pagina, today: { ...hoy('antes'), closesAt: null } }, m);
        assert.equal(sinCierre.line, 'Hoy abrimos a las 16:30. Quedan huecos esta tarde.');
    });

    test('hoy sin huecos: la frase sin huecos, tono neutro y la acción de la página', () => {
        const s = resolverSituacion({ page: pagina, today: hoy('antes', false) }, m);
        assert.equal(s.line, 'Hoy abrimos de 16:30 a 21:30.');
        assert.equal(s.tone, 'neutral');
        assert.deepEqual(s.action, pagina.action);
    });

    test('hoy abierto, completo y cerrado', () => {
        assert.equal(resolverSituacion({ page: pagina, today: hoy('abierto') }, m).line, 'Abierto hasta las 21:30. Quedan huecos.');
        assert.equal(resolverSituacion({ page: pagina, today: hoy('abierto', false) }, m).line, 'Abierto hasta las 21:30.');
        assert.equal(resolverSituacion({ page: pagina, today: hoy('completo') }, m).line, 'Hoy está completo. Mira mañana.');
        const cerrado = resolverSituacion({ page: pagina, today: hoy('cerrado') }, m);
        assert.equal(cerrado.line, 'Abrimos mañana a las 16:30.');
        assert.equal(cerrado.tone, 'neutral', 'Cerrado no es algo vivo.');
    });

    test('una página que no vende (situación 15): el horario de hoy entero y SU acción, nunca «Reservar para hoy»', () => {
        const apoyo = { kind: 'apoyo', action: { label: 'Reservar', panel: 'plan' } };
        const s = resolverSituacion({ page: apoyo, today: hoy('antes') }, m);
        assert.equal(s.id, 'hoy');
        assert.equal(s.line, 'Hoy abrimos de 16:30 a 21:30', 'El rango con espacio duro: no se parte.');
        assert.equal(s.tone, 'live', 'Con huecos, vivo.');
        assert.deepEqual(s.action, apoyo.action, 'El selector ya ofrece «Para hoy»: la acción no cambia.');
        assert.equal(resolverSituacion({ page: apoyo, today: hoy('abierto', false) }, m).tone, 'neutral');
        assert.equal(resolverSituacion({ page: apoyo, today: hoy('completo') }, m).tone, 'neutral', 'Completo no es vivo.');
        const cerrado = resolverSituacion({ page: apoyo, today: hoy('cerrado') }, m);
        assert.equal(cerrado.line, 'Abrimos mañana a las 16:30.', 'Cerrado, «Hoy abrimos…» no sería verdad.');
        assert.equal(cerrado.tone, 'neutral');
    });

    test('un cálculo con aviso se dice en alerta y no abre el resumen; sin aviso, neutro y lo abre', () => {
        const con = resolverSituacion({ page: pagina, quote: { text: 'Faltan 2 niños para el mínimo', alert: true } }, m);
        assert.equal(con.tone, 'alert');
        assert.equal(con.opens, null);
        const sin = resolverSituacion({ page: pagina, quote: { text: '10 niños · 169,50 €' } }, m);
        assert.equal(sin.tone, 'neutral');
        assert.equal(sin.opens, 'resumen');
    });

    test('con un botón de la página a la vista, la acción NO se va: baja a secundaria con el mismo texto (Z6a)', () => {
        const s = resolverSituacion({ page: pagina, today: hoy('antes'), ctaVisible: true }, m);
        assert.deepEqual(s.action, { label: 'Reservar para hoy', href: '#precio' }, 'la misma acción, en el mismo sitio');
        assert.equal(s.calm, true);
        assert.equal(s.line, 'Hoy abrimos de 16:30 a 21:30. Quedan huecos esta tarde.');
        const desde = resolverSituacion({ page: pagina, ctaVisible: true }, m);
        assert.deepEqual([desde.id, desde.calm], ['desde', true]);
        assert.deepEqual(desde.action, pagina.action);
    });

    test('el orden de la tabla: la compra manda sobre todo; la reserva de hoy ya no es una fila', () => {
        const todo = { page: pagina, today: hoy('antes'), offer: 'oferta', reassurance: 'miedo', quote: { text: 'q' },
            chosen: { text: 'c' }, resume: { text: 'r' }, payment: 'failed', bookingToday: { text: 'b' },
            checkout: { summary: 'compra', action: { label: 'Pagar' } } };
        assert.equal(resolverSituacion(todo, m).id, 'compra');
        assert.equal(resolverSituacion({ ...todo, checkout: null }, m).id, 'pago-fallido', 'con reserva hoy, manda el pago fallido');
        assert.equal(resolverSituacion({ ...todo, checkout: null, payment: null }, m).id, 'a-medias');
        assert.equal(resolverSituacion({ page: pagina, chosen: { text: 'c' }, quote: { text: 'q' }, today: hoy('antes') }, m).id, 'elegido');
        assert.equal(resolverSituacion({ page: pagina, quote: { text: 'q' }, today: hoy('antes') }, m).id, 'calculado');
        assert.equal(resolverSituacion({ page: pagina, today: hoy('antes'), offer: 'o' }, m).id, 'hoy');
        assert.equal(resolverSituacion({ page: pagina, offer: 'o', reassurance: 'r' }, m).id, 'oferta');
        assert.equal(resolverSituacion({ page: pagina, reassurance: 'r' }, m).id, 'miedo');
    });

    test('lo que resuelve algo que ya pasó no baja a secundaria aunque haya un botón a la vista', () => {
        const medias = resolverSituacion({ page: pagina, resume: { text: 'Pack Kids · sáb 26' }, ctaVisible: true }, m);
        assert.deepEqual([medias.action.label, medias.urgent, medias.calm], ['Sigue con tu reserva', true, false]);
        const fallo = resolverSituacion({ page: pagina, payment: 'failed', ctaVisible: true }, m);
        assert.deepEqual([fallo.locked, fallo.calm], [true, false]);
        const compra = resolverSituacion({ page: pagina, checkout: { summary: 'compra', action: { label: 'Pagar' } }, ctaVisible: true }, m);
        assert.equal(compra.calm, false);
    });

    test('pago fallido: Bizum es la acción, y el pedido manual solo si la página lo ofrece', () => {
        const sin = resolverSituacion({ page: pagina, payment: 'failed' }, m);
        assert.equal(sin.line, 'No se ha cobrado nada.');
        assert.equal(sin.action.label, 'Pagar con Bizum');
        assert.equal(sin.extra.manual, null);
        const con = resolverSituacion({ page: pagina, payment: 'failed', onManual: () => {} }, m);
        assert.equal(con.extra.manual.label, 'O lo reservamos nosotros y pagas por Bizum');
    });

    test('la tarea y la reserva de hoy ya no mandan en la barra: ponen el punto en la cuenta (Z6a)', () => {
        const task = { text: 'Tu reserva del sáb 26: añade a tus hijos', action: { label: 'Añadir a mis hijos' }, product: 'kids' };
        assert.equal(resolverSituacion({ page: pagina, task }, m).id, 'desde');
        assert.equal(resolverSituacion({ page: { kind: 'portada' }, task }, m).id, 'desde');
        const conHoy = resolverSituacion({ page: pagina, bookingToday: { text: 'Hoy a las 17:00' }, today: hoy('abierto') }, m);
        assert.equal(conHoy.id, 'hoy', 'la reserva de hoy no le quita la frase a la página');
        assert.notEqual(conHoy.action.label, 'Ver mi QR');
    });

    test('elegido: con el botón del widget a la vista, la acción se queda en secundaria; si no, pagar la señal en principal', () => {
        const visto = resolverSituacion({ page: pagina, chosen: { text: 'c', widgetVisible: true } }, m);
        assert.deepEqual([visto.action.label, visto.calm], ['Reservar y pagar la señal', true]);
        const sinVer = resolverSituacion({ page: pagina, chosen: { text: 'c' } }, m);
        assert.deepEqual([sinVer.action.label, sinVer.calm], ['Reservar y pagar la señal', false]);
        assert.equal(resolverSituacion({ page: pagina, chosen: { text: 'c', label: 'Reservar y pagar' } }, m).action.label, 'Reservar y pagar');
    });
});

describe('cómo se reparte la frase (Z6a: siempre)', () => {
    const base = { top: false, menuOpen: false, inCheckout: false };

    test('la frase va siempre: un precio, una hora o contexto blando; nunca dentro del botón', () => {
        for (const line of ['Desde 8 €', 'Hoy abrimos a las 16:30.', 'Solo pagas los niños que vengan', 'x'.repeat(90)]) {
            const r = reparto({ ...base, s: { line, action: { label: 'Reservar' } } });
            assert.equal(r.hasLine, true, line);
            assert.equal(r.row, false, 'abajo, en su renglón encima de la fila');
        }
    });

    test('arriba (escritorio), la frase va EN la fila', () => {
        const r = reparto({ ...base, top: true, s: { line: 'Desde 8 €', action: { label: 'Reservar' } } });
        assert.deepEqual([r.hasLine, r.row], [true, true]);
    });

    test('ya no hay isla cedida: con un botón a la vista sigue abajo en bloque, con su frase', () => {
        const r = reparto({ ...base, s: { line: 'Desde 8 €', action: { label: 'Reservar' }, calm: true } });
        assert.deepEqual([r.hasLine, r.row], [true, false]);
    });

    test('sin frase, nada que poner; con el menú abierto o en la compra, fuera', () => {
        assert.equal(reparto({ ...base, s: { line: '', action: { label: 'Reservar' } } }).hasLine, false);
        const s = { line: 'Reservas', action: { label: 'Reservar' } };
        assert.equal(reparto({ ...base, s, menuOpen: true }).hasLine, false);
        assert.equal(reparto({ ...base, s, inCheckout: true }).hasLine, false);
    });
});
