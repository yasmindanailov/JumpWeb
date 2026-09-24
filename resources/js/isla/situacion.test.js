import { test, describe } from 'node:test';
import assert from 'node:assert/strict';
import { resolverSituacion, reparto, productoDe } from './situacion.js';

/**
 * T2b de la isla — la tabla de prioridades del diseño (`ParkIsland.jsx`), portada a `situacion.js`.
 *
 * Los textos son los del grupo `isla` de `lang/es` (copiados aquí los que se prueban): en español, la isla dice
 * letra a letra lo que dice el diseño, porque se juzga contra él píxel a píxel.
 */
const m = {
    accion: { reservar: 'Reservar', reservar_hoy: 'Reservar para hoy', ver_qr: 'Ver mi QR', seguir: 'Sigue con tu reserva',
        pagar_senal: 'Reservar y pagar la señal', pagar_bizum: 'Pagar con Bizum', reintentar_tarjeta: 'Volver a intentar con tarjeta',
        manual: 'O lo reservamos nosotros y pagas por Bizum' },
    hoy: { antes: 'Hoy abrimos a las :hora.', antes_con_huecos: 'Hoy abrimos a las :hora. Quedan huecos esta tarde.',
        abierto: 'Abierto hasta las :hora.', abierto_con_huecos: 'Abierto hasta las :hora. Quedan huecos.',
        completo: 'Hoy está completo. Mira mañana.', cerrado: 'Abrimos mañana a las :hora.' },
    pago: { no_cobrado: 'No se ha cobrado nada.' },
};

const pagina = { kind: 'producto', product: 'kids', action: { label: 'Reservar Kids', href: '#precio' }, from: 'Desde 8 €' };
const hoy = (state, slots = true) => ({ state, opensAt: '16:30', closesAt: '21:30', slots });

describe('qué dice la isla', () => {
    test('sin nada más, el «desde» de la página y su acción', () => {
        const s = resolverSituacion({ page: pagina }, m);
        assert.equal(s.id, 'desde');
        assert.equal(s.line, 'Desde 8 €');
        assert.deepEqual(s.action, pagina.action);
        assert.equal(s.tone, 'neutral');
    });

    test('sin acción de página, «Reservar» del grupo de textos', () => {
        assert.deepEqual(resolverSituacion({ page: { kind: 'portada' } }, m).action, { label: 'Reservar' });
    });

    test('hoy, antes de abrir y con huecos: la frase entera, tono vivo y «Reservar para hoy» sin perder el destino', () => {
        const s = resolverSituacion({ page: pagina, today: hoy('antes') }, m);
        assert.equal(s.id, 'hoy');
        assert.equal(s.line, 'Hoy abrimos a las 16:30. Quedan huecos esta tarde.');
        assert.equal(s.tone, 'live');
        assert.deepEqual(s.action, { label: 'Reservar para hoy', href: '#precio' });
    });

    test('hoy sin huecos: la frase corta, tono neutro y la acción de la página', () => {
        const s = resolverSituacion({ page: pagina, today: hoy('antes', false) }, m);
        assert.equal(s.line, 'Hoy abrimos a las 16:30.');
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

    test('con un botón de la página a la vista, la isla cede la acción y «hoy» no la resucita', () => {
        const s = resolverSituacion({ page: pagina, today: hoy('antes'), ctaVisible: true }, m);
        assert.equal(s.action, null);
        assert.equal(s.line, 'Hoy abrimos a las 16:30. Quedan huecos esta tarde.');
    });

    test('el orden de la tabla: la compra manda sobre todo, y la reserva de hoy sobre el pago fallido', () => {
        const todo = { page: pagina, today: hoy('antes'), offer: 'oferta', reassurance: 'miedo', quote: { text: 'q' },
            chosen: { text: 'c' }, resume: { text: 'r' }, payment: 'failed', bookingToday: { text: 'b' },
            checkout: { summary: 'compra', action: { label: 'Pagar' } } };
        assert.equal(resolverSituacion(todo, m).id, 'compra');
        assert.equal(resolverSituacion({ ...todo, checkout: null }, m).id, 'reserva-hoy');
        assert.equal(resolverSituacion({ ...todo, checkout: null, bookingToday: null }, m).id, 'pago-fallido');
        assert.equal(resolverSituacion({ ...todo, checkout: null, bookingToday: null, payment: null }, m).id, 'a-medias');
        assert.equal(resolverSituacion({ page: pagina, chosen: { text: 'c' }, quote: { text: 'q' }, today: hoy('antes') }, m).id, 'elegido');
        assert.equal(resolverSituacion({ page: pagina, quote: { text: 'q' }, today: hoy('antes') }, m).id, 'calculado');
        assert.equal(resolverSituacion({ page: pagina, today: hoy('antes'), offer: 'o' }, m).id, 'hoy');
        assert.equal(resolverSituacion({ page: pagina, offer: 'o', reassurance: 'r' }, m).id, 'oferta');
        assert.equal(resolverSituacion({ page: pagina, reassurance: 'r' }, m).id, 'miedo');
    });

    test('las situaciones urgentes no ceden la acción aunque haya un botón a la vista', () => {
        const s = resolverSituacion({ page: pagina, resume: { text: 'Pack Kids · sáb 26' }, ctaVisible: true }, m);
        assert.equal(s.action.label, 'Sigue con tu reserva');
    });

    test('pago fallido: Bizum es la acción, y el pedido manual solo si la página lo ofrece', () => {
        const sin = resolverSituacion({ page: pagina, payment: 'failed' }, m);
        assert.equal(sin.line, 'No se ha cobrado nada.');
        assert.equal(sin.action.label, 'Pagar con Bizum');
        assert.equal(sin.extra.manual, null);
        const con = resolverSituacion({ page: pagina, payment: 'failed', onManual: () => {} }, m);
        assert.equal(con.extra.manual.label, 'O lo reservamos nosotros y pagas por Bizum');
    });

    test('la tarea sale en la portada y en la página de SU producto, no en otra', () => {
        const task = { text: 'Tu reserva del sáb 26: añade a tus hijos', action: { label: 'Añadir a mis hijos' }, product: 'kids' };
        assert.equal(resolverSituacion({ page: pagina, task }, m).id, 'tarea');
        assert.equal(resolverSituacion({ page: { kind: 'portada' }, task }, m).id, 'tarea');
        assert.equal(resolverSituacion({ page: { kind: 'producto', product: 'jump' }, task }, m).id, 'desde');
        assert.equal(productoDe({ kind: 'cumpleanos' }), 'cumpleanos');
    });

    test('elegido: sin botón si el del widget o uno de la página ya se ve; si no, pagar la señal por defecto', () => {
        assert.equal(resolverSituacion({ page: pagina, chosen: { text: 'c', widgetVisible: true } }, m).action, null);
        assert.equal(resolverSituacion({ page: pagina, chosen: { text: 'c' } }, m).action.label, 'Reservar y pagar la señal');
        assert.equal(resolverSituacion({ page: pagina, chosen: { text: 'c', label: 'Reservar y pagar' } }, m).action.label, 'Reservar y pagar');
    });
});

describe('cómo se reparte la línea', () => {
    const base = { top: false, compact: 'auto', scrolledDown: false, isOpen: false, menuOpen: false, inCheckout: false, mobileContext: 'auto', cookies: null, shownNotice: null };

    test('un precio o una hora son dato duro: renglón propio, nunca dentro del botón', () => {
        const s = { line: 'Desde 8 €', action: { label: 'Reservar' } };
        const r = reparto({ ...base, s });
        assert.equal(r.hardData, true);
        assert.equal(r.lineInButton, false);
        assert.equal(r.hasLine, true);
        assert.equal(reparto({ ...base, s: { line: 'Hoy abrimos a las 16:30.', action: s.action } }).hardData, true);
    });

    test('contexto blando corto: dentro del botón en móvil; arriba (escritorio), en la fila', () => {
        const s = { line: 'Solo pagas los niños que vengan', action: { label: 'Reservar' } };
        assert.equal(reparto({ ...base, s }).lineInButton, true);
        const arriba = reparto({ ...base, s, top: true });
        assert.equal(arriba.lineInButton, false);
        assert.equal(arriba.hasLine, true);
        assert.equal(arriba.row, true);
    });

    test('más de 62 caracteres ya no cabe dentro del botón', () => {
        const s = { line: 'x'.repeat(63), action: { label: 'Reservar' } };
        assert.equal(reparto({ ...base, s }).softFits, false);
        assert.equal(reparto({ ...base, s: { ...s, line: 'x'.repeat(62) } }).softFits, true);
    });

    test('sin acción, la isla cede y se queda en fila también abajo', () => {
        const r = reparto({ ...base, s: { line: 'Desde 8 €', action: null } });
        assert.equal(r.yielded, true);
        assert.equal(r.row, true);
    });

    test('compacta al bajar, pero nunca sin acción ni con las cookies o un aviso a la vista', () => {
        const s = { line: 'Desde 8 €', action: { label: 'Reservar' } };
        assert.equal(reparto({ ...base, s, scrolledDown: true }).isCompact, true);
        assert.equal(reparto({ ...base, s: { ...s, action: null }, scrolledDown: true }).isCompact, false);
        assert.equal(reparto({ ...base, s, scrolledDown: true, cookies: {} }).isCompact, false);
        assert.equal(reparto({ ...base, s, scrolledDown: true, shownNotice: 'Enlace copiado' }).isCompact, false);
    });

    test('con el menú abierto, fuera la línea', () => {
        const s = { line: 'Reservas', action: { label: 'Reservar' } };
        const r = reparto({ ...base, s, menuOpen: true, isOpen: true });
        assert.equal(r.lineInButton, false);
        assert.equal(r.hasLine, false);
    });
});
