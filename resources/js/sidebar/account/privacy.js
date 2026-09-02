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
 * **Los consentimientos, tal como la pantalla los pinta** (tanda 3 · paso 11).
 *
 * ⚠️ **Ni el nombre del documento ni la fecha se componen aquí**: el servidor publica `type_label`
 * —para que el cliente no lleve su propia tabla de cuatro rótulos, que envejecería sola al añadirse
 * un quinto tipo— y `accepted_label` con la zona horaria de la instalación aplicada.
 *
 * ⚠️⚠️ **Devuelve TROZOS y no una cadena, y esa es la corrección de `#346`.** `#344` añadió la
 * cláusula de retirada al final de la misma línea —«02/09/2026 · v2026-05-23 · retirado el
 * 02/09/2026»— y esa línea la pinta `.account__consent-meta`, que lleva `white-space: nowrap` desde
 * que solo tenía dos trozos. Resultado medido en navegador: **82 px de desborde** en el carril del
 * cajón y una barra de scroll horizontal que aparecía **al pulsar el interruptor**.
 * ▶ *El `nowrap` no estaba mal: dejó de ser cierto cuando alguien alargó lo que envolvía.* Cada
 * trozo sigue siendo INDIVISIBLE —una fecha no se parte por la mitad— y entre trozos ya se puede
 * saltar de línea, que es lo único que faltaba.
 */
export function consentRows(payload, { revokedWord = '' } = {}) {
    return latestPerType(payload?.data ?? []).map(([consent, index]) => ({
        key: consent.type + '-' + index,
        label: consent.type_label,
        // ⚠️⚠️ **Una fila RETIRADA no se puede leer igual que una viva** (art. 7.3, `#344`): sin esta
        // rama, la lista diría «Comunicaciones comerciales · 23/08/2026» encima de un interruptor
        // apagado, que es exactamente la contradicción que la revisión de la spec señaló. La palabra
        // la pone quien llama —este módulo es plano y no lee `lang/`— y la FECHA la compone el
        // servidor con la zona horaria de la instalación.
        revoked: Boolean(consent.revoked_at),
        parts: [
            consent.accepted_label,
            consent.version ? 'v' + consent.version : '',
            consent.revoked_at && revokedWord ? revokedWord + ' ' + (consent.revoked_label ?? '') : '',
        ].map((part) => String(part ?? '').trim()).filter(Boolean),
    }));
}

/**
 * **Un consentimiento, una fila: la ÚLTIMA de cada tipo** (`#346`).
 *
 * ⚠️⚠️ **Esto no oculta nada, y la distinción importa.** Cada vez que el titular vuelve a encender el
 * marketing se escribe una fila nueva —es un hecho nuevo y `#344` lo dejó así a propósito, porque la
 * anterior sigue probando lo que se hizo mientras valía—. Pero la tarjeta se titula «Tus
 * consentimientos» y responde a *«¿a qué estoy apuntado ahora?»*: con cinco vueltas del interruptor
 * decía **cinco veces «Comunicaciones comerciales»**, cuatro tachadas. Medido en navegador.
 * ▶ **El rastro completo sigue existiendo** en la BD (art. 5.2 / 7.1) y viaja entero en el documento
 * de portabilidad (art. 20), que se descarga desde **esta misma tarjeta**. Lo que se colapsa es la
 * lectura, no la prueba.
 *
 * ⚠️ **La «última» sale del ORDEN QUE MANDA EL SERVIDOR**, que ya publica `accepted_at DESC, id
 * DESC` (`MePrivacyController::consents`): aquí no se reordena ni se comparan fechas como texto —dos
 * formatos de fecha localizados no se ordenan comparando cadenas—, se conserva la primera aparición.
 *
 * @return {Array<[object, number]>} cada superviviente con su índice ORIGINAL, que es lo que hace la
 *   clave de Vue estable aunque el colapso cambie de tamaño entre dos cargas.
 */
function latestPerType(rows) {
    const vistos = new Set();

    return rows.reduce((keep, consent, index) => {
        if (vistos.has(consent.type)) return keep;

        vistos.add(consent.type);
        keep.push([consent, index]);

        return keep;
    }, []);
}

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
