import { test, describe } from 'node:test';
import assert from 'node:assert/strict';
import { browserShareDeps, shareOrCopy } from './share-link.js';

/**
 * **Compartir o copiar el enlace del justificante** (`specs/waiver-por-reserva.md` §13.8).
 *
 * La regla vive aquí y no en el componente porque **decidir cuál de los dos caminos toca y qué pasa
 * cuando falla no es pintar**: un árbol dice qué se pintó, no qué rama se eligió.
 */
describe('compartir o copiar', () => {
    test('en un teléfono comparte, y ni siquiera toca el portapapeles', async () => {
        const tocado = [];
        const estado = await shareOrCopy('https://x/autorizacion/7', {
            share: async (d) => { tocado.push(['share', d.url]); },
            copy: async () => { tocado.push(['copy']); },
        });

        assert.equal(estado, 'shared');
        assert.deepEqual(tocado, [['share', 'https://x/autorizacion/7']], 'compartir y copiar a la vez sería pegarlo dos veces');
    });

    test('sin `share` cae al portapapeles', async () => {
        const estado = await shareOrCopy('https://x/7', { share: null, copy: async () => {} });

        assert.equal(estado, 'copied');
    });

    /**
     * ❗ **Cerrar la hoja de compartir NO es un fallo**: es un «no, gracias». Tratarlo como error
     * enseñaría un mensaje a quien acaba de cambiar de idea — y, peor, copiaría al portapapeles algo
     * que la persona decidió no mandar.
     */
    test('cancelar la hoja de compartir no dice nada y NO copia', async () => {
        let copiado = false;
        const abort = Object.assign(new Error('cancelado'), { name: 'AbortError' });
        const estado = await shareOrCopy('https://x/7', {
            share: async () => { throw abort; },
            copy: async () => { copiado = true; },
        });

        assert.equal(estado, 'cancelled');
        assert.equal(copiado, false);
    });

    test('un fallo REAL de `share` sí cae al portapapeles', async () => {
        // La diferencia con el caso de arriba es el `name`, no el mensaje —que cambia con el idioma
        // del sistema—. Sin esta distinción, o se pierde el respaldo o se copia lo que se canceló.
        const estado = await shareOrCopy('https://x/7', {
            share: async () => { throw new Error('NotAllowedError'); },
            copy: async () => {},
        });

        assert.equal(estado, 'copied');
    });

    test('si las dos fallan se DICE, y el input sigue siendo la salida', async () => {
        assert.equal(await shareOrCopy('https://x/7', { share: null, copy: async () => { throw new Error('sin permiso'); } }), 'failed');
        assert.equal(await shareOrCopy('https://x/7', {}), 'failed');
        assert.equal(await shareOrCopy('', { copy: async () => {} }), 'failed', 'sin enlace no hay nada que compartir');
    });

    test('las dependencias del navegador se leen sin reventar donde no existen', () => {
        // ⚠️ En el Node del contenedor `navigator` no existe: un módulo que lo leyera del global no se
        // podría probar. Y leer `navigator.clipboard` LANZA en algunos contextos, como `localStorage`.
        assert.deepEqual(browserShareDeps(null), { share: null, copy: null });
        assert.deepEqual(browserShareDeps(undefined), { share: null, copy: null });

        // ⚠️ `share` solo se ofrece si además hay `canShare`: algunos navegadores de escritorio
        // declaran `share` y abren un diálogo inútil.
        assert.equal(browserShareDeps({ share: () => {} }).share, null);
        assert.notEqual(browserShareDeps({ share: () => {}, canShare: () => true }).share, null);

        const conPortapapeles = browserShareDeps({ clipboard: { writeText: () => {} } });
        assert.notEqual(conPortapapeles.copy, null);
    });
});
