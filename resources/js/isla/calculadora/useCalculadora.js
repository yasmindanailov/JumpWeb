/**
 * **LA CALCULADORA DE LA PÁGINA SOBRE EL MOTOR** (T4d·4 de `docs/specs/isla-y-landing-nueva.md` §4.12): el borrador —la
 * fila, el día, la hora, cuántos y los calcetines—, lo que se le pide a la OFERTA del motor al cambiarlo y su vista
 * (`vista.js`). Es una app aparte de la compra (su Pinia, sus stores), y por eso SIN `usePurchaseFlow`: el alta, la
 * admisión y el cobro no son suyos; los días, las horas y el dinero salen de los MISMOS stores y peticiones que la
 * pantalla 0 de la isla (`compra/oferta.js`). «Reservar y pagar» le pasa la selección a la compra de la isla
 * (`cajon.openWith`, intención `linea`), que la lleva sola a «Tus datos».
 *
 * ⚠️ **La cesta, con su TITULAR** (`owner`, el `auth()->id()` que da la página, el mismo del arranque del motor): las
 * horas se ofrecen descontando lo que la cesta ya retiene (`AFORO-02`), y leerla con otro titular la PURGARÍA
 * (`cart.js::decideOwnership`). Aquí solo se LEE: la calculadora nunca guarda la cesta.
 * ⚠️ **Todo lo que pide va EN COLA**: dos cambios seguidos lanzan dos peticiones, y si la primera respondiera la última
 * se pintaría el total VIEJO. Mientras haya alguna en camino, el botón espera (`pendiente`).
 * ⚠️ El borrador nace SIN día (`inicio="vacio"` del diseño: un día de fábrica es un día que alguien puede pagar sin
 * haberlo elegido), y elegir una fila que no se vende ese día vacía el día y la hora: el precio no cambia a escondidas.
 */
import { computed, reactive } from 'vue';
import { api } from '../../sidebar/api.js';
import { todayIso } from '../../sidebar/cart.js';
import { useCartStore } from '../../sidebar/stores/cart.js';
import { useCatalogStore } from '../../sidebar/stores/catalog.js';
import { useSelectionStore } from '../../sidebar/stores/selection.js';
import { useTimeStore } from '../../sidebar/stores/time.js';
import { calcetinDe, cargarDiasDeFilas, horaDelMotor, horaQueCabe } from '../compra/oferta.js';
import { cargarCargo } from './cargo.js';
import { cierreDelDia, vistaCalculadora } from './vista.js';

export function useCalculadora({ pagina, textos, locale, owner = null }) {
    const catalogStore = useCatalogStore();
    const timeStore = useTimeStore();
    const selectionStore = useSelectionStore();
    const cartStore = useCartStore();
    const hoy = todayIso();
    const e = reactive({
        borrador: { fila: pagina.filas[0]?.id ?? null, dia: null, hora: null, n: Number(pagina.textos.inicio?.personas) || 1, cal: 0 },
        precios: {}, cargoCalcetines: null, pendientes: 0, tocada: false,
    });

    let cola = Promise.resolve();
    const enCola = (tarea) => {
        e.pendientes += 1;
        cola = cola.then(tarea).catch(() => {}).finally(() => { e.pendientes -= 1; });

        return cola;
    };

    /**
     * Los calcetines: los de la ficha que trae el motor (`calcetinDe`, la regla de la compra) y, hasta que llega, los que
     * la página ya sabe de su hecho (`pagina.calcetin`): la pregunta se pinta desde el primer momento, sin pedir nada.
     */
    const calcetinActual = () => (catalogStore.product ? calcetinDe(catalogStore.product) : (pagina.calcetin ?? null));

    /** Lo que cuesta la selección: la línea del servidor con hora; sin ella, solo el cargo de los calcetines. */
    async function resolver() {
        const b = e.borrador;
        const calcetin = calcetinActual();
        const addons = calcetin && b.cal > 0 ? [{ product_id: calcetin.id, quantity: b.cal }] : [];

        selectionStore.setQuantity(b.n);
        selectionStore.setQuantities(addons);
        selectionStore.setChoices([]);
        if (b.dia && b.hora) {
            timeStore.select(b.hora);
            await selectionStore.loadAddons({ api, productId: b.fila, date: b.dia, time: b.hora });
            const cargo = (selectionStore.addons?.singles ?? []).find((a) => a.product_id === calcetin?.id)?.charged_cents;

            e.cargoCalcetines = addons.length && Number.isInteger(cargo) ? cargo : null;
            return;
        }
        selectionStore.setLine(null);
        e.cargoCalcetines = addons.length ? await cargarCargo({ api, productId: b.fila, quantity: b.n, addons, id: calcetin.id }) : null;
    }

    /** Las horas del día con la cesta (`AFORO-02`); una hora que ya no cabe se vacía. */
    async function cargarHoras() {
        const b = e.borrador;

        if (b.dia) {
            await timeStore.loadOffer({ api, productId: b.fila, date: b.dia, cartLines: cartStore.lines });
            if (b.hora && ! horaQueCabe(timeStore.offered, b.hora, b.n)) b.hora = null;
        } else {
            timeStore.setOffer([]);
        }
        await resolver();
    }

    /** La ficha de la fila (su complemento por cantidad) y sus horas. */
    async function cargarFila() {
        catalogStore.select(e.borrador.fila);
        await catalogStore.loadProduct({ api, id: e.borrador.fila });
        await cargarHoras();
    }

    /**
     * Lo que se PIDE, una sola vez: la cesta de su titular (solo leída), los días de TODAS las filas y la ficha de la
     * primera. Lo llama quien monta al ACERCARSE la pieza —la vista ya está pintada con lo que da la página—, o el primer
     * cambio si llega antes.
     */
    let arrancada = null;
    function arrancar() {
        if (arrancada) return arrancada;
        cartStore.setOwner(owner);
        cartStore.setLines(cartStore.restore(hoy).lines);
        arrancada = enCola(async () => {
            e.precios = await cargarDiasDeFilas({ api, ids: pagina.filas.map((f) => f.id) });
            await cargarFila();
        });

        return arrancada;
    }

    /** Lo que la vista avisa que ha cambiado. */
    function cambiar(campo, valor) {
        const b = e.borrador;

        arrancar();
        e.tocada = true;

        if (campo === 'dia') { b.dia = valor; return enCola(cargarHoras); }
        if (campo === 'hora') { b.hora = horaDelMotor(timeStore.offered, valor); return enCola(resolver); }
        if (campo === 'cal') { b.cal = valor; return enCola(resolver); }
        if (campo === 'n') {
            b.n = valor;
            if (b.hora && ! horaQueCabe(timeStore.offered, b.hora, b.n)) b.hora = null;

            return enCola(resolver);
        }
        if (campo === 'fila') {
            b.fila = Number(valor);
            if (b.dia && ! (e.precios[b.fila] ?? []).some((d) => d.date === b.dia)) Object.assign(b, { dia: null, hora: null });

            return enCola(cargarFila);
        }

        return null;
    }

    /** «Reservar y pagar»: la selección ENTERA a la compra de la isla, que sigue sola a «Tus datos». */
    function reservar() {
        const b = e.borrador;
        const calcetin = calcetinActual();

        window.JumpWeb?.cajon?.openWith?.({
            type: 'linea', id: b.fila, date: b.dia, time: b.hora, quantity: b.n,
            addons: calcetin && b.cal > 0 ? [{ product_id: calcetin.id, quantity: b.cal }] : [], continuar: true,
        });
    }

    const vista = computed(() => vistaCalculadora({
        pagina, textos, locale, hoy, borrador: e.borrador, precios: e.precios, horas: timeStore.offered, linea: selectionStore.line,
        cargoCalcetines: e.cargoCalcetines, calcetin: calcetinActual(), minimo: catalogStore.minQuantity,
        maximo: e.borrador.hora ? timeStore.maxQuantity : null, cierre: cierreDelDia(pagina.cierres, e.borrador.dia), pendiente: e.pendientes > 0,
        tocada: e.tocada,
    }));

    /** «Reservar para hoy» desde la isla o la página (el `pedirHoy` del diseño): hoy elegido; queda la hora. */
    const elegirHoy = () => cambiar('dia', hoy);

    return { vista, arrancar, cambiar, reservar, elegirHoy };
}
