/**
 * **Adelantar la compra** (`#783`): los trozos que la compra necesita al abrirse (`config.precargar`, del servidor:
 * `Http\Instancia\PrecargaDeCompra`) se piden en segundo plano cuando la página ya ha cargado y está ociosa, con
 * `modulepreload` —se bajan y se compilan, no se ejecutan—; al pulsar, el `import()` del cajón los encuentra. Medido en 4G:
 * sin esto, la compra aparecía a los 2s de tocar. Nunca con «ahorro de datos» ni en 2G: ahí se baja al pulsar, como antes.
 */

/** Si esta conexión admite adelantar descargas. Puro: recibe `navigator.connection` (o nada). */
export function puedePrecargar(conexion) {
    return ! conexion?.saveData && ! /(^|-)2g$/.test(conexion?.effectiveType ?? '');
}

export function precargarCompra(urls, { doc = document, win = window } = {}) {
    if (! urls?.length || ! puedePrecargar(win.navigator?.connection)) return;
    const pedir = () => urls.forEach((href) => {
        if ([...doc.querySelectorAll('link[rel="modulepreload"]')].some((l) => l.href === href)) return;
        const l = doc.createElement('link');

        l.rel = 'modulepreload';
        l.href = href;
        doc.head.append(l);
    });
    const ociosa = () => (win.requestIdleCallback ? win.requestIdleCallback(pedir, { timeout: 3000 }) : win.setTimeout(pedir, 1500));

    if (doc.readyState === 'complete') ociosa();
    else win.addEventListener('load', ociosa, { once: true });
}
