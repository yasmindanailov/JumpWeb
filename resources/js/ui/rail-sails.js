/**
 * LAS VELAS DEL CARRIL DE COMPLEMENTOS · `DECISIONES #498`
 *
 * ❗❗ **Este módulo NO decide diseño: publica un HECHO.** Marca el envoltorio con `data-rail-scroll`
 * cuando su carril tiene contenido que no cabe, y el CSS decide qué hacer con eso. Es la doctrina de
 * `#195` —*«el JavaScript publica UNA custom property y no decide diseño»*—: si los números vivieran
 * aquí serían la única parte del tema que un cliente no puede tocar.
 *
 * ▶ **Por qué hace falta, habiendo velas en CSS puro.** Las dos velas se apagan y se encienden solas
 * con `animation-timeline: scroll()`, sin una línea de JS. Lo que el CSS **no puede** saber es si el
 * carril tiene scroll: sin desbordamiento la timeline no tiene rango, la animación se queda en su
 * fotograma inicial y la vela derecha aparece **sin nada detrás**. Medido: pasa de verdad —con dos
 * complementos, el carril de tarifas cabe entero en escritorio (desborde 0) y desborda 330 px en
 * móvil—, así que **no se puede decidir en el servidor**: depende del ancho, no del número de fichas.
 *
 * ⚠️ Y falla hacia lo seguro: sin JavaScript no hay atributo, y el CSS trata su ausencia como «hay
 * más» — que es el mismo defecto que ya eligió la vela del pie (`#252`). Una lista que parece
 * terminada sin estarlo esconde contenido; una vela de más solo es un adorno.
 */
const SELECTOR = '.addons-rail__wrap';

/** ¿El carril de este envoltorio tiene algo que no cabe? */
function desborda(wrap) {
    const track = wrap.querySelector('.addons-rail__track');

    // ⚠️ El margen de 4 px no es superstición: `scrollWidth` y `clientWidth` se redondean distinto
    // según el zoom del navegador, y sin él un carril que cabe justo parpadea entre los dos estados.
    return !!track && track.scrollWidth - track.clientWidth > 4;
}

function marcar(wrap) {
    wrap.toggleAttribute('data-rail-scroll', desborda(wrap));
}

export function initRailSails(raiz = document) {
    const wraps = [...raiz.querySelectorAll(SELECTOR)];

    if (wraps.length === 0) return;

    wraps.forEach(marcar);

    // ⚠️⚠️ **`ResizeObserver` y no un `resize` de ventana**: el carril cambia de anchura cuando lo
    // hace su columna, y la columna cambia por cosas que no mueven la ventana —abrir el panel de
    // otra zona en tarifas, por ejemplo—. Es la misma lección que `#256`, donde el motor del
    // minijuego cacheaba el ancho del lienzo y se estiraba un 62 % a 1920.
    if (!('ResizeObserver' in window)) return;

    const ro = new ResizeObserver((entradas) => {
        for (const e of entradas) {
            const wrap = e.target.closest(SELECTOR);

            if (wrap) marcar(wrap);
        }
    });

    for (const wrap of wraps) {
        const track = wrap.querySelector('.addons-rail__track');

        if (track) ro.observe(track);
    }
}
