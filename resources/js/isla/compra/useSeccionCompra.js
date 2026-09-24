/**
 * **LA COMPRA DE LA ISLA sobre el motor** (T3e de `docs/specs/isla-y-landing-nueva.md` §4.10, `DECISIONES #692`).
 *
 * La secuencia es la del cajón (`usePurchaseFlow()`, `#691`): el montaje —catálogo, `/config`, la pausa, la cesta y
 * el desenlace—, la admisión, el alta, el cobro y el sondeo son LOS MISMOS. Lo que es de la isla vive aquí y en los
 * dos que llama: su borrador de la pantalla 0 (un formulario, no un embudo: el tiempo cambia después de la hora),
 * «Tus datos» (`useDatosCompra.js`), «Pagar» y los desenlaces (`usePagoCompra.js`); la traducción a sus pantallas,
 * sin estado y con su `node --test`, en `vista.js`, `pasos.js`, `recibo.js`, `datos.js` y `linea.js`.
 *
 * El orden, sobre la máquina (que no cambia): «Continuar» mete la línea (`→ CART`) y admite (`checkout()`: `IDENTIFY`
 * sin sesión, `PAY` con ella); «Tus datos» identifica (`→ PAY`); «Pagar» crea el pedido y sale al banco
 * (`→ REDIRECTING`); los desenlaces los pone la vuelta del banco. Volver a la pantalla 0 quita la línea (`→ CATALOG`).
 *
 * ⚠️ Los stores del motor se comparten con el cajón (una sola app, un solo Pinia), y con la isla como carcasa el
 * cajón no monta su compra: aquí se usan sin pasar por sus pasos (`selectProduct()` borraría día y hora).
 */
import { computed, inject, onMounted, reactive, watch } from 'vue';
import { usePurchaseFlow } from '../../sidebar/usePurchaseFlow.js';
import { TEXTOS_ISLA } from '../../sidebar/carcasa.js';
import { STEPS, isOutcome } from '../../sidebar/machine.js';
import { api } from '../../sidebar/api.js';
import { t } from '../../sidebar/i18n.js';
import { todayIso } from '../../sidebar/cart.js';
import { useSuperficie } from './useSuperficie.js';
import { useDatosCompra } from './useDatosCompra.js';
import { usePagoCompra } from './usePagoCompra.js';
import { borradorDeIntencion, calcetinDe, cargarDiasDeFilas, horaDelMotor, horaQueCabe, primerDia } from './oferta.js';
import { euros, filasDeZona, pantallaCuando } from './vista.js';
import { meterLinea, pedidoDe } from './linea.js';
import { lineaListo, reciboDe, resumenDe, resumenDelPedido } from './recibo.js';
import { ckDelPaso, direccion, empiezaOtra, pantallaListo, pasoDelMotor, rango } from './pasos.js';

/** Lo que `SeccionCompra.vue` da a los pasos de después de la pantalla 0, que viajan en otro trozo (`PasosCompra.vue`). */
export const COMPRA = Symbol('la compra de la isla');

export function useSeccionCompra(props) {
    const textos = inject(TEXTOS_ISLA, {});
    const flow = usePurchaseFlow(props);
    const { store, catalogStore, timeStore, selectionStore, cartStore, outcomeStore, authStore } = flow;
    const { abierta, cerrar: cerrarSuperficie } = useSuperficie();
    const compra = reactive({
        borrador: borradorDeIntencion(null, []), precios: {}, cargandoHoras: false, intencion: null,
        paso: 'cuando', aviso: '', ocupado: null, pedido: null, pagado: null, dir: null,
    });

    /**
     * ⚠️ **En COLA**: dos cambios seguidos (más gente, un par de calcetines) lanzan dos peticiones, y si la primera
     * respondiera la última la isla enseñaría el total VIEJO. En fila, lo último pedido es lo último que se pinta.
     */
    let cola = Promise.resolve();
    const enCola = (tarea) => (cola = cola.then(tarea, tarea));

    const datos = useDatosCompra({ flow, props, textos });
    const pago = usePagoCompra({ flow, props, compra, enCola, alPagarMal });

    /** El paso que se ve: el que manda la máquina (el cobro y los desenlaces) o el de la isla. */
    const paso = computed(() => pasoDelMotor(store.step) ?? compra.paso);

    // ── La pantalla 0 ────────────────────────────────────────────────────────────────────────────────

    /** La línea que resuelve el SERVIDOR con la selección de ahora (el dinero, con los calcetines dentro). */
    async function resolverLinea() {
        const b = compra.borrador;

        if (! b.hora) { selectionStore.setLine(null); return; }
        timeStore.select(b.hora);
        selectionStore.setQuantity(b.n);
        const calcetin = calcetinDe(catalogStore.product);
        selectionStore.setQuantities(calcetin && b.cal > 0 ? [{ product_id: calcetin.id, quantity: b.cal }] : []);
        await selectionStore.loadAddons({ api, productId: b.fila, date: b.dia, time: b.hora });
    }

    /** Las horas de la fila y el día, con la cesta (`AFORO-02`). Una hora que ya no cabe se vacía. */
    async function cargarHoras() {
        const b = compra.borrador;

        if (! b.fila || ! b.dia) return;
        compra.cargandoHoras = true;
        await timeStore.loadOffer({ api, productId: b.fila, date: b.dia, cartLines: cartStore.lines });
        compra.cargandoHoras = false;
        if (b.hora && ! horaQueCabe(timeStore.offered, b.hora, b.n)) b.hora = null;
        await resolverLinea();
    }

    /** La ficha de la fila (su complemento por cantidad) y sus horas. */
    async function cargarFila() {
        catalogStore.select(compra.borrador.fila);
        await catalogStore.loadProduct({ api, id: compra.borrador.fila });
        await cargarHoras();
    }

    /** Sitúa la pantalla 0 en un borrador: los días de TODAS las filas de su zona, y con ellos el día y la fila. */
    async function situar(borrador) {
        compra.borrador = borrador;
        compra.precios = {};
        if (! borrador.zona) return;
        const filas = filasDeZona(catalogStore.products, borrador.zona);

        // Mientras llegan los días, las horas enseñan su hueco (el esqueleto del diseño): nada salta después.
        compra.cargandoHoras = true;
        compra.precios = await cargarDiasDeFilas({ api, ids: filas.map((p) => p.id) });
        const dias = compra.precios[borrador.fila] ?? [];
        if (! dias.some((d) => d.date === borrador.dia)) compra.borrador.dia = dias.some((d) => d.date === todayIso()) ? todayIso() : primerDia(dias);
        await cargarFila();
    }

    /** Lo que la pantalla avisa que ha cambiado. */
    function cambiar(campo, valor) {
        const b = compra.borrador;

        compra.aviso = '';
        if (campo === 'zona') return enCola(() => situar({ ...borradorDeIntencion({ type: 'zone', slug: valor }, catalogStore.products), elegirZona: b.elegirZona, dia: b.dia, n: b.n }));
        if (campo === 'dia') { b.dia = valor; return enCola(cargarHoras); }
        if (campo === 'fila') { b.fila = Number(valor); return enCola(cargarFila); }
        if (campo === 'hora') { b.hora = horaDelMotor(timeStore.offered, valor); return enCola(resolverLinea); }
        if (campo === 'n') { b.n = valor; return b.hora ? enCola(cargarHoras) : null; }
        if (campo === 'cal') { b.cal = valor; return enCola(resolverLinea); }

        return null;
    }

    /**
     * La intención de la landing (`index.js` la deja en la cola de la máquina): se toma una vez y, si el catálogo aún
     * no ha llegado, espera a él. Sin intención, «Para hoy» eligiendo zona. Una compra que ya terminó (o se quedó en
     * un desenlace) deja paso a la nueva: es lo que hace «Hacer otra reserva».
     *
     * ⚠️⚠️ **Pero SIN intención, un desenlace se queda** (`empiezaOtra`): es la vuelta del banco, que monta el motor
     * con la isla abierta en «¡Reservado!» o en el pago no completado. Empezar ahí borraba el desenlace y enseñaba la
     * pantalla 0 a quien acababa de pagar (lo cazó la sonda en el navegador).
     */
    function applyIntent() {
        compra.intencion = store.machine?.takeIntent?.() ?? compra.intencion;
        if (catalogStore.products.length === 0) return;
        const intencion = compra.intencion;

        compra.intencion = null;
        if (empiezaOtra(intencion, store.step)) empezar(intencion);
    }

    function empezar(intencion) {
        cartStore.setError('');
        if (isOutcome(store.step)) flow.addAnother();
        Object.assign(compra, { paso: 'cuando', aviso: '', pedido: null, pagado: null });
        enCola(() => situar(borradorDeIntencion(intencion, catalogStore.products)));
    }

    watch(() => catalogStore.products, () => applyIntent());
    onMounted(applyIntent);

    // ── Los pasos ────────────────────────────────────────────────────────────────────────────────────

    /** «Continuar» de la pantalla 0: la línea a la cesta (la sustituye, `linea.js`) y la admisión del motor. */
    async function continuar() {
        if (compra.ocupado) return;
        compra.ocupado = 'cuando';
        compra.aviso = '';

        try {
            // El texto del descargo, ya: viaja mientras se valida la línea y se admite, y la casilla de «Tus datos» no
            // aparece tarde empujando el formulario.
            datos.waiverStore.ensureLegal();
            await enCola(() => {});
            const pedido = pedidoDe(compra.borrador, {
                minimo: catalogStore.minQuantity, maximo: timeStore.maxQuantity,
                calcetin: calcetinDe(catalogStore.product), guardian: catalogStore.product?.guardian_authorization,
            });

            cartStore.setError('');
            const r = await meterLinea({ api, pedido, resueltos: selectionStore.resolved, cartStore, messages: props.messages });

            if (! r.ok) { compra.aviso = r.aviso; return; }
            compra.pedido = { ...pedido, n: cartStore.lines[0]?.quantity ?? pedido.n };
            store.go(STEPS.CART);
            await flow.checkout();

            if (store.step !== STEPS.IDENTIFY && store.step !== STEPS.PAY) {
                compra.aviso = cartStore.error || t(props.messages, 'errors.try_later');

                return;
            }

            datos.preparar();
            compra.paso = 'datos';
        } finally {
            compra.ocupado = null;
        }
    }

    /** «Continuar al pago» de «Tus datos». */
    async function continuarDatos() {
        if (compra.ocupado) return;
        compra.ocupado = 'datos';

        try {
            if (await datos.continuar()) compra.paso = 'pagar';
        } finally {
            compra.ocupado = null;
        }
    }

    /** El «no» del pago sobre lo que el comprador debe (el teléfono): a «Tus datos», con el error en su campo. */
    function alPagarMal(errores) {
        compra.paso = 'datos';
        Object.assign(datos.estado, { vista: null, errores: { telefono: errores.phone ?? '' }, aviso: errores.accept_terms ?? '' });
    }

    /** Volver de «Tus datos» a la pantalla 0: la línea sale de la cesta, y la pantalla 0 sigue como estaba. */
    async function aCuando() {
        Object.assign(compra, { paso: 'cuando', pedido: null, aviso: '' });
        cartStore.setError('');
        await flow.removeLine(0);
        enCola(cargarHoras);
    }

    // Lo que la máquina hace sola —el sondeo que caduca, un reintento que ya no puede, el titular que cambió— devuelve
    // a la isla a su pantalla 0, con el aviso del motor. (Lo que la isla hace ella misma limpia antes ese aviso.)
    watch(() => store.step, (ahora, antes) => {
        if (ahora === STEPS.CATALOG && antes !== STEPS.CATALOG && (compra.paso !== 'cuando' || cartStore.error)) {
            Object.assign(compra, { paso: 'cuando', aviso: cartStore.error, pedido: null });
            enCola(cargarHoras);
        }

        if (ahora === STEPS.IDENTIFY && antes === STEPS.DECLINED) {
            datos.preparar();
            compra.paso = 'datos';
        }
    });

    /** La X: cierra la isla. Tras «Listo», la próxima vez se empieza otra compra (el `cerrar()` del diseño). */
    function cerrar() {
        cerrarSuperficie();
        if (store.step === STEPS.CONFIRMED) empezar(null);
    }

    // ── Lo que se pinta ──────────────────────────────────────────────────────────────────────────────

    const vista = computed(() => pantallaCuando({
        borrador: compra.borrador, productos: catalogStore.products, precios: compra.precios, horas: timeStore.offered,
        cargandoHoras: compra.cargandoHoras, maximo: compra.borrador.hora ? timeStore.maxQuantity : null, minimo: catalogStore.minQuantity,
        umbral: timeStore.lowMax, calcetin: calcetinDe(catalogStore.product), linea: selectionStore.line, textos, locale: flow.locale, hoy: todayIso(),
    }));

    const deLaCesta = (quote) => ({ summary: resumenDe(quote.lines, { textos, locale: flow.locale }), total: euros(quote.total_cents, flow.locale) });

    // La foto de la última cesta presupuestada: se vacía al crear el pedido, y «saliendo al banco» sigue enseñándola.
    watch(() => cartStore.quote, (quote) => { if (quote?.lines?.length) compra.pagado = deLaCesta(quote); }, { immediate: true });

    /** Lo de debajo: la línea y el total de la cesta mientras se compra; del pedido, cuando ya existe. */
    const resumen = computed(() => {
        if (paso.value === 'datos' || paso.value === 'pagar') return cartStore.quote?.lines?.length ? deLaCesta(cartStore.quote) : {};
        if (outcomeStore.confirmation) return resumenDelPedido(outcomeStore.confirmation, { textos, locale: flow.locale });

        return paso.value === 'banco' ? (compra.pagado ?? {}) : {};
    });

    watch(() => rango(paso.value, datos.estado.vista), (ahora, antes) => { compra.dir = direccion(antes, ahora); });

    const ck = computed(() => {
        if (paso.value === 'cuando') {
            const c = vista.value.ck;

            return {
                ...c, dir: compra.dir, onBack: null, onClose: cerrar,
                action: { ...c.action, onClick: continuar, loading: compra.ocupado === 'cuando' ? t(textos, 'pieza.cargando') : false },
            };
        }

        return {
            ...ckDelPaso({
                paso: paso.value, vista: datos.estado.vista, textos, resumen: resumen.value, ocupado: compra.ocupado,
                importe: euros(cartStore.quote?.online_amount_cents ?? 0, flow.locale),
                acciones: {
                    cerrar,
                    volver: paso.value === 'pagar' ? () => { compra.paso = 'datos'; } : datos.estado.vista ? () => { datos.estado.vista = null; } : aCuando,
                    continuar: continuarDatos, pagar: pago.pagar, salir: pago.salir, reintentar: pago.reintentar, miQr: pago.miQr,
                },
            }),
            dir: compra.dir,
        };
    });

    const pantallaDatos = computed(() => ({
        cuenta: datos.cuenta.value,
        nombrePila: datos.contexto.context?.first_name ?? '',
        valores: datos.estado.f,
        firmado: ! datos.firma.value,
        pedirTelefono: datos.pedirTelefono.value,
        errores: datos.estado.errores,
        aviso: datos.estado.aviso,
        // Lo de después de pagar (los hijos, los adultos) solo tiene sentido si la instalación firma dentro y viene más gente.
        lineaMenores: (datos.contexto.context?.waiver?.mode ?? datos.waiverStore.legal?.mode) === 'interno' && (compra.pedido?.n ?? 0) > 1,
        // T3e·4: «Entra» y Google (y Apple, corchete apagado). Hasta entonces, sin los botones que aún no entran.
        entrar: false,
        social: false,
    }));

    const listo = computed(() => pantallaListo({
        linea: lineaListo(outcomeStore.confirmation, { textos, locale: flow.locale }),
        confirmacion: outcomeStore.confirmation, correo: pago.listo.correo, qrSrc: pago.qrSrc.value, cuentaNueva: pago.listo.cuentaNueva,
        firmaDentro: datos.contexto.context?.waiver?.mode === 'interno', textos,
    }));
    watch(paso, (ahora) => { if (ahora === 'listo') pago.listo.cuentaNueva = datos.cuentaNueva(); }, { immediate: true });

    /**
     * Un desenlace que aún espera lo suyo (el pedido para «Listo», el motivo para el pago no completado) enseña la
     * espera del sistema, no una pantalla a medias. Si no llega, se pinta con lo que haya: nunca una espera eterna.
     */
    const esperando = computed(() => flow.busy.value && (
        (paso.value === 'listo' && ! outcomeStore.confirmation) || (paso.value === 'fallido' && ! outcomeStore.declinedReason)
    ));

    return {
        abierta, textos, ck, paso, esperando, compra, flow, authStore, outcomeStore,
        cuando: computed(() => ({ ...vista.value.props, aviso: compra.aviso })), cambiar,
        datos, pantallaDatos, pago, listo,
        recibo: computed(() => ({ ...reciboDe({ quote: cartStore.quote, pedido: compra.pedido, textos, locale: flow.locale }), aviso: compra.aviso })),
        fallido: computed(() => ({ hora: outcomeStore.holdUntil, motivo: outcomeStore.declinedReason, aviso: compra.aviso })),
        otraReserva: () => empezar(null),
        rotuloOtra: t(textos, 'compra.listo.otra'),
        urls: props.urls ?? {},
        refreshBookingStatus: flow.refreshBookingStatus, refreshIdentity: flow.refreshIdentity,
        openProduct: (id) => { store.machine?.queueIntent?.({ type: 'product', id }); applyIntent(); return true; },
        applyIntent,
    };
}
