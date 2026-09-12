/**
 * El PIE dinámico del cajón (Fase 4 · paso 4.3·2).
 *
 * Es la barra pegada al fondo del panel, y no es decoración: **es la navegación del embudo**. Su CTA
 * cambia en cada paso —«Ir al carrito» en el catálogo, «Continuar» en el calendario, «Añadir al
 * carrito» en la hora, «Ir a pagar» en la cesta— y con él viaja el total. Sin pie, el cajón SPA
 * auto-avanzaba del calendario a la hora, que es una divergencia con la web y no un atajo.
 *
 * **No calcula dinero** (`PAY-12`): recibe importes ya tarificados por el servidor y los formatea.
 * Las tres cosas que un pie honesto NO puede hacer, y que aquí se respetan:
 *  - **no sumar** el total del paso 3: lo publica `POST catalog/products/{id}/addons` como
 *    `line.total_cents` desde 4.3·2, porque componerlo con `subtotal_cents + addons_total_cents`
 *    mezcla dos recorridos distintos del servidor;
 *  - **no restar** el desglose: `deposit_cents` y `gate_remainder_cents` vienen publicados, y
 *    reconstruir el segundo restando sería reimplementar la Opción A de #225 (los complementos de una
 *    línea con señal van íntegros al parque);
 *  - **no pluralizar a mano** el rótulo de la barra-carrito: lo resuelve `i18n.js` con el selector de
 *    Laravel.
 *
 * ⚠️ El rótulo del desglose **no es el mismo en el paso 3 y en la cesta**: allí es «Pagas ahora
 * (señal)» —producto único, y la señal explica por qué se cobra menos que el total— y aquí es «Pagas
 * ahora», NEUTRO a propósito, porque en una cesta mixta (una entrada que se paga entera y un
 * cumpleaños con señal) no todo lo que se cobra ahora es señal (#225). Reutilizar el mismo rótulo
 * pasa el diff de árbol —que no compara texto— y cambia la copia.
 */

import { t, tc } from './i18n.js';
import { money } from './money.js';

/** Lo que el servidor pinta cuando todavía no hay importe que enseñar. No es un importe: es un guion. */
export const AMOUNT_PLACEHOLDER = '—';

/**
 * El view-model del pie para el estado actual, o `null` en los pasos que no lo llevan.
 *
 * Espejo de `Purchase::footer()`. Devolver `null` es tan significativo como devolver un pie: en el
 * catálogo con la cesta vacía y en la cesta vacía **no hay barra**, y un motor que la pintara igual
 * enseñaría un total de 0,00 € donde la web no enseña nada.
 *
 * @param {{
 *   step: number,
 *   messages: object,
 *   locale: string,
 *   cartCount?: number,
 *   cartTotalCents?: number,
 *   cartOnlineCents?: number,
 *   hasDate?: boolean,
 *   hasTime?: boolean,
 *   lineTotalCents?: number|null,
 *   lineHasDeposit?: boolean,
 *   lineDepositCents?: number,
 *   lineGateRemainderCents?: number,
 * }} state
 * @returns {object|null}
 */
export function buildFooter(state) {
    const { step, messages, locale } = state;

    if (step === 1) {
        return state.cartCount > 0 ? cartBar(state) : null;
    }

    if (step === 2) {
        return {
            type: 'bar',
            action: 'goToTime',
            cta: t(messages, 'continue'),
            label: t(messages, 'total'),
            // El precio depende del día, así que hasta elegirlo no hay total que enseñar. El CTA se
            // habilita al elegir; no auto-avanza, que es lo que da control y mantiene el pie
            // coherente con los demás pasos.
            amount: AMOUNT_PLACEHOLDER,
            disabled: ! state.hasDate,
            icon: 'arrow',
            splitMode: null,
            split: null,
            // «Continuar» no cobra: secundario, relleno de tinta (`#551`). Se declara en las CUATRO
            // formas del pie y no se deja en `undefined` a propósito — un pie nuevo que se olvide del
            // campo sale en tinta, que es el lado seguro, pero el censo de `SidebarActionRoleTest`
            // solo puede contarlo si está escrito.
            sells: false,
            note: t(messages, 'iva_note'),
        };
    }

    if (step === 3) {
        return stepFooter(state);
    }

    if (step === 4) {
        return state.cartCount > 0 ? cartFooter(state, 'checkout', t(messages, 'go_to_pay'), 'arrow', 'popover') : null;
    }

    // ⚠️ **El paso de PAGO cambia las dos cosas que el carrito dejaba fijas**: el icono es una tarjeta
    // y el desglose sube a una BANDA propia (`bk-paybreakdown`, siempre visible) en vez de esconderse
    // tras el ⓘ. No es cosmética: en la pantalla donde se paga, lo que se va a cobrar no puede estar
    // detrás de un clic. Y a diferencia del paso 4, **no se condiciona a que haya cesta**: aquí ya se
    // ha pasado por la identificación, así que una cesta vacía en este paso no es un estado alcanzable
    // — el servidor mismo lo rechazaría con `cart_empty`.
    if (step === 8) {
        return cartFooter(state, 'confirmReservation', t(messages, 'pay_confirm'), 'card', 'band', true);
    }

    return null;

    /** La barra-carrito del catálogo: un único botón con badge, recuento e importe. */
    function cartBar({ cartCount, cartTotalCents }) {
        return {
            type: 'cart',
            action: 'goToCart',
            cta: t(messages, 'go_to_cart'),
            count: cartCount,
            // ⚠️ Pluralización de Laravel, no `n === 1`: en francés el CERO cae en el singular.
            label: tc(messages, 'cart_items', cartCount, locale),
            amount: money(cartTotalCents),
            // «Ir al carrito» tampoco cobra: el artboard la dibuja en tinta con su píldora en Lima.
            sells: false,
        };
    }
}

/**
 * El pie del paso 3 (producto único). **Siempre presente.**
 *
 * Hasta elegir hora el total es «—» y el CTA está inactivo, y eso último es lo ÚNICO que lo inactiva:
 * la cantidad mínima y los campos obligatorios del pack se validan AL PULSAR, con un aviso que dice
 * qué falta, en vez de con un botón muerto que no lo explica.
 */
function stepFooter(state) {
    const { messages, hasTime, lineTotalCents, lineHasDeposit, lineDepositCents, lineGateRemainderCents } = state;

    // El desglose solo se anuncia si de verdad queda algo para el parque: un producto con la señal
    // configurada al 100 % no deja nada, y un desglose vacío es ruido.
    const split = hasTime && lineHasDeposit
        ? {
            nowLabel: t(messages, 'footer_pay_now_deposit'),
            now: money(lineDepositCents),
            park: money(lineGateRemainderCents),
        }
        : null;

    return {
        type: 'bar',
        action: 'addToCart',
        cta: t(messages, 'add_to_cart'),
        label: t(messages, 'total'),
        amount: hasTime && lineTotalCents !== null ? money(lineTotalCents) : AMOUNT_PLACEHOLDER,
        disabled: ! hasTime,
        icon: 'arrow',
        splitMode: 'popover',
        split,
        // «Añadir al carrito» no cobra (`#551`).
        sells: false,
        note: t(messages, 'iva_note'),
    };
}

/**
 * La barra de la cesta y del pago. Ancla en el TOTAL, como el catálogo.
 *
 * ⚠️ Aquí el rótulo del desglose es NEUTRO («Pagas ahora»), no «Pagas ahora (señal)»: en una cesta
 * mixta lo que se cobra ahora no es solo señal (#225).
 *
 * ❗❗❗ **`sells` NO es cosmética: es el MAPA DEL NARANJA, y lo decide este módulo** (`#551`). El
 * sistema del cliente dice que el relleno de acción significa **comprar** y que *solo hay un botón de
 * relleno de acción por pantalla*; dentro del cajón eso es **un solo CTA en las once pantallas del
 * embudo: «Pagar»**. Los otros tres del pie —«Continuar», «Añadir al carrito», «Ir a pagar»— son
 * secundarios, y el artboard los dibuja en **relleno de tinta** (`#101418`).
 *
 * ⚠️ Va aquí y no en la plantilla porque **quien sabe si un pie vende es quien lo compone**. Deducirlo
 * del rótulo o del `action` en el marcado sería una segunda copia de la regla, y el día que alguien
 * añada un paso que cobre, la plantilla no se enteraría — y el botón saldría en tinta sin que nada
 * fallara.
 */
function cartFooter(state, action, cta, icon, splitMode, sells = false) {
    const { messages, cartTotalCents, cartOnlineCents } = state;

    return {
        type: 'bar',
        action,
        cta,
        label: t(messages, 'total'),
        amount: money(cartTotalCents),
        disabled: false,
        icon,
        splitMode,
        sells,
        // La ÚNICA resta permitida del cajón, y lo es porque es la diferencia de dos AGREGADOS de la
        // MISMA fuente —el presupuesto— y porque es literalmente lo que hace el servidor. Sumar
        // subtotales de línea en su lugar dejaría los complementos fuera.
        split: cartOnlineCents < cartTotalCents
            ? {
                nowLabel: t(messages, 'footer_pay_now'),
                now: money(cartOnlineCents),
                park: money(cartTotalCents - cartOnlineCents),
            }
            : null,
        note: t(messages, 'iva_note'),
    };
}
