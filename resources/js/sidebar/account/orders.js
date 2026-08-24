/**
 * **De la respuesta de `GET /me/orders` a lo que la zona «Mis reservas» pinta**
 * (`docs/specs/area-cliente.md` §4.4).
 *
 * Módulo PLANO, sin Vue (`CE-6`): aquí vive la composición y por eso se puede probar con
 * `node --test` y comparar campo a campo contra la página que este cajón sustituye.
 *
 * ⚠️⚠️ **Ninguna regla de negocio vive aquí** (`CE-4`), y en esta zona la tentación es grande porque
 * la página de la que se copia SÍ las tiene: si un pedido se puede reintentar, cuánto queda por
 * cobrar en puerta, si procede el aviso de señal y si una reserva admite post-form lo decide el
 * SERVIDOR y llega resuelto (`can_be_retried`, `gate_remainder_cents`, `shows_deposit_note`,
 * `guest_form_url`). Recomponer cualquiera de esas cuatro aquí sería la quinta superficie que
 * diverge — que es exactamente lo que `openapi/v1.yaml` avisa para `shows_deposit_note`.
 *
 * ⚠️ Y **las fechas tampoco se componen**: llegan como `date_label` / `created_label` porque `Intl`
 * no reproduce lo que compone Carbon en español y porque la del pedido lleva la zona horaria de la
 * instalación, que el navegador no conoce (`DECISIONES #120(j)`).
 */

import { t, tp } from '../i18n.js';
import { money } from '../money.js';

/** Los estados de pedido en los que la web enseña el bloque del post-form. Medido contra la vista. */
const TERMINATED = ['cancelled', 'refunded'];

/**
 * El bloque de post-form de una reserva, o `null` si no lleva ninguno.
 *
 * ⚠️ **Los cuatro estados están MEDIDOS contra `account/orders.blade.php`**, no inventados, y el
 * orden entre ellos es contrato: una reserva cancelada dentro de un pedido pagado enseña «cancelada»
 * aunque su franja ya haya pasado, y por eso el caso de cancelación va PRIMERO.
 *
 * ⚠️ El botón deshabilitado se conserva a propósito (decisión de la clienta, 2026-06-15): al pulsarlo
 * daría 404 —el controlador exige pedido pagado y no cancelado— pero quitarlo haría «desaparecer» el
 * contexto de la reserva.
 */
export function guestFormOf(order, item, account) {
    if (item.guest_form_status === null || item.guest_form_status === undefined) return null;

    const terminated = TERMINATED.includes(order.status);

    if (order.status !== 'paid' && ! terminated) return null;

    const product = { product: item.product_name };

    if (item.cancelled || terminated) {
        return { state: 'cancelled', label: tp(account, 'orders.guest_form_cancelled', product), url: null };
    }

    if (item.status === 'finished') {
        return { state: 'past', label: tp(account, 'orders.guest_form_past', product), url: item.guest_form_url };
    }

    return item.needs_guest_form
        ? { state: 'pending', label: tp(account, 'orders.guest_form_pending', product), url: item.guest_form_url }
        : { state: 'done', label: tp(account, 'orders.guest_form_done', product), url: item.guest_form_url };
}

/**
 * El distintivo de una línea: cancelada o ya disfrutada.
 *
 * ⚠️ **Cancelada gana**, y no es un detalle de estilo: una reserva cancelada cuya franja ya pasó
 * cumple las dos condiciones, y anunciarla como «disfrutada» diría algo falso al cliente.
 */
export function lineBadgeOf(item, account) {
    if (item.cancelled) return { key: 'cancelled', label: t(account, 'orders.item_cancelled') };
    if (item.status === 'finished') return { key: 'finished', label: t(account, 'orders.item_finished') };

    return null;
}

/**
 * **EL ANCLA DE CAJA**: la fila «cobrado», con su método y su fecha — o `null` si no se cobró nada.
 *
 * ⚠️ **La fecha es la mitad de lo que la hace conciliable**: «cobrado 30,00 €» no se busca en un
 * extracto bancario; «30,00 € · 24/08/2026» sí. La compone el servidor (`charged_at_label`), como
 * todas las fechas de esta zona. El separador es el mismo «·» que ya usa la línea de la reserva.
 *
 * ⚠️⚠️ **`anchor` no es decoración: el bloque entero heredaba el color de REEMBOLSO.** Mientras solo
 * se pintaba cuando había devoluciones eso pasaba desapercibido; en cuanto el ancla se enseña en todo
 * pedido cobrado, un cargo corriente se leería en ámbar **como si algo se hubiera devuelto** — el
 * mismo error de fondo que la tanda B quitó del eje del valor. La marca deja que la hoja lo pinte
 * neutro sin tocar las dos filas que sí son de devolución.
 */
function chargedLine(messages, cash, desk) {
    if (cash.charged_online_cents <= 0) return null;

    const label = t(messages, desk ? 'ledger.charged_desk' : 'ledger.charged_online');

    return {
        label: cash.charged_at_label ? label + ' · ' + cash.charged_at_label : label,
        amountLabel: money(cash.charged_online_cents),
        anchor: true,
    };
}

/**
 * **El desglose de un pedido, en DOS BLOQUES** (`DECISIONES #127`, spec §10.3).
 *
 * ⚠️⚠️ **Ni un solo importe se calcula aquí, y ahora tampoco se DECIDE cuál es cuál.** El servidor
 * publica el ledger entero en `order.ledger`, compuesto por `Booking\Services\OrderLedger` — la
 * MISMA composición que leen el panel, la sub-card, el PDF y los correos. Aquí solo se elige qué
 * líneas tienen algo que enseñar.
 *
 * ⚠️⚠️ **Los dos bloques NO se mezclan, y ésa es la corrección de fondo.** Arriba, el EJE VALOR:
 * cinco canales que suman el valor, siempre. Abajo, el EJE CAJA: qué ha pasado con su dinero. Hasta
 * ahora «Devuelto» y «Pendiente de devolución» se pintaban como restas dentro de la columna del
 * valor —de la que **no restan**— y por eso la columna dejaba de leerse: medido, 18 de 23 gestiones
 * del panel la dejaban ilegible.
 *
 * ⚠️ **«Importe al reservar» sale de la columna** y va al pie, solo si difiere: era el «Subtotal»,
 * que apilaba dos bases distintas sin decirlo.
 * ⚠️ Y **«Pagado por web» ya no se oculta** cuando el producto no lleva señal — se ocultaba, y el
 * cliente no veía cuánto había pagado.
 */
export function financialsOf(order, messages) {
    const l = order.ledger;
    const v = l.value;
    const c = l.cash;
    const line = (key, cents) => (cents > 0 ? { label: t(messages, 'ledger.' + key), amountLabel: money(cents) } : null);
    /**
     * ⚠️⚠️ **La fecha va donde el importe ES el que se cobró, y en ningún otro sitio**
     * (`DECISIONES #131`).
     *
     * `#130` la pegó a la línea del canal para que el importe se pudiera buscar en un extracto
     * —«30,00 €» no se busca; «30,00 € el 24/08/2026» sí—. Pero `paid_online` es el canal del VALOR
     * («lo cobrado por web que respalda producto vivo, neto de compensación»), y en cuanto hay una
     * devolución **deja de ser lo que se cobró ese día**. Medido sobre los 58 pedidos: en **7** la
     * pantalla afirmaba «Pagado por web · 24/08/2026 — 9,90 €» cuando ese día se cobraron 19,80 € —
     * y **6 de los 7 eran pedidos SANOS**. Justo lo contrario de conciliable.
     *
     * ▶ La regla: la fecha solo acompaña al importe **cuando coinciden**, que es exactamente cuando
     * el bloque «Tu dinero» no se pinta (`!has_cash` ⟹ `charged_online === paid_online`, porque ese
     * término es uno de los tres del predicado). Cuando difieren, la fecha va abajo, pegada al
     * importe que sí se cobró. **Un solo predicado del dominio gobierna las dos mitades.**
     */
    const paidLine = (key, cents) => {
        const fila = line(key, cents);

        if (fila && ! c.has_cash && c.charged_at_label) fila.label += ' · ' + c.charged_at_label;

        return fila;
    };
    // ⚠️ El MÉTODO decide el rótulo de los DOS canales de cobro adelantado (`DECISIONES #128`). El
    // eje de caja suma todo lo cobrado sin mirar el `provider`, así que el importe es correcto y el
    // nombre no puede quemarse: un pedido de taquilla que dijera «por web» mentiría. El panel ya lo
    // distinguía desde `P1/P10` y el cliente no — ésa era la divergencia.
    const desk = c.charged_method === 'desk';

    const gate = v.pending_at_gate_cents > 0
        ? {
            label: t(messages, 'ledger.pending_at_gate'),
            amountLabel: money(v.pending_at_gate_cents),
            showLabel: t(messages, 'show_breakdown'),
            hideLabel: t(messages, 'hide_breakdown'),
            caption: t(messages, l.has_deposit ? 'at_gate_caption_deposit' : 'at_gate_caption'),
            // Las etiquetas llegan compuestas por el dominio, en el orden que la página usa.
            lines: (l.gate_lines ?? []).map((gl) => ({ label: gl.label, amountLabel: money(gl.amount_cents) })),
        }
        : null;

    // ⚠️⚠️ **Si el desglose NO CIERRA, no se descompone** (`DECISIONES #132`). Cuando las identidades
    // del dominio fallan, ninguna de las líneas por canal es cierta: enseñarlas es poner delante del
    // cliente dos importes que se contradicen sin decirle nada. Lo que SÍ sigue siendo un hecho es lo
    // que vale el pedido y lo que se le cobró, así que eso se queda — y la frase explica el resto.
    // La condición la decide el SERVIDOR (`is_consistent`); derivarla aquí sería la novena vez.
    if (l.is_consistent === false) {
        return {
            value: {
                title: t(messages, 'ledger.value_title'),
                rows: [],
                gate: null,
                total: { label: t(messages, 'ledger.value_total'), amountLabel: money(v.total_cents) },
            },
            cash: chargedLine(messages, c, desk)
                ? { title: t(messages, 'ledger.cash_title'), caption: t(messages, 'ledger.cash_caption'), rows: [chargedLine(messages, c, desk)] }
                : null,
            note: l.note ?? null,
            invoiced: null,
        };
    }

    return {
        value: {
            title: t(messages, 'ledger.value_title'),
            rows: [
                paidLine(desk ? 'paid_desk' : 'paid_online', v.paid_online_cents),
                line('pending_online', v.pending_online_cents),
                line('paid_at_gate', v.paid_at_gate_cents),
                line('compensated', v.compensated_cents),
            ].filter(Boolean),
            gate,
            total: { label: t(messages, 'ledger.value_total'), amountLabel: money(v.total_cents) },
        },
        // ⚠️⚠️ **El eje de caja se enseña en cuanto el parque ha cobrado algo, y ése era el defecto
        // `L1`** (`DECISIONES #128`, `specs/desglose-dinero-cliente.md` §17.1). Esta condición se
        // derivaba AQUÍ y le faltaba justo el ancla, así que en un pedido normal —sin devoluciones—
        // el cliente **nunca veía cuánto había salido de su banco**: lo único que puede cotejar con
        // su extracto, y lo que convierte el desglose en algo VERIFICABLE en vez de solo legible.
        // Medido el 2026-08-24: el panel lo enseñaba en 28 de 38 pedidos sanos y el cliente en 9.
        //
        // ⚠️ Ahora la condición **la decide el dominio** (`OrderLedger::hasCash()`) y llega
        // publicada. Re-derivarla aquí es lo que la hizo divergir la primera vez.
        cash: c.has_cash
            ? {
                title: t(messages, 'ledger.cash_title'),
                caption: t(messages, 'ledger.cash_caption'),
                rows: [
                    chargedLine(messages, c, desk),
                    line('refunded', c.refunded_cents),
                    line('pending_refund', c.pending_refund_cents),
                ].filter(Boolean),
            }
            : null,
        // La FRASE que explica el estado. La compone el servidor: decidir qué caso es, es regla.
        note: l.note ?? null,
        // ⚠️⚠️ **Trazabilidad, y su frase la compone el SERVIDOR** (`L6`, `DECISIONES #133`). Era
        // una cadena fija del diccionario —«…es porque el pedido cambió después»— que decía QUE el
        // pedido había cambiado y **no en qué dirección ni cuánto**: una bajada de 12 a 8 invitados
        // no dejaba más rastro que un número mudo al pie. Elegir entre «vale X más» y «vale X menos»
        // es decidir qué caso es, y eso es regla de dominio, igual que la frase de estado.
        //
        // ⚠️ **Y la CONDICIÓN también llega publicada**: `invoiced_hint` es `null` exactamente cuando
        // lo facturado coincide con el valor. Comparar aquí los dos importes sería re-derivar una
        // condición del dominio, que es lo que dejó al cliente sin ver el ancla de caja (`L1`).
        invoiced: l.invoiced_hint
            ? {
                label: t(messages, 'ledger.invoiced'),
                amountLabel: money(l.invoiced_cents),
                hint: l.invoiced_hint,
            }
            : null,
    };
}

/** Un complemento anidado, tal como la página lo pinta bajo su línea. */
function addonRow(addon, account) {
    return {
        id: addon.id,
        name: addon.product_name,
        // ⚠️ La etiqueta, no el número pelado: el complemento sufría el MISMO defecto `L2` que la
        // línea principal —`· 2×` pegado al importe de la línea— y arreglar solo el principal habría
        // dejado la ambigüedad viva una fila más abajo. La compone el servidor.
        quantityLabel: addon.quantity_label,
        priceLabel: money(addon.charged_subtotal_cents),
        badge: lineBadgeOf(addon, account),
    };
}

/** Una línea principal con sus complementos. */
function lineRow(order, item, { messages, account }) {
    return {
        id: item.id,
        name: item.product_name,
        // Ya compuestos por el servidor los dos: el día porque `Intl` no lo reproduce, la ventana
        // porque componerla —según la franja tenga fin o no— es regla del dominio.
        whenLabel: [item.date_label, item.time_window].filter(Boolean).join(' · '),
        // ⚠️⚠️ **La cantidad con su SUSTANTIVO, y ése era el defecto `L2`** (`DECISIONES #128`,
        // `specs/desglose-dinero-cliente.md` §17.1). La tarjeta pintaba `8×` seguido del importe de
        // la línea —`8×216,00 €`—, que se lee como «8 unidades a 216 € cada una» = 1.728 € cuando
        // son **8 invitados y 216 € en total**. La compone el servidor porque el sustantivo depende
        // del tipo de producto y del idioma (`OrderItem::displayQuantityLabel()`).
        quantityLabel: item.quantity_label,
        isPack: item.is_pack,
        priceLabel: money(item.charged_subtotal_cents),
        badge: lineBadgeOf(item, account),
        guestForm: guestFormOf(order, item, account),
        addons: (item.addons ?? []).map((addon) => addonRow(addon, account)),
    };
}

/**
 * Un pedido, tal como la zona lo pinta.
 *
 * ⚠️ **El estado se traduce con `tickets.statuses.*`, que es el MISMO diccionario que usa la página**
 * —y que ya viaja entero en el montaje—, no con una tabla propia. Un segundo juego de rótulos para
 * los mismos cinco estados es una divergencia esperando su turno.
 */
export function orderRow(order, ctx) {
    return {
        code: order.code,
        status: order.status,
        statusLabel: t(ctx.messages, 'statuses.' + order.status),
        createdLabel: order.created_label,
        totalLabel: money(order.ledger.value.total_cents),
        canRetry: order.can_be_retried === true,
        // El reembolso es un eje INDEPENDIENTE del estado: un pedido puede estar pagado y
        // parcialmente reembolsado a la vez (`openapi/v1.yaml`).
        // ⚠️ CUÁNDO se devolvió. **El importe vive en el bloque de caja del ledger**: tenerlo en dos
        // sitios era la clase de duplicado del que nacen las divergencias.
        refund: order.refund?.refunded_at ? { label: order.refund.refunded_label } : null,
        guestFormPending: order.guest_form_pending === true,
        // ⚠️ Si alguna línea es un pack, el pedido PUEDE tener respuestas del evento — y solo
        // entonces se ofrece el despliegue. Cuáles son no se sabe hasta pedirlas: no viajan en la
        // lista a propósito (art. 9), que es justo lo que hace que haya que preguntar.
        hasPack: (order.items ?? []).some((item) => item.is_pack === true),
        // El ledger entero (tanda 3 · paso 10): qué líneas se enseñan y con qué rótulo.
        financials: financialsOf(order, ctx.messages),
        lines: (order.items ?? []).map((item) => lineRow(order, item, ctx)),
    };
}

/**
 * **Una TARJETA DE RESERVA**: una línea principal con el contexto mínimo de su pedido
 * (`docs/specs/mis-reservas-por-reserva.md` §4.4).
 *
 * ⚠️⚠️ **Reutiliza `lineRow()` entera, y ahí está el punto.** Una reserva pintada suelta y una
 * reserva pintada dentro de su pedido son **la misma cosa**: el mismo nombre, la misma ventana
 * horaria, el mismo distintivo, el mismo aviso de señal, el mismo post-form y los mismos
 * complementos. Escribir una segunda composición «porque ahora la tarjeta es de la reserva» habría
 * creado dos sitios donde arreglar el mismo fallo — y este fichero ya lleva escrito, para el ledger,
 * por qué eso no se hace.
 *
 * ⚠️ **El estado del PEDIDO llega resuelto** (`order.status` es el de HECHO, no la columna) y aquí
 * solo se traduce con `tickets.statuses.*`, el mismo diccionario de siempre.
 *
 * ⚠️ **Y no se decide si la tarjeta va atenuada.** Eso es propiedad de la PANTALLA —el historial
 * atenúa lo que pinta— y no de la fila: recomponerlo aquí sería una segunda definición del predicado
 * que reparte los dos ámbitos, que vive en SQL. El distintivo «cancelada» / «disfrutada», que sí es
 * de la reserva, lo pone `lineBadgeOf()` con los datos que sí viajan.
 */
export function cardRow(card, ctx) {
    const order = card.order ?? {};

    return {
        ...lineRow({ status: order.status }, card.reservation ?? {}, ctx),
        orderCode: order.code,
        orderStatus: order.status,
        orderStatusLabel: t(ctx.messages, 'statuses.' + order.status),
        orderCreatedLabel: order.created_label,
        canRetry: order.can_be_retried === true,
    };
}

/**
 * La página de tarjetas entera.
 *
 * @param {{data?: Array<object>}} payload  la respuesta de `GET /me/reservations/{scope}`
 * @param {{messages: object, account: object}} ctx  los diccionarios del montaje
 */
export function cardRows(payload, ctx) {
    return (payload?.data ?? []).map((card) => cardRow(card, ctx));
}

/**
 * **La página de «Mis pedidos»**: una fila por PEDIDO (`specs/desglose-dinero-cliente.md` §19).
 *
 * ⚠️⚠️ **Es `orderRow()` sin una línea propia, y ahí está el punto.** El pedido que se despliega
 * desde una reserva y el pedido de esta lista **son el mismo pedido**, servidos por el MISMO
 * `OrderResource` —`GET /orders/{code}` y `GET /me/orders` lo comparten—, así que componerlo aquí
 * otra vez habría creado la novena superficie de dinero. Lo que cambia es la pantalla que lo pinta.
 *
 * @param {{data?: Array<object>}} payload  la respuesta de `GET /me/orders`
 * @param {{messages: object, account: object}} ctx  los diccionarios del montaje
 */
export function purchaseRows(payload, ctx) {
    return (payload?.data ?? []).map((order) => orderRow(order, ctx));
}

/**
 * La paginación, o `null` cuando cabe en una sola página.
 *
 * ⚠️ **`null` es un estado real y no un descuido**: la página tampoco pinta la barra con un único
 * resultado, y pintarla deshabilitada anunciaría un recorrido que no existe.
 *
 * ⚠️ **El GRUPO de rótulos es un parámetro** desde que hay dos listas paginadas: «Mis reservas» habla
 * de reservas y «Mis pedidos» de pedidos, y una barra que dijera «Paginación de reservas» sobre una
 * lista de pedidos sería un texto falso que ningún test de composición vería.
 */
export function pageInfo(payload, account, group = 'orders') {
    const meta = payload?.meta ?? {};
    const current = Number(meta.current_page ?? 1);
    const last = Number(meta.last_page ?? 1);

    if (! Number.isFinite(current) || ! Number.isFinite(last) || last <= 1) return null;

    return {
        current,
        last,
        canPrev: current > 1,
        canNext: current < last,
        label: t(account, group + '.pagination.label'),
        prevLabel: t(account, group + '.pagination.prev'),
        nextLabel: t(account, group + '.pagination.next'),
        pageLabel: tp(account, group + '.pagination.page', { current, last }),
    };
}
