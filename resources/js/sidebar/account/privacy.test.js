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

    /** El nombre y la fecha llegan compuestos por el servidor: aquí solo se juntan fecha y versión. */
    test('se pintan con lo que el servidor resolvió, en su orden', () => {
        assert.deepEqual(consentRows(payload), [
            { key: 'waiver-0', label: 'Descargo de responsabilidad (waiver)', meta: '01/06/2026 · v2026-06-01', revoked: false },
            { key: 'privacy-1', label: 'Política de privacidad', meta: '23/05/2026 · v2026-05-23', revoked: false },
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
        assert.equal(rows[0].meta, '23/05/2026 · v1 · retirado el 02/09/2026');
    });

    /** Y sin la palabra —quien llama no la pasó— la fila sigue diciendo que está retirada por su bandera. */
    test('sin la palabra, la retirada sigue marcada', () => {
        const rows = consentRows({ data: [{
            type: 'marketing', type_label: 'Comunicaciones comerciales',
            accepted_label: '23/05/2026', version: '1', revoked_at: '2026-09-02T10:00:00+02:00',
        }] });

        assert.equal(rows[0].revoked, true);
        assert.equal(rows[0].meta, '23/05/2026 · v1');
    });

    /**
     * ⚠️ **La clave incluye el índice y no solo el tipo**: nada impide que un titular acepte dos
     * versiones del mismo documento, y dos claves iguales en un `v-for` hacen que Vue reutilice el
     * nodo equivocado — una fila enseñaría la fecha de la otra.
     */
    test('dos consentimientos del mismo tipo no comparten clave', () => {
        const rows = consentRows({ data: [
            { type: 'privacy', type_label: 'Política de privacidad', accepted_label: '01/06/2026', version: '2' },
            { type: 'privacy', type_label: 'Política de privacidad', accepted_label: '23/05/2026', version: '1' },
        ] });

        assert.notEqual(rows[0].key, rows[1].key);
    });

    /** Sin versión no se pinta una «v» suelta, que no diría nada. */
    test('una versión ausente no deja un separador colgando', () => {
        const rows = consentRows({ data: [{ type: 'terms', type_label: 'Términos', accepted_label: '01/06/2026', version: '' }] });

        assert.equal(rows[0].meta, '01/06/2026');
    });

    test('sin datos, no hay filas', () => {
        assert.deepEqual(consentRows(null), []);
        assert.deepEqual(consentRows({ data: [] }), []);
    });
});
