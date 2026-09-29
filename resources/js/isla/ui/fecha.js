/**
 * **UNA FECHA COMO SE TECLEA EN LA ISLA** («07/03/2019»: teclado de números y las barras solas, `PMC.fecha` del mockup).
 * La usan la de un hijo (`cuenta/hijos.js`, T5d) y la del titular (`compra/datos.js` y `cuenta/ajustes.js`, `#792`): un
 * solo control para pedir una fecha, y un módulo sin dependencias para que el trozo de la COMPRA no arrastre el de los
 * menores. Sin estado, con su `node --test` (`fecha.test.js`).
 */

/** «07032019» → «07/03/2019». */
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

/** «2019-03-07» → «07/03/2019», como se teclea; sin fecha (o con otra forma), `''`. */
export const fechaDeIso = (iso) => (/^\d{4}-\d{2}-\d{2}$/.test(String(iso ?? '')) ? String(iso).split('-').reverse().join('/') : '');
