/**
 * **LA COMPRA DE LA ISLA sobre el motor** (T3e de `docs/specs/isla-y-landing-nueva.md` §4.10, `DECISIONES #692`).
 *
 * La secuencia es la del cajón (`usePurchaseFlow()`, `#691`): el montaje —catálogo, `/config`, la pausa, la cesta y
 * el desenlace—, la admisión, el alta, el cobro y el sondeo son LOS MISMOS. Lo que es de la isla vive aquí: su
 * borrador de la pantalla 0 (un formulario, no un embudo: el tiempo cambia después de la hora) y la traducción a sus
 * pantallas, que hace `vista.js` sin estado y con su `node --test`.
 *
 * ▶ **T3e·2b**: la pantalla 0 de las ENTRADAS con los datos del motor. «Continuar» se enciende en la T3e·3, que trae
 * «Tus datos»: hasta entonces la isla enseña, elige y resuelve la línea, pero no la mete en la cesta.
 *
 * ⚠️ Los stores del motor se comparten con el cajón (una sola app, un solo Pinia), y con la isla como carcasa el
 * cajón no monta su compra: aquí se usan sin pasar por sus pasos (`selectProduct()` borraría día y hora).
 */
import { computed, inject, onMounted, reactive, watch } from 'vue';
import { usePurchaseFlow } from '../../sidebar/usePurchaseFlow.js';
import { TEXTOS_ISLA } from '../../sidebar/carcasa.js';
import { api } from '../../sidebar/api.js';
import { todayIso } from '../../sidebar/cart.js';
import { useSuperficie } from './useSuperficie.js';
import { borradorDeIntencion, calcetinDe, cargarDiasDeFilas, horaDelMotor, horaQueCabe, primerDia } from './oferta.js';
import { filasDeZona, pantallaCuando } from './vista.js';

export function useSeccionCompra(props) {
    const textos = inject(TEXTOS_ISLA, {});
    const flow = usePurchaseFlow(props);
    const { store, catalogStore, timeStore, selectionStore, cartStore } = flow;
    const { abierta, cerrar } = useSuperficie();
    const compra = reactive({ borrador: borradorDeIntencion(null, []), precios: {}, cargandoHoras: false, intencion: null });

    /**
     * ⚠️ **En COLA**: dos cambios seguidos (más gente, un par de calcetines) lanzan dos peticiones, y si la primera
     * respondiera la última la isla enseñaría el total VIEJO. En fila, lo último pedido es lo último que se pinta.
     */
    let cola = Promise.resolve();
    const enCola = (tarea) => (cola = cola.then(tarea, tarea));

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
     * no ha llegado, espera a él. Sin intención, «Para hoy» eligiendo zona.
     */
    function applyIntent() {
        compra.intencion = store.machine?.takeIntent?.() ?? compra.intencion;
        if (catalogStore.products.length === 0) return;
        const intencion = compra.intencion;

        compra.intencion = null;
        enCola(() => situar(borradorDeIntencion(intencion, catalogStore.products)));
    }

    watch(() => catalogStore.products, () => applyIntent());
    onMounted(applyIntent);

    const vista = computed(() => pantallaCuando({
        borrador: compra.borrador, productos: catalogStore.products, precios: compra.precios, horas: timeStore.offered,
        cargandoHoras: compra.cargandoHoras, maximo: compra.borrador.hora ? timeStore.maxQuantity : null, minimo: catalogStore.minQuantity,
        umbral: timeStore.lowMax, calcetin: calcetinDe(catalogStore.product), linea: selectionStore.line, textos, locale: flow.locale, hoy: todayIso(),
    }));

    const ck = computed(() => ({
        ...vista.value.ck,
        dir: null,
        onBack: null,
        onClose: cerrar,
        // ⚠️ T3e·2b: apagado hasta la T3e·3 («Tus datos»), que es quien sigue la compra desde aquí.
        action: { ...vista.value.ck.action, disabled: true, onClick: () => {} },
    }));

    return {
        abierta, textos, ck, cuando: computed(() => vista.value.props), cambiar,
        refreshBookingStatus: flow.refreshBookingStatus, refreshIdentity: flow.refreshIdentity,
        openProduct: (id) => { store.machine?.queueIntent?.({ type: 'product', id }); applyIntent(); return true; },
        applyIntent,
    };
}
