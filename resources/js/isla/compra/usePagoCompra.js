/**
 * **«PAGAR» Y LOS DESENLACES de la compra de la isla, sobre el motor** (T3e·3 de `docs/specs/isla-y-landing-nueva.md`
 * §4.10, `DECISIONES #692`).
 *
 * El cobro es el del motor (`usePurchaseFlow`): `confirmReservation()` crea el pedido y abre el cobro en UNA petición
 * (`CheckoutOrchestrator`, `#37`), el reintento y el sondeo también. Lo de la isla: el recibo que se cambia sin salir
 * (la línea se rehace, `linea.js`), las condiciones aceptadas al pagar (`#692`·2) y lo que cada desenlace enseña.
 *
 * ⚠️ **La hora NO se guarda antes de pagar** (`#688`): la retención nace con el pedido, en el mismo acto que abre el
 * cobro, así que «Pagar» no dice «tu hora queda guardada hasta…». Y si la hora se llenó entretanto, lo dice el
 * servidor al pagar (`line_sold_out`, o `line_pack_sold_out` en una fiesta) y la isla pasa a «Esa hora ya no está
 * libre» con las horas cercanas (T3e·6, `PjcPerdida`): es verdad que no se ha cobrado nada, porque el pedido no nació.
 */
import { computed, reactive, ref, watch } from 'vue';
import { STEPS } from '../../sidebar/machine.js';
import { api } from '../../sidebar/api.js';
import { t } from '../../sidebar/i18n.js';
import { useCardStore } from '../../sidebar/stores/card.js';
import { cardImageUrl } from '../../sidebar/account/card.js';
import { cajonHost } from '../../sidebar/host-bridge.js';
import { complementosDe, meterLinea } from './linea.js';
import { cambioDe } from './recibo.js';
import { destinoDeTarea } from './pasos.js';

/** Los «no» de `POST /orders` que dicen que la HORA se llenó: la de una entrada y la de una fiesta. */
const HORA_LLENA = new Set(['line_sold_out', 'line_pack_sold_out']);

export function usePagoCompra({ flow, props, textos = {}, compra, enCola, alPagarMal, alLlenarse }) {
    const { store, cartStore, selectionStore, outcomeStore, buyerDue } = flow;
    const cardStore = useCardStore();
    const listo = reactive({ correo: '', cuentaNueva: false });
    const aviso = (clave) => t(props.messages, clave);

    /**
     * Rehace la línea con otra gente u otros pares: los complementos RESUELTOS de nuevo por el servidor (los hay que
     * dependen de la cantidad) y la línea validada y presupuestada otra vez. La cantidad se ve cambiar al momento
     * —como en la pantalla 0— y el dinero, cuando llega; si el servidor dice que no, vuelve la de antes. Con OTRA
     * HORA es lo mismo (T3e·6: la que se elige tras llenarse la primera). Dice si el servidor la aceptó.
     */
    async function rehacer(cambio) {
        const antes = compra.pedido;
        const p = { ...antes, ...cambio };

        compra.pedido = p;
        compra.aviso = '';
        selectionStore.setQuantity(p.n);
        selectionStore.setQuantities(complementosDe(p));
        // El menú de una fiesta se conserva: sin él, el servidor resolvería el incluido (y se perdería el elegido).
        selectionStore.setChoices(p.elecciones ?? []);

        const resuelto = await selectionStore.loadAddons({ api, productId: p.fila, date: p.dia, time: p.hora });
        const r = resuelto
            ? await meterLinea({ api, pedido: p, resueltos: selectionStore.resolved, cartStore, messages: props.messages })
            : { ok: false, aviso: aviso('errors.try_later') };

        if (! r.ok) {
            compra.pedido = antes;
            compra.aviso = r.aviso;

            return false;
        }

        compra.pedido = { ...p, n: cartStore.lines[0]?.quantity ?? p.n };
        Object.assign(compra.borrador, { n: compra.pedido.n, cal: p.cal, hora: p.hora });

        return true;
    }

    const cantidad = (id, n) => enCola(() => rehacer(cambioDe(id, n)));
    const calcetines = (n) => enCola(() => rehacer({ cal: n }));

    /**
     * «Pagar N € con tarjeta». ⚠️ `accept_terms` viaja SIEMPRE (`#692`·2, «Al pagar aceptas las condiciones»): el
     * servidor solo lo mira si faltan, y lo registra antes de crear el pedido (`OrdersController::requireBuyerDuties`).
     */
    async function pagar() {
        if (compra.ocupado) return;
        compra.ocupado = 'pagar';
        compra.aviso = '';

        try {
            // Lo último que se pidió en el recibo es lo que se paga.
            await enCola(() => {});
            if (store.step === STEPS.CART) await flow.checkout();
            if (store.step !== STEPS.PAY) {
                compra.aviso = cartStore.error || aviso('errors.try_later');

                return;
            }

            buyerDue.acceptTerms = true;
            const r = await flow.confirmReservation();

            // Un «no» sobre lo que el comprador debe (el teléfono) se corrige en «Tus datos», donde está su campo.
            if (Object.keys(buyerDue.errors ?? {}).length) alPagarMal(buyerDue.errors);
            else if (HORA_LLENA.has(r?.code)) await alLlenarse(cartStore.error);
            else if (store.step !== STEPS.REDIRECTING) compra.aviso = cartStore.error || aviso('errors.try_later');
        } finally {
            compra.ocupado = null;
        }
    }

    /**
     * «Continuar al pago», si la pasarela no salta sola: el mismo formulario firmado, otra vez. Es un CONTADOR que el
     * formulario oye (`FormularioPasarela.vue`), porque el formulario vive en otro trozo y otro componente.
     */
    const salidas = ref(0);
    const salir = () => { salidas.value += 1; };

    /**
     * «Volver a intentar con tarjeta»: reabre el cobro del MISMO pedido, que sigue retenido (`retryPayment`). ⚠️ Si
     * el servidor dice que no y el pedido sigue ahí (la pausa, el limitador), la isla lo DICE en el paso: el cajón lo
     * calla, fiel a la web, y está anotado como deuda de producto (`DEUDA.md`); aquí no hay web que copiar.
     */
    async function reintentar() {
        if (compra.ocupado) return;
        compra.ocupado = 'fallido';
        compra.aviso = '';

        try {
            await flow.retryPayment();
            if (store.step === STEPS.DECLINED) compra.aviso = cartStore.error || aviso('errors.try_later');
        } finally {
            compra.ocupado = null;
        }
    }

    /**
     * Lo que enseña cada desenlace y no trae el montaje. «Listo»: el carné (su QR), el correo al que se envió y si la
     * cuenta nació en esta compra. El pago no completado y el que se confirma: el pedido, para su línea y su total
     * (la cesta ya se vació al crearlo).
     */
    async function alLlegar(step) {
        if (step === STEPS.CONFIRMED) {
            const [yo] = await Promise.all([api.get('/me'), cardStore.ensure({ api })]);

            listo.correo = yo?.ok ? (yo.data?.email ?? '') : '';
        }

        if ((step === STEPS.DECLINED || step === STEPS.VERIFYING) && ! outcomeStore.confirmation && outcomeStore.orderCode) {
            await outcomeStore.loadConfirmation({ api });
        }
    }

    watch(() => store.step, alLlegar, { immediate: true });

    const qrSrc = computed(() => cardImageUrl(cardStore.card));

    /** «Guardar en el móvil»: la imagen del carné, descargada. */
    function guardarQr() {
        if (! qrSrc.value) return;
        const a = Object.assign(document.createElement('a'), { href: qrSrc.value, download: 'qr.png' });

        document.body.append(a);
        a.click();
        a.remove();
    }

    /** «Ir a Mi QR»: la cuenta, en su lateral (hasta la T5), en el carné. */
    const miQr = () => cajonHost()?.openAccount?.({ preventDefault() {} }, 'card');

    /** «Añadir a mis hijos», en la cuenta: sus menores a cargo. */
    const menores = () => cajonHost()?.openAccount?.({ preventDefault() {} }, 'dependents');

    /**
     * Un botón de «Antes de venir»: el de los hijos, a la cuenta; los de la FIESTA (T3e·5), a su formulario de invitados
     * o a su invitación, con la URL que compone el servidor (`pasos.js::destinoDeTarea`). La compra ya terminó: se sale.
     */
    function tarea(id, boton) {
        if (id === 'menores') return menores();
        const destino = destinoDeTarea((outcomeStore.confirmation?.lines ?? []).find((l) => l.is_pack), boton, textos);

        if (destino) window.location.assign(destino);

        return null;
    }

    /** «Escribirnos», en otra pestaña: la compra sigue aquí. La dirección la compone el servidor (`urls.contact`). */
    const escribir = () => props.urls?.contact && window.open(props.urls.contact, '_blank', 'noopener');

    /** Las condiciones, en otra pestaña por lo mismo (`urls.terms`). */
    function condiciones(evento) {
        evento?.preventDefault?.();
        if (props.urls?.terms) window.open(props.urls.terms, '_blank', 'noopener');
    }

    return { listo, qrSrc, salidas, rehacer, cantidad, calcetines, pagar, salir, reintentar, guardarQr, miQr, menores, tarea, escribir, condiciones };
}
