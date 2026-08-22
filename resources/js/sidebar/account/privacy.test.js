import { test, describe } from 'node:test';
import assert from 'node:assert/strict';
import { exportFilename, saveExport } from './privacy.js';

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
