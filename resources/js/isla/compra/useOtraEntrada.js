/**
 * **«AÑADIR OTRA ENTRADA», sobre el motor** (K3 de `docs/specs/otra-zona.md` §4.3; lo puro, en `otra-entrada.js`): desde
 * «Pagar», la pantalla 0 en modo «otra» con el día y la hora del pedido fijos (D1-A). Lo de la línea nueva —su ficha, lo que
 * resuelve sin hora— se guarda POR FILA en los mismos mapas que las tarjetas de la pantalla 0 (`compra.fichasOtras`…); lo que
 * resuelve a ESA hora, en su sitio (`compra.nuevaOferta`): es de esta pantalla, con su gente y lo elegido en ella.
 * «Continuar» rehace el pedido con ella (`usePagoCompra::rehacer`: validado entero, todo o nada) y vuelve a «Pagar»; si no
 * cabe, el aviso lo dice con el nombre de su zona y la pantalla se queda (proponer las horas en que caben todas es la K4).
 *
 * ⚠️ Todo lo que pide va EN COLA (`enCola`, del orquestador), como la pantalla 0: dos cambios seguidos no pintan lo viejo.
 */
import { computed } from 'vue';
import { api } from '../../sidebar/api.js';
import { t as texto } from '../../sidebar/i18n.js';
import { cargarDiasDeFilas } from './oferta.js';
import { cargarSinHora, conExtra, eleccionesDelBorrador, quedanEn } from './complementos.js';
import { zonasConFilas } from './otra-zona.js';
import { conNuevaLinea } from './linea.js';
import { nuevaEnZona, nuevaEntrada, pantallaOtraEntrada } from './otra-entrada.js';

export function useOtraEntrada({ flow, compra, enCola, textos, pago, alLlenarse }) {
    const { catalogStore } = flow;

    /** Las filas de entradas cuyos días aún no han llegado: sin ellos, ninguno de sus tiempos «se vende». */
    const sinDias = () => zonasConFilas(catalogStore.products).flatMap((z) => z.filas.map((f) => f.id)).filter((id) => ! Array.isArray(compra.precios[id]));

    /** Lo que decide la línea de partida: el catálogo, los días, el del pedido, la duración de su fila y lo que ya lleva. */
    const base = () => ({
        productos: catalogStore.products, precios: compra.precios, dia: compra.pedido?.dia, zona: compra.borrador.zona,
        duracion: catalogStore.products.find((p) => p.id === compra.pedido?.fila)?.duration_min ?? null,
        enLaReserva: [compra.pedido?.fila, ...(compra.pedido?.otras ?? []).map((o) => o.fila)].filter(Number.isInteger),
    });

    /** Lo elegido de cada grupo de la nueva, como lo pide el servidor (`#881`). */
    const elecciones = () => eleccionesDelBorrador(compra.nuevaOferta?.groups ?? compra.gruposOtras[compra.nueva?.fila], { elecciones: compra.nueva?.elecciones ?? {} });

    /**
     * Los días de TODAS las filas de entradas que aún no están (tras la vuelta de Google la compra pasa a «Pagar» sin la
     * pantalla 0, y sin ellos ningún tiempo tendría precio), su ficha y lo que resuelve sin hora si faltan, y lo que resuelve
     * a ESA hora con su gente y lo elegido. Lo elegido que su fila no vende, fuera.
     */
    async function cargar() {
        const nueva = compra.nueva;
        const pedido = compra.pedido;

        if (! nueva || ! pedido) return;
        const fila = nueva.fila;
        const faltan = sinDias();
        const [dias, ficha, sinHora, aEsaHora] = await Promise.all([
            faltan.length ? cargarDiasDeFilas({ api, ids: faltan }) : null,
            compra.fichasOtras[fila]?.id === fila ? null : api.get(`/catalog/products/${fila}`),
            fila in compra.sueltosOtras ? null : cargarSinHora({ api, productId: fila, quantity: nueva.n }),
            api.post(`/catalog/products/${fila}/addons`, { quantity: nueva.n, date: pedido.dia, time: pedido.hora, addons: nueva.extras ?? [], choices: elecciones() }),
        ]);

        if (dias) compra.precios = { ...compra.precios, ...dias.dias };
        // Si mientras tanto cambió de fila (o se cerró), lo que llegó ya no es suyo: lo pinta la petición de detrás.
        if (compra.nueva?.fila !== fila) return;
        if (ficha) compra.fichasOtras = { ...compra.fichasOtras, [fila]: ficha.ok ? ficha.data : null };
        if (sinHora) Object.assign(compra, { sueltosOtras: { ...compra.sueltosOtras, [fila]: sinHora.sueltos }, gruposOtras: { ...compra.gruposOtras, [fila]: sinHora.grupos } });
        // Sin respuesta a esa hora, lo de antes se queda: un fallo de red no dice que no cabe.
        if (aEsaHora?.ok) compra.nuevaOferta = { singles: aEsaHora.data?.singles ?? [], groups: aEsaHora.data?.groups ?? [] };
        compra.nueva = { ...compra.nueva, extras: quedanEn(compra.nueva.extras, compra.fichasOtras[fila]) };
    }

    /**
     * «Añadir otra entrada» en «Pagar»: la pantalla 0 en modo «otra», con la línea de partida.
     *
     * ⚠️ PRIMERO los días de las filas que falten: tras la vuelta de Google (o sin pasar por la pantalla 0) la compra llega a
     * «Pagar» sin ellos, ninguna fila «se vende» y el botón no hacía NADA (el owner, 03-10, en staging). Y si de verdad ese
     * día no queda nada que añadir, se dice en «Pagar»: un botón mudo parece roto.
     */
    async function abrir() {
        if (! compra.pedido) return;
        const faltan = sinDias();

        if (faltan.length) {
            const llegados = await enCola(() => cargarDiasDeFilas({ api, ids: faltan }));

            if (llegados) compra.precios = { ...compra.precios, ...llegados.dias };
        }
        const nueva = nuevaEntrada(base());

        if (! nueva) {
            compra.aviso = texto(textos, 'compra.pagar.sin_otra');

            return;
        }
        Object.assign(compra, { modo: 'otra', nueva, nuevaOferta: null, aviso: '', paso: 'cuando' });
        enCola(cargar);
    }

    /** Lo que la pantalla avisa que ha cambiado: la zona, el tiempo, la gente, un complemento o un grupo de la nueva. */
    function cambiar(campo, valor) {
        const nueva = compra.nueva;

        if (! nueva) return null;
        compra.aviso = '';
        if (campo === 'zona') compra.nueva = nuevaEnZona(nueva, { ...base(), zona: valor });
        else if (campo === 'fila') compra.nueva = { ...nueva, fila: Number(valor), extras: [], elecciones: {} };
        else if (campo === 'n') compra.nueva = { ...nueva, n: valor };
        else if (campo === 'extra') compra.nueva = { ...nueva, extras: conExtra(nueva.extras, valor.id, valor.n) };
        else if (campo === 'eleccion') compra.nueva = { ...nueva, elecciones: { ...(nueva.elecciones ?? {}), [valor.grupo]: valor.valor } };
        else return null;
        if (compra.nueva.fila !== nueva.fila) compra.nuevaOferta = null;

        return enCola(cargar);
    }

    /** Vuelve a «Pagar» sin la nueva: nada ha cambiado en la cesta. */
    function volver() {
        Object.assign(compra, { modo: null, nueva: null, nuevaOferta: null, aviso: '', paso: 'pagar' });
    }

    /**
     * «Continuar»: el pedido con la nueva (o con más gente en su línea, si su tiempo ya estaba), validado y presupuestado
     * entero (`rehacer`); si el servidor dice que sí, a «Pagar». Si no cabe a ESA hora, las cercanas en que caben todos, la
     * nueva incluida (D1-A, K4: elegir una cambia la hora de toda la reserva; su flecha vuelve aquí); si no, aquí, con su aviso.
     */
    async function continuar() {
        const nueva = compra.nueva;

        if (! nueva || ! compra.pedido || compra.ocupado) return;
        compra.ocupado = 'otra';
        try {
            const ficha = compra.fichasOtras[nueva.fila];
            const linea = { fila: nueva.fila, n: nueva.n, extras: nueva.extras ?? [], elecciones: elecciones(), guardian: ficha?.guardian_authorization ?? 'none' };
            const cambio = conNuevaLinea(compra.pedido, linea);
            const r = await enCola(() => pago.rehacer(cambio));
            const zona = catalogStore.products.find((p) => p.id === r.fila)?.zone?.name ?? '';

            if (r.ok) Object.assign(compra, { modo: null, nueva: null, nuevaOferta: null, paso: 'pagar' });
            else if (r.horaLlena) await alLlenarse(r.aviso, { zona: r.fila === compra.pedido.fila ? '' : zona, pendiente: cambio });
        } finally {
            compra.ocupado = null;
        }
    }

    const vista = computed(() => pantallaOtraEntrada({
        nueva: compra.nueva, pedido: compra.pedido, productos: catalogStore.products, precios: compra.precios,
        fichas: compra.fichasOtras, sueltos: compra.sueltosOtras, grupos: compra.gruposOtras, oferta: compra.nuevaOferta,
        excluir: compra.pedido?.calcetin ? [compra.pedido.calcetin.id] : [], textos, locale: flow.locale,
    }));

    return { vista, abrir, cambiar, volver, continuar };
}
