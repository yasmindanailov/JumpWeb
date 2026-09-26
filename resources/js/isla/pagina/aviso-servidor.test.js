import { test, describe } from 'node:test';
import assert from 'node:assert/strict';
import { loTomaUnaCapa, tomarAvisoDelServidor } from './aviso-servidor.js';

/** T5e·2 (`#779`): el aviso que dejó el servidor, tomado UNA vez por quien se abre primero. */

/** Un documento con el `<script>` de la isla de la página (o sin él), y su `<body>`. */
function documento({ config, texto, zona = '' } = {}) {
    const el = { textContent: texto ?? JSON.stringify({ config }), dataset: {} };

    return { el, getElementById: (id) => (id === 'jw-isla-pagina' && (config !== undefined || texto !== undefined) ? el : null), body: { dataset: zona ? { accountZone: zona } : {} } };
}

describe('tomar el aviso del servidor', () => {
    test('la primera vez, su texto y su tono; la segunda, nada (lo tomó otro)', () => {
        const doc = documento({ config: { aviso: { texto: 'Hemos vinculado tu cuenta de Google.', tono: 'success' } } });

        assert.deepEqual(tomarAvisoDelServidor(doc), { texto: 'Hemos vinculado tu cuenta de Google.', tono: 'success' });
        assert.equal(doc.el.dataset.avisoTomado, '1');
        assert.equal(tomarAvisoDelServidor(doc), null);
    });

    test('sin aviso, sin isla o con el JSON roto, nada, y no se marca', () => {
        const sinAviso = documento({ config: { aviso: null } });

        assert.equal(tomarAvisoDelServidor(sinAviso), null);
        assert.equal(sinAviso.el.dataset.avisoTomado, undefined);
        assert.equal(tomarAvisoDelServidor(documento()), null);
        assert.equal(tomarAvisoDelServidor(documento({ texto: '{roto' })), null);
        assert.equal(tomarAvisoDelServidor(undefined), null);
    });

    test('con `si`, solo se toma si le vale a quien lo pide; si no, se queda para el siguiente', () => {
        const doc = documento({ config: { aviso: { texto: 'Esa cuenta de Google ya está vinculada a otra.', tono: 'danger' } } });

        assert.equal(tomarAvisoDelServidor(doc, { si: (a) => a.tono === 'success' }), null, 'la isla solo confirma');
        assert.equal(doc.el.dataset.avisoTomado, undefined);
        assert.equal(tomarAvisoDelServidor(doc).tono, 'danger', 'Mi cuenta, al abrirse, sí');
    });

    test('un tono que no es de los tres se lee como informativo (nunca como éxito)', () => {
        assert.equal(tomarAvisoDelServidor(documento({ config: { aviso: { texto: 'x', tono: 'otro' } } })).tono, 'info');
    });
});

describe('¿lo toma una capa?', () => {
    test('Mi cuenta por su enlace o por una puerta, y la compra que vuelve de Google', () => {
        assert.equal(loTomaUnaCapa({ hash: '#mi-cuenta', search: '' }, documento()), true);
        assert.equal(loTomaUnaCapa({ hash: '#mi-cuenta/acceso', search: '' }, documento()), true);
        assert.equal(loTomaUnaCapa({ hash: '', search: '?compra=reanudar' }, documento()), true);
        assert.equal(loTomaUnaCapa({ hash: '', search: '' }, documento({ zona: 'orders' })), true);
    });

    test('sin ninguna, la isla', () => {
        assert.equal(loTomaUnaCapa({ hash: '#precio', search: '?utm_source=x' }, documento()), false);
        assert.equal(loTomaUnaCapa({ hash: '#mi-cuentas', search: '' }, documento()), false, 'solo el enlace de Mi cuenta');
    });
});
