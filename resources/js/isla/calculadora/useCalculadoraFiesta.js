/**
 * **LA CALCULADORA DE LA FIESTA SOBRE EL MOTOR** (T6b·3 de `docs/specs/isla-y-landing-nueva.md` §4.18, `#836`): el
 * borrador —la edad, los niños, el día, la hora, el menú y la hora extra—, lo que se le pide a la OFERTA del motor al
 * cambiarlo y su vista (`vistaFiesta.js`). Como la de entradas (`useCalculadora.js`): una app aparte de la compra, SIN
 * `usePurchaseFlow`; los días, las horas, los menús y el dinero salen de los MISMOS stores y peticiones que la pantalla 0
 * de la fiesta en la isla. «Reservar y pagar la señal» le pasa la selección ENTERA a la compra (intención `fiesta`).
 *
 * ⚠️ **La cesta, con su TITULAR** (`owner`): las horas se ofrecen descontando lo que la cesta ya retiene (`AFORO-02`), y
 * leerla con otro titular la purgaría. Aquí solo se LEE.
 * ⚠️ **Todo lo que pide va EN COLA** (dos cambios seguidos, la última respuesta manda) y el botón espera (`pendiente`).
 * ⚠️ El borrador nace SIN edad, SIN día y SIN hora extra: una fiesta no se paga con lo que alguien no eligió. Cambiar de
 * pack (la edad) vacía el día que ese pack no vende y ajusta los niños a su mínimo y su máximo.
 */
import { computed, reactive } from 'vue';
import { api } from '../../sidebar/api.js';
import { todayIso } from '../../sidebar/cart.js';
import { useCartStore } from '../../sidebar/stores/cart.js';
import { useSelectionStore } from '../../sidebar/stores/selection.js';
import { useTimeStore } from '../../sidebar/stores/time.js';
import { cargarDiasDeFilas, horaDelMotor, horaQueCabe } from '../compra/oferta.js';
import { eleccionesDe } from '../compra/fiesta.js';
import { medirEleccion } from '../compra/embudo-isla.js';
import { medir } from '../medir.js';
import { menuDeLaFiesta, menusDeLaFiesta, packDeLaEdad, vistaCalculadoraFiesta } from './vistaFiesta.js';

/** Los menús de la ficha como grupo de elección, mientras el servidor no los resuelve (su `choice_group`). */
const gruposDeFicha = (ficha) => {
    const opciones = (ficha?.addons ?? []).filter((a) => a.choice_group);

    return opciones.length ? [{ key: opciones[0].choice_group, options: opciones.map((a) => ({ product_id: a.id, selected: a.included })) }] : [];
};

export function useCalculadoraFiesta({ pagina, textos, locale, owner = null }) {
    const timeStore = useTimeStore();
    const selectionStore = useSelectionStore();
    const cartStore = useCartStore();
    const hoy = todayIso();
    const packs = pagina.packs ?? [];
    const e = reactive({
        borrador: { edad: null, n: packs[0]?.min_quantity ?? 1, dia: null, hora: null, menu: null, horaExtra: false },
        precios: {}, llegaron: [], grupos: null, pendientes: 0, tocada: false, abriendo: false,
    });

    let cola = Promise.resolve();
    const enCola = (tarea) => {
        e.pendientes += 1;
        cola = cola.then(tarea).catch(() => {}).finally(() => { e.pendientes -= 1; });

        return cola;
    };
    const pack = () => packDeLaEdad(packs, e.borrador.edad);
    const extension = (p) => (p ? pagina.extensiones?.[p.id] ?? null : null);
    const menu = (p) => menuDeLaFiesta(menusDeLaFiesta(e.grupos, p, { tp: () => '', locale, persona: '' }), e.grupos, p, e.borrador.menu);

    /** La línea que resuelve el SERVIDOR (el dinero, con el menú y la hora extra dentro), con pack, día y hora. */
    async function resolver() {
        const b = e.borrador;
        const p = pack();

        if (! p || ! b.dia || ! b.hora) { selectionStore.setLine(null); return; }
        const ext = extension(p);

        timeStore.select(b.hora);
        selectionStore.setQuantity(b.n);
        selectionStore.setQuantities(ext && b.horaExtra ? [{ product_id: ext.id, quantity: 1 }] : []);
        selectionStore.setChoices(eleccionesDe(e.grupos ?? gruposDeFicha(p), menu(p)));
        await selectionStore.loadAddons({ api, productId: p.id, date: b.dia, time: b.hora });
        if (selectionStore.addons?.groups?.length) e.grupos = selectionStore.addons.groups;
    }

    /** Las horas del día para el pack (o el primero, sin edad), con la cesta (`AFORO-02`); la que ya no cabe se vacía. */
    async function cargarHoras() {
        const b = e.borrador;
        const p = pack() ?? packs[0];

        if (b.dia && p) {
            await timeStore.loadOffer({ api, productId: p.id, date: b.dia, cartLines: cartStore.lines });
            if (b.hora && ! horaQueCabe(timeStore.offered, b.hora, b.n)) b.hora = null;
        } else {
            timeStore.setOffer([]);
        }
        await resolver();
    }

    /** Lo que se PIDE, una vez: la cesta de su titular (solo leída) y los días de TODOS los packs. */
    let arrancada = null;
    function arrancar() {
        if (arrancada) return arrancada;
        cartStore.setOwner(owner);
        cartStore.setLines(cartStore.restore(hoy).lines);
        arrancada = enCola(async () => {
            const oferta = await cargarDiasDeFilas({ api, ids: packs.map((p) => p.id) });

            Object.assign(e, { precios: oferta.dias, llegaron: oferta.llegaron });
            await cargarHoras();
        });

        return arrancada;
    }

    /** La EDAD: puede cambiar de pack, y con él sus niños, sus días, sus menús y su hora extra. */
    function cambiarEdad(valor) {
        const b = e.borrador;
        const antes = pack();

        b.edad = Number(valor);
        const ahora = pack();

        if (ahora && ahora.id !== antes?.id) {
            b.n = Math.min(Math.max(b.n, ahora.min_quantity ?? 1), ahora.max_quantity ?? Infinity);
            if (b.dia && ! (e.precios[ahora.id] ?? []).some((d) => d.date === b.dia)) Object.assign(b, { dia: null, hora: null });
            if (antes) e.grupos = null;
        }

        return enCola(cargarHoras);
    }

    /**
     * La demanda sin hueco (`#758`, `demanda.js`): el pack cuyos días enseña (el de la edad o, sin ella, el primero), cuando
     * el cliente TOCA la calculadora —no al arrancar ella sola al acercarse la pieza—. Con `import()` y el pack tomado
     * ANTES, como la de entradas (`useCalculadora.js`: estático, arrastraba el calendario del motor a la página).
     */
    const mirada = () => {
        const p = pack() ?? packs[0];
        const fila = { id: p?.id, dias: e.precios[p?.id], llegaron: e.llegaron };

        import('../compra/demanda.js').then((m) => m.informarDemanda(fila)).catch(() => {});
    };

    /** Lo que la vista avisa que ha cambiado. */
    function cambiar(campo, valor) {
        const b = e.borrador;

        // El `then` corre cuando `cambiar` ya ha escrito el borrador: informa el pack de DESPUÉS del cambio (la edad).
        arrancar().then(mirada);
        e.tocada = true;
        // Al embudo como en la pantalla 0 (`embudo-isla.js`): el día y la hora, con el pack cuyos días enseña; y la EDAD,
        // que elige el pack: cuenta como elegirlo.
        const aqui = (nombre, datos) => medir(nombre, datos);

        if (campo === 'edad') medirEleccion('fila', packDeLaEdad(packs, Number(valor))?.id, {}, aqui);
        else medirEleccion(campo, valor, { fila: (pack() ?? packs[0])?.id ?? null }, aqui);
        if (campo === 'edad') return cambiarEdad(valor);
        if (campo === 'n') { b.n = valor; return enCola(cargarHoras); }
        if (campo === 'dia') { b.dia = valor; return enCola(cargarHoras); }
        if (campo === 'hora') { b.hora = horaDelMotor(timeStore.offered, valor); return enCola(resolver); }
        if (campo === 'menu') { b.menu = valor; return enCola(resolver); }
        if (campo === 'horaExtra') { b.horaExtra = Boolean(valor); return enCola(resolver); }

        return null;
    }

    /**
     * «Reservar y pagar la señal»: la selección ENTERA a la compra de la isla (`#836`), que sigue sola a «Pagar». La hora
     * extra viaja como la RESOLVIÓ el servidor (`selection`): si a esa hora no cabía, no va. El botón carga hasta que la
     * compra aparece (`#783`), se cierra o pasan 8s.
     */
    function reservar() {
        const b = e.borrador;
        const p = pack();
        const ext = extension(p);
        const soltar = () => {
            e.abriendo = false;
            window.removeEventListener('isla:relevada', soltar);
            document.removeEventListener('jw:cajon:close', soltar);
        };

        if (! p) return;
        e.abriendo = true;
        window.addEventListener('isla:relevada', soltar);
        document.addEventListener('jw:cajon:close', soltar);
        window.setTimeout(soltar, 8000);
        window.JumpWeb?.cajon?.openWith?.({
            type: 'fiesta', id: p.id, edad: b.edad, date: b.dia, time: b.hora, quantity: b.n, menu: menu(p),
            extras: (selectionStore.resolved ?? []).filter((s) => ext && s.product_id === ext.id && s.quantity > 0), continuar: true,
        });
    }

    const vista = computed(() => vistaCalculadoraFiesta({
        pagina, textos, locale, hoy, borrador: e.borrador, precios: e.precios, horas: timeStore.offered, grupos: e.grupos,
        sueltos: selectionStore.addons?.singles ?? [], linea: selectionStore.line, maximo: e.borrador.hora ? timeStore.maxQuantity : null,
        pendiente: e.pendientes > 0, tocada: e.tocada, abriendo: e.abriendo,
    }));

    /** Un día con hueco de la página («Próximos fines de semana con hueco»): elegido aquí; quedan la edad y la hora. */
    const elegirDia = (dia) => cambiar('dia', dia);

    return { vista, arrancar, cambiar, reservar, elegirDia };
}
