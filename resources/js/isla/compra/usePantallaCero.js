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
import { calcetinDe, cargarDiasDeFilas, cargarFichas, horaDelMotor, horaQueCabe, primerDia } from './oferta.js';
import { informarDemanda } from './demanda.js';
import { borradorDeIntencion, borradorVacio } from './intencion.js';
import { horaCorta } from './vista.js';
import { datosDeReserva, filasDeZona, pantallaCuando, preguntaCalcetines, respuestasDe } from './pantalla-cuando.js';
import { campoDeEdad, eleccionesDe, menuElegido, packPorEdad, packsDeFiesta, pantallaCuandoFiesta } from './fiesta.js';
import { cargarSinHora, complementosDe, conExtra, quedanEn } from './complementos.js';

export function usePantallaCero({ flow, compra, enCola, textos }) {
    const { catalogStore, timeStore, selectionStore, cartStore } = flow;

    /** Los packs de fiesta de la zona del borrador, con su ficha (los que preguntan la edad). */
    const packs = computed(() => packsDeFiesta(catalogStore.products, compra.fichas, compra.borrador.zona));

    /** La ficha del producto del borrador: la de la fila o, en una fiesta, la de su pack. */
    const ficha = () => (compra.borrador.fiesta ? (compra.fichas[compra.borrador.fila] ?? null) : catalogStore.product);

    /**
     * Los complementos que se PIDEN (`#880`): los pares de calcetines —de una entrada y, desde la M1, también de una
     * fiesta— y lo elegido en la lista (`extras`; de una fiesta, también lo que la alarga desde la calculadora, T6b·3). Lo
     * que a esa hora no se ofrezca lo suelta el servidor de `selection` (lo que se guarda); el borrador lo sigue pidiendo
     * por si vuelve a caber, como la calculadora.
     */
    function pedidos() {
        const b = compra.borrador;
        const calcetin = calcetinDe(ficha());

        return [
            ...(calcetin && b.cal > 0 ? [{ product_id: calcetin.id, quantity: b.cal }] : []),
            ...(b.extras ?? []).filter((x) => x.product_id !== calcetin?.id),
        ];
    }

    /** La línea que resuelve el SERVIDOR con la selección de ahora (el dinero, con los complementos y el menú dentro). */
    async function resolverLinea() {
        const b = compra.borrador;

        if (! b.hora) { selectionStore.setLine(null); return; }
        timeStore.select(b.hora);
        selectionStore.setQuantity(b.n);
        selectionStore.setQuantities(pedidos());
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

    /** La demanda sin hueco (`#758`, `demanda.js`): la fila o el pack que la pantalla 0 enseña es el que el cliente mira. */
    const mirada = () => informarDemanda({ id: compra.borrador.fila, dias: compra.precios[compra.borrador.fila], llegaron: compra.llegaron });

    /** Los complementos del producto resueltos SIN día ni hora: con ellos la lista tiene precio antes de la hora (`#880`). */
    async function cargarSueltos() {
        const b = compra.borrador;

        compra.sueltos = (await cargarSinHora({ api, productId: b.fila, quantity: b.n ?? 1 })).sueltos;
    }

    /**
     * La ficha de la fila (sus complementos), lo que se le pide sin hora y sus horas. Lo elegido de la fila anterior que
     * ésta no vende, fuera: otro producto, otros complementos (`#880`).
     */
    async function cargarFila() {
        mirada();
        catalogStore.select(compra.borrador.fila);
        await catalogStore.loadProduct({ api, id: compra.borrador.fila });
        compra.borrador.extras = quedanEn(compra.borrador.extras, catalogStore.product);
        await Promise.all([cargarSueltos(), cargarHoras()]);
    }

    /**
     * El PACK del borrador (T3e·5): su ficha —ya cargada con las de su zona— como producto elegido, sus menús y sus
     * complementos (antes de la hora, sin línea: `complementos.js::cargarSinHora`) y, si hay día, sus horas. El menú
     * elegido se conserva si el nuevo pack lo ofrece; si no, el que deje elegido el servidor.
     */
    async function cargarPack() {
        const b = compra.borrador;
        const ficha = compra.fichas[b.fila] ?? null;

        mirada();
        if (! ficha) { compra.cargandoHoras = false; return; }
        catalogStore.select(b.fila);
        catalogStore.setProduct(ficha);
        catalogStore.rememberFields(b.fila, ficha.event_fields ?? []);
        const sinHora = await cargarSinHora({ api, productId: b.fila, quantity: b.n });

        compra.grupos = sinHora.grupos;
        compra.sueltos = sinHora.sueltos;
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
        Object.assign(compra, { precios: {}, llegaron: [], fichas: {}, grupos: [], cargandoHoras: true });
        const ids = catalogStore.products.filter((p) => p?.type === 'pack' && p.zone?.slug === borrador.zona).map((p) => p.id);
        const [fichas, oferta] = await Promise.all([cargarFichas({ api, ids }), cargarDiasDeFilas({ api, ids })]);

        Object.assign(compra, { fichas, precios: oferta.dias, llegaron: oferta.llegaron });
        const b = compra.borrador;

        // Un pack SIN edad (una excursión, T6c·3) no es una fiesta: se vende como las entradas, con los packs de su zona
        // por filas (`filasDeZona`), lo que pida al reservar (`#839`) y lo que ya traía elegido (la calculadora).
        if (packs.value.length === 0) {
            return situar({ ...borradorVacio(), zona: b.zona, fila: b.fila, dia: b.dia, hora: b.hora, n: b.n ?? fichas[b.fila]?.min_quantity ?? 1 }, oferta);
        }
        if (! fichas[b.fila]) b.fila = packs.value[0]?.id ?? b.fila;
        if (b.n == null) b.n = fichas[b.fila]?.min_quantity ?? 1;
        await cargarPack();
    }

    /**
     * Sitúa la pantalla 0 en un borrador: los días de TODAS las filas de su zona, y con ellos el día y la fila. `cargados`:
     * la oferta que ya trajo quien llama (`{dias, llegaron}`: la fiesta que resultó no serlo), para no pedirla dos veces.
     */
    async function situar(borrador, cargados = null) {
        if (borrador.fiesta) return situarFiesta(borrador);
        compra.borrador = borrador;
        Object.assign(compra, { precios: {}, llegaron: [] });
        if (! borrador.zona) return;
        const filas = filasDeZona(catalogStore.products, borrador.zona);

        // Mientras llegan los días, las horas enseñan su hueco (el esqueleto del diseño): nada salta después.
        compra.cargandoHoras = true;
        const oferta = cargados ?? await cargarDiasDeFilas({ api, ids: filas.map((p) => p.id) });

        Object.assign(compra, { precios: oferta.dias, llegaron: oferta.llegaron });
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
            // La hora extra es de CADA pack (otro complemento, otro precio): la del anterior no vale en éste; lo que el
            // nuevo también vende (los calcetines), sí (`#880`).
            b.extras = quedanEn(b.extras, pack);
            b.n = Math.min(Math.max(b.n ?? 0, pack.min_quantity ?? 1), pack.max_quantity ?? Infinity);

            return enCola(cargarPack);
        }
        if (campo === 'n') { b.n = valor; return enCola(cargarHoras); }
        if (campo === 'dia') { b.dia = valor; return enCola(cargarHoras); }
        if (campo === 'hora') { b.hora = horaDelMotor(timeStore.offered, valor); return enCola(resolverLinea); }
        if (campo === 'menu') { b.menu = valor; return enCola(resolverLinea); }
        if (campo === 'cal') { b.cal = valor; return enCola(resolverLinea); }
        if (campo === 'extra') { b.extras = conExtra(b.extras, valor.id, valor.n); return enCola(resolverLinea); }

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
        // Un complemento de la lista (`#880`): `{ id, n }`; 0 lo quita.
        if (campo === 'extra') { b.extras = conExtra(b.extras, valor.id, valor.n); return enCola(resolverLinea); }
        // Un dato de la reserva de un pack (`#839`): se escribe en el borrador y viaja al continuar; no cambia la oferta.
        if (campo === 'evento') { b.evento = { ...(b.evento ?? {}), [valor.key]: valor.valor }; return null; }

        return null;
    }

    /**
     * Lo que la línea lleva de la pantalla 0 además del borrador: los calcetines y lo elegido en la lista de complementos
     * (`#880`) y, de una fiesta, la edad y el menú.
     */
    function extrasDelPedido() {
        const b = compra.borrador;
        const calcetin = calcetinDe(ficha());
        const extras = (b.extras ?? []).filter((x) => x.product_id !== calcetin?.id);

        // De un pack sin edad, lo que pide al reservar (`#839`): solo sus campos y contestados, como los valida el servidor.
        if (! b.fiesta) return { calcetin, evento: respuestasDe(datosDeReserva(catalogStore.product, b.evento)), elecciones: [], extras };
        const campo = campoDeEdad(compra.fichas[b.fila]);

        return { calcetin, evento: campo ? { [campo.key]: b.edad } : {}, elecciones: eleccionesDe(compra.grupos, b.menu), extras };
    }

    const vista = computed(() => {
        const b = compra.borrador;
        const deLaFila = ficha();
        const calcetin = calcetinDe(deLaFila);
        // La lista de complementos (`#880`): con hora, lo que el servidor resolvió para ESA hora; sin ella, `null`.
        const complementos = complementosDe({
            ficha: deLaFila, excluir: calcetin ? [calcetin.id] : [], extras: b.extras, sinHora: compra.sueltos,
            conHora: b.hora && selectionStore.line ? (selectionStore.addons?.singles ?? []) : null, hora: horaCorta(b.hora), textos,
        });
        const comun = {
            borrador: b, precios: compra.precios, horas: timeStore.offered, cargandoHoras: compra.cargandoHoras,
            maximo: b.hora ? timeStore.maxQuantity : null, linea: selectionStore.line, textos, locale: flow.locale, hoy: todayIso(),
            complementos,
        };

        return b.fiesta
            ? pantallaCuandoFiesta({
                ...comun, packs: packs.value, grupos: compra.grupos, corte: flow.configuracion.value?.guest_count_cutoff_hours,
                calcetines: preguntaCalcetines(calcetin, b.cal, { textos, locale: flow.locale }),
            })
            : pantallaCuando({ ...comun, productos: catalogStore.products, minimo: catalogStore.minQuantity, umbral: timeStore.lowMax, calcetin, ficha: deLaFila });
    });

    return { vista, situar, cambiar, cargarHoras, extrasDelPedido };
}
