import { test, describe } from 'node:test';
import assert from 'node:assert/strict';
import { avisoDeCookies, cajaSiVisible, medirVista, preferenciasDeCookies, propsDeLaIsla } from './pagina.js';
import { paginaConSelector } from './con-selector.js';

/**
 * T4e — la isla viva en una página declarada (`pagina.js`), contra lo que hace `paginas/entradas/pagina.jsx` del diseño.
 */
const textos = { accion: { elige_dia: 'Elige el día', elige_hora: 'Elige la hora', elige_edad: 'Elige la edad' } };
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

    test('lo que la llegada limpia esconde no cuenta: sigue en su sitio, pero no se ve (zip del 27-09)', () => {
        const caja = { top: 300, bottom: 350, height: 50 };
        // Un elemento de mentira: solo lo que `cajaSiVisible` le pregunta.
        const el = (dentroDeOculto) => ({ closest: (sel) => (sel === '[data-llegada="oculto"]' && dentroDeOculto ? {} : null), getBoundingClientRect: () => caja });
        assert.deepEqual(cajaSiVisible(el(false)), caja);
        assert.equal(cajaSiVisible(el(true)), null);
        assert.equal(cajaSiVisible(null), null);
        assert.equal(medirVista({ ctas: [cajaSiVisible(el(true))], alto }).cta, false, 'El botón de la cabecera oculto al llegar: la isla no cede el suyo.');
        assert.equal(medirVista({ ctas: [cajaSiVisible(el(false))], alto }).cta, true);
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

    test('de la calculadora de la FIESTA (T6b·3): la edad también falta, y el ancla es la que manda ella', () => {
        assert.deepEqual(props({ calculo: { falta: 'edad', faltaHref: '#p6-edad', elegido: null } }).page.action, { label: 'Elige la edad', href: '#p6-edad' });
        assert.deepEqual(props({ calculo: { falta: 'dia', faltaHref: '#p6-dia', elegido: null } }).page.action, { label: 'Elige el día', href: '#p6-dia' });
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

    test('T5c: lo que el servidor sabe de la próxima —su primera tarea y si es hoy— llega a la isla, solo con sesión', () => {
        const cuenta = {
            pending: true, pendingText: 'Siguiente: Formulario de invitados',
            task: { text: 'Rellena el formulario de invitados, hasta el jueves 24.', action: { label: 'Rellenar el formulario', href: '/reserva/7/datos-invitados' } },
            bookingToday: { text: 'Hoy a las 17:00' },
        };
        const p = propsDeLaIsla({ config: { ...config, owner: 7, cuenta }, estado: estado(), acciones, textos });

        assert.equal(p.account.pending, true, 'el punto del menú');
        assert.equal(p.account.pendingText, 'Siguiente: Formulario de invitados');
        assert.deepEqual(p.bookingToday, { text: 'Hoy a las 17:00' });
        assert.deepEqual(p.task, { text: 'Rellena el formulario de invitados, hasta el jueves 24.', product: null, action: { label: 'Rellenar el formulario', href: '/reserva/7/datos-invitados' } });

        const sinNada = propsDeLaIsla({ config: { ...config, owner: 7, cuenta: { pending: false, pendingText: null, task: null, bookingToday: null } }, estado: estado(), acciones, textos });
        assert.equal(sinNada.account.pending, false);
        assert.equal(sinNada.bookingToday, null);
        assert.equal(sinNada.task, null);

        // T5d: una tarea que resuelve una pantalla de la cuenta la abre en su zona, sin navegar.
        const hijos = propsDeLaIsla({ config: { ...config, owner: 7, cuenta: { ...cuenta, task: { text: 'Añade a tus hijos…', product: 'kids', action: { label: 'Añadir a mis hijos', href: '/mi-cuenta/hijos', zone: 'dependents' } } } }, estado: estado(), acciones, textos });
        assert.equal(hijos.task.product, 'kids');
        assert.equal(hijos.task.action.href, undefined, 'sin href: cambiar el ancla en la misma página no la recarga');
        llamadas.length = 0;
        hijos.task.action.onClick();
        assert.deepEqual(llamadas, [['cuenta', 'dependents', 'isla']]);

        // Sin sesión, aunque la página trajera algo (una caché, un error), la isla no lo dice.
        const invitado = propsDeLaIsla({ config: { ...config, owner: null, cuenta }, estado: estado(), acciones, textos });
        assert.equal(invitado.bookingToday, null);
        assert.equal(invitado.task, null);
        assert.equal(invitado.account.pending, undefined);
    });
});

describe('el selector de planes (T6a)', () => {
    const compradas = [];
    const conComprar = { ...acciones, comprar: (intencion) => compradas.push(intencion) };
    const plans = {
        title: '¿Qué quieres reservar?', footer: 'Pago con tarjeta en la pasarela del banco; tu tarjeta no se guarda.',
        options: [
            { title: 'Un cumpleaños', featured: true, price: 'desde 14,95 €', intent: { type: 'packs' } },
            { title: 'Entrada Kids', note: 'De 4 a 7 años', price: 'desde 6,40 €', intent: { type: 'zone', slug: 'kids' } },
            { title: 'Para hoy', today: true, highlight: true, note: 'Quedan huecos esta tarde' },
        ],
    };

    test('lo que da la página, tal cual, y cada opción compra con SU intención (sin intención, «Para hoy»: `{}`)', () => {
        const p = propsDeLaIsla({ config: { ...config, plans }, estado: estado(), acciones: conComprar, textos });

        assert.equal(p.plans.title, plans.title);
        assert.equal(p.plans.footer, plans.footer);
        assert.deepEqual(p.plans.options.map((o) => [o.title, o.featured ?? false, o.today ?? false]), [['Un cumpleaños', true, false], ['Entrada Kids', false, false], ['Para hoy', false, true]]);
        p.plans.options.forEach((o) => o.onClick());
        // `#831`: la compra sabe que nace del selector —su flecha lo reabre— y si se abrió desde «Reservar para hoy».
        const desde = { desde: 'selector', desdeHoy: false };
        assert.deepEqual(compradas, [{ type: 'packs', ...desde }, { type: 'zone', slug: 'kids', ...desde }, desde]);
        p.plans.options[1].onClick({ fromToday: true });
        assert.deepEqual(compradas.at(-1), { type: 'zone', slug: 'kids', desde: 'selector', desdeHoy: true });
    });

    test('sin selector en la página —o vacío—, ninguno: la acción de la isla sigue siendo la de la página', () => {
        assert.equal(props().plans, null);
        assert.equal(propsDeLaIsla({ config: { ...config, plans: { title: 'x', options: [] } }, estado: estado(), acciones: conComprar, textos }).plans, null);
    });

    test('quien no es la isla sabe si la página lo trae, por la configuración que publica; rota o ausente, no', () => {
        const doc = (texto) => ({ getElementById: (id) => (id === 'jw-isla-pagina' && texto !== null ? { textContent: texto } : null) });

        assert.equal(paginaConSelector(doc(JSON.stringify({ config: { ...config, plans } }))), true);
        assert.equal(paginaConSelector(doc(JSON.stringify({ config }))), false);
        assert.equal(paginaConSelector(doc('{roto')), false);
        assert.equal(paginaConSelector(doc(null)), false);
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

describe('el aviso de cookies nombra solo lo que pide (#860)', () => {
    // Los textos de `lang/es/isla.php`, tal cual.
    const textosCookies = { cookies: {
        texto: 'Usamos cookies para que la web funcione y para contar las visitas. Con tu permiso, también para :para. Puedes aceptarlas, rechazarlas o configurarlas.',
        texto_corto: 'Cookies necesarias y, con tu permiso, :cortas.',
        para: { maps: 'enseñarte el mapa de Google', social: 'enseñarte nuestras redes sociales', analytics: 'entender cómo usas la web con tu cuenta', marketing: 'enseñarte nuestros anuncios en otras webs' },
        cortas: { maps: 'mapa', social: 'redes', analytics: 'análisis', marketing: 'anuncios' },
        y: ' y ',
    } };

    test('sin píxeles, no dice «anuncios»: solo el análisis, que se pide siempre', () => {
        const a = avisoDeCookies(['analytics'], textosCookies);
        assert.equal(a.text, 'Usamos cookies para que la web funcione y para contar las visitas. Con tu permiso, también para entender cómo usas la web con tu cuenta. Puedes aceptarlas, rechazarlas o configurarlas.');
        assert.equal(a.shortText, 'Cookies necesarias y, con tu permiso, análisis.');
    });

    test('lo encendido, en el orden del <body> y dicho como una lista: «a y b», «a, b y c»', () => {
        assert.equal(avisoDeCookies(['analytics', 'marketing'], textosCookies).shortText, 'Cookies necesarias y, con tu permiso, análisis y anuncios.');
        const tres = avisoDeCookies(['maps', 'analytics', 'marketing'], textosCookies);
        assert.equal(tres.shortText, 'Cookies necesarias y, con tu permiso, mapa, análisis y anuncios.');
        assert.ok(tres.text.includes('para enseñarte el mapa de Google, entender cómo usas la web con tu cuenta y enseñarte nuestros anuncios en otras webs.'));
    });

    test('la isla lo recibe con el aviso, y la categoría sin frase no deja un hueco en la lista', () => {
        const p = propsDeLaIsla({ config, estado: estado({ cookies: true, categorias: ['analytics', 'desconocida'] }), acciones, textos: { ...textos, ...textosCookies } });
        assert.equal(p.cookies.shortText, 'Cookies necesarias y, con tu permiso, análisis.');
        assert.ok(! p.cookies.text.includes('anuncios'));
    });
});
