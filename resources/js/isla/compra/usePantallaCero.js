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
import { toApiItems, todayIso } from '../../sidebar/cart.js';
import { calcetinDe, cargarDiasDeFilas, cargarFichas, horaDelMotor, horaQueCabe, primerDia } from './oferta.js';
import { informarDemanda } from './demanda.js';
import { borradorDeIntencion, borradorVacio } from './intencion.js';
import { horaCorta } from './vista.js';
import { datosDeReserva, filasDeZona, pantallaCuando, preguntaCalcetines, respuestasDe } from './pantalla-cuando.js';
import { campoDeEdad, menuElegido, packPorEdad, packsDeFiesta, pantallaCuandoFiesta } from './fiesta.js';
import { cargarSinHora, complementosDe, conExtra, eleccionesDelBorrador, gruposComoFilas, quedanEn } from './complementos.js';
import { cargarHorasDe, horasQueNoCaben, otraDeLaPantalla, otraDelDia, otraNueva, otrasZonas } from './otra-zona.js';
import { lineasDe, pedidoDe, resolverOtrasConOferta } from './linea.js';

export function usePantallaCero({ flow, compra, enCola, textos }) {
    const { catalogStore, timeStore, selectionStore, cartStore } = flow;

    /** Los packs de fiesta de la zona del borrador, con su ficha (los que preguntan la edad). */
    const packs = computed(() => packsDeFiesta(catalogStore.products, compra.fichas, compra.borrador.zona));

    /** La ficha del producto del borrador: la de la fila o, en una fiesta, la de su pack. */
    const ficha = () => (compra.borrador.fiesta ? (compra.fichas[compra.borrador.fila] ?? null) : catalogStore.product);

    /** ¿Es una compra de ENTRADAS? La otra zona es solo suya (`otra-zona.md` §2: ni fiestas ni excursiones, el mockup). */
    const deEntradas = () => ! compra.borrador.fiesta && catalogStore.products.find((p) => p.id === compra.borrador.fila)?.type === 'entry';

    /** Los minutos de la fila del pedido (`duration_min`; sin ellos, ilimitada): de partida, el tiempo de la otra (`#882`). */
    const duracionDelPedido = () => catalogStore.products.find((p) => p.id === compra.borrador.fila)?.duration_min ?? null;

    /**
     * La línea de la otra zona como la pide el pedido (`linea.js::pedidoDe`): su fila, su gente, su justificante (el de SU
     * ficha) y, desde la K2·b (`#882`), lo elegido en su tarjeta: sus complementos y sus grupos.
     */
    const otraDelPedido = () => {
        const o = compra.borrador.otra;
        const ficha = compra.fichaOtra?.id === o?.fila ? compra.fichaOtra : null;

        return o ? [{
            fila: o.fila, n: o.n, guardian: ficha?.guardian_authorization ?? 'none',
            extras: o.extras ?? [], elecciones: eleccionesDelBorrador(compra.gruposOtra, { elecciones: o.elecciones ?? {} }),
        }] : [];
    };

    /**
     * **Con la otra zona, el presupuesto de TODAS las líneas** (`POST /orders/quote`, sin tocar la cesta: K2 de
     * `otra-zona.md`): el total de la pantalla 0 es el suyo (`PAY-12`), nunca una suma. Y, de paso, lo que su tarjeta pinta
     * a esa hora (sus complementos, con su precio y si caben: `#882`). Sin ella, o sin hora, ninguno.
     */
    async function cotizar() {
        const b = compra.borrador;

        if (! b.otra || ! b.hora || ! selectionStore.line) { Object.assign(compra, { cotizacion: null, conHoraOtra: null }); return; }
        const fila = b.otra.fila;
        const pedido = pedidoDe(b, { otras: otraDelPedido() });
        const otras = await resolverOtrasConOferta({ api, pedido });
        const r = otras === null ? null
            : await api.post('/orders/quote', { items: toApiItems(lineasDe(pedido, selectionStore.resolved, otras.resueltos)) });

        // Si mientras tanto la quitaron o cambió de tiempo, lo que llegó ya no es suyo: lo pinta la petición que viene detrás.
        if (compra.borrador.otra?.fila !== fila) return;
        const oferta = otras?.ofertas[fila] ?? null;

        compra.conHoraOtra = oferta ? oferta.singles : null;
        if (oferta?.groups.length) compra.gruposOtra = oferta.groups;
        compra.cotizacion = r?.ok ? r.data : null;
    }

    /**
     * ¿Caben TODOS a la hora elegida? La otra línea, en su zona y con su gente (D1-A): si no, la hora se vacía —el selector
     * dice por qué—, como cuando no cabe la del pedido.
     */
    function caben() {
        const b = compra.borrador;

        if (b.otra && b.hora && horasQueNoCaben(compra.horasOtra, b.otra.n)(horaCorta(b.hora))) b.hora = null;
    }

    /**
     * La ficha de la fila de la otra zona —su justificante y sus complementos— y lo que resuelve SIN hora (sus complementos
     * con su nota y sus grupos), si la que hay no es la suya (`#882`: su tiempo se cambia en la tarjeta). Lo elegido de otra
     * fila que ésta no vende, fuera: otro producto, otros complementos (como `cargarFila`).
     */
    async function fichaDeOtra() {
        const o = compra.borrador.otra;

        if (! o || compra.fichaOtra?.id === o.fila) return;
        const [ficha, sinHora] = await Promise.all([api.get(`/catalog/products/${o.fila}`), cargarSinHora({ api, productId: o.fila, quantity: o.n })]);

        if (compra.borrador.otra?.fila !== o.fila) return;
        Object.assign(compra, { fichaOtra: ficha?.ok ? ficha.data : null, sueltosOtra: sinHora.sueltos, gruposOtra: sinHora.grupos });
        compra.borrador.otra = { ...compra.borrador.otra, extras: quedanEn(compra.borrador.otra.extras, compra.fichaOtra) };
    }

    /**
     * Cambió lo de la otra zona y la línea del pedido NO (su resolución no depende de la otra): basta el presupuesto de
     * todas. Si la hora se vació (la otra ya no cabe), la línea del pedido también se vacía (`resolverLinea`).
     */
    const recotizar = () => (compra.borrador.hora && selectionStore.line ? cotizar() : resolverLinea());

    /** La fila de la otra zona: su ficha (`fichaDeOtra`) y sus horas ese día, con la cesta de contexto (`AFORO-02`). */
    async function cargarOtra() {
        const b = compra.borrador;

        if (! b.otra) return;
        const [horas] = await Promise.all([
            b.dia ? cargarHorasDe({ api, fila: b.otra.fila, dia: b.dia, lineas: cartStore.lines }) : null,
            fichaDeOtra(),
        ]);

        compra.horasOtra = horas;
        caben();
        await recotizar();
    }

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

    /**
     * La línea que resuelve el SERVIDOR con la selección de ahora (el dinero, con los complementos y el menú dentro) y, con
     * la otra zona, el presupuesto de todas (`cotizar`).
     */
    async function resolverLinea() {
        const b = compra.borrador;

        if (! b.hora) { selectionStore.setLine(null); compra.cotizacion = null; return; }
        timeStore.select(b.hora);
        selectionStore.setQuantity(b.n);
        selectionStore.setQuantities(pedidos());
        selectionStore.setChoices(elecciones());
        await selectionStore.loadAddons({ api, productId: b.fila, date: b.dia, time: b.hora });
        // Con hora, los grupos de elección (el menú, o los de una entrada) vuelven RESUELTOS para esa gente: son los que se pintan.
        if (selectionStore.addons?.groups?.length) compra.grupos = selectionStore.addons.groups;
        await cotizar();
    }

    /** Lo elegido de cada grupo de elección (`#881`): el menú de una fiesta y lo de los demás grupos. */
    const elecciones = () => {
        const b = compra.borrador;

        return eleccionesDelBorrador(compra.grupos, { menu: b.menu, elecciones: b.elecciones, conMenu: Boolean(b.fiesta) });
    };

    /**
     * Las horas de la fila y el día, con la cesta (`AFORO-02`) y, con la otra zona, también las suyas (en paralelo), y su
     * ficha si su tiempo cambió con el día (`otraDelDia`). Una hora que ya no cabe —la del pedido o la otra— se vacía.
     */
    async function cargarHoras() {
        const b = compra.borrador;

        if (! b.fila || ! b.dia) { compra.cargandoHoras = false; return; }
        compra.cargandoHoras = true;
        const [, horasOtra] = await Promise.all([
            timeStore.loadOffer({ api, productId: b.fila, date: b.dia, cartLines: cartStore.lines }),
            b.otra ? cargarHorasDe({ api, fila: b.otra.fila, dia: b.dia, lineas: cartStore.lines }) : null,
            fichaDeOtra(),
        ]);

        compra.horasOtra = horasOtra;
        compra.cargandoHoras = false;
        if (b.hora && ! horaQueCabe(timeStore.offered, b.hora, b.n)) b.hora = null;
        caben();
        await resolverLinea();
    }

    /** La demanda sin hueco (`#758`, `demanda.js`): la fila o el pack que la pantalla 0 enseña es el que el cliente mira. */
    const mirada = () => informarDemanda({ id: compra.borrador.fila, dias: compra.precios[compra.borrador.fila], llegaron: compra.llegaron });

    /**
     * Lo que el producto resuelve SIN día ni hora: sus complementos —con ellos la lista tiene precio antes de la hora, `#880`—
     * y sus grupos de elección (`#881`; con hora, `resolverLinea` los trae resueltos, con las mismas claves).
     */
    async function cargarSueltos() {
        const b = compra.borrador;
        const sinHora = await cargarSinHora({ api, productId: b.fila, quantity: b.n ?? 1 });

        compra.sueltos = sinHora.sueltos;
        if (! b.hora || ! compra.grupos.length) compra.grupos = sinHora.grupos;
    }

    /**
     * La ficha de la fila (sus complementos), lo que se le pide sin hora y sus horas. Lo elegido de la fila anterior que
     * ésta no vende, fuera: otro producto, otros complementos (`#880`) y otros grupos de elección (`#881`).
     */
    async function cargarFila() {
        mirada();
        catalogStore.select(compra.borrador.fila);
        await catalogStore.loadProduct({ api, id: compra.borrador.fila });
        compra.borrador.extras = quedanEn(compra.borrador.extras, catalogStore.product);
        compra.borrador.elecciones = {};
        compra.grupos = [];
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
        Object.assign(compra, { precios: {}, llegaron: [], horasOtra: null, fichaOtra: null, cotizacion: null, sueltosOtra: [], conHoraOtra: null, gruposOtra: [] });
        if (! borrador.zona) return;
        const filas = filasDeZona(catalogStore.products, borrador.zona);
        // Y las filas de cada OTRA zona de entradas (K2 de `otra-zona.md`; todas desde la K2·b, `#882`): sus días dicen si se
        // ofrece ese día y el precio de cada tiempo de su tarjeta.
        const otras = filas[0]?.type === 'entry' ? otrasZonas(catalogStore.products, borrador.zona).flatMap((z) => z.filas.map((f) => f.id)) : [];

        // Mientras llegan los días, las horas enseñan su hueco (el esqueleto del diseño): nada salta después.
        compra.cargandoHoras = true;
        const oferta = cargados ?? await cargarDiasDeFilas({ api, ids: [...filas.map((p) => p.id), ...otras] });

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
            // nuevo también vende (los calcetines), sí (`#880`). Sus grupos de elección, los suyos (`#881`).
            b.extras = quedanEn(b.extras, pack);
            b.elecciones = {};
            b.n = Math.min(Math.max(b.n ?? 0, pack.min_quantity ?? 1), pack.max_quantity ?? Infinity);

            return enCola(cargarPack);
        }
        if (campo === 'n') { b.n = valor; return enCola(cargarHoras); }
        if (campo === 'dia') { b.dia = valor; return enCola(cargarHoras); }
        if (campo === 'hora') { b.hora = horaDelMotor(timeStore.offered, valor); return enCola(resolverLinea); }
        if (campo === 'menu') { b.menu = valor; return enCola(resolverLinea); }
        if (campo === 'cal') { b.cal = valor; return enCola(resolverLinea); }
        if (campo === 'extra') { b.extras = conExtra(b.extras, valor.id, valor.n); return enCola(resolverLinea); }
        if (campo === 'eleccion') { b.elecciones = { ...(b.elecciones ?? {}), [valor.grupo]: valor.valor }; return enCola(resolverLinea); }

        return null;
    }

    /** Lo que la pantalla avisa que ha cambiado. */
    function cambiar(campo, valor) {
        const b = compra.borrador;

        compra.aviso = '';
        if (b.fiesta) return cambiarFiesta(campo, valor);
        if (campo === 'zona') return enCola(() => situar({ ...borradorDeIntencion({ type: 'zone', slug: valor }, catalogStore.products), elegirZona: b.elegirZona, dia: b.dia, n: b.n }));
        if (campo === 'dia') {
            b.dia = valor;
            // La otra zona, si su tiempo no se vende ese día y otro sí, al parecido (`#882`): nunca una opción apagada elegida.
            b.otra = otraDelDia(b.otra, otrasZonas(catalogStore.products, b.zona), compra.precios, valor, duracionDelPedido());

            return enCola(cargarHoras);
        }
        if (campo === 'fila') { b.fila = Number(valor); return enCola(cargarFila); }
        if (campo === 'hora') { b.hora = horaDelMotor(timeStore.offered, valor); return enCola(resolverLinea); }
        if (campo === 'n') { b.n = valor; return b.hora ? enCola(cargarHoras) : null; }
        if (campo === 'cal') { b.cal = valor; return enCola(resolverLinea); }
        // Un complemento de la lista (`#880`): `{ id, n }`; 0 lo quita.
        if (campo === 'extra') { b.extras = conExtra(b.extras, valor.id, valor.n); return enCola(resolverLinea); }
        // Lo elegido en un grupo de elección (`#881`): `{ grupo, valor }`.
        if (campo === 'eleccion') { b.elecciones = { ...(b.elecciones ?? {}), [valor.grupo]: valor.valor }; return enCola(resolverLinea); }
        // Un dato de la reserva de un pack (`#839`): se escribe en el borrador y viaja al continuar; no cambia la oferta.
        if (campo === 'evento') { b.evento = { ...(b.evento ?? {}), [valor.key]: valor.valor }; return null; }
        // La OTRA ZONA (K2 de `otra-zona.md`; su tarjeta completa, K2·b de `#882`): añadirla —con el tiempo parecido al del
        // pedido, para una persona—, cambiar su tiempo, su gente, sus complementos o sus grupos, o quitarla.
        if (campo === 'otra') {
            b.otra = otraNueva(otrasZonas(catalogStore.products, b.zona), compra.precios, b.dia, duracionDelPedido());

            return b.otra ? enCola(cargarOtra) : null;
        }
        if (campo === 'otraFila' && b.otra) { b.otra = { ...b.otra, fila: Number(valor), elecciones: {} }; return enCola(cargarOtra); }
        if (campo === 'otraN' && b.otra) { b.otra = { ...b.otra, n: valor }; caben(); return enCola(recotizar); }
        if (campo === 'otraExtra' && b.otra) { b.otra = { ...b.otra, extras: conExtra(b.otra.extras, valor.id, valor.n) }; return enCola(cotizar); }
        if (campo === 'otraEleccion' && b.otra) {
            b.otra = { ...b.otra, elecciones: { ...(b.otra.elecciones ?? {}), [valor.grupo]: valor.valor } };

            return enCola(cotizar);
        }
        if (campo === 'quitarOtra') {
            b.otra = null;
            Object.assign(compra, { horasOtra: null, fichaOtra: null, cotizacion: null, sueltosOtra: [], conHoraOtra: null, gruposOtra: [] });

            return null;
        }

        return null;
    }

    /**
     * Lo que la línea lleva de la pantalla 0 además del borrador: los calcetines, lo elegido en la lista de complementos
     * (`#880`) y en los grupos de elección (`#881`) y, de una fiesta, la edad.
     */
    function extrasDelPedido() {
        const b = compra.borrador;
        const calcetin = calcetinDe(ficha());
        const extras = (b.extras ?? []).filter((x) => x.product_id !== calcetin?.id);

        // De un pack sin edad, lo que pide al reservar (`#839`): solo sus campos y contestados, como los valida el servidor. Y
        // de unas entradas, la línea de la otra zona (K2 de `otra-zona.md`).
        if (! b.fiesta) {
            return { calcetin, evento: respuestasDe(datosDeReserva(catalogStore.product, b.evento)), elecciones: elecciones(), extras, otras: otraDelPedido() };
        }
        const campo = campoDeEdad(compra.fichas[b.fila]);

        return { calcetin, evento: campo ? { [campo.key]: b.edad } : {}, elecciones: elecciones(), extras };
    }

    const vista = computed(() => {
        const b = compra.borrador;
        const deLaFila = ficha();
        const calcetin = calcetinDe(deLaFila);
        // La lista de complementos (`#880`): con hora, lo que el servidor resolvió para ESA hora; sin ella, `null`. Delante, los
        // grupos de elección que no tienen su pregunta (`#881`: en una fiesta, todos menos el menú).
        const complementos = [
            ...gruposComoFilas(compra.grupos, { elecciones: b.elecciones, desde: b.fiesta ? 1 : 0 }),
            ...complementosDe({
                ficha: deLaFila, excluir: calcetin ? [calcetin.id] : [], extras: b.extras, sinHora: compra.sueltos,
                conHora: b.hora && selectionStore.line ? (selectionStore.addons?.singles ?? []) : null, hora: horaCorta(b.hora), textos,
            }),
        ];
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
            : pantallaCuando({
                ...comun, productos: catalogStore.products, minimo: catalogStore.minQuantity, umbral: timeStore.lowMax, calcetin, ficha: deLaFila,
                // La otra zona, solo en ENTRADAS (K2 de `otra-zona.md`), y el presupuesto de todas las líneas si la hay. Su tarjeta,
                // completa (`#882`): sus complementos —menos los calcetines, que se preguntan una vez para todos— y la estancia
                // del pedido, para decir a qué hora sale cada grupo si no coinciden.
                otra: deEntradas() ? otraDeLaPantalla({
                    borrador: b, productos: catalogStore.products, precios: compra.precios, horasOtra: compra.horasOtra, fichaOtra: compra.fichaOtra,
                    sueltosOtra: compra.sueltosOtra, conHoraOtra: compra.conHoraOtra, gruposOtra: compra.gruposOtra,
                    principal: { duracion: duracionDelPedido(), complementos }, excluir: calcetin ? [calcetin.id] : [], textos, locale: flow.locale,
                }) : null,
                cotizacion: compra.cotizacion,
            });
    });

    return { vista, situar, cambiar, cargarHoras, extrasDelPedido };
}
