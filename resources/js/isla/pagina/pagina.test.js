import { test, describe } from 'node:test';
import assert from 'node:assert/strict';
import { medirVista, preferenciasDeCookies, propsDeLaIsla } from './pagina.js';

/**
 * T4e — la isla viva en una página declarada (`pagina.js`), contra lo que hace `paginas/entradas/pagina.jsx` del diseño.
 */
const textos = { accion: { elige_dia: 'Elige el día', elige_hora: 'Elige la hora' } };
const config = {
    page: { kind: 'producto', product: 'kids', action: { label: 'Reservar Kids', href: '#precio' }, from: 'Desde 8 €' },
    today: { state: 'antes', opensAt: '16:30', closesAt: '21:30' },
    owner: null, homeLabel: 'Play Jump Park', menuItems: [{ label: 'Kids', href: '/kids', active: true }], contact: { phone: '641 99 57 14', whatsapp: '34641995714' }, lang: 'Español',
};
const llamadas = [];
const acciones = {
    reservar: (paraHoy) => llamadas.push(['reservar', paraHoy]), irAlResumen: () => llamadas.push(['resumen']),
    abrirCuenta: (zona, desde) => llamadas.push(desde === undefined ? ['cuenta', zona] : ['cuenta', zona, desde]),
    aceptarCookies: () => {}, rechazarCookies: () => {}, configurarCookies: () => {}, politicaCookies: () => {}, navegar: () => {},
};
const estado = (extra = {}) => ({ vista: { cta: false, hoy: false }, calculo: null, cookies: false, ...extra });
const props = (extra, huecos = false) => propsDeLaIsla({ config: { ...config, today: { ...config.today, slots: huecos } }, estado: estado(extra), acciones, textos });

describe('qué se ve de la página', () => {
    const alto = 800;

    test('un primario cuenta en cuanto asoma fuera de la franja de la isla, abajo o arriba', () => {
        const abajo = { top: 720, bottom: 790, height: 70 };
        assert.equal(medirVista({ ctas: [{ top: 600, bottom: 650, height: 50 }], isla: abajo, alto }).cta, true);
        assert.equal(medirVista({ ctas: [{ top: 730, bottom: 780, height: 50 }], isla: abajo, alto }).cta, false, 'Tapado por la isla de abajo no cuenta.');
        const arriba = { top: 10, bottom: 80, height: 70 };
        assert.equal(medirVista({ ctas: [{ top: 20, bottom: 70, height: 50 }], isla: arriba, alto }).cta, false, 'Tapado por la isla de arriba no cuenta.');
        assert.equal(medirVista({ ctas: [{ top: 900, bottom: 950, height: 50 }], isla: arriba, alto }).cta, false, 'Fuera de la pantalla no cuenta.');
        assert.equal(medirVista({ ctas: [{ top: 300, bottom: 350, height: 0 }], alto }).cta, false, 'Oculto (sin alto) no cuenta.');
    });

    test('la línea [Hoy] de la pieza 6, con la misma regla', () => {
        assert.equal(medirVista({ hoyLinea: { top: 300, bottom: 330, height: 30 }, alto }).hoy, true);
        assert.equal(medirVista({ hoyLinea: null, alto }).hoy, false);
    });
});

describe('las props de la isla', () => {
    test('en reposo: [Hoy] con los huecos que da la página y su acción, que al pulsarla pide hoy solo si quedan', () => {
        const con = props({}, true);
        assert.deepEqual(con.today, { state: 'antes', opensAt: '16:30', closesAt: '21:30', slots: true });
        assert.equal(con.page.action.label, 'Reservar Kids');
        assert.equal(con.page.action.href, '#precio');
        llamadas.length = 0;
        con.page.action.onClick();
        assert.deepEqual(llamadas, [['reservar', true]]);
        llamadas.length = 0;
        props({}, false).page.action.onClick();
        assert.deepEqual(llamadas, [['reservar', false]], 'Sin huecos, «Reservar» no elige hoy.');
        assert.equal(props({}, false).today.slots, false, 'Sin huecos, la isla no los promete.');
    });

    test('la oferta de la página, tal cual la da la página; sin ella, ninguna', () => {
        assert.equal(props({}).offer, null);
        const p = propsDeLaIsla({ config: { ...config, offer: 'Hasta el 30 de septiembre, −20 % si reservas online' }, estado: estado(), acciones, textos });
        assert.equal(p.offer, 'Hasta el 30 de septiembre, −20 % si reservas online');
    });

    test('calla [Hoy] cuando la pieza 6 lo dice o mientras se calcula', () => {
        assert.equal(props({ vista: { cta: false, hoy: true } }).today, null);
        assert.equal(props({ calculo: { falta: 'hora', elegido: null } }).today, null);
    });

    test('mientras se calcula, la acción es el paso que falta', () => {
        assert.deepEqual(props({ calculo: { falta: 'dia', elegido: null } }).page.action, { label: 'Elige el día', href: '#p3-dia' });
        assert.deepEqual(props({ calculo: { falta: 'hora', elegido: null } }).page.action, { label: 'Elige la hora', href: '#p3-hora' });
    });

    test('con todo elegido, lo elegido y el botón de la calculadora; sin botón si el de la página se ve', () => {
        const p = props({ calculo: { falta: '', elegido: 'Sáb 26 · 17:00 · 3 niños · 24 €', boton: 'Reservar y pagar' }, vista: { cta: true, hoy: false } });
        assert.equal(p.chosen.text, 'Sáb 26 · 17:00 · 3 niños · 24 €');
        assert.equal(p.chosen.label, 'Reservar y pagar');
        assert.equal(p.chosen.widgetVisible, true);
        assert.equal(p.ctaVisible, true);
    });

    test('el aviso de cookies solo mientras hay que decidir; la cuenta, de invitado o con sesión, en su zona', () => {
        assert.equal(props({ cookies: false }).cookies, null);
        assert.equal(typeof props({ cookies: true }).cookies.onAccept, 'function');
        llamadas.length = 0;
        const invitado = props({}).account;
        assert.equal(invitado.state, 'guest');
        invitado.onClick();
        const sesion = propsDeLaIsla({ config: { ...config, owner: 7 }, estado: estado(), acciones, textos }).account;
        assert.equal(sesion.state, 'session');
        sesion.onQr();
        sesion.onClick();
        assert.deepEqual(llamadas, [['cuenta', 'login'], ['cuenta', 'card'], ['cuenta', 'home']], 'Entrar, Mi QR y Mi cuenta, cada uno a su zona.');

        // T5: de dónde viene viaja con ella (la flecha de Mi cuenta vuelve al menú si se abrió desde él).
        llamadas.length = 0;
        sesion.onClick({ from: 'menu' });
        sesion.onQr({ from: 'isla' });
        assert.deepEqual(llamadas, [['cuenta', 'home', 'menu'], ['cuenta', 'card', 'isla']]);
    });
});

describe('la segunda capa de las cookies', () => {
    const legales = {
        necessary_title: 'Necesarias', necessary_desc: 'Imprescindibles.', always_on: 'Siempre activas',
        maps_title: 'Mapa y reseñas (Google)', maps_desc: 'El mapa.', analytics_title: 'Análisis', analytics_desc: 'Uso.',
        accept_all: 'Aceptar todo', reject_all: 'Rechazar todo', policy_link: 'Leer la política de cookies',
    };
    const hechas = [];
    const accionesCookies = { cambiar: (id, v) => hechas.push([id, v]), aceptarTodas: () => hechas.push(['todas', true]), rechazarTodas: () => hechas.push(['todas', false]), politica: () => {} };

    test('cada finalidad de la instalación, con su texto LEGAL y su estado; las necesarias, sin interruptor', () => {
        const p = preferenciasDeCookies({ categorias: ['maps', 'analytics'], prefs: { maps: true }, legales, textos: { cookies: { si: 'Sí', no: 'No' } }, acciones: accionesCookies });
        assert.deepEqual(p.necesarias, { titulo: 'Necesarias', texto: 'Imprescindibles.', etiqueta: 'Siempre activas' });
        assert.deepEqual(p.categorias, [
            { id: 'maps', titulo: 'Mapa y reseñas (Google)', texto: 'El mapa.', activa: true },
            { id: 'analytics', titulo: 'Análisis', texto: 'Uso.', activa: false },
        ]);
        assert.deepEqual([p.textos.aceptar, p.textos.rechazar, p.textos.si, p.textos.no], ['Aceptar todo', 'Rechazar todo', 'Sí', 'No']);
    });

    test('una finalidad sin título legal sale con su nombre, nunca un interruptor mudo; y cada botón hace lo suyo', () => {
        const p = preferenciasDeCookies({ categorias: ['social'], prefs: {}, legales, acciones: accionesCookies });
        assert.equal(p.categorias[0].titulo, 'social');
        hechas.length = 0;
        p.onCambiar('social', true);
        p.onAceptarTodas();
        p.onRechazarTodas();
        assert.deepEqual(hechas, [['social', true], ['todas', true], ['todas', false]]);
    });
});
