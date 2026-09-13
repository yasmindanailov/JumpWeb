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
 *
 * ❗❗❗ **EL ANCLA NO ES SIEMPRE EL TOTAL, Y EL PASO 08 ES LA EXCEPCIÓN** (`#554`, parada 04 del
 * canvas). En las cuatro pantallas con pie el número pegado al botón es el total **porque allí es
 * cierto**; en la de pagar deja de serlo en cuanto el carrito lleva una señal — medido por el canvas:
 * el pie decía **162,40 €** y a la tarjeta iban **92,80 €**. ▶ *Un botón y la cifra de al lado se leen
 * como una frase*, así que ahí ancla en **lo que se cobra ahora** y el total sube a la banda.
 * ⚠️ **Sin ninguna resta nueva**: los tres importes los publica el presupuesto por separado.
 *
 * ⚠️ **El desglose tiene UNA forma, `split.rows`, y dos sitios donde se pinta** —el ⓘ de los pasos 3
 * y 4, y la banda del 8—. Nació con campos `nowLabel`/`now`/`park`, y con el ancla nueva eso obligaba
 * a que en la banda `nowLabel` valiera «Total»: un nombre diciendo lo contrario de su contenido, que
 * es justo la clase de mentira que este proyecto paga cara. Dos filas con su etiqueta no mienten.
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
            // ⚠️ **El MOTIVO va en el rótulo del total, no dentro del botón apagado** (`#554`). La
            // primera versión del artboard lo metía en el botón, y está mal medido: el inactivo del
            // sistema es Nube con su gris, y una instrucción que hay que leer no puede vivir ahí. El
            // botón se queda con el nombre de la acción y el pie dice qué falta, donde se lee.
            label: state.hasDate ? t(messages, 'total') : t(messages, 'footer_pick_day'),
            // El precio depende del día, así que hasta elegirlo no hay total que enseñar. El CTA se
            // habilita al elegir; no auto-avanza, que es lo que da control y mantiene el pie
            // coherente con los demás pasos.
            amount: AMOUNT_PLACEHOLDER,
            disabled: ! state.hasDate,
            icon: 'arrow',
            splitMode: null,
            split: null,
            // «Continuar» no cobra. ⚠️ Desde `#584` eso ya NO decide su color —el pie entero va en
            // primario—: decide el ancla de la cifra y el rótulo del pie de pagar. Se declara en las
            // CUATRO formas del pie y no se deja en `undefined` a propósito: `foot.test.js` exige un
            // booleano en cada una.
            sells: false,
            note: t(messages, 'iva_note'),
        };
    }

    if (step === 3) {
        return stepFooter(state);
    }

    if (step === 4) {
        return state.cartCount > 0 ? cartFooter(state) : null;
    }

    // ⚠️ **El paso de PAGO cambia TRES cosas que el carrito dejaba fijas**: el icono es una tarjeta, el
    // desglose sube a una BANDA propia (`bk-paybreakdown`, siempre visible) en vez de esconderse tras
    // el ⓘ, y el importe grande **deja de ser el total**. Las tres son la misma idea: en la pantalla
    // donde se paga, lo que se va a cobrar no puede estar escondido ni ser otro número.
    // Y a diferencia del paso 4, **no se condiciona a que haya cesta**: aquí ya se ha pasado por la
    // identificación, así que una cesta vacía en este paso no es un estado alcanzable — el servidor
    // mismo lo rechazaría con `cart_empty`.
    if (step === 8) {
        return payFooter(state);
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
            rows: [
                { label: t(messages, 'footer_pay_now_deposit'), value: money(lineDepositCents) },
                { label: t(messages, 'pay_at_park'), value: money(lineGateRemainderCents) },
            ],
        }
        : null;

    return {
        type: 'bar',
        action: 'addToCart',
        cta: t(messages, 'add_to_cart'),
        // El motivo en el rótulo, como en el paso 2: sin hora no hay precio que dar, y decirlo donde
        // se lee es lo que evita un botón muerto que no explica nada.
        // ⚠️ El artboard dibuja este estado solo para el DÍA; aquí se aplica su regla a la HORA, que
        // no dibuja. Es extrapolación declarada, no una cita.
        label: hasTime ? t(messages, 'total') : t(messages, 'footer_pick_time'),
        amount: hasTime && lineTotalCents !== null ? money(lineTotalCents) : AMOUNT_PLACEHOLDER,
        disabled: ! hasTime,
        icon: 'arrow',
        splitMode: 'popover',
        split,
        // «Añadir al carrito» no cobra (y aun así va en primario desde `#584`).
        sells: false,
        note: t(messages, 'iva_note'),
    };
}

/**
 * La barra de la CESTA. Ancla en el TOTAL, como el catálogo, **porque ahí el total es cierto**: de la
 * cesta no se sale pagando, se sale pasando al paso siguiente.
 *
 * ⚠️ Aquí el rótulo del desglose es NEUTRO («Pagas ahora»), no «Pagas ahora (señal)»: en una cesta
 * mixta lo que se cobra ahora no es solo señal (#225).
 */
function cartFooter(state) {
    const { messages, cartTotalCents, cartOnlineCents } = state;

    return {
        type: 'bar',
        action: 'checkout',
        cta: t(messages, 'go_to_pay'),
        label: t(messages, 'total'),
        amount: money(cartTotalCents),
        disabled: false,
        icon: 'arrow',
        splitMode: 'popover',
        // «Ir a pagar» no cobra: lleva a la pantalla que cobra (y va en primario desde `#584`).
        sells: false,
        split: cartOnlineCents < cartTotalCents
            ? {
                rows: [
                    { label: t(messages, 'footer_pay_now'), value: money(cartOnlineCents) },
                    { label: t(messages, 'pay_at_park'), value: money(parkCents(state)) },
                ],
            }
            : null,
        note: t(messages, 'iva_note'),
    };
}

/**
 * El pie del paso 08 · **el único que ancla en lo que se cobra** y el único que vende.
 *
 * ❗❗❗ **`sells` dice QUIÉN COBRA, y lo decide este módulo** (`#551`). Nació como el mapa del relleno
 * de acción —solo «Pagar» lo llevaba—, y **`#584` lo desató del color** (`[DECIDIDO owner, 2026-09-13]`):
 * los cuatro pies del embudo van en primario, porque el pie es la navegación y avanza con un solo color.
 * Lo que `sells` sigue gobernando es lo que cambia en la pantalla que cobra: el rótulo del ancla sube
 * de peso (`.bk-foot:has(.bk-cta--sells)`).
 *
 * ⚠️ Va aquí y no en la plantilla porque **quien sabe si un pie vende es quien lo compone**. Deducirlo
 * del rótulo o del `action` en el marcado sería una segunda copia de la regla, y el día que alguien
 * añada un paso que cobre, la plantilla no se enteraría — y su pie no ganaría el rótulo de cobro sin
 * que nada fallara.
 *
 * ⚠️ **El rótulo sigue al hecho**: con desglose dice «Pagas ahora», y sin él «Total». Sin señal no hay
 * ningún «después», así que «Pagas ahora» insinuaría un resto que no existe — y las dos cifras son la
 * misma, porque lo que se cobra ES el total.
 */
function payFooter(state) {
    const { messages, cartTotalCents, cartOnlineCents } = state;
    const conDesglose = cartOnlineCents < cartTotalCents;

    return {
        type: 'bar',
        action: 'confirmReservation',
        cta: t(messages, 'pay_confirm'),
        label: t(messages, conDesglose ? 'footer_pay_now' : 'total'),
        amount: money(cartOnlineCents),
        disabled: false,
        icon: 'card',
        splitMode: 'band',
        sells: true,
        // El total no se pierde: sube a la banda, encima de lo que queda para el parque.
        split: conDesglose
            ? {
                rows: [
                    { label: t(messages, 'total'), value: money(cartTotalCents) },
                    { label: t(messages, 'footer_park_total'), value: money(parkCents(state)) },
                ],
            }
            : null,
        note: t(messages, 'iva_note'),
    };
}

/**
 * Lo que queda para el parque.
 *
 * **La ÚNICA resta permitida del cajón**, y lo es porque es la diferencia de dos AGREGADOS de la MISMA
 * fuente —el presupuesto— y porque es literalmente lo que hace el servidor. Sumar subtotales de línea
 * en su lugar dejaría los complementos fuera.
 */
function parkCents({ cartTotalCents, cartOnlineCents }) {
    return cartTotalCents - cartOnlineCents;
}
