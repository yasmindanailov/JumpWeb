import { test, describe } from 'node:test';
import assert from 'node:assert/strict';
import { NOTICE_STEPS, buildNotice, showsNotice } from './paused.js';

/**
 * Fase 4 · paso 4.3·3 — la red del aviso de pausa (criterio CE-6).
 *
 * Aquí se fija la CONDUCTA; que coincida con el servidor lo comprueba `SidebarPausedParityTest`
 * (mapa de pasos, enlaces, títulos por idioma). Lo que este fichero cubre y esa paridad no alcanza
 * son los estados degradados: sin respuesta del endpoint, con el aviso a medias, con las reservas
 * abiertas.
 */

const MESSAGES = {
    paused: {
        call: 'Llamar al :phone',
        whatsapp: 'Escríbenos por WhatsApp',
        contact: 'Ir a contacto',
    },
};

const status = (overrides = {}) => ({
    reservations_paused: true,
    notice: {
        title: 'Estamos en mantenimiento',
        message: 'Disculpa las molestias.',
        phone: null, phone_tel: null, whatsapp: null, contact_url: null,
        ...overrides,
    },
});

describe('cuándo se tapa el flujo', () => {
    test('los seis pasos del embudo, y solo esos', () => {
        assert.deepEqual([...NOTICE_STEPS], [1, 2, 3, 4, 5, 8]);
    });

    /**
     * ⚠️ **Los pasos de RESULTADO no se tapan nunca.** Son acciones ya iniciadas: taparlas dejaría a
     * quien vuelve de la pasarela mirando un cartel de mantenimiento en vez de su reserva.
     */
    test('los pasos de resultado quedan fuera aunque haya pausa', () => {
        for (const step of [6, 7, 9, 10, 11]) {
            assert.equal(showsNotice(true, step), false, `el paso ${step} no se tapa`);
        }
    });

    test('sin pausa no se tapa nada', () => {
        for (const step of [1, 2, 3, 4, 5, 8]) {
            assert.equal(showsNotice(false, step), false);
        }
    });

    /**
     * ⚠️ **El bit de pausa es `reservations_paused` y nada más.** El objeto `notice` viaja SIEMPRE,
     * también con las reservas abiertas, así que su presencia no es la señal: quien se guíe por él
     * tapa el flujo para siempre.
     */
    test('un aviso sin pausa no tapa nada', () => {
        // El caso EXACTO que engaña: reservas ABIERTAS y `contact_url` con valor. Medido en la API:
        // sin ningún canal directo configurado, el enlace de contacto viaja aunque no haya pausa.
        const abierto = { ...status({ contact_url: 'https://jump.test/contacto' }), reservations_paused: false };

        assert.equal(buildNotice({ status: abierto, step: 1, messages: MESSAGES }), null);
        assert.equal(buildNotice({ status: { ...status(), reservations_paused: false }, step: 1, messages: MESSAGES }), null);
    });

    /** Sin respuesta del endpoint no se inventa una pausa: el cajón sigue vendiendo, como la web. */
    test('sin respuesta del endpoint no hay aviso', () => {
        assert.equal(buildNotice({ status: null, step: 1, messages: MESSAGES }), null);
        assert.equal(buildNotice({ status: {}, step: 1, messages: MESSAGES }), null);
    });
});

describe('los canales', () => {
    /**
     * ⚠️ **A la vez, no en cascada.** Un `v-else-if` encadenado escondería el WhatsApp de toda
     * instalación con teléfono, en silencio — y el diff de árbol no lo vería, porque los dos enlaces
     * llevan las mismas clases.
     */
    test('el teléfono y el WhatsApp se ofrecen LOS DOS', () => {
        const notice = buildNotice({
            status: status({ phone: '968 22 22 22', phone_tel: '968222222', whatsapp: '34600112233' }),
            step: 1, messages: MESSAGES,
        });

        assert.deepEqual(notice.ctas.map((c) => c.key), ['call', 'whatsapp']);
    });

    /** Solo el de llamar es el canal principal: es el único con `btn--ink`. */
    test('solo el de llamar es el principal', () => {
        const notice = buildNotice({
            status: status({ phone: '968', phone_tel: '968', whatsapp: '34600112233' }),
            step: 1, messages: MESSAGES,
        });

        assert.deepEqual(notice.ctas.map((c) => c.primary), [true, false]);
        assert.deepEqual(notice.ctas.map((c) => c.external), [false, true]);
    });

    /**
     * ⚠️ **`contact_url` es el último recurso YA DECIDIDO por el servidor**, no «la página de
     * contacto»: llega a `null` en cuanto hay un canal directo. El cliente pinta lo que no sea nulo y
     * no evalúa ninguna condición — reimplementarla abre la puerta a que los dos motores discrepen.
     */
    test('el enlace de contacto se pinta si viene, y no se condiciona', () => {
        const sinCanales = buildNotice({
            status: status({ contact_url: 'https://jump.test/contacto' }),
            step: 1, messages: MESSAGES,
        });

        assert.deepEqual(sinCanales.ctas.map((c) => c.key), ['contact']);
        assert.equal(sinCanales.ctas[0].href, 'https://jump.test/contacto');
    });

    /** El número de WhatsApp llega en dígitos, sin `+` ni esquema: `wa.me` los rechaza. */
    test('el enlace de WhatsApp se compone sobre los dígitos que manda el servidor', () => {
        const notice = buildNotice({ status: status({ whatsapp: '34600112233' }), step: 1, messages: MESSAGES });

        assert.equal(notice.ctas[0].href, 'https://wa.me/34600112233');
    });

    /**
     * ⚠️ **Dos cadenas para el mismo teléfono, cada una a su sitio**: la del enlace va sin espacios y
     * la del texto tal cual la escribió la dueña. Intercambiarlas da «Llamar al 968222222» o un `tel:`
     * con espacios, y el diff de árbol no ve ninguna de las dos cosas.
     */
    test('el enlace usa una forma del teléfono y el texto la otra', () => {
        const notice = buildNotice({
            status: status({ phone: '968 22 22 22', phone_tel: '968222222' }),
            step: 1, messages: MESSAGES,
        });

        assert.equal(notice.ctas[0].href, 'tel:968222222');
        assert.equal(notice.ctas[0].label, 'Llamar al 968 22 22 22');
    });

    /** Los rótulos son claves ANIDADAS del diccionario: leerlas planas las deja vacías en silencio. */
    test('los rótulos salen del subarray `paused` del diccionario', () => {
        const notice = buildNotice({ status: status({ whatsapp: '34600112233' }), step: 1, messages: MESSAGES });

        assert.equal(notice.ctas[0].label, 'Escríbenos por WhatsApp');
    });
});

describe('el texto del aviso', () => {
    /**
     * ⚠️ Título y mensaje son ajustes del PANEL por idioma, y llegan ya resueltos. Los literales de
     * `tickets.paused.*` son solo su respaldo, así que el módulo NO los toca.
     */
    test('salen de la respuesta, no del diccionario', () => {
        const notice = buildNotice({
            status: status({ title: 'Volvemos el lunes', message: 'Estamos de obras.' }),
            step: 1, messages: MESSAGES,
        });

        assert.equal(notice.title, 'Volvemos el lunes');
        assert.equal(notice.message, 'Estamos de obras.');
    });
});
