/**
 * **La entrega del documento de portabilidad al titular** (`specs/area-cliente.md` §9, paso 8).
 *
 * `GET /me/export` devuelve el documento, no un fichero adjunto: la cabecera `Content-Disposition` es
 * una decisión de NAVEGADOR, y un cliente nativo quiere el cuerpo. Convertirlo en algo que el titular
 * pueda guardar es, por tanto, trabajo de este cliente — y es lo único que hay aquí.
 *
 * ⚠️ **El DOM se recibe por parámetro**, como el `api` de los stores: es lo que permite ejercer esto
 * con `node --test` en vez de dejarlo como el trozo que solo se prueba a ojo. Es la misma decisión que
 * `DECISIONES #120(k)` tomó con el cliente HTTP tras equivocarse primero.
 *
 * Módulo PLANO, sin Vue (`CE-6`).
 */

/** Cuatro espacios: el MISMO sangrado que `JSON_PRETTY_PRINT` de PHP, para que los dos ficheros —el de la web y el del cajón— se lean igual. */
const INDENT = 4;

/**
 * El nombre del fichero, derivado del propio documento.
 *
 * ⚠️ **Sale de `exported_at` y no de la fecha del navegador**, y la diferencia importa: el sello lo
 * pone el servidor con la zona horaria de la instalación, así que un titular que descargue a las
 * 00:30 desde otro huso no acaba con un fichero fechado el día anterior al que el documento declara.
 */
export function exportFilename(exportedAt) {
    const day = String(exportedAt ?? '').slice(0, 10);

    return 'mis-datos-' + (/^\d{4}-\d{2}-\d{2}$/.test(day) ? day : 'export') + '.json';
}

/**
 * Entrega el documento como fichero descargado. Devuelve el nombre con el que se guardó.
 *
 * ⚠️ **Se revoca la URL del blob al terminar**: sin eso el documento —que es la PII más densa del
 * producto— se queda vivo en memoria del navegador hasta que se cierre la pestaña, y accesible por su
 * URL para cualquier script de la página.
 */
export function saveExport(data, { doc = globalThis.document, urls = globalThis.URL, blob = globalThis.Blob } = {}) {
    const filename = exportFilename(data?.exported_at);
    const href = urls.createObjectURL(new blob([JSON.stringify(data, null, INDENT)], { type: 'application/json' }));
    const link = doc.createElement('a');

    link.href = href;
    link.download = filename;

    // Adjuntar antes de pulsar: un `<a>` suelto no dispara la descarga en todos los navegadores.
    doc.body.appendChild(link);
    link.click();
    link.remove();
    urls.revokeObjectURL(href);

    return filename;
}
