import { test, describe, beforeEach } from 'node:test';
import assert from 'node:assert/strict';
import { createPinia, setActivePinia } from 'pinia';
import { ZONES, createNavigation } from '../account/navigation.js';
import { SECTIONS } from '../section.js';
import { useAccountStore } from './account.js';
import { useSectionStore } from './section.js';
import { useConfirmStore } from './confirm.js';

/**
 * La red del store del área de cliente.
 *
 * ⚠️ Lo que aquí se prueba y NO está en `navigation.test.js` son las dos costuras que un módulo plano
 * no puede tener: que el estado que Vue observa **se copie de verdad** (la trampa de `#118`) y que
 * «volver» sin historia **salga de la sección**.
 */

function arranca(zone) {
    setActivePinia(createPinia());

    const store = useAccountStore();
    store.boot(createNavigation(zone ? { zone } : undefined));

    return store;
}

describe('el estado que Vue observa', () => {
    beforeEach(() => setActivePinia(createPinia()));

    test('arranca en el índice y sin historia', () => {
        const store = arranca();

        assert.equal(store.zone, ZONES.HOME);
        assert.equal(store.canBack, false);
    });

    test('⚠️ `zone` y `canBack` se COPIAN en cada movimiento, no se leen de la navegación', () => {
        const store = arranca();

        store.go(ZONES.ORDERS);
        assert.equal(store.zone, ZONES.ORDERS, 'el store se quedó atrás: Vue pintaría la zona vieja');
        assert.equal(store.canBack, true);

        store.back();
        assert.equal(store.zone, ZONES.HOME);
        assert.equal(store.canBack, false);
    });

    test('el store y la navegación NO se desincronizan', () => {
        const store = arranca();

        store.go(ZONES.ORDERS);

        assert.equal(store.zone, store.nav.zone);
        assert.equal(store.canBack, store.nav.canBack);
    });
});

describe('volver', () => {
    beforeEach(() => setActivePinia(createPinia()));

    test('con historia, se queda dentro del área y NO sale de la sección', () => {
        const section = useSectionStore();
        section.showAccount();

        const store = useAccountStore();
        store.boot(createNavigation());
        store.go(ZONES.ORDERS);

        assert.equal(store.back(), true);
        assert.equal(store.zone, ZONES.HOME);
        assert.equal(section.active, SECTIONS.ACCOUNT, 'volver dentro del área no puede sacar de ella');
    });

    test('⚠️ sin historia, SALE a la compra en vez de no hacer nada', () => {
        const section = useSectionStore();
        section.showAccount();

        const store = useAccountStore();
        store.boot(createNavigation());

        assert.equal(store.back(), false);
        assert.equal(section.active, SECTIONS.PURCHASE, 'un «volver» que no responde acaba cerrando el cajón');
        assert.equal(store.zone, ZONES.HOME, 'y el área no se queda sin pantalla para cuando se vuelva');
    });

    test('entrar DIRECTO a una zona: el primer «volver» ya sale', () => {
        const section = useSectionStore();
        section.showAccount();

        const store = useAccountStore();
        store.boot(createNavigation({ zone: ZONES.ORDERS }));

        assert.equal(store.back(), false);
        assert.equal(section.active, SECTIONS.PURCHASE);
    });
});

describe('entrar y salir', () => {
    beforeEach(() => setActivePinia(createPinia()));

    test('reentrar vacía la historia de la visita anterior', () => {
        const store = arranca();
        store.go(ZONES.ORDERS);

        store.enter();

        assert.equal(store.zone, ZONES.HOME);
        assert.equal(store.canBack, false, 'arrastrar el recorrido viejo haría que «volver» sorprendiera');
    });

    test('se puede entrar pidiendo una zona concreta', () => {
        const store = arranca();

        store.enter(ZONES.ORDERS);

        assert.equal(store.zone, ZONES.ORDERS);
        assert.equal(store.canBack, false);
    });

    /**
     * ⚠️⚠️ **Los CTA de alta de la landing siguen pidiendo `register`** (A4a, `acceso-con-codigo.md` §4.11): desde fuera del
     * cajón esa zona retirada abre la PUERTA —crear cuenta es una cara suya—, y el área se muestra. En el índice, un invitado
     * lo vería con los rótulos en blanco.
     */
    test('abrir desde fuera una zona retirada lleva a su sucesora: `register`, a la puerta', () => {
        const store = arranca();
        const section = useSectionStore();

        store.openZone('register');

        assert.equal(store.zone, ZONES.LOGIN);
        assert.equal(section.active, SECTIONS.ACCOUNT);
    });

    test('y `forgot`, retirada en la A4b (`#813`), también a la puerta, sin nada debajo', () => {
        const store = arranca();

        store.openZone('forgot');

        assert.equal(store.zone, ZONES.LOGIN);
        assert.equal(store.canBack, false, 'desde fuera, «volver» sale del área');
    });
});

describe('el código que confirma una acción (A4b, `#813`)', () => {
    beforeEach(() => setActivePinia(createPinia()));

    /** Es de la pantalla donde se pidió: en otra, ofrecería escribir un código que nadie pidió allí. */
    test('se vacía al CAMBIAR de zona', () => {
        const store = arranca(ZONES.SESSIONS);
        const confirm = useConfirmStore();
        Object.assign(confirm, { action: 'close_sessions', shown: true, code: '4829' });

        store.go(ZONES.PRIVACY);

        assert.equal(confirm.isShown('close_sessions'), false);
        assert.equal(confirm.code, '');
    });

    test('y no al quedarse en la misma (lo escrito sigue ahí)', () => {
        const store = arranca(ZONES.SESSIONS);
        const confirm = useConfirmStore();
        Object.assign(confirm, { action: 'close_sessions', shown: true, code: '4829' });

        store.go(ZONES.SESSIONS);

        assert.equal(confirm.code, '4829');
    });
});
