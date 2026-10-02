import { test, describe, mock } from 'node:test';
import assert from 'node:assert/strict';
import { effectScope, nextTick, reactive } from 'vue';
import { FUNDE, SHORT_MAX, puntoDelTono, resolverSituacion, reparto } from './situacion.js';
import { useEnCabecera, varianteDe } from './useEnCabecera.js';
import { PROPS_BARRA, RELEVO_BARRA_MS, estiloCirculo, useBarraAccion } from './piezas/barra-accion.js';

/**
 * La Z6c (el experimento B3 del zip (6), `isla-y-landing-nueva.md` §4.27): en el móvil y en reposo, la frase de las
 * situaciones de lectura va DENTRO de la acción (la cara de barra); mientras se ve la cabecera, el botón grande sin su
 * frase; y la flecha de la barra va SIEMPRE en naranja (`#868`, del owner, contra el zip). Los textos, los de `lang/es`.
 */
const m = {
    accion: { reservar: 'Reservar', reservar_hoy: 'Reservar para hoy', seguir: 'Sigue con tu reserva', pagar_senal: 'Reservar y pagar la señal' },
    hoy: {
        antes: 'Hoy abrimos a las :hora.', antes_con_huecos: 'Hoy abrimos a las :hora. Quedan huecos esta tarde.',
        antes_de_a: 'Hoy abrimos de :abre a :cierra.', antes_de_a_con_huecos: 'Hoy abrimos de :abre a :cierra. Quedan huecos esta tarde.',
        abierto: 'Abierto hasta las :hora.', abierto_con_huecos: 'Abierto hasta las :hora. Quedan huecos.',
        completo: 'Hoy está completo. Mira mañana.', cerrado: 'Abrimos mañana a las :hora.', apoyo: 'Hoy abrimos de :abre a :cierra',
        corta_huecos: 'Quedan huecos', corta_de_a: 'Hoy, :abre–:cierra', corta_desde: 'Hoy, desde las :hora',
        corta_hasta: 'Hasta las :hora', corta_completo: 'Hoy, completo', corta_manana: 'Mañana, a las :hora',
    },
};
const pagina = { kind: 'producto', action: { label: 'Reservar Kids', href: '#precio' }, from: 'Desde 8 € la hora, de 4 a 7 años', fromShort: 'Desde 8 €.' };
const hoy = (state, extra = {}) => ({ state, opensAt: '16:30', closesAt: '21:30', slots: false, ...extra });
const corta = (today, page = pagina) => resolverSituacion({ page, today }, m).short;
// La isla en el móvil, en reposo y con el B3: lo que decide la barra.
const b3 = (s, extra = {}) => reparto({ s, top: false, menuOpen: false, inCheckout: false, b3: true, ...extra });

describe('las frases cortas (lo que va dentro de la acción)', () => {
    test('las de hoy: sin las horas que no caben, que van en lo que se abre', () => {
        assert.equal(corta(hoy('antes', { slots: true })), 'Quedan huecos');
        assert.equal(corta(hoy('abierto', { slots: true })), 'Quedan huecos');
        assert.equal(corta(hoy('antes')), 'Hoy, 16:30–21:30');
        assert.equal(corta(hoy('antes', { closesAt: null })), 'Hoy, desde las 16:30');
        assert.equal(corta(hoy('abierto')), 'Hasta las 21:30');
        assert.equal(corta(hoy('completo')), 'Hoy, completo');
        assert.equal(corta(hoy('cerrado')), 'Mañana, a las 16:30');
    });

    test('las de las páginas que no venden (situación 15)', () => {
        const apoyo = { kind: 'apoyo', action: { label: 'Reservar' } };
        assert.equal(corta(hoy('abierto', { slots: true }), apoyo), 'Hoy, 16:30–21:30');
        assert.equal(corta(hoy('cerrado'), apoyo), 'Mañana, a las 16:30');
    });

    test('las de la página: su «desde», su oferta y la frase de la pieza, si las trae', () => {
        assert.equal(resolverSituacion({ page: pagina }, m).short, 'Desde 8 €.');
        assert.equal(resolverSituacion({ page: { ...pagina, fromShort: undefined } }, m).short, null);
        const oferta = resolverSituacion({ page: pagina, offer: { text: '−20 % online hasta el 30 de septiembre', short: '−20 % hasta el 30' } }, m);
        assert.deepEqual([oferta.id, oferta.line, oferta.short], ['oferta', '−20 % online hasta el 30 de septiembre', '−20 % hasta el 30']);
        assert.equal(resolverSituacion({ page: pagina, offer: 'Hasta el 30' }, m).line, 'Hasta el 30', 'la oferta en texto, como siempre');
        const miedo = resolverSituacion({ page: pagina, reassurance: 'Si llueve, cambias el día sin coste.', reassuranceShort: 'Cambias si llueve' }, m);
        assert.deepEqual([miedo.id, miedo.short], ['miedo', 'Cambias si llueve']);
    });
});

describe('la barra del B3: cuándo la frase va dentro de la acción', () => {
    const desde = resolverSituacion({ page: pagina }, m);

    test('abajo y en reposo, una situación de lectura con su corta: la barra, sin el punto final y sin frase encima', () => {
        const r = b3(desde);
        assert.equal(r.b2Row, true);
        assert.equal(r.fused, true);
        assert.equal(r.corta, 'Desde 8 €', 'la corta pierde su punto final');
        assert.equal(r.hasLine, false, 'la frase va dentro: no también encima');
    });

    test('sin el B3, la isla de hoy: ni fila de 64 px ni barra', () => {
        const r = reparto({ s: desde, top: false, menuOpen: false, inCheckout: false });
        assert.deepEqual([r.b2Row, r.fused, r.corta, r.hasLine], [false, false, null, true]);
    });

    test('mientras se ve la cabecera: el botón grande SIN su frase (la cabecera ya la dice)', () => {
        const r = b3(desde, { enCabecera: true });
        assert.deepEqual([r.b2Row, r.fused, r.hasLine], [false, false, false]);
    });

    test('arriba (escritorio), con algo abierto o encima, la de hoy', () => {
        assert.equal(b3(desde, { top: true }).fused, false, 'en escritorio no hay barra');
        assert.equal(b3(desde, { isOpen: true }).b2Row, false);
        assert.equal(b3(desde, { inCheckout: true }).b2Row, false);
        assert.equal(b3(desde, { aviso: true }).b2Row, false);
        assert.equal(b3(desde, { cookies: true }).b2Row, false);
        assert.equal(b3({ ...desde, extra: { onDismiss: () => {} } }).b2Row, false, 'el pago fallido');
    });

    test('solo en las de lectura y sin nada que la impida: banner, frase que se toca, nota, urgencia, bloqueo, atasco', () => {
        assert.deepEqual(Object.keys(FUNDE).sort(), ['desde', 'hoy', 'miedo', 'oferta']);
        for (const id of ['elegido', 'a-medias', 'calculado', 'espera', 'compra']) assert.equal(b3({ ...desde, id }).fused, false, id);
        assert.equal(b3({ ...desde, bn: { type: 'razon', text: 'x' } }).fused, false, 'con el banner de la razón, el banner');
        assert.equal(b3({ ...desde, opens: 'resumen' }).fused, false);
        assert.equal(b3({ ...desde, note: 'Quedan 3' }).fused, false);
        assert.equal(b3({ ...desde, urgent: true }).fused, false);
        assert.equal(b3({ ...desde, locked: true }).fused, false);
        assert.equal(b3({ ...desde, action: null }).fused, false, 'sin acción no hay barra');
        assert.equal(b3(desde, { atascada: true }).fused, false, '«¿Lo hablamos?» bajo la frase: la de hoy');
    });

    test('sin corta, la frase va dentro solo si cabe; si no, la de hoy (con su frase encima); sin frase, la barra sin ella', () => {
        const larga = { ...desde, short: null, line: 'Una frase de la página que no cabe en el renglón de la barra' };
        assert.equal(b3(larga).fused, false);
        assert.equal(b3(larga).hasLine, true);
        const cabe = { ...desde, short: null, line: 'Desde 8 €' };
        assert.ok(cabe.line.length <= SHORT_MAX);
        assert.deepEqual([b3(cabe).fused, b3(cabe).corta], [true, 'Desde 8 €']);
        assert.deepEqual([b3({ ...desde, short: null, line: '' }).fused, b3({ ...desde, short: null, line: '' }).corta], [true, '']);
    });
});

describe('la flecha de la barra, siempre naranja (#868)', () => {
    test('el círculo es la acción, en naranja: no hay secundaria que pueda apagarlo', () => {
        assert.equal(estiloCirculo().background, 'var(--action-bg)');
        assert.equal(estiloCirculo({ hover: true }).background, 'var(--action-bg-hover)');
        assert.equal(estiloCirculo().color, 'var(--action-fg)');
        assert.equal('calm' in PROPS_BARRA, false, 'la barra no tiene secundaria: con un botón de la página a la vista, sigue naranja');
    });

    test('el punto de la frase, por el tono: vivo, alerta, foco; neutro, ninguno', () => {
        assert.equal(puntoDelTono('live'), 'var(--isla-vivo)');
        assert.equal(puntoDelTono('alert'), 'var(--isla-alerta)');
        assert.equal(puntoDelTono('focus'), 'var(--isla-foco)');
        assert.equal(puntoDelTono('neutral'), null);
    });

    test('cuando cambian la etiqueta o la frase, la pareja de antes se va encima y se quita sola', async () => {
        mock.timers.enable({ apis: ['setTimeout'] });
        try {
            const props = reactive({ label: 'Reservar', sub: 'Desde 8 €', disabled: false, loading: false, pulsar: null });
            const scope = effectScope();
            const b = scope.run(() => useBarraAccion(props));
            assert.equal(b.sale.value, null);
            props.sub = 'Quedan huecos';
            await nextTick();
            assert.deepEqual(b.sale.value, { clave: 'Reservar|Desde 8 €', label: 'Reservar', sub: 'Desde 8 €' });
            mock.timers.tick(RELEVO_BARRA_MS);
            assert.equal(b.sale.value, null);
            scope.stop();
        } finally {
            mock.timers.reset();
        }
    });
});

/** Una página de mentira: su `<html>`, su cabecera (o ninguna) y un `IntersectionObserver` que se dispara a mano. */
function pagina3({ variante = null, cabecera = null, alto = 844 } = {}) {
    const observadores = [];
    const doc = {
        cabecera,
        documentElement: { getAttribute: (a) => (a === 'data-isla-variante' ? variante : null) },
        querySelector(sel) { return sel === '[data-pj-hero]' ? this.cabecera : null; },
    };
    const win = {
        innerHeight: alto,
        setTimeout: (fn, ms) => setTimeout(fn, ms),
        clearTimeout: (id) => clearTimeout(id),
        IntersectionObserver: class {
            constructor(cb) { this.cb = cb; this.vivo = true; observadores.push(this); }
            observe(el) { this.el = el; }
            disconnect() { this.vivo = false; }
        },
    };

    return { doc, win, observadores };
}
const caja = (top, bottom) => ({ getBoundingClientRect: () => ({ top, bottom }) });

describe('la cara y la cabecera a la vista', () => {
    test('la cara: la prop manda; si no, la del `<html>`; si no, la de hoy', () => {
        assert.equal(varianteDe('b3', pagina3().doc), 'b3');
        assert.equal(varianteDe(null, pagina3({ variante: 'b3' }).doc), 'b3');
        assert.equal(varianteDe(null, pagina3({ variante: 'hoy' }).doc), 'hoy');
        assert.equal(varianteDe(null, pagina3().doc), 'hoy');
        assert.equal(varianteDe('otra', pagina3({ variante: 'b3' }).doc), 'hoy', 'solo `b3` es el B3');
    });

    test('con el B3, la cabecera se mide al crearse la isla y después la sigue el observador', () => {
        const p = pagina3({ cabecera: caja(0, 600) });
        const scope = effectScope();
        const en = scope.run(() => useEnCabecera(true, p));
        assert.equal(en.value, true, 'arriba del todo, la cabecera se ve');
        p.observadores[0].cb([{ isIntersecting: false }]);
        assert.equal(en.value, false, 'pasada la cabecera, la barra');
        p.observadores[0].cb([{ isIntersecting: true }]);
        assert.equal(en.value, true);
        scope.stop();
        assert.equal(p.observadores[0].vivo, false, 'al irse la isla, deja de mirar');
    });

    test('una cabecera que ya no se ve al llegar (la página bajada), fuera desde el principio', () => {
        const scope = effectScope();
        assert.equal(scope.run(() => useEnCabecera(true, pagina3({ cabecera: caja(-900, -20) }))).value, false);
        scope.stop();
    });

    test('sin cabecera la busca dos veces más (a los 0 y a los 300 ms) y, si llega, la mira; si no, la barra', () => {
        mock.timers.enable({ apis: ['setTimeout'] });
        try {
            const tarde = pagina3();
            const scope = effectScope();
            const en = scope.run(() => useEnCabecera(true, tarde));
            assert.equal(en.value, false);
            mock.timers.tick(0);
            tarde.doc.cabecera = caja(0, 500);
            mock.timers.tick(300);
            assert.equal(en.value, true, 'la cabecera que montó un poco después');
            scope.stop();

            const nunca = pagina3();
            const scope2 = effectScope();
            scope2.run(() => useEnCabecera(true, nunca));
            mock.timers.tick(10_000);
            assert.equal(nunca.observadores.length, 0, 'tras dos búsquedas, no insiste');
            scope2.stop();
        } finally {
            mock.timers.reset();
        }
    });

    test('sin el B3, o sin el observador del navegador, nunca hay cabecera que mirar', () => {
        const p = pagina3({ cabecera: caja(0, 600) });
        const scope = effectScope();
        assert.equal(scope.run(() => useEnCabecera(false, p)).value, false);
        assert.equal(p.observadores.length, 0);
        const sinIo = pagina3({ cabecera: caja(0, 600) });
        delete sinIo.win.IntersectionObserver;
        assert.equal(scope.run(() => useEnCabecera(true, sinIo)).value, false);
        scope.stop();
    });
});
