import { test, describe } from 'node:test';
import assert from 'node:assert/strict';
import { consentRows, exportFilename, saveExport } from './privacy.js';

/**
 * La red de la entrega del documento de portabilidad.
 *
 * ⚠️ **Existe porque el DOM se recibe por parámetro.** Sin esa decisión, esto sería el trozo del paso
 * 8 que solo se comprueba a ojo — y lo que aquí se vigila no se ve mirando: que la URL del blob **se
 * revoque**, que el fichero se llame como el documento dice y que el sangrado sea el de PHP.
 */

/** Un DOM de mentira, con lo justo que `saveExport` toca — y con memoria de lo que le pasó. */
function fakeDom() {
    const seen = { created: [], appended: [], clicked: 0, removed: 0, revoked: [] };
    const link = {
        href: '', download: '',
        click() { seen.clicked++; },
        remove() { seen.removed++; },
    };

    return {
        seen,
        link,
        dom: {
            doc: {
                body: { appendChild: (node) => seen.appended.push(node) },
                createElement: (tag) => { seen.created.push(tag); return link; },
            },
            urls: {
                createObjectURL: () => 'blob:jumpweb/1',
                revokeObjectURL: (href) => seen.revoked.push(href),
            },
            blob: class { constructor(parts, options) { this.parts = parts; this.options = options; } },
        },
    };
}

describe('el nombre del fichero', () => {
    test('sale del sello del DOCUMENTO, no del reloj del navegador', () => {
        assert.equal(exportFilename('2026-08-22T16:07:39+00:00'), 'mis-datos-2026-08-22.json');
    });

    /**
     * ⚠️ Sin sello no se inventa una fecha: un nombre con la del navegador diría que el documento es
     * de hoy cuando el servidor no lo ha dicho. Mejor un nombre neutro que uno que miente.
     */
    test('sin sello utilizable, un nombre neutro', () => {
        for (const raro of [undefined, null, '', 'ayer', 2026]) {
            assert.equal(exportFilename(raro), 'mis-datos-export.json');
        }
    });
});

describe('la entrega', () => {
    test('compone el enlace, lo pulsa y lo retira', () => {
        const { seen, link, dom } = fakeDom();

        const name = saveExport({ exported_at: '2026-08-22T16:07:39+00:00', profile: {} }, dom);

        assert.equal(name, 'mis-datos-2026-08-22.json');
        assert.deepEqual(seen.created, ['a']);
        assert.equal(link.download, 'mis-datos-2026-08-22.json');
        assert.equal(seen.appended.length, 1, 'un `<a>` suelto no dispara la descarga en todos los navegadores');
        assert.equal(seen.clicked, 1);
        assert.equal(seen.removed, 1, 'el enlace se ha quedado colgando del documento');
    });

    /**
     * ⚠️ **La revocación es la mitad que importa.** Sin ella el documento —la PII más densa del
     * producto— se queda vivo en memoria del navegador y accesible por su URL para cualquier script
     * de la página, hasta que se cierre la pestaña.
     */
    test('revuelve la URL del blob al terminar', () => {
        const { seen, dom } = fakeDom();

        saveExport({ exported_at: '2026-08-22T00:00:00+00:00' }, dom);

        assert.deepEqual(seen.revoked, ['blob:jumpweb/1']);
    });

    /** El mismo sangrado que `JSON_PRETTY_PRINT` de PHP: los dos ficheros se leen igual. */
    test('el contenido va con cuatro espacios, como el de la web', () => {
        const { link, dom } = fakeDom();
        let written = null;

        dom.urls.createObjectURL = (blob) => { written = blob.parts[0]; return 'blob:x'; };

        saveExport({ exported_at: '2026-08-22T00:00:00+00:00', roles: ['staff'] }, dom);

        assert.match(written, /^\{\n {4}"exported_at"/);
        assert.equal(link.download, 'mis-datos-2026-08-22.json');
    });
});

describe('los consentimientos', () => {
    const payload = {
        data: [
            { type: 'waiver', type_label: 'Descargo de responsabilidad (waiver)', accepted_at: '2026-06-01T10:00:00+02:00', accepted_label: '01/06/2026', version: '2026-06-01' },
            { type: 'privacy', type_label: 'Política de privacidad', accepted_at: '2026-05-23T10:00:00+02:00', accepted_label: '23/05/2026', version: '2026-05-23' },
        ],
        meta: { total: 2 },
    };

    /** El nombre y la fecha llegan compuestos por el servidor: aquí solo se ordenan los trozos. */
    test('se pintan con lo que el servidor resolvió, en su orden', () => {
        assert.deepEqual(consentRows(payload), [
            { key: 'waiver-0', label: 'Descargo de responsabilidad (waiver)', parts: ['01/06/2026', 'v2026-06-01'], revoked: false },
            { key: 'privacy-1', label: 'Política de privacidad', parts: ['23/05/2026', 'v2026-05-23'], revoked: false },
        ]);
    });

    /**
     * ⚠️⚠️ **Una fila RETIRADA no puede leerse igual que una viva** (art. 7.3, `#344`): sin esto, la
     * lista diría «Comunicaciones comerciales · 23/08/2026» encima de un interruptor apagado.
     */
    test('un consentimiento retirado lo dice, con su fecha', () => {
        const rows = consentRows({ data: [{
            type: 'marketing', type_label: 'Comunicaciones comerciales',
            accepted_label: '23/05/2026', version: '1',
            revoked_at: '2026-09-02T10:00:00+02:00', revoked_label: '02/09/2026',
        }] }, { revokedWord: 'retirado el' });

        assert.equal(rows[0].revoked, true);
        assert.deepEqual(rows[0].parts, ['23/05/2026', 'v1', 'retirado el 02/09/2026']);
    });

    /**
     * ⚠️⚠️ **LA REGRESIÓN DE `#346`, y es la razón de que esto sean TROZOS.**
     *
     * `#344` metió la cláusula de retirada al final de la MISMA línea, y esa línea la pinta
     * `.account__consent-meta` con `white-space: nowrap` —puesto cuando solo había dos trozos—.
     * Medido en navegador: **82 px de desborde** en el carril del cajón y una barra de scroll
     * horizontal que salía al pulsar el interruptor. Si alguien vuelve a juntarlos en una cadena
     * única, el `nowrap` no puede partirla por ninguna parte y el defecto vuelve entero.
     */
    test('la retirada NO se pega a la fecha en una sola cadena indivisible', () => {
        const rows = consentRows({ data: [{
            type: 'marketing', type_label: 'Comunicaciones comerciales',
            accepted_label: '02/09/2026', version: '2026-05-23',
            revoked_at: '2026-09-02T10:00:00+02:00', revoked_label: '02/09/2026',
        }] }, { revokedWord: 'retirado el' });

        assert.equal(rows[0].parts.length, 3, 'la fila retirada tiene que llegar en tres trozos separables');

        for (const part of rows[0].parts) {
            assert.ok(part.length <= 24, `«${part}» es un trozo demasiado largo para caber en el carril`);
        }
    });

    /** Y sin la palabra —quien llama no la pasó— la fila sigue diciendo que está retirada por su bandera. */
    test('sin la palabra, la retirada sigue marcada', () => {
        const rows = consentRows({ data: [{
            type: 'marketing', type_label: 'Comunicaciones comerciales',
            accepted_label: '23/05/2026', version: '1', revoked_at: '2026-09-02T10:00:00+02:00',
        }] });

        assert.equal(rows[0].revoked, true);
        assert.deepEqual(rows[0].parts, ['23/05/2026', 'v1']);
    });

    /**
     * ⚠️⚠️ **UN CONSENTIMIENTO, UNA FILA: la última de cada tipo** (`#346`).
     *
     * Cada vuelta del interruptor de marketing escribe una fila nueva —es un hecho nuevo, y `#344`
     * lo dejó así a propósito—. Pero esta tarjeta responde a «¿a qué estoy apuntado ahora?», y con
     * cinco vueltas decía **cinco veces «Comunicaciones comerciales»**, cuatro tachadas (medido en
     * navegador). El rastro completo sigue en la BD y en el documento de portabilidad, que se
     * descarga desde esta misma tarjeta: se colapsa la lectura, no la prueba.
     */
    test('un tipo repetido se colapsa en su fila más reciente', () => {
        const rows = consentRows({ data: [
            // El servidor los manda `accepted_at DESC, id DESC`: el primero es el vigente.
            { type: 'marketing', type_label: 'Comunicaciones comerciales', accepted_label: '02/09/2026', version: '1' },
            { type: 'marketing', type_label: 'Comunicaciones comerciales', accepted_label: '01/09/2026', version: '1', revoked_at: '2026-09-01T10:00:00+02:00' },
            { type: 'marketing', type_label: 'Comunicaciones comerciales', accepted_label: '31/08/2026', version: '1', revoked_at: '2026-08-31T10:00:00+02:00' },
            { type: 'privacy', type_label: 'Política de privacidad', accepted_label: '23/05/2026', version: '1' },
        ] });

        assert.equal(rows.length, 2, 'tres filas de marketing tienen que quedar en una');
        assert.equal(rows[0].revoked, false, 'la que sobrevive es la VIGENTE, no una retirada');
        assert.deepEqual(rows[0].parts, ['02/09/2026', 'v1']);
        assert.equal(rows[1].label, 'Política de privacidad');
    });

    /**
     * ⚠️ **La clave incluye el índice ORIGINAL y no solo el tipo**: dos claves iguales en un `v-for`
     * hacen que Vue reutilice el nodo equivocado. Y el índice tiene que ser el de la lista del
     * servidor, no el de la lista ya colapsada: si fuera el del colapso, aparecer o desaparecer una
     * fila renumeraría a las demás y Vue reutilizaría nodos entre tipos distintos.
     */
    test('las claves siguen siendo únicas y llevan el índice original', () => {
        const rows = consentRows({ data: [
            { type: 'marketing', type_label: 'Comunicaciones comerciales', accepted_label: '02/09/2026', version: '2' },
            { type: 'marketing', type_label: 'Comunicaciones comerciales', accepted_label: '01/09/2026', version: '1' },
            { type: 'privacy', type_label: 'Política de privacidad', accepted_label: '23/05/2026', version: '1' },
        ] });

        assert.deepEqual(rows.map((r) => r.key), ['marketing-0', 'privacy-2']);
        assert.equal(new Set(rows.map((r) => r.key)).size, rows.length);
    });

    /** Sin versión no se pinta una «v» suelta, que no diría nada — ni un trozo vacío que el CSS
     *  convertiría en un separador colgando, porque el «·» lo dibuja la hoja entre trozos. */
    test('una versión ausente no deja un separador colgando', () => {
        const rows = consentRows({ data: [{ type: 'terms', type_label: 'Términos', accepted_label: '01/06/2026', version: '' }] });

        assert.deepEqual(rows[0].parts, ['01/06/2026']);
    });

    test('sin datos, no hay filas', () => {
        assert.deepEqual(consentRows(null), []);
        assert.deepEqual(consentRows({ data: [] }), []);
    });
});
