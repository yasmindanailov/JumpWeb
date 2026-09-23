/**
 * **EL PAQUETE DEL CAJÓN, en una sola llamada** (F4 · T3b, `docs/specs/cajon-empaquetable.md` §4.1).
 *
 * Montar el cajón en una página son cuatro cosas, y hasta la T3b estaban repartidas por `app.js` —o sea, solo
 * las hacía el producto—:
 *
 *  1. el **controlador** sin framework, que es el estado y la lógica de abrir y cerrar;
 *  2. publicarlo en **`window.JumpWeb.cajon`**, que es la API de apertura de cualquier página;
 *  3. los **atributos** `data-jw-*`, para abrirlo sin escribir una línea de JavaScript;
 *  4. la **carcasa**: adoptarla si la página la trae (el producto) o construirla al abrir si no (una landing
 *     de instancia), y arrancar el motor si el cajón NACE abierto.
 *
 * Quien aloja el cajón llama a esto una vez y ya está. `resources/js/app.js` —la entrada del producto— lo hace
 * junto a lo suyo; el cargador que publicará el paquete (T5) hará exactamente lo mismo y nada más.
 *
 * ⚠️ **El cerrojo de scroll llega por parámetro y no se crea aquí**: es de DUEÑO ÚNICO
 * (`ui/scroll-lock.js`) y lo comparten todos los superpuestos de la página. Dos instancias serían dos dueños
 * de `body.no-scroll`, que es el fallo que ese módulo existe para cerrar. Una página sin más superpuestos
 * puede pasar el suyo recién instalado; el producto pasa el que ya comparten su nav y sus ofertas.
 */
import { createCajonController } from './controller.js';
import { installDeclarativeOpeners } from './declarative.js';
import { installShell } from './shell.js';

/**
 * @param {{scrollLock: {lock: Function, unlock: Function}, win?: Window, doc?: Document}} deps
 * @returns {object} el controlador, ya publicado y en marcha
 */
export function installCajon({ scrollLock, win = window, doc = document }) {
    const cajon = createCajonController({ scrollLock });

    // `track` es el BUZÓN hasta que llegue el tracker (abajo): va ANTES de `start()`, que anuncia el cajón que
    // nace abierto, y eso es justo lo primero que no puede perderse.
    win.JumpWeb = { ...(win.JumpWeb ?? {}), cajon, track: trackMailbox(doc) };

    // ⚠️ Los dos se piden con una FUNCIÓN y no capturan el cajón: con Alpine, `win.JumpWeb.cajon` pasa a ser el
    // proxy reactivo del store tras `alpine:init`, y quien se hubiera quedado con el primero escribiría en el
    // objeto crudo — sin que nada fallara y sin que la página se enterara.
    const anfitrion = () => win.JumpWeb.cajon;

    installDeclarativeOpeners(anfitrion, doc);
    // Sin `boot` a propósito: aquí solo se ADOPTA la carcasa que la página traiga. Construirla exige el
    // arranque, y pedirlo en cada carga sería una petición por visita para un cajón que quizá no se abra;
    // por eso la construye `bootSpaEngine()`, en la primera apertura, que es cuando ya hace falta.
    installShell(anfitrion, doc);

    cajon.start();
    loadTracker(win, doc);

    return cajon;
}

/**
 * **El buzón de la analítica** (`docs/specs/analitica.md` §4.2). `track.js` llega DIFERIDO, y lo que el cajón
 * anuncie antes —que nace abierto, el primer paso de su motor— o lo que alguien quiera contar con
 * `JumpWeb.track()` no puede perderse: se guarda aquí y el tracker lo vacía, en orden, al instalarse. En cuanto
 * sustituye a esta función por la suya (que no lleva `pending`), el buzón deja de recoger.
 */
function trackMailbox(doc) {
    const pending = [];
    const stub = Object.assign((name, props) => { pending.push([name, props]); }, { pending });

    for (const type of ['open', 'step', 'purchased', 'close']) {
        doc.addEventListener(`jw:cajon:${type}`, (e) => { if (stub.pending) pending.push([e.type, e.detail]); });
    }

    return stub;
}

/**
 * El tracker se trae tras `load` y en un rato ocioso: la página no espera a la analítica, y así el trozo no pesa
 * en la entrada de ninguna de las dos páginas que montan el cajón (`SidebarBundleBudgetTest` le pone su techo).
 * Que no cargue no es un fallo del cajón: se calla.
 */
function loadTracker(win, doc) {
    const bring = () => {
        const idle = (fn) => (win.requestIdleCallback ? win.requestIdleCallback(fn) : win.setTimeout(fn, 1));

        idle(() => import('./track.js').then((m) => m.installTracker({ win, doc })).catch(() => {}));
    };

    if (doc.readyState === 'complete') bring();
    else win.addEventListener('load', bring, { once: true });
}
