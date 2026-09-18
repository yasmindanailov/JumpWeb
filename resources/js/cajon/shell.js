/**
 * **LA CARCASA DEL CAJÓN, con UN solo dueño y sin framework** (F4 · T3a, `docs/specs/cajon-empaquetable.md` §4.2).
 *
 * La carcasa es lo que envuelve al motor: el telón, el panel `role="dialog"`, la cabecera con su cierre. Hasta el
 * 2026-09-18 su conducta estaba repartida en atributos de Alpine sobre el marcado del layout —`:class` para
 * abrirla y para el modo, `@click` en el telón y en el cierre, `@keydown.escape.window`, y el componente
 * `a11yPanel` para la trampa de foco—. Una página que no cargue Alpine tendría la carcasa pintada y MUERTA.
 *
 * Este módulo ADOPTA el marcado que encuentre (`.sidecart`) y es el único que le pone clases y oyentes. No lee
 * el estado de ningún store: se entera por los eventos que el controlador anuncia (`jw:cajon:open`, `:close`,
 * `:mode`) y, al instalarse, pregunta una vez cómo NACE el cajón — que puede ser abierto (`/entradas`, una
 * puerta de cuenta, la vuelta de la pasarela).
 *
 * ⚠️⚠️ **El modo del panel NO es decoración** (`DECISIONES #118`): `is-{modo}` es lo que COLAPSA el bloque de
 * cuenta en tres pantallas. Lo publica el motor, llega aquí como evento, y sin este puente el panel se queda en
 * `is-catalog` para siempre sin que nada falle. Ya estuvo muerto una vez sin que nadie lo notara.
 *
 * ⚠️ **La trampa de foco filtra por visibilidad al CICLAR pero NO al elegir el primer foco**, igual que el
 * `a11yPanel` que sustituye: es la conducta que `SidebarAccountVisibilityTest` documenta y protege, y cambiarla
 * aquí sería cambiar el cajón, que es lo que esta tanda promete no hacer.
 */

const FOCUSABLE = 'a[href],button:not([disabled]),input:not([disabled]):not([type=hidden]),select:not([disabled]),textarea:not([disabled]),[tabindex]:not([tabindex="-1"])';

// ⚠️ **`createShell()` NO vive aquí, y no es por orden**: construir la carcasa solo hace falta en una página
// que no la trae, y este módulo lo carga TODA página pública del producto. Vive en `cajon/standalone.js`, que
// se trae con `import()` desde `bootSpaEngine()`. Medido: metido aquí, el JS de la entrada pasaba de 26 a
// 27,3 kB y `SidebarBundleBudgetTest` lo cazó.

/** La clase de modo que toca, o `''` si el modo llega vacío (el controlador ya lo normaliza a «catalog»). */
export function modeClass(mode) {
    return mode ? `is-${mode}` : '';
}

/**
 * A dónde salta el foco con Tab para no salirse del panel; `null` si el navegador puede seguir solo.
 *
 * @param {{key: string, shiftKey: boolean}} event
 * @param {Array<object>} items   los enfocables VISIBLES del panel, en orden
 * @param {object|null} active    el elemento que tiene el foco
 */
export function trapTarget(event, items, active) {
    if (event.key !== 'Tab' || items.length === 0) return null;

    const first = items[0];
    const last = items[items.length - 1];

    if (event.shiftKey && active === first) return last;
    if (! event.shiftKey && active === last) return first;

    return null;
}

/**
 * @param {() => {isOpen: boolean, mode: string, close: Function}|null} getCajon  se PIDE cada vez: con Alpine,
 *   `window.JumpWeb.cajon` pasa a ser el proxy reactivo tras `alpine:init`.
 * @param {Document} doc
 * @param {Element|null} construida  una carcasa ya construida (`standalone.js::createShell()`), para colgarla
 *   del documento cuando la página no trae ninguna. Sin ella solo se ADOPTA la que haya, que es lo que hace la
 *   página del producto, donde la pinta Blade.
 * @returns {{root: Element}|null}  `null` si no hay carcasa que adoptar y no se dio ninguna construida.
 */
export function installShell(getCajon, doc = document, construida = null) {
    let root = doc.querySelector('.sidecart');

    if (! root) {
        if (! construida) return null;

        root = construida;
        doc.body.append(root);
    }

    // ⚠️ Idempotente: en una página ajena se llama DOS veces —al cargar, que no encuentra nada, y al abrir,
    // que ya puede construirla—. Sin esta marca, la segunda pasada dejaría dos oyentes por gesto y un clic en
    // el telón cerraría y volvería a cerrar.
    if (root.dataset.jwShell === 'on') return { root };
    root.dataset.jwShell = 'on';

    const panel = root.querySelector('.sidecart__panel');
    let currentMode = '';

    const paintMode = (mode) => {
        const next = modeClass(mode);
        if (next === currentMode) return;

        if (currentMode) panel?.classList.remove(currentMode);
        if (next) panel?.classList.add(next);
        currentMode = next;
    };

    const focusFirst = () => root.querySelector(FOCUSABLE)?.focus();

    const paintOpen = (open) => {
        root.classList.toggle('is-open', open);
        // El foco entra DESPUÉS de que el navegador haya hecho visible la carcasa: un elemento con
        // `visibility: hidden` no se puede enfocar, y pedirlo en el mismo tic no haría nada.
        if (open) (doc.defaultView?.requestAnimationFrame ?? ((fn) => fn()))(focusFirst);
    };

    doc.addEventListener('jw:cajon:open', () => paintOpen(true));
    doc.addEventListener('jw:cajon:close', () => paintOpen(false));
    doc.addEventListener('jw:cajon:mode', (event) => paintMode(event.detail?.mode));

    const close = () => getCajon()?.close();

    root.querySelector('.sidecart__backdrop')?.addEventListener('click', close);
    root.querySelector('.sidecart__close')?.addEventListener('click', close);

    // Escape cierra desde cualquier sitio de la página, como antes (`@keydown.escape.window`)… pero SOLO si
    // está abierto: cerrar un cajón cerrado soltaba una llave del cerrojo que no tenía y anunciaba un cierre
    // que no había ocurrido.
    doc.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && getCajon()?.isOpen) close();
    });

    // ⚠️⚠️ **«Visible» es que SE PUEDE ENFOCAR, no que tenga caja** (medido en navegador el 2026-09-18). El
    // `a11yPanel` que esto sustituye filtraba solo por `offsetParent`, que no ve `visibility: hidden`: en el
    // instante en que el motor acaba de montar, el botón de cerrar es el ÚNICO control enfocable de verdad,
    // pero detrás quedan otros con caja y ocultos. La trampa creía que no estaba en el último, no daba la
    // vuelta, y el navegador —que sí se los salta— sacaba el foco del diálogo al banner de cookies.
    const view = doc.defaultView;
    const canFocus = (el) => el.offsetParent !== null
        && (view?.getComputedStyle?.(el)?.visibility ?? 'visible') !== 'hidden';

    root.addEventListener('keydown', (event) => {
        const items = [...root.querySelectorAll(FOCUSABLE)].filter(canFocus);
        const target = trapTarget(event, items, doc.activeElement);

        if (target) {
            event.preventDefault();
            target.focus();
        }
    });

    // Cómo NACE: el servidor puede haberlo pedido abierto, y el motor puede haber publicado ya su modo.
    // ⚠️⚠️ **Al NACER abierto SÍ se mete el foco** (`[DECIDIDO owner]` 2026-09-18, `DECISIONES #634`). Es la
    // conducta correcta de un diálogo modal: quien llega a `/entradas`, a una puerta de cuenta o de vuelta del
    // banco se encuentra el panel delante, y con teclado o lector de pantalla tiene que poder operarlo sin
    // buscarlo. El `a11yPanel` que esto sustituye QUERÍA hacerlo —su comentario lo decía— y su comprobación
    // (`this.$data.$evaluate`) no existía, así que nunca corrió: no es una conducta nueva, es la que estaba
    // escrita y nunca se ejecutó. Se lleva por delante un cambio visible —el anillo de foco sobre la ×— que el
    // owner vio y aprobó; la captura de `/entradas` cambia por eso y solo por eso.
    const cajon = getCajon();
    paintMode(cajon?.mode);
    if (cajon?.isOpen) paintOpen(true);

    return { root };
}
