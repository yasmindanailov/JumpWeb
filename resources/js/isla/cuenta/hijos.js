/**
 * **LOS HIJOS EN MI CUENTA, sin estado** (T5d de `docs/specs/isla-y-landing-nueva.md` §4.13, `DECISIONES #777`): lo que
 * pintan «Quién viene contigo», «Añade a tus hijos» (`paginas/mi-cuenta/pantallas.jsx`, `PmcHijos`) y la ficha de un hijo
 * (que el mockup no dibuja: firmar por él y quitarlo, con las piezas del sistema, `#773`·d).
 *
 * ⚠️ **El cliente no decide NADA sobre un menor** (`CE-4`, la doctrina de `sidebar/account/dependents.js`, que se usa sin
 * tocarla): si es menor, su edad y si su exención está al día los publica el servidor. Aquí solo se revisa la FORMA antes de
 * preguntar (un nombre, una fecha que exista y no sea futura, qué eres suyo) y se eligen los textos. La edad junto al campo
 * de la fecha es una PISTA, como en el mockup; si tiene 18 lo dice el servidor (`422 dependent_not_minor`).
 *
 * Por `#773`: sin apellidos (a) y la relación con las CINCO del catálogo (b), sin ninguna marcada de antemano —una marcada
 * por defecto metería un dato que nadie ha mirado en lo que sostiene la firma (`#236`)—.
 */
import { tp, t as texto } from '../../sidebar/i18n.js';
import { RELATIONSHIPS, bornOnLabel, dependentWaiverAction } from '../../sidebar/account/dependents.js';
import { fieldError } from '../../sidebar/account/form-outcome.js';

let siguiente = 1;

/** Una ficha vacía (su `id` es solo la clave del `v-for`). */
export const fichaVacia = () => ({ id: siguiente++, nombre: '', fecha: '', rel: '' });

/** El formulario de «Añade a tus hijos»: una ficha y la casilla del descargo, sin marcar. */
export const formularioHijos = () => ({ lista: [fichaVacia()], descargo: false });

/** «07032019» → «07/03/2019»: la fecha como se dice, con las barras solas (`PMC.fecha` del mockup). */
export function fechaTecleada(valor) {
    const d = String(valor ?? '').replace(/\D/g, '').slice(0, 8);

    if (d.length > 4) return `${d.slice(0, 2)}/${d.slice(2, 4)}/${d.slice(4)}`;

    return d.length > 2 ? `${d.slice(0, 2)}/${d.slice(2)}` : d;
}

/** «07/03/2019» → «2019-03-07», o `null` si no es un día que exista (el 30 de febrero no se desborda a marzo). */
export function isoDeFecha(fecha) {
    const m = /^(\d{2})\/(\d{2})\/(\d{4})$/.exec(String(fecha ?? ''));

    if (m === null) return null;
    const iso = `${m[3]}-${m[2]}-${m[1]}`;
    const dia = new Date(`${iso}T12:00:00Z`);

    return ! Number.isNaN(dia.getTime()) && dia.toISOString().slice(0, 10) === iso ? iso : null;
}

/** La edad que diría esa fecha en `hoy` (`Y-m-d`): una pista junto al campo, o `null`. */
export function edadDe(fecha, hoy) {
    const iso = isoDeFecha(fecha);

    if (iso === null || iso > hoy) return null;
    const [a, m, d] = iso.split('-').map(Number);
    const [ha, hm, hd] = String(hoy).split('-').map(Number);

    return ha - a - (hm < m || (hm === m && hd < d) ? 1 : 0);
}

/** «7 años», «1 año». */
export const edadEscrita = (edad, textos = {}) => tp(textos, edad === 1 ? 'mi_cuenta.quien.anio' : 'mi_cuenta.quien.anios', { n: edad });

/** Las cinco relaciones del catálogo (`Dependent::RELATIONSHIPS`), como tarjetas: «Soy su…». */
export const relacionesDe = (textos = {}) => RELATIONSHIPS.map((valor) => ({ value: valor, title: texto(textos, `mi_cuenta.hijos.relaciones.${valor}`) }));

/**
 * Lo que falta ANTES de preguntar al servidor, o `null` si nada: por ficha, el nombre, una fecha que exista y no sea
 * futura, y qué eres suyo; y la casilla del descargo, si hay texto que firmar (`firma`).
 *
 * @param {{lista: object[], descargo: boolean}} h
 * @param {{firma?: boolean, textos?: object, hoy: string}} deps
 */
export function revisarHijos(h, { firma = false, textos = {}, hoy }) {
    const e = (clave) => texto(textos, `mi_cuenta.hijos.errores.${clave}`);
    const lista = h.lista.map((f) => {
        const iso = isoDeFecha(f.fecha);

        return {
            ...(f.nombre.trim() ? {} : { nombre: e('nombre') }),
            ...(iso === null || iso > hoy ? { fecha: e('fecha') } : {}),
            ...(RELATIONSHIPS.includes(f.rel) ? {} : { rel: e('relacion') }),
        };
    });
    const descargo = firma && ! h.descargo ? e('descargo') : '';

    return lista.some((x) => Object.keys(x).length) || descargo ? { lista, descargo } : null;
}

/** Lo que el servidor dijo de UNA ficha (`fields` y `notice` del motor), en los campos del formulario. */
export function erroresDeFicha(fields = {}, notice = '') {
    const ficha = {
        ...(fieldError(fields, 'name') ? { nombre: fieldError(fields, 'name') } : {}),
        ...(fieldError(fields, 'born_on') ? { fecha: fieldError(fields, 'born_on') } : {}),
        ...(fieldError(fields, 'relationship') ? { rel: fieldError(fields, 'relationship') } : {}),
    };
    const descargo = fieldError(fields, 'accept_waiver') || fieldError(fields, 'waiver_document_id') || '';

    return { ficha, descargo, aviso: Object.keys(ficha).length || descargo ? '' : notice };
}

/** La cabeza de cada ficha cuando hay más de una: su nombre, o «Hijo 2». Y su edad, como pista. */
export function fichasDe(h, { textos = {}, hoy } = {}) {
    return h.lista.map((f, i) => {
        const edad = edadDe(f.fecha, hoy);

        return { ...f, titulo: f.nombre.trim() || tp(textos, 'mi_cuenta.hijos.hijo_n', { n: i + 1 }), pista: edad === null ? '' : edadEscrita(edad, textos) };
    });
}

/** ¿Su exención está al día? Solo tiene sentido si la instalación firma dentro (`interno`). */
const firmado = (d) => d?.waiver?.mode === 'interno' && d.waiver.signed === true && d.waiver.outdated !== true;

/**
 * «Quién viene contigo»: cada hijo con su inicial, su nombre, su edad (la del SERVIDOR) y, si firma dentro, «firmado» o
 * que falta su firma (firmar, o antes verificar el correo: `dependentWaiverAction`).
 */
export function quienDe(lista, { textos = {}, emailVerified } = {}) {
    return (lista ?? []).map((d) => ({
        id: d.id,
        nombre: String(d.name ?? ''),
        inicial: String(d.name ?? '?').charAt(0).toUpperCase(),
        edad: typeof d.age === 'number' ? edadEscrita(d.age, textos) : '',
        firmado: firmado(d),
        pendiente: dependentWaiverAction(d, emailVerified) !== null,
        aria: [d.name, typeof d.age === 'number' ? edadEscrita(d.age, textos) : '',
            firmado(d) ? texto(textos, 'mi_cuenta.quien.firmado') : (dependentWaiverAction(d, emailVerified) ? texto(textos, 'mi_cuenta.quien.falta_firma') : '')]
            .filter(Boolean).join(', '),
    }));
}

/**
 * La ficha de un hijo: su nombre completo, su edad y su fecha, qué eres suyo y su exención —firmada (con su fecha, su
 * versión y el PDF), de una versión anterior, sin firmar o pendiente de verificar el correo—, y si es ya mayor de edad.
 */
export function hijoDe(d, { textos = {}, emailVerified } = {}) {
    if (! d) return null;
    const accion = dependentWaiverAction(d, emailVerified);
    const w = d.waiver ?? {};
    const nombre = String(d.name ?? '');
    let firma = null;

    if (w.mode === 'interno') {
        firma = firmado(d)
            ? { estado: 'firmada', texto: tp(textos, 'mi_cuenta.hijo.firmada', { fecha: w.accepted_label ?? '', version: w.version ?? '' }), pdf: w.pdf_url ?? '' }
            : { estado: w.signed ? 'anterior' : 'sin', texto: tp(textos, w.signed ? 'mi_cuenta.hijo.anterior' : 'mi_cuenta.hijo.sin_firma', { nombre }), pdf: '' };
    }

    return {
        id: d.id,
        nombre,
        completo: String(d.full_name || nombre),
        datos: [typeof d.age === 'number' ? edadEscrita(d.age, textos) : '', tp(textos, 'mi_cuenta.hijo.nacido', { fecha: bornOnLabel(d.born_on) })].filter(Boolean).join(' · '),
        // La relación, dentro de una frase («eres su madre»): en minúscula; en su tarjeta va con mayúscula.
        relacion: d.relationship ? tp(textos, 'mi_cuenta.hijo.relacion', { relacion: texto(textos, `mi_cuenta.hijos.relaciones.${d.relationship}`).toLocaleLowerCase() }) : '',
        adulto: d.is_minor === false ? tp(textos, 'mi_cuenta.hijo.adulto', { nombre }) : '',
        firma,
        firmar: accion === 'sign',
        verificar: accion === 'verify' ? tp(textos, 'mi_cuenta.hijo.verificar', { nombre }) : '',
    };
}
