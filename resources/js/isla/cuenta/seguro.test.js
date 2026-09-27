import { test, describe } from 'node:test';
import assert from 'node:assert/strict';
import { computed, effect, ref } from 'vue';
import { ROTO, estaRoto, protegido } from './seguro.js';

/** Cada bloque de Mi cuenta, protegido (T5f, spec §4.13): el que revienta deja su hueco y el resto sigue. */

describe('los datos de un bloque, protegidos', () => {
    test('lo que se compone bien, tal cual', () => {
        assert.deepEqual(protegido('proxima', () => ({ a: 1 }), { avisar: () => assert.fail('no avisa') }), { a: 1 });
        assert.equal(protegido('otra-vez', () => null), null);
    });

    test('lo que revienta sale ROTO y se apunta con su nombre y su mensaje', () => {
        const apuntes = [];
        const x = protegido('proxima', () => { throw new RangeError('Invalid time value'); }, { avisar: (n, e) => apuntes.push([n, e.message]) });

        assert.equal(estaRoto(x), true);
        assert.equal(x, ROTO);
        assert.deepEqual(apuntes, [['proxima', 'Invalid time value']]);
    });

    test('con su propio valor de roto (la línea de arriba se queda vacía, sin hueco)', () => {
        assert.equal(protegido('linea', () => { throw new Error('x'); }, { roto: '', avisar: () => {} }), '');
    });

    test('⚠️ un `computed` se protege DENTRO: Vue lo reevalúa al mirar si repinta, fuera del `try` de quien lo lee', () => {
        const avisar = () => {};
        const leer = (fecha) => { if (fecha === 'rota') throw new RangeError('Invalid time value'); return fecha; };

        // Protegido solo donde se LEE (lo que cazó la sonda): el repintado revienta igual (`isDirty` → `refreshComputed`).
        const fechaA = ref('2026-09-26');
        const fragil = computed(() => leer(fechaA.value));
        const fuera = computed(() => protegido('proxima', () => fragil.value, { avisar }));
        effect(() => fuera.value);
        assert.throws(() => { fechaA.value = 'rota'; }, RangeError);

        // Protegido DENTRO: el repintado sigue, y el bloque sale roto.
        const fechaB = ref('2026-09-26');
        const dentro = computed(() => protegido('proxima', () => leer(fechaB.value), { avisar }));
        const pintado = [];
        effect(() => { pintado.push(dentro.value); });
        assert.doesNotThrow(() => { fechaB.value = 'rota'; });
        assert.deepEqual(pintado, ['2026-09-26', ROTO]);
    });

    test('un bloque roto no rompe al de al lado', () => {
        const avisar = () => {};
        const inicio = {
            proxima: protegido('proxima', () => { throw new Error('x'); }, { avisar }),
            otras: protegido('otras', () => ({ otras: [] }), { avisar }),
        };

        assert.equal(estaRoto(inicio.proxima), true);
        assert.deepEqual(inicio.otras, { otras: [] });
    });
});
