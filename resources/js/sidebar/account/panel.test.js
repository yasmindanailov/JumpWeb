import { test, describe } from 'node:test';
import assert from 'node:assert/strict';
import { alertOf, counterOf, initialOf, panelOf } from './panel.js';

/**
 * La red del bloque de cuenta del panel (`docs/specs/account-context-vue.md` §4.10).
 *
 * ⚠️ Los diccionarios se doblan con el CAMINO real de `lang/` (`sidecart.form_pending_one`), no con
 * claves planas: `i18n.js` lee por camino, y una forma inventada aquí probaría algo que en producción
 * no ocurre. Los literales son los de `lang/es/account.php`, copiados tal cual.
 */

const ACCOUNT = {
    nav: { hello: 'Hola, :name', sign_out: 'Cerrar sesión', login: 'Iniciar sesión' },
    account: { title: 'Mi cuenta', card: { title: 'Mi QR' } },
    sidecart: {
        guest_hello: 'Hola, saltador/a',
        guest_sub: 'Inicia sesión y guarda tus reservas.',
        form_pending_one: 'Tienes pendiente un formulario para :product',
        form_pending_many: 'Tienes :count formularios pendientes',
        extras_invite: 'Añade extras a tu fiesta de :product',
        upcoming_count: ':count reservas próximas',
    },
};

const MESSAGES = { my_reservations: 'Ver mis reservas' };
const URLS = { my_orders: 'https://parque.test/mi-cuenta/pedidos' };
const DEPS = { account: ACCOUNT, messages: MESSAGES, urls: URLS };

/** El contexto tal como llega de `GET /me/account-context` o de la semilla del montaje. */
const ctx = (over = {}) => ({
    first_name: 'Ada',
    upcoming_count: 0,
    next_reservation: null,
    pending_forms: [],
    pending_forms_count: 0,
    ...over,
});

const NEXT = {
    date: '2026-09-05',
    date_label: 'Sáb. 5 sep.',
    time_window: '10:00–12:00',
    product_name: 'Cumpleaños Jump',
};

describe('la inicial del avatar', () => {
    test('es la primera letra, en mayúscula', () => {
        assert.equal(initialOf('Ada'), 'A');
        assert.equal(initialOf('ada'), 'A');
    });

    test('respeta los acentos y las mayúsculas del idioma', () => {
        assert.equal(initialOf('Ángela'), 'Á');
        assert.equal(initialOf('Óscar'), 'Ó');
    });

    /**
     * ⚠️ El servidor recorta el nombre y uno de solo espacios da cadena vacía —lo fija
     * `CustomerAccountContextTest`—. Sin respaldo, el avatar sería un círculo vacío, que se lee como
     * un fallo de carga y no como «no sabemos tu nombre».
     */
    test('cae al punto medio cuando no hay nombre', () => {
        assert.equal(initialOf(''), '·');
        assert.equal(initialOf('   '), '·');
        assert.equal(initialOf(null), '·');
        assert.equal(initialOf(undefined), '·');
    });

    /** Un nombre que empieza fuera del BMP no se parte por la mitad. */
    test('no parte un carácter de dos unidades', () => {
        assert.equal(initialOf('𝒜da'), '𝒜');
    });
});

/**
 * ⚠️⚠️ **Aquí vivía `describe('la sub-línea')`, con sus tres casos, y murió con su SUJETO el
 * 2026-08-28** (`identidad-qr-puerta.md` §9.7 C·2): `sublineOf()` componía la próxima reserva bajo el
 * nombre en la cara identificada, y esa frase se retiró del bloque porque el índice del área ya la
 * enseña. Sus casos NO se re-apuntan a otra cosa —lo que probaban ya no existe— y se van con ella
 * los dos rótulos que leían (`sidecart.next`, `sidecart.no_upcoming`), también del arranque.
 *
 * ▶ El caso «no recompone la fecha: usa la etiqueta del servidor» **no queda huérfano**: lo que
 * defendía —que `date_label` se pinta tal como llega— lo sigue fijando `DayLabelSingleSourceTest` en
 * el servidor, y la única superficie del cajón que hoy pinta esa etiqueta (`AccountHomeZone`) la
 * interpola sin tocarla.
 */

describe('el aviso de formularios pendientes', () => {
    test('sin pendientes no hay aviso', () => {
        assert.equal(alertOf(ctx(), ACCOUNT, URLS), null);
    });

    /**
     * ⚠️⚠️ D15 · **sin deuda pero con extras abiertos, el hueco INVITA en vez de callarse.** Es el
     * caso normal tras rellenar las fichas, y hasta hoy la tarjeta se quedaba muda justo ahí.
     */
    test('sin pendientes pero con extras abiertos, invita', () => {
        const alert = alertOf(
            ctx({ extras_invite: { product_name: 'Cumpleaños Jump', url: '/reserva/7/datos-invitados' } }),
            ACCOUNT,
            URLS,
        );

        assert.deepEqual(alert, {
            text: 'Añade extras a tu fiesta de Cumpleaños Jump',
            href: '/reserva/7/datos-invitados',
            zone: null,
        });
    });

    /** Y la DEUDA gana: un formulario a medias importa más que vender un cubo de refrescos. */
    test('con un formulario pendiente, la deuda gana a la invitación', () => {
        const alert = alertOf(
            ctx({
                pending_forms_count: 1,
                pending_forms: [{ product_name: 'Cumpleaños Jump', url: '/reserva/7/datos-invitados' }],
                extras_invite: { product_name: 'Cumpleaños Jump', url: '/reserva/7/datos-invitados' },
            }),
            ACCOUNT,
            URLS,
        );

        assert.equal(alert.text, 'Tienes pendiente un formulario para Cumpleaños Jump');
    });

    /** Con UNO lleva al formulario, y `zone: null` significa «deja navegar»: no es del cajón. */
    test('con uno lleva a ese formulario y deja navegar', () => {
        const aviso = alertOf(ctx({
            pending_forms_count: 1,
            pending_forms: [{ product_name: 'Cumpleaños Jump', url: 'https://parque.test/reserva/7/datos-invitados' }],
        }), ACCOUNT, URLS);

        assert.equal(aviso.text, 'Tienes pendiente un formulario para Cumpleaños Jump');
        assert.equal(aviso.href, 'https://parque.test/reserva/7/datos-invitados');
        assert.equal(aviso.zone, null, 'el post-form es una página: el cajón no se hace cargo');
    });

    /** Con VARIOS no hay un destino que acertar, así que se atienden dentro del cajón. */
    test('con varios lleva a la lista, dentro del cajón', () => {
        const aviso = alertOf(ctx({
            pending_forms_count: 3,
            pending_forms: [{ product_name: 'Uno', url: 'https://parque.test/reserva/7/datos-invitados' }],
        }), ACCOUNT, URLS);

        assert.equal(aviso.text, 'Tienes 3 formularios pendientes');
        assert.equal(aviso.zone, 'orders');
        assert.equal(aviso.href, URLS.my_orders, 'el href se conserva: clic central y pestaña nueva');
    });

    /**
     * ⚠️⚠️ **El caso que la SEMILLA obliga a cubrir.** El montaje viaja podado por cardinalidad
     * (`Http\Sidebar\AccountContextSeed`), así que el contador y la lista **no siempre se
     * corresponden**. Sin esta rama, un contexto con contador 1 y lista vacía daría `href:
     * undefined`: un enlace que no falla y no lleva a ninguna parte (`DECISIONES #117`).
     */
    test('si el contador dice uno pero la lista no lo trae, degrada a la lista', () => {
        const aviso = alertOf(ctx({ pending_forms_count: 1, pending_forms: [] }), ACCOUNT, URLS);

        assert.equal(aviso.zone, 'orders');
        assert.equal(aviso.href, URLS.my_orders);
        assert.notEqual(aviso.href, undefined);
    });

    /** Y sin `urls` tampoco inventa una ruta: el enrutador es del servidor. */
    test('sin urls no compone una ruta a mano', () => {
        const aviso = alertOf(ctx({ pending_forms_count: 2, pending_forms: [] }), ACCOUNT, {});

        assert.equal(aviso.href, '');
    });
});

describe('el contador de reservas próximas', () => {
    test('sin reservas no hay contador', () => {
        assert.equal(counterOf(ctx(), ACCOUNT), null);
    });

    test('con reservas lleva el número y su lectura', () => {
        const c = counterOf(ctx({ upcoming_count: 2 }), ACCOUNT);

        assert.equal(c.count, 2);
        assert.equal(c.label, '2 reservas próximas');
    });
});

describe('el bloque entero', () => {
    /**
     * ⚠️⚠️ **La cara se decide por si HAY CONTEXTO, no por `userId`.** `userId` es una prop estática
     * del HTML de esa carga, y quien entra dentro del embudo **no recarga**: mirarlo dejaría el
     * bloque saludando como invitado a alguien que acaba de entrar — literalmente el fallo que hizo
     * que este bloque fuera Livewire en 2026-06-14.
     */
    test('sin contexto pinta la cara de invitado', () => {
        const p = panelOf(null, DEPS);

        assert.equal(p.identified, false);
        assert.equal(p.hello, 'Hola, saltador/a');
        assert.equal(p.subline, 'Inicia sesión y guarda tus reservas.');
        assert.equal(p.login, 'Iniciar sesión');
        assert.equal(p.reservations, 'Ver mis reservas');
    });

    test('con contexto pinta la cara con sesión, entera', () => {
        const p = panelOf(ctx({
            first_name: 'Ada',
            upcoming_count: 2,
            next_reservation: NEXT,
            pending_forms_count: 1,
            pending_forms: [{ product_name: 'Cumpleaños Jump', url: 'https://parque.test/reserva/7/datos-invitados' }],
        }), DEPS);

        assert.equal(p.identified, true);
        assert.equal(p.initial, 'A');
        assert.equal(p.hello, 'Hola, Ada');
        // ⚠️ **La cara identificada YA NO lleva sub-línea** (§9.7 C·2): en su sitio va «Mi QR», y la
        // próxima reserva la dice el índice del área. Se asevera la AUSENCIA, no se calla el campo:
        // si alguien la devuelve aquí, vuelven a existir dos sitios que decir lo mismo.
        assert.equal(p.subline, undefined, 'la próxima reserva ya no se dice en el bloque de cuenta');
        assert.equal(p.card, 'Mi QR', 'el atajo se rotula con el título de su zona');
        assert.equal(p.alert.text, 'Tienes pendiente un formulario para Cumpleaños Jump');
        assert.equal(p.counter.count, 2);
        assert.equal(p.signOut, 'Cerrar sesión');
        assert.equal(p.reservations, 'Ver mis reservas');
        assert.equal(p.account, 'Mi cuenta', 'el tercer destino de la fila de botones');
    });

    /** Un titular recién identificado y sin nada: la cara con sesión sigue siendo coherente. */
    test('con contexto vacío no hay aviso ni contador, pero sí saludo', () => {
        const p = panelOf(ctx({ first_name: 'Grace' }), DEPS);

        assert.equal(p.identified, true);
        assert.equal(p.hello, 'Hola, Grace');
        assert.equal(p.alert, null);
        assert.equal(p.counter, null);
        assert.equal(p.subline, undefined);
    });

    /**
     * ⚠️ El contexto DEFENSIVO del servidor —el que devuelve `CustomerAccountContext` cuando falla al
     * leer las reservas— llega con el nombre vacío y todo a cero. No debe romper nada: es una
     * cortesía de interfaz, no la pantalla.
     */
    test('sobrevive al contexto vacío que el servidor devuelve ante un fallo', () => {
        const p = panelOf(ctx({ first_name: '' }), DEPS);

        assert.equal(p.identified, true);
        assert.equal(p.initial, '·');
        assert.equal(p.alert, null);
        assert.equal(p.counter, null);
    });
});

describe('los tres destinos de la fila de botones', () => {
    /**
     * ⚠️ **El rótulo de «Mi cuenta» es el MISMO que el índice usa para su propia pantalla**
     * (`account.account.title`). Dos nombres para el mismo sitio hacen creer al cliente que va a
     * otro, y es la clase de divergencia que aparece el día que alguien retoca uno solo.
     */
    test('el botón de cuenta se llama como la pantalla a la que lleva', () => {
        assert.equal(panelOf(ctx(), DEPS).account, ACCOUNT.account.title);
    });

    /**
     * ⚠️ **Y el atajo del QR, igual** (§9.7 C·2): se rotula con `account.card.title`, el mismo título
     * que lleva su tarjeta en el índice y la cabecera de la zona. Es la regla de arriba aplicada al
     * cuarto destino, y por eso se compara contra el diccionario y no contra un literal.
     * ⚠️ Solo existe en la cara IDENTIFICADA: `account.card` viaja únicamente con sesión, así que un
     * invitado leería una cadena vacía — `i18n.js` devuelve `''` cuando falta y nada avisa.
     */
    test('el atajo del QR se llama como la zona a la que lleva, y solo con sesión', () => {
        assert.equal(panelOf(ctx(), DEPS).card, ACCOUNT.account.card.title);
        assert.equal(panelOf(null, DEPS).card, undefined);
    });

    /**
     * ⚠️ **El de salir conserva su rótulo aunque se pinte solo con icono**: es su nombre accesible
     * (`aria-label`), y sin él un lector de pantalla anunciaría «botón» a secas.
     */
    test('el de salir conserva su rótulo, que es su nombre accesible', () => {
        assert.equal(panelOf(ctx(), DEPS).signOut, 'Cerrar sesión');
    });

    /** Y la cara de invitado no tiene tercer destino: no hay cuenta a la que ir. */
    test('el invitado no tiene botón de cuenta', () => {
        assert.equal(panelOf(null, DEPS).account, undefined);
    });
});
