/**
 * LAS VELAS DE LOS CARRILES QUE SE DESLIZAN · `DECISIONES #498` y `#522`
 *
 * ❗❗ **Este módulo NO decide diseño: publica un HECHO.** Marca el envoltorio con `data-rail-scroll`
 * cuando su carril tiene contenido que no cabe, y el CSS decide qué hacer con eso. Es la doctrina de
 * `#195` —*«el JavaScript publica UNA custom property y no decide diseño»*—: si los números vivieran
 * aquí serían la única parte del tema que un cliente no puede tocar.
 *
 * ▶ **Por qué hace falta, habiendo velas en CSS puro.** Las velas se apagan y se encienden solas
 * con `animation-timeline: scroll()`, sin una línea de JS. Lo que el CSS **no puede** saber es si el
 * carril tiene scroll: sin desbordamiento la timeline no tiene rango, la animación no se aplica y la
 * vela aparece **sin nada detrás**. Medido: pasa de verdad —con dos complementos, el carril de
 * tarifas cabe entero en escritorio (desborde 0) y desborda 330 px en móvil—, así que **no se puede
 * decidir en el servidor**: depende del ancho, no del número de fichas.
 *
 * ⚠️⚠️ **CORRECCIÓN (`#522`): esta cabecera decía que sin JavaScript las velas se comportan «como si
 * hubiera más», y es FALSO.** El CSS de los carriles las APAGA cuando falta el atributo
 * (`:not([data-rail-scroll])`), así que sin JS no hay vela — el carril se sigue pudiendo deslizar,
 * pero nada dice que siga. Se deja escrito lo que el código hace de verdad; el día que se quiera el
 * estado contrario hay que publicar el hecho opuesto («cabe»), no reescribir este comentario.
 *
 * ▶ **Desde `#522` publica el hecho para CUATRO carriles, no uno**: el de complementos, las dos tiras
 * del pie (destinos y legal) y la lista del menú. Las tres últimas tenían además un defecto peor: su
 * línea de tiempo era `scroll(nearest …)` sobre el `::after` del ENVOLTORIO, que busca el contenedor
 * de scroll ANTECESOR —el documento en el pie, el propio `.menu` en el menú— y no el carril, que es
 * su hermano o su descendiente. **No se apagaron nunca**: estuvieron siempre visibles, que es por lo
 * que nadie lo notó.
 */

/**
 * Los carriles con vela: `[envoltorio, carril, eje]`. El envoltorio recibe la marca; el carril es el
 * que desliza; el eje es por dónde (`inline` si no se dice: la lista del menú es la única vertical).
 */
const CARRILES = [
    ['.foot__links-wrap', '.foot__links'],
    ['.foot__legal-wrap', '.foot__legal'],
    ['.menu__inner', '.menu__col-list', 'block'],
];

/** ¿El carril de este envoltorio tiene algo que no cabe en su eje? */
function desborda({ wrap, carril, eje }) {
    const track = wrap.querySelector(carril);

    if (!track) return false;

    const sobra = eje === 'block'
        ? track.scrollHeight - track.clientHeight
        : track.scrollWidth - track.clientWidth;

    // ⚠️ El margen de 4 px no es superstición: las medidas de scroll y de caja se redondean distinto
    // según el zoom del navegador, y sin él un carril que cabe justo parpadea entre los dos estados.
    return sobra > 4;
}

function marcar(par) {
    par.wrap.toggleAttribute('data-rail-scroll', desborda(par));
}

/** Una medida por fotograma como mucho: una transición dispara un evento por propiedad y por fila. */
function programar(par) {
    if (par.pendiente) return;

    par.pendiente = requestAnimationFrame(() => {
        par.pendiente = 0;
        marcar(par);
    });
}

export function initRailSails(raiz = document) {
    const pares = CARRILES.flatMap(([envoltorio, carril, eje = 'inline']) =>
        [...raiz.querySelectorAll(envoltorio)].map((wrap) => ({ wrap, carril, eje, pendiente: 0 })));

    if (pares.length === 0) return;

    pares.forEach(marcar);

    // ⚠️⚠️ **Lo que desborda puede cambiar SIN que el carril cambie de tamaño**, y entonces ningún
    // observador de tamaño se entera. Pasa de dos formas, las dos medidas en el menú: las fuentes de
    // la instalación llegan después del primer pintado y ensanchan cada destino, y las filas del menú
    // entran con un desplazamiento vertical que cuenta como contenido mientras dura (la misma lista
    // dio 173 px de desborde a mitad de la entrada y 158 al terminar). Se vuelve a medir cuando las
    // fuentes están y cuando termina cualquier transición de dentro del envoltorio.
    document.fonts?.ready?.then(() => pares.forEach(marcar));

    for (const par of pares) par.wrap.addEventListener('transitionend', () => programar(par));

    // ⚠️⚠️ **`ResizeObserver` y no un `resize` de ventana**: el carril cambia de tamaño cuando lo
    // hace su columna, y la columna cambia por cosas que no mueven la ventana —abrir el panel de
    // otra zona en tarifas, por ejemplo—. Es la misma lección que `#256`, donde el motor del
    // minijuego cacheaba el ancho del lienzo y se estiraba un 62 % a 1920.
    if (!('ResizeObserver' in window)) return;

    const observados = new Map();
    const ro = new ResizeObserver((entradas) => {
        for (const e of entradas) {
            const par = observados.get(e.target);

            if (par) marcar(par);
        }
    });

    for (const par of pares) {
        const track = par.wrap.querySelector(par.carril);

        if (track) {
            observados.set(track, par);
            ro.observe(track);
        }
    }
}
