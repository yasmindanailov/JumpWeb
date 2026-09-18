import { test, describe } from 'node:test';
import assert from 'node:assert/strict';
import { sessionGained } from './session-gained.js';

function deps({ alpine = {}, falla = false } = {}) {
    const hecho = [];

    return {
        hecho,
        alpine,
        context: {
            refresh: async () => {
                hecho.push('refresh');
                if (falla) throw new Error('red caída');
            },
        },
        reservations: { invalidate: () => hecho.push('invalidate') },
        // El ANFITRIÓN del cajón (`window.JumpWeb.cajon`), no Alpine: desde F4 · T2 el motor no nombra un
        // framework. La variable sigue llamándose `alpine` porque con Alpine es su proxy reactivo.
        win: { JumpWeb: { cajon: alpine } },
    };
}

describe('cuando el cajón consigue sesión sin recargar', () => {
    test('hace las TRES cosas: avisa, invalida y refresca', async () => {
        const d = deps();

        await sessionGained(d);

        assert.equal(d.alpine.authChanged, true, 'sin esto, cerrar el cajón no recarga y el nav se queda de invitado');
        assert.deepEqual(d.hecho, ['invalidate', 'refresh']);
    });

    /**
     * ⚠️ El aviso a Alpine va PRIMERO y no cuelga de ninguna petición: es un booleano que no puede
     * fallar, y de él depende que cerrar el cajón recargue la página.
     */
    test('avisa a Alpine aunque el refresco falle', async () => {
        const d = deps({ falla: true });

        await assert.rejects(() => sessionGained(d));
        assert.equal(d.alpine.authChanged, true);
        assert.deepEqual(d.hecho, ['invalidate', 'refresh']);
    });

    /** Sin anfitrión —un montaje raro, o un test— no revienta: lo demás sigue haciéndose. */
    test('sin anfitrión no lanza y el resto se hace igual', async () => {
        const d = deps();
        d.win = { JumpWeb: undefined };

        await sessionGained(d);

        assert.deepEqual(d.hecho, ['invalidate', 'refresh']);
    });

    /**
     * ⚠️ **El motor ya no mira a Alpine** (F4 · T2): una página ajena no lo carga. Si alguien devolviera la
     * lectura a `win.Alpine.store('purchase')`, con este `window` —que SÍ tiene Alpine y NO tiene anfitrión—
     * la marca se pondría, y en una landing sin Alpine cerrar el cajón dejaría de recargar sin que nada avisara.
     */
    test('la marca va al anfitrión, no a un store de Alpine que ande por ahí', async () => {
        const d = deps();
        const deAlpine = {};
        d.win = { Alpine: { store: () => deAlpine } };

        await sessionGained(d);

        assert.equal(deAlpine.authChanged, undefined);
    });

    /**
     * ⚠️⚠️ **Invalidar las próximas reservas no es opcional.** `stores/reservations.js::ensure()` pide
     * «solo si no las tiene»: quien entra dentro del embudo y luego abre «Mi cuenta» vería el índice
     * del invitado —vacío— y **nada fallaría**.
     */
    test('invalida las próximas reservas del titular anterior', async () => {
        const d = deps();

        await sessionGained(d);

        assert.ok(d.hecho.includes('invalidate'));
    });
});
