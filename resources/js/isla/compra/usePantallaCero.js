/**
 * **LA PANTALLA 0 de la compra de la isla sobre el motor** (T3e de `docs/specs/isla-y-landing-nueva.md` §4.10): las
 * ENTRADAS (T3e·2b) y las FIESTAS (T3e·5, `DECISIONES #696`). Salió de `useSeccionCompra.js` al llegar las fiestas:
 * es una unidad —el borrador, lo que se pide al motor al cambiarlo y su vista— y el orquestador se queda con los pasos.
 *
 * La pantalla 0 es un FORMULARIO, no un embudo: el tiempo (el producto) cambia después de elegir la hora, y en una
 * fiesta la EDAD cambia el pack. Por eso la isla lleva su borrador y pide la oferta a los stores del motor sin pasar
 * por sus pasos (`selectProduct()` borraría día y hora). La traducción a pantalla, sin estado: `vista.js` y `fiesta.js`.
 *
 * ⚠️ **Todo lo que pide va EN COLA** (`enCola`, del orquestador): dos cambios seguidos lanzan dos peticiones, y si la
 * primera respondiera la última se pintaría el total VIEJO.
 */
import { computed } from 'vue';
import { api } from '../../sidebar/api.js';
import { todayIso } from '../../sidebar/cart.js';
import {
    borradorDeIntencion, calcetinDe, cargarDiasDeFilas, cargarFichas, cargarGrupos, horaDelMotor, horaQueCabe, primerDia,
} from './oferta.js';
import { filasDeZona, pantallaCuando } from './vista.js';
import { campoDeEdad, eleccionesDe, menuElegido, packPorEdad, packsDeFiesta, pantallaCuandoFiesta } from './fiesta.js';

export function usePantallaCero({ flow, compra, enCola, textos }) {
    const { catalogStore, timeStore, selectionStore, cartStore } = flow;

    /** Los packs de fiesta de la zona del borrador, con su ficha (los que preguntan la edad). */
    const packs = computed(() => packsDeFiesta(catalogStore.products, compra.fichas, compra.borrador.zona));

    /** La línea que resuelve el SERVIDOR con la selección de ahora (el dinero, con los calcetines o el menú dentro). */
    async function resolverLinea() {
        const b = compra.borrador;

        if (! b.hora) { selectionStore.setLine(null); return; }
        timeStore.select(b.hora);
        selectionStore.setQuantity(b.n);
        const calcetin = b.fiesta ? null : calcetinDe(catalogStore.product);
        selectionStore.setQuantities(calcetin && b.cal > 0 ? [{ product_id: calcetin.id, quantity: b.cal }] : []);
        selectionStore.setChoices(b.fiesta ? eleccionesDe(compra.grupos, b.menu) : []);
        await selectionStore.loadAddons({ api, productId: b.fila, date: b.dia, time: b.hora });
        // Con hora, los menús vuelven RESUELTOS para esa gente: son los que se pintan.
        if (b.fiesta && selectionStore.addons?.groups?.length) compra.grupos = selectionStore.addons.groups;
    }

    /** Las horas de la fila y el día, con la cesta (`AFORO-02`). Una hora que ya no cabe se vacía. */
    async function cargarHoras() {
        const b = compra.borrador;

        if (! b.fila || ! b.dia) { compra.cargandoHoras = false; return; }
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

    /**
     * El PACK del borrador (T3e·5): su ficha —ya cargada con las de su zona— como producto elegido, sus menús (antes
     * de la hora, sin línea: `oferta.js::cargarGrupos`) y, si hay día, sus horas. El menú elegido se conserva si el
     * nuevo pack lo ofrece; si no, el que deje elegido el servidor.
     */
    async function cargarPack() {
        const b = compra.borrador;
        const ficha = compra.fichas[b.fila] ?? null;

        if (! ficha) { compra.cargandoHoras = false; return; }
        catalogStore.select(b.fila);
        catalogStore.setProduct(ficha);
        catalogStore.rememberFields(b.fila, ficha.event_fields ?? []);
        compra.grupos = await cargarGrupos({ api, productId: b.fila, quantity: b.n });
        if (! (compra.grupos[0]?.options ?? []).some((o) => String(o.product_id) === b.menu)) b.menu = menuElegido(compra.grupos);
        const dias = compra.precios[b.fila] ?? [];

        if (b.dia && ! dias.some((d) => d.date === b.dia)) Object.assign(b, { dia: null, hora: null });
        await cargarHoras();
    }

    /**
     * Sitúa la pantalla 0 de una FIESTA: las fichas y los días de TODOS los packs de su zona (en paralelo), y los niños
     * en el mínimo del pack. Sin día elegido: una fiesta no nace «para hoy».
     */
    async function situarFiesta(borrador) {
        compra.borrador = borrador;
        Object.assign(compra, { precios: {}, fichas: {}, grupos: [], cargandoHoras: true });
        const ids = catalogStore.products.filter((p) => p?.type === 'pack' && p.zone?.slug === borrador.zona).map((p) => p.id);
        const [fichas, precios] = await Promise.all([cargarFichas({ api, ids }), cargarDiasDeFilas({ api, ids })]);

        Object.assign(compra, { fichas, precios });
        const b = compra.borrador;

        if (! fichas[b.fila]) b.fila = packs.value[0]?.id ?? b.fila;
        if (b.n == null) b.n = fichas[b.fila]?.min_quantity ?? 1;
        await cargarPack();
    }

    /** Sitúa la pantalla 0 en un borrador: los días de TODAS las filas de su zona, y con ellos el día y la fila. */
    async function situar(borrador) {
        if (borrador.fiesta) return situarFiesta(borrador);
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

    /**
     * Lo que avisa la pantalla de una FIESTA: la edad (que puede cambiar de pack, y con él sus niños, sus días y sus
     * menús), los niños (caben o no a esa hora), el día, la hora y el menú.
     */
    function cambiarFiesta(campo, valor) {
        const b = compra.borrador;

        if (campo === 'edad') {
            b.edad = Number(valor);
            const pack = packPorEdad(packs.value, b.edad);

            if (! pack || pack.id === b.fila) return null;
            b.fila = pack.id;
            b.n = Math.min(Math.max(b.n ?? 0, pack.min_quantity ?? 1), pack.max_quantity ?? Infinity);

            return enCola(cargarPack);
        }
        if (campo === 'n') { b.n = valor; return enCola(cargarHoras); }
        if (campo === 'dia') { b.dia = valor; return enCola(cargarHoras); }
        if (campo === 'hora') { b.hora = horaDelMotor(timeStore.offered, valor); return enCola(resolverLinea); }
        if (campo === 'menu') { b.menu = valor; return enCola(resolverLinea); }

        return null;
    }

    /** Lo que la pantalla avisa que ha cambiado. */
    function cambiar(campo, valor) {
        const b = compra.borrador;

        compra.aviso = '';
        if (b.fiesta) return cambiarFiesta(campo, valor);
        if (campo === 'zona') return enCola(() => situar({ ...borradorDeIntencion({ type: 'zone', slug: valor }, catalogStore.products), elegirZona: b.elegirZona, dia: b.dia, n: b.n }));
        if (campo === 'dia') { b.dia = valor; return enCola(cargarHoras); }
        if (campo === 'fila') { b.fila = Number(valor); return enCola(cargarFila); }
        if (campo === 'hora') { b.hora = horaDelMotor(timeStore.offered, valor); return enCola(resolverLinea); }
        if (campo === 'n') { b.n = valor; return b.hora ? enCola(cargarHoras) : null; }
        if (campo === 'cal') { b.cal = valor; return enCola(resolverLinea); }

        return null;
    }

    /** Lo que la línea lleva de la pantalla 0 además del borrador: de una fiesta, la edad y el menú. */
    function extrasDelPedido() {
        const b = compra.borrador;

        if (! b.fiesta) return { calcetin: calcetinDe(catalogStore.product), evento: {}, elecciones: [] };
        const campo = campoDeEdad(compra.fichas[b.fila]);

        return { calcetin: null, evento: campo ? { [campo.key]: b.edad } : {}, elecciones: eleccionesDe(compra.grupos, b.menu) };
    }

    const vista = computed(() => {
        const b = compra.borrador;
        const comun = {
            borrador: b, precios: compra.precios, horas: timeStore.offered, cargandoHoras: compra.cargandoHoras,
            maximo: b.hora ? timeStore.maxQuantity : null, linea: selectionStore.line, textos, locale: flow.locale, hoy: todayIso(),
        };

        return b.fiesta
            ? pantallaCuandoFiesta({ ...comun, packs: packs.value, grupos: compra.grupos, corte: flow.configuracion.value?.guest_count_cutoff_hours })
            : pantallaCuando({ ...comun, productos: catalogStore.products, minimo: catalogStore.minQuantity, umbral: timeStore.lowMax, calcetin: calcetinDe(catalogStore.product) });
    });

    return { vista, situar, cambiar, cargarHoras, extrasDelPedido };
}
