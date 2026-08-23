/**
 * El BLOQUEO DE SCROLL del fondo, con **un solo dueño** (`sidebar-spa.md` §6).
 *
 * Cinco superpuestos de la web tapan la página —el cajón de compra, el modal de auth, el cajón del
 * nav móvil, el modal de ofertas y el de «gestionar reserva» de Mis pedidos— y hasta este punto **cada
 * uno escribía `body.no-scroll` por su cuenta**: seis `classList.add/remove` repartidos en tres
 * ficheros. Con un booleano en el `<body>` y varios escritores, el último en cerrar manda.
 *
 * ⚠️ **Y eso es un fallo alcanzable, no una pulcritud**: con el cajón de compra abierto, el bloque de
 * cuenta ofrece «Iniciar sesión», que abre el modal de auth; al cerrar el modal, su `remove()`
 * **desbloqueaba el scroll con el cajón todavía abierto**, y la página se movía por detrás del panel.
 * La SPA lo hace más probable, no menos: el cajón se queda montado.
 *
 * La solución es un CONTADOR con nombre, no un booleano: cada superpuesto pide y suelta su propia
 * llave, y la clase está puesta mientras quede alguna. Quien cierra solo puede soltar la suya.
 *
 * ⚠️ **La llave tiene que ser única por INSTANCIA, no por tipo.** «Mis pedidos» pinta un modal por
 * pedido; con una llave compartida, abrir A, abrir B y cerrar A soltaría el scroll con B abierto —el
 * mismo fallo, dentro de una sola pantalla—.
 *
 * Vive fuera de Alpine a propósito (`CE-6`): así se prueba con `node --test`, sin navegador. El store
 * de Alpine es solo el envoltorio que le da acceso a las plantillas.
 *
 * ⚠️ Y vive en `ui/` y no suelto en `resources/js/` por un motivo medido: el patrón de `npm run test:js`
 * lo expande `sh` **sin globstar**, así que solo alcanza UN nivel de carpeta — un test en la raíz no se
 * ejecutaría nunca y la suite diría «pass». Lo vigila `PrePushGateTest`.
 */

/**
 * Crea el cerrojo.
 *
 * @param {(locked: boolean) => void} apply  aplica el estado (en la web: la clase del `<body>`)
 */
export function createScrollLock(apply) {
    /** Las llaves pedidas y no soltadas. Un `Set` y no un contador: soltar dos veces no descuadra. */
    const held = new Set();

    const sync = () => apply(held.size > 0);

    return {
        /** Pide el bloqueo para `owner`. Idempotente: pedirlo dos veces no obliga a soltarlo dos. */
        lock(owner) {
            held.add(String(owner));
            sync();
        },

        /** Suelta la llave de `owner`. Si otro sigue teniendo la suya, el scroll NO se libera. */
        unlock(owner) {
            held.delete(String(owner));
            sync();
        },

        /** Atajo para los superpuestos que viven de un booleano observado (el cajón del nav). */
        set(owner, locked) {
            if (locked) {
                this.lock(owner);
            } else {
                this.unlock(owner);
            }
        },

        /** Quién lo tiene ahora mismo. Es lo que hace verificable «un solo dueño» sin mirar el DOM. */
        owners() {
            return [...held];
        },
    };
}

/**
 * Instala el cerrojo sobre el `<body>` del documento.
 *
 * ⚠️ **Existe para que la manipulación de la clase viva DENTRO del dueño.** Si el `apply` que toca el
 * DOM se escribiera en `app.js` —que es lo natural—, ese fichero volvería a ser un escritor de
 * `body.no-scroll` y `ScrollLockOwnerTest` no podría distinguir el cableado legítimo del siguiente que
 * se cuele. El núcleo sigue sin conocer el DOM, que es lo que lo hace probable con `node --test`: los
 * tests importan `createScrollLock`, nunca esto.
 */
export function installScrollLock() {
    // ⚠️⚠️ **La clase va en el `<html>` TAMBIÉN, y desde el 2026-08-23**. Con `overflow: hidden` solo
    // en el `<body>` el fondo seguía moviéndose: cuando el elemento que scrollea es el DOCUMENTO —lo
    // normal si el `<body>` no acota su altura, y prácticamente siempre en táctil— esa regla recorta
    // el desbordamiento del body pero no congela el documento. Es el fallo clásico de los
    // superpuestos, y aquí se veía al hacer scroll dentro del cajón: la página de detrás se movía.
    //
    // ⚠️ **Sigue habiendo UN dueño y UNA clase**: lo que cambia es que la pone en dos elementos. Un
    // segundo módulo tocando `no-scroll` sería exactamente lo que `#58` desmontó —seis escritores, y
    // el último en cerrar mandaba— y lo que `ScrollLockOwnerTest` vigila.
    return createScrollLock((locked) => {
        document.documentElement.classList.toggle('no-scroll', locked);
        document.body.classList.toggle('no-scroll', locked);
    });
}
