/**
 * LA LÓGICA PURA de la lista de invitados del sistema nuevo (`specs/fiesta-sistema-nuevo.md` §4.5): sin DOM, para
 * probarla con `node --test` (`logica.test.js`). Es el port de lo que `paginas/lista-invitados/estado.jsx` y
 * `zonas-1-2.jsx` del diseño hacen en el navegador —limpiar una lista pegada, normalizar un nombre, el
 * estado de una ficha, la vista previa de la invitación (F2)— y de `choice()`, el plural de Laravel resuelto en el
 * navegador.
 */

/** La clave de un nombre: sin tildes, en minúsculas y con los espacios colapsados (el `norm()` del diseño). */
export function clave(s) {
    return String(s ?? '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().replace(/\s+/g, ' ').trim();
}

/** Un nombre tecleado, con los espacios normalizados y en mayúscula inicial si venía todo en minúsculas. */
export function capitalizar(s) {
    const t = String(s ?? '').trim().replace(/\s+/g, ' ');

    return t === t.toLowerCase() ? t.replace(/(^|\s)\S/g, (m) => m.toUpperCase()) : t;
}

/**
 * «Pegar una lista»: la del grupo de WhatsApp sirve tal cual. Se parte por líneas, comas y puntos y coma; se quitan
 * los teléfonos, las viñetas y las numeraciones; y los repetidos (contra la lista y entre sí) no entran.
 *
 * @param {string} texto
 * @param {string[]} existentes  los nombres que ya están en la lista
 * @returns {{nombres: string[], repetidos: number}}
 */
export function limpiar(texto, existentes = []) {
    const vistos = new Set(existentes.map(clave));
    const nombres = [];
    let repetidos = 0;
    String(texto ?? '').split(/\n|,|;/).forEach((l) => {
        let s = l.replace(/\+?\d[\d\s().-]{6,}\d/g, ' ').replace(/^[\s\-–—•*·~>\d.)\]]+/, '').replace(/\s+/g, ' ').trim();
        if (s.length < 2 || !/[a-záéíóúñü]/i.test(s)) return;
        if (s === s.toLowerCase()) s = s.replace(/(^|\s)\S/g, (m) => m.toUpperCase());
        const k = clave(s);
        if (vistos.has(k)) { repetidos++; return; }
        vistos.add(k);
        nombres.push(s);
    });

    return { nombres, repetidos };
}

/**
 * El ESTADO de una ficha, la misma regla que pinta el servidor: completa si ninguna columna obligatoria está vacía,
 * y «Falta …» nombra la primera obligatoria vacía de una ficha que ya tiene algún dato.
 *
 * @param {{required: boolean, filled: boolean, label: string}[]} campos
 * @returns {{completa: boolean, conDatos: boolean, falta: string|null}}
 */
export function estadoFicha(campos, sinProducto = false) {
    const conDatos = campos.some((c) => c.filled);
    const primeraVacia = campos.find((c) => c.required && !c.filled);

    return {
        completa: !sinProducto && primeraVacia === undefined,
        conDatos,
        falta: conDatos && primeraVacia !== undefined ? primeraVacia.label : null,
    };
}


/**
 * Una cadena con plural en el formato de Laravel, resuelta en el navegador: admite «uno|varios» y los intervalos
 * `{0}`, `{1}`, `[2,*]`, y sustituye `:count` y el resto de marcadores.
 */
export function choice(template, count, replacements = {}) {
    const forms = String(template ?? '').split('|');
    let chosen = null;

    for (const form of forms) {
        const exact = form.match(/^\s*\{(\d+)\}\s*/);
        const range = form.match(/^\s*\[(\d+),\s*(\d+|\*)\]\s*/);
        if (exact && Number(exact[1]) === count) { chosen = form.slice(exact[0].length); break; }
        if (range && count >= Number(range[1]) && (range[2] === '*' || count <= Number(range[2]))) { chosen = form.slice(range[0].length); break; }
    }

    if (chosen === null) {
        const plain = forms.filter((form) => !/^\s*[{[]/.test(form));
        const pool = plain.length > 0 ? plain : forms;
        chosen = pool.length > 1 && count !== 1 ? pool[pool.length - 1] : pool[0];
    }

    const values = { count, ...replacements };

    return Object.keys(values)
        .sort((a, b) => b.length - a.length)
        .reduce((text, key) => text.split(`:${key}`).join(String(values[key])), chosen.trim());
}

/** Solo las cifras de una edad tecleada, dos como mucho (el `setCu("age")` de `zonas-1-2.jsx`). */
export function soloEdad(s) {
    return String(s ?? '').replace(/\D/g, '').slice(0, 2);
}

/**
 * LA VISTA PREVIA de la invitación en «Personalizar» (F2 de `fiesta-sistema-nuevo.md`): qué enseña la tarjeta para lo
 * que se ha tecleado, con las MISMAS reglas que `components/fiesta/invitacion.blade.php` pinta en el servidor —la chapa
 * solo con edad; el titular «cumple N años…» o «te invita…»; la figura si hay palabras o quien invita; la burbuja con la
 * inicial de quien invita (o de quien cumple) si hay palabras; el pie con quien invita en su forma con o sin palabras;
 * «Llamar» si se marcó; la línea del regalo si hay pistas—.
 *
 * @param {{nombre?: string, edad?: string, invita?: string, palabras?: string, pistas?: string, telefono?: boolean}} campos
 * @param {{restoCon?: string, restoSin?: string}} textos  «cumple :age años y te invita a saltar» y «te invita a saltar»
 */
export function vistaInvitacion(campos = {}, textos = {}) {
    const nombre = String(campos.nombre ?? '').trim();
    const edad = soloEdad(campos.edad);
    const invita = String(campos.invita ?? '').trim();
    const palabras = String(campos.palabras ?? '').trim();
    const pistas = String(campos.pistas ?? '').trim();
    const base = invita || nombre;

    return {
        nombre,
        edad,
        conEdad: edad !== '',
        resto: edad !== '' ? String(textos.restoCon ?? '').split(':age').join(edad) : String(textos.restoSin ?? ''),
        figura: palabras !== '' || invita !== '',
        conPalabras: palabras !== '',
        palabras,
        inicial: base === '' ? '' : [...base][0].toUpperCase(),
        pieCon: invita !== '' && palabras !== '',
        pieSin: invita !== '' && palabras === '',
        invita,
        telefono: Boolean(campos.telefono),
        conPistas: pistas !== '',
        pistas,
    };
}

/**
 * LO DE LOS PADRES (F5 de `fiesta-sistema-nuevo.md` §4.11, `cubrir()` de `zonas-3-5.jsx`): la cuenta MÁS BARATA que cubre
 * a `adultos` con las variantes de una familia (cada una «para N», a su precio y con su tope); a igual precio, la de menos
 * unidades. `null` si ni con los topes se llega. Nunca se aplica sola: la página la PROPONE y el anfitrión la pone.
 *
 * @param {{para: number, precio: number, max?: number}[]} variantes
 * @param {number} adultos
 * @returns {{q: number[], coste: number, uds: number}|null}
 */
export function cubrir(variantes, adultos) {
    let best = null;
    const q = variantes.map(() => 0);
    const rec = (i, resto, coste, uds) => {
        if (i === variantes.length) {
            if (resto <= 0 && (!best || coste < best.coste || (coste === best.coste && uds < best.uds))) best = { q: q.slice(), coste, uds };
            return;
        }
        const v = variantes[i];
        const hasta = Math.min(v.max > 0 ? v.max : Infinity, Math.ceil(Math.max(0, resto) / v.para));
        for (let n = 0; n <= hasta; n++) { q[i] = n; rec(i + 1, resto - n * v.para, coste + n * v.precio, uds + n); }
        q[i] = 0;
    };
    if (adultos > 0 && variantes.length > 0 && variantes.every((v) => v.para > 0)) rec(0, adultos, 0, 0);

    return best;
}

/**
 * LA TARTA, VARIAS A LA VEZ (K2 de `fiesta-sistema-nuevo.md` §4.17, `#807`): lo pedido de cada tarta del panel contra los niños
 * de la fiesta, para la línea de debajo de sus tarjetas. `null` si no hay nada que decir: nada pedido, ningún niño, o alguna
 * pedida que no dice raciones («Traemos la nuestra»: su cuenta no se sabe, y afirmar que no llega sería mentir). Si no, cuántas
 * tartas son (`uds`), cuántas raciones hacen y si cubren a los niños (`cubre`).
 *
 * @param {{uds: number, serves: number|null}[]} tartas  lo pedido de cada una y sus raciones (del panel)
 * @param {number} ninos
 * @returns {{cubre: boolean, uds: number, raciones: number}|null}
 */
export function racionesTarta(tartas, ninos) {
    const pedidas = (tartas ?? []).filter((t) => t.uds > 0);
    if (pedidas.length === 0 || !(ninos > 0) || pedidas.some((t) => !(t.serves > 0))) return null;
    const uds = pedidas.reduce((suma, t) => suma + t.uds, 0);
    const raciones = pedidas.reduce((suma, t) => suma + t.uds * t.serves, 0);

    return { cubre: raciones >= ninos, uds, raciones };
}

/**
 * «TUS RESPUESTAS» (F6b de `fiesta-sistema-nuevo.md` §4.12, `InvMias`): la lista de este móvil sin lo caducado y, con
 * `nueva`, con esa respuesta guardada o renovada (una por fiesta e id). Cada entrada: `{fiesta, id, nombre, url, hasta}`,
 * con `hasta` en milisegundos. Lo que no tenga esa forma se tira: el almacenamiento es del navegador, no nuestro.
 * ⚠️ Renovar la deja EN SU SITIO (el orden es el de contestar, como el diseño): al final, los chips cambiaban de orden
 * cada vez que se abría uno (medido en la sonda).
 *
 * @param {unknown} lista  lo que había guardado
 * @param {number} ahora  `Date.now()`
 * @param {{fiesta: string, id: number|string, nombre: string, url: string, hasta: number}|null} nueva
 */
export function misRespuestas(lista, ahora, nueva = null) {
    const vivas = (Array.isArray(lista) ? lista : []).filter((r) => r !== null && typeof r === 'object'
        && typeof r.fiesta === 'string' && r.fiesta !== '' && r.id !== undefined && typeof r.url === 'string'
        && typeof r.nombre === 'string' && Number(r.hasta) > ahora);
    if (!nueva) return vivas;
    const misma = (r) => r.fiesta === nueva.fiesta && String(r.id) === String(nueva.id);

    return vivas.some(misma) ? vivas.map((r) => (misma(r) ? nueva : r)) : [...vivas, nueva];
}

/** Las de UNA fiesta, en el orden en que se contestaron (solo las propias: la lista ya es de este móvil). */
export function deLaFiesta(lista, fiesta) {
    return lista.filter((r) => r.fiesta === fiesta);
}

/** El `expires` de una URL firmada, en milisegundos: la entrada caduca con su enlace. `null` si no lo lleva. */
export function caducaEn(url) {
    try {
        const e = new URL(url).searchParams.get('expires');

        return e !== null && /^\d+$/.test(e) ? Number(e) * 1000 : null;
    } catch {
        return null;
    }
}

/** El espacio duro entre una cifra y su unidad («16 €», «7 años»), como lo escribe el servidor. */
export const NBSP = String.fromCharCode(160);

/** Un importe en céntimos como lo escribe el servidor en español («16 €», «16,95 €»). Solo anticipa: el que vale lo recalcula el servidor. */
export function euros(cents) {
    const n = Math.round(cents) / 100;
    const s = Number.isInteger(n) ? String(n) : n.toFixed(2).replace('.', ',');

    return `${s}${NBSP}€`;
}

/**
 * Un importe COBRADO como lo escribe `Money::format()` («25,00 €», «1.234,50 €»): siempre dos decimales, el punto de los
 * millares y un espacio normal. Es el registro de los complementos de la lista —su precio y su «… en total» salen así del
 * servidor—; con `euros()` (el de escaparate) el total saltaba de «78,00 €» a «78 €» al tocar + (medido, 26-09).
 */
export function importe(cents) {
    const n = Math.round(Number(cents) || 0);
    const signo = n < 0 ? '-' : '';
    const abs = Math.abs(n);
    const enteros = String(Math.floor(abs / 100)).replace(/\B(?=(\d{3})+(?!\d))/g, '.');

    return `${signo}${enteros},${String(abs % 100).padStart(2, '0')} €`;
}

/*
 * LA ISLA DE LAS PÁGINAS DE ENLACE (§4.18, `#814`; `navigation/LinkIsland.jsx` del zip (6)): las reglas, sin DOM. Lo que
 * mide y mueve, en `isla.js`.
 */

/**
 * ¿Se aparta la isla (sale por abajo)? Mientras no ha llegado (tras el confeti), si la página ya enseña lo que diría (regla
 * 3), si no le queda nada que hacer (regla 5) o con una capa abierta encima (el descargo, el vídeo); y al escribir en un
 * campo de la página que NO es suyo (regla 4). El de la respuesta SÍ es suyo: con el teclado abierto, se queda.
 */
export function islaSale({ llegada = true, vista = false, sin = false, capa = false, escribiendo = false, suya = false } = {}) {
    return !llegada || vista || sin || capa || (escribiendo && !suya);
}

/**
 * ¿Se ve ya en pantalla lo que la isla diría? Alguno de esos rectángulos asoma por encima de su franja (los últimos 96 px
 * de la ventana) y no está escondido (alto 0). Es `LinkIsland.useOnScreen` del diseño.
 *
 * @param {{top: number, bottom: number, height: number}[]} rects
 * @param {number} alto  el alto de la ventana
 */
export function asoma(rects, alto) {
    const franja = alto - 96;

    return rects.some((r) => r.height > 0 && r.bottom > 8 && r.top < franja);
}

/**
 * El hueco que la isla deja al final del contenido (`--link-island-space`): su alto (con el borde) y el aire de debajo, así
 * al final del scroll nada queda bajo ella. Se mantiene mientras se aparta —la página no salta—; sin nada que hacer, nada.
 */
export function huecoIsla(alto, sin = false) {
    return sin || alto === null || alto === undefined
        ? '0px'
        : `calc(${alto + 2}px + var(--island-inset) * 2 + env(safe-area-inset-bottom, 0px))`;
}

/**
 * QUÉ CARA LLEVA LA ISLA DE LA LISTA (L2 de §4.18; `paginas/lista-invitados/isla.jsx`): el ÚNICO Guardar, en naranja y solo
 * con algo que guardar, con lo exacto —la tarta que cierra (si se pidió y cambió), cuántos cambios y si el borrador es de
 * este móvil o recuperado, las respuestas por repasar—; guardando, ocupada; sin nada que guardar, «Enviar por WhatsApp» si
 * la invitación aún no salió y se puede contestar; si no, nada (se va). Sin el recordatorio del diseño: la lista no tiene
 * «sin contestar» desde `#805`.
 *
 * @param {{cambios?: number, repasar?: number, recuperado?: boolean, tarta?: string, guardando?: boolean, abiertas?: boolean, compartida?: boolean}} estado
 * @param {{corto: (n: number) => string, respuestas: (n: number) => string, movil: string, recuperado: string}} tx
 * @returns {{cara: ?string, primary?: boolean, ocupada?: boolean, sub?: string}}
 */
export function caraDeLaLista({ cambios = 0, repasar = 0, recuperado = false, tarta = '', guardando = false, abiertas = false, compartida = false } = {}, tx) {
    if (guardando) return { cara: 'guardar', primary: true, ocupada: true, sub: '' };
    if (cambios > 0 || repasar > 0) {
        let sub = tx.respuestas(repasar);
        if (cambios > 0) sub = `${tx.corto(cambios)} · ${repasar > 0 ? tx.respuestas(repasar) : (recuperado ? tx.recuperado : tx.movil)}`;

        return { cara: 'guardar', primary: true, ocupada: false, sub: tarta || sub };
    }
    if (abiertas && ! compartida) return { cara: 'enviar' };

    return { cara: null };
}

/**
 * LO QUE FALTA PARA FIRMAR, por su nombre (L3 de §4.18; `paginas/autorizacion/isla.jsx`, `cuenta`): uno, «Falta tu
 * teléfono»; dos, «Faltan tu teléfono y la casilla»; más, «Faltan 4 datos y la casilla» (los datos se cuentan, la casilla se
 * nombra); nada, «Todo listo». Nunca «tienes cosas pendientes». `falta`: las claves de `tx.campos`, en el orden del formulario.
 *
 * @param {string[]} falta
 * @param {{campos: Record<string, string>, uno: string, dos: string, varios: string, varios_casilla: string, listo: string}} tx
 */
export function faltaParaFirmar(falta, tx) {
    const nombre = (k) => tx.campos?.[k] ?? k;
    if (falta.length === 0) return tx.listo;
    if (falta.length === 1) return tx.uno.replace(':a', nombre(falta[0]));
    if (falta.length === 2) return tx.dos.replace(':a', nombre(falta[0])).replace(':b', nombre(falta[1]));
    const datos = falta.filter((k) => k !== 'casilla').length;

    return (falta.includes('casilla') ? tx.varios_casilla : tx.varios).replace(':n', String(datos));
}

/*
 * EL CORREO MAL ESCRITO (F9): la regla vive en `ui/correo.js`, movida tal cual el 27-09 (plataforma, §4.16 de
 * `isla-y-landing-nueva.md`) porque también la usan los campos de correo de la isla, y desde aquí se llevaban este módulo
 * entero. Se reexporta: quien la importaba de aquí no cambia.
 */
export { sugerirCorreo } from '../ui/correo.js';
