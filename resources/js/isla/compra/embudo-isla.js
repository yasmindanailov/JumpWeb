/**
 * **LO QUE EL CLIENTE ELIGE EN LA PANTALLA 0 Y EN LAS CALCULADORAS, AL EMBUDO** (`docs/specs/analitica.md` §4.2; el owner,
 * 03-10).
 *
 * En la isla, el día, la hora y el producto se eligen en la pantalla 0 SIN cambiar de paso —el motor pasa del catálogo al
 * carrito de golpe—, y en las calculadoras de las páginas, sin abrir la compra; así que quien elige día y se va antes del
 * carrito solo cuenta si se mide aquí: `date_chosen`, `time_chosen` y `product_chosen`, lo que el cuadro llama «eligió
 * fecha» y «productos elegidos». En una fiesta, la EDAD elige el pack: quien llama lo pasa como `fila` con el pack de su
 * tramo. El carrito los vuelve a contar por línea (`sidebar/embudo.js`): el cuadro cuenta SESIONES, no veces. Ningún dato
 * personal: el id del producto y la fecha. Módulo plano, con `medir` por parámetro (`CE-6`, `embudo-isla.test.js`; el
 * enganche de cada superficie, en `demanda.test.js`).
 *
 * @param {string} campo  lo que la pantalla avisa que ha cambiado (`usePantallaCero::cambiar`)
 * @param {*} valor  su valor nuevo
 * @param {{fila?: number|string|null}} borrador  el borrador de la compra, con el producto que se mira
 * @param {(nombre: string, datos: object) => void} medir
 * @returns {string|null}  el hecho que emitió
 */
export function medirEleccion(campo, valor, borrador, medir) {
    const product = borrador?.fila ?? null;

    if (campo === 'dia' && valor) {
        medir('date_chosen', { product, date: String(valor) });

        return 'date_chosen';
    }
    if (campo === 'hora' && valor) {
        medir('time_chosen', { product });

        return 'time_chosen';
    }
    if (campo === 'fila' && valor !== null && valor !== undefined && valor !== '') {
        medir('product_chosen', { product: Number(valor) });

        return 'product_chosen';
    }

    return null;
}
