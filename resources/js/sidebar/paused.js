/**
 * El aviso de RESERVAS EN PAUSA (Fase 4 · paso 4.3·3).
 *
 * Cuando la dueña acciona el interruptor, el cajón **sustituye el flujo de compra entero** por un
 * aviso con los canales de contacto. Hasta este paso el motor SPA no sabía nada de esto: seguía
 * vendiendo —catálogo, calendario, cesta y «Ir a pagar»— mientras el motor Livewire enseñaba el
 * aviso. No era fuga de dinero (`ReservationAdmissionPolicy` rechaza en servidor), pero sí la
 * divergencia más visible que la fase tenía abierta.
 *
 * ⚠️ **Lo que este módulo NO decide, y son cuatro cosas**:
 *  - **si hay pausa**: es el bit `reservations_paused` de `GET /booking/status`. ⚠️ El objeto `notice`
 *    viaja SIEMPRE, también con las reservas abiertas, así que su presencia **no** es la señal;
 *  - **qué texto tiene el aviso**: `title` y `message` son ajustes que la dueña edita por idioma en el
 *    panel, y llegan ya resueltos. Los literales de `tickets.paused.*` son solo su respaldo, así que
 *    pintarlos desde el diccionario daría el texto genérico en toda instalación personalizada;
 *  - **qué canales se ofrecen**: van **a la vez, no en cascada** (`DECISIONES #38(g)`, corregido
 *    contra el código porque el spec afirmaba lo contrario). Un `v-else-if` encadenado escondería el
 *    WhatsApp de toda instalación con teléfono, en silencio;
 *  - **cuándo procede el enlace de contacto**: `contact_url` es **el último recurso ya decidido** por
 *    el servidor —`null` en cuanto hay un canal directo—, para que el cliente pinte lo que no sea
 *    nulo sin evaluar ninguna condición.
 *
 * Lo que sí es de aquí: **en qué pasos se enseña** (ningún endpoint lo publica; lo dice el propio
 * `ReservationNotice`) y **componer los dos `href`**, que el servidor no manda hechos.
 */

import { t, tp } from './i18n.js';

/**
 * Los pasos que el aviso TAPA. Nació como espejo de `Purchase::showPausedNotice()`; retirado ese
 * componente en 4.7·2b·3, esta es la única fuente, y quien la vigila es `SidebarPausedParityTest`.
 *
 * ⚠️ **No se puede derivar**: son seis, e incluyen el paso de PAGO (8, que es modo `cart`) y excluyen
 * el 7 —verificación de correo, que `machine.js` ni siquiera tiene—. Los pasos de RESULTADO (6, 9, 10
 * y 11) rinden NORMALES aunque las reservas estén pausadas, porque son acciones **ya iniciadas** que
 * deben poder completarse: un `v-if="paused"` colgado de la raíz taparía la pantalla de «pago
 * confirmado» a quien acaba de pagar. La lista se compara entera contra el servidor en
 * `SidebarPausedParityTest`.
 *
 * @type {ReadonlyArray<number>}
 */
export const NOTICE_STEPS = [1, 2, 3, 4, 5, 8];

/** ¿Toca enseñar el aviso en este paso? */
export function showsNotice(paused, step) {
    return paused === true && NOTICE_STEPS.includes(step);
}

/**
 * El aviso listo para pintar, o `null` si no toca.
 *
 * @param {{status: object|null, step: number, messages: object}} state
 * @returns {{title: string, message: string, ctas: Array<{key: string, href: string, label: string, primary: boolean, external: boolean}>}|null}
 */
export function buildNotice({ status, step, messages = {} }) {
    if (! showsNotice(status?.reservations_paused, step)) {
        return null;
    }

    const notice = status.notice ?? {};

    return {
        title: notice.title ?? '',
        message: notice.message ?? '',
        // ⚠️ Los tres son INDEPENDIENTES, no una cascada. Y el orden es fijo: teléfono, WhatsApp y —solo
        // si el servidor lo mandó— contacto.
        ctas: [
            // ⚠️ **Dos cadenas distintas para el mismo teléfono, y cada una va a un sitio**: `phone_tel`
            // (sin espacios) al enlace y `phone` (tal cual lo escribió la dueña) al texto del botón.
            // Cambiarlas de sitio da «Llamar al +34968123456» o un `tel:` con espacios, y el diff de
            // árbol no ve ninguna de las dos cosas.
            notice.phone_tel
                ? { key: 'call', href: 'tel:' + notice.phone_tel, label: tp(messages, 'paused.call', { phone: notice.phone ?? '' }), primary: true, external: false }
                : null,
            // El número llega en DÍGITOS, sin `+` ni esquema: `wa.me` los rechaza. Y no es el teléfono:
            // es un ajuste propio, que puede ser otro número.
            notice.whatsapp
                ? { key: 'whatsapp', href: 'https://wa.me/' + notice.whatsapp, label: t(messages, 'paused.whatsapp'), primary: false, external: true }
                : null,
            // Llega ya decidido y ABSOLUTO. No se compone ni se condiciona: si viene, se pinta.
            notice.contact_url
                ? { key: 'contact', href: notice.contact_url, label: t(messages, 'paused.contact'), primary: false, external: false }
                : null,
        ].filter(Boolean),
    };
}
