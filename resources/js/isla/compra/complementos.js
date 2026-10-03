/**
 * **LOS COMPLEMENTOS DE LA PANTALLA 0, puros** (M1 de `docs/specs/isla-y-landing-nueva.md` §4.29, `DECISIONES #880`):
 * TODOS los que se venden al reservar el producto —«los que sean» (el owner, 02-10 noche)—, menos los que la pantalla ya
 * pregunta a su manera: el de por cantidad (los calcetines, `oferta.js::calcetinDe`) y los grupos de elección (el menú).
 * Los de la lista de invitados no llegan nunca: la ficha y `POST /catalog/products/{id}/addons` publican solo los de
 * `stage = booking`, y el `stage` es del panel.
 *
 * Cada fila, con la forma que dicen sus DATOS (`CE-4`):
 *  · `fijo` — incluido u obligatorio que no se amplía: se enseña, no se elige;
 *  · `si-no` — con tope 1 o por invitado: la hora extra (`UpgradeRow` del diseño, `ui/FilaMejora.vue`);
 *  · `cantidad` — el resto, con su − / +.
 *
 * ⚠️⚠️ El precio y si se ofrece los dice el SERVIDOR (`PAY-12`): la nota (`note`) de lo resuelto con día y hora o, sin
 * hora todavía, de lo resuelto sin ellos. Si con hora no lo ofrece (no cabe, o ese día no tiene precio), la fila SIGUE,
 * apagada y diciendo por qué: una fila que aparece y desaparece es un salto, y una muda parece una avería (`UpgradeRow`:
 * «si el sistema no lo permite, se dice por qué»). Lo elegido se queda en el borrador por si vuelve a caber, como en la
 * calculadora; a la línea va lo que el servidor resuelva (`selection`).
 * ⚠️ Fuera de `oferta.js` a propósito: aquél lo comparten las calculadoras de la página, y lo de aquí solo lo usa la compra.
 */
import { t as texto, tp as textoCon } from '../../sidebar/i18n.js';

/** Los sueltos de la ficha que pinta la lista: sin grupo de elección y sin los que la pantalla ya pregunta (`excluir`). */
export function deLaFicha(ficha, excluir = []) {
    return (Array.isArray(ficha?.addons) ? ficha.addons : []).filter((a) => a && ! a.choice_group && ! excluir.includes(a.id));
}

/** La forma de una fila, de sus datos: `fijo`, `si-no` o `cantidad`. */
export function formaDe(a) {
    if ((a.included || a.mandatory) && ! a.allow_extra) return 'fijo';
    if (a.max_quantity === 1 || a.per_guest) return 'si-no';

    return 'cantidad';
}

/** Lo elegido de un complemento en el borrador (`extras`), o 0. */
export const elegido = (extras, id) => (Array.isArray(extras) ? extras : []).find((x) => x.product_id === id)?.quantity ?? 0;

/** El borrador con la cantidad nueva de un complemento; con 0, fuera. El orden de los demás no cambia. */
export function conExtra(extras, id, n) {
    const lista = Array.isArray(extras) ? extras : [];
    const resto = lista.filter((x) => x.product_id !== id);

    if (! (n > 0)) return resto;

    return lista.some((x) => x.product_id === id)
        ? lista.map((x) => (x.product_id === id ? { product_id: id, quantity: n } : x))
        : [...lista, { product_id: id, quantity: n }];
}

/** Lo elegido que el producto NUEVO también vende (al cambiar de fila o de pack: lo de otro no vale, `#836`). */
export function quedanEn(extras, ficha) {
    const ids = new Set((Array.isArray(ficha?.addons) ? ficha.addons : []).map((a) => a.id));

    return (Array.isArray(extras) ? extras : []).filter((x) => ids.has(x.product_id));
}

/** Sus ventajas, una frase cada una: «La fiesta dura 3 horas en vez de 2.» */
const frases = (lista) => (Array.isArray(lista) ? lista : []).map((f) => String(f).trim()).filter(Boolean)
    .map((f) => (/[.!?…]$/.test(f) ? f : `${f}.`)).join(' ');

/**
 * Las filas de la lista.
 *
 * @param {object} e  `ficha` · `excluir` (ids que la pantalla ya pregunta) · `extras` (lo elegido, `[{product_id,
 *   quantity}]`) · `sinHora` (los sueltos resueltos sin día ni hora) · `conHora` (los resueltos con día y hora, o `null`
 *   mientras no hay hora) · `hora` (la elegida, `HH:MM`) · `textos`
 * @returns {Array<{id: number, forma: string, titulo: string, descripcion: string, precio: string, n: number,
 *   marcada: boolean, max: number, disponible: boolean, porQue: string, minutos: number|null, porUnidad: boolean}>}
 *   (`minutos` y `porUnidad`: lo que alarga la estancia, de la ficha —`stay_minutes`, `stay_per_unit`, `#882`—)
 */
export function complementosDe(e) {
    const t = (clave) => texto(e.textos, `compra.cuando.${clave}`);
    const tp = (clave, p) => textoCon(e.textos, `compra.cuando.${clave}`, p);
    const busca = (lista, id) => (Array.isArray(lista) ? lista : []).find((s) => s?.product_id === id) ?? null;
    const conHora = Array.isArray(e.conHora) ? e.conHora : null;

    return deLaFicha(e.ficha, e.excluir).map((a) => {
        const forma = formaDe(a);
        const resuelto = conHora ? busca(conHora, a.id) : null;
        const nota = (resuelto ?? busca(e.sinHora, a.id))?.note ?? '';
        // Uno que depende de otro (`requires_addon_id`) espera a que se elija aquél; el servidor lo confirma con hora.
        const requisito = a.requires_addon_id ? elegido(e.extras, a.requires_addon_id) > 0 : true;
        // Sin hora no se sabe si cabe lo que se añade de sí o no (la hora extra): espera a la hora, como en la calculadora.
        const disponible = forma !== 'fijo' && (conHora
            ? resuelto !== null && resuelto.available !== false
            : forma === 'cantidad' && requisito);
        const requiere = (nombre) => (nombre ? tp('extra_requiere', { nombre }) : tp('extra_no', { hora: e.hora ?? '' }));
        const porQue = forma === 'fijo' || disponible ? ''
            : ! conHora ? (forma === 'si-no' ? t('extra_sin_hora') : requiere((e.ficha?.addons ?? []).find((x) => x?.id === a.requires_addon_id)?.name))
                : resuelto === null ? tp('extra_no', { hora: e.hora ?? '' })
                    : requiere(resuelto.requires_name);
        const n = disponible ? elegido(e.extras, a.id) : 0;

        return {
            id: a.id,
            forma,
            titulo: String(a.name ?? ''),
            descripcion: frases(a.features),
            precio: nota,
            n,
            marcada: n > 0,
            max: a.max_quantity ?? 40,
            disponible,
            porQue,
            minutos: Number.isInteger(a.stay_minutes) && a.stay_minutes > 0 ? a.stay_minutes : null,
            porUnidad: a.stay_per_unit === true,
        };
    });
}

/**
 * **Cuánto DURA la estancia de una línea** (`#882`): la de su fila más lo que la alarga lo elegido de la lista —la hora
 * extra—, con los minutos de la ficha (el dominio, no el nombre) y su regla de bloques (cada unidad, o una vez). Solo
 * cuenta lo que la pantalla tiene elegido Y disponible a esa hora (`n` de cada fila). `null`: sin fin (una fila ilimitada).
 *
 * @param {number|null|undefined} duracion  los minutos de la fila (`duration_min`; sin él, ilimitada)
 * @param {Array<{n: number, minutos: number|null, porUnidad: boolean}>} filas  las de `complementosDe`
 */
export function estancia(duracion, filas = []) {
    if (! Number.isInteger(duracion) || duracion <= 0) return null;

    return (Array.isArray(filas) ? filas : []).reduce(
        (total, f) => total + (f?.n > 0 && Number.isInteger(f.minutos) ? (f.porUnidad ? f.minutos * f.n : f.minutos) : 0),
        duracion,
    );
}

/**
 * **Los GRUPOS DE ELECCIÓN como preguntas de la lista** («esto o aquello»; `#881`, con la K2 de `otra-zona.md`): cada uno
 * con sus opciones —su nombre, sus ventajas y la nota de su precio, del servidor— y la elegida: la del borrador o, sin
 * elegir, la que el servidor deja (`selected`). En una fiesta, desde el SEGUNDO (`desde`: el primero es el menú, que tiene
 * su pregunta). Un grupo sin opciones no se pregunta.
 */
export function gruposComoFilas(grupos, { elecciones = {}, desde = 0 } = {}) {
    return (Array.isArray(grupos) ? grupos : []).slice(desde).filter((g) => g?.key && (g.options ?? []).length > 0).map((g) => {
        const elegida = elecciones?.[g.key] ?? g.options.find((o) => o.selected)?.product_id ?? null;

        return {
            id: `grupo-${g.key}`,
            forma: 'grupo',
            grupo: g.key,
            titulo: String(g.label ?? ''),
            items: g.options.map((o) => ({
                value: String(o.product_id), title: o.product_name, description: frases(o.features), price: o.note ?? '', disabled: o.available === false,
            })),
            valor: elegida === null ? null : String(elegida),
        };
    });
}

/**
 * **Lo elegido de CADA grupo de elección** («esto o aquello»; `#881`, con la L2): en una fiesta, el MENÚ en el primero
 * (`menu`, como siempre); en los demás —y en TODOS, en una entrada— lo que el borrador guarda por grupo (`elecciones`,
 * `{ [clave del grupo]: producto }`). Lo que no se eligió no viaja: el servidor deja el de por defecto (`selected_by_default`).
 *
 * @returns {Array<{group: string, product_id: number}>}
 */
export function eleccionesDelBorrador(grupos, { menu = null, elecciones = {}, conMenu = false } = {}) {
    return (Array.isArray(grupos) ? grupos : []).flatMap((g, i) => {
        const elegido = conMenu && i === 0 ? menu : elecciones?.[g?.key];

        return g?.key && elegido != null && elegido !== '' ? [{ group: g.key, product_id: Number(elegido) }] : [];
    });
}

/**
 * Lo que el servidor resuelve de un producto SIN día ni hora (con la gente que va): sus grupos de elección —el menú de un
 * pack— y sus sueltos, con su nota. El endpoint los da sin día ni hora, pero rechaza `null` en ellos, y el store del motor
 * siempre los manda (`selection.js::loadAddons`): por eso esta llamada aparte. Sin línea: el dinero llega con la hora.
 *
 * @returns {Promise<{grupos: Array<object>, sueltos: Array<object>}>}
 */
export async function cargarSinHora({ api, productId, quantity, choices = [] }) {
    const r = await api.post(`/catalog/products/${productId}/addons`, { quantity, addons: [], choices });

    return r.ok ? { grupos: r.data?.groups ?? [], sueltos: r.data?.singles ?? [] } : { grupos: [], sueltos: [] };
}
