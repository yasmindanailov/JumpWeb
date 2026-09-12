/**
 * LAS FLECHAS DE LOS CARRILES QUE SE DESLIZAN · `DECISIONES #549`
 *
 * `[owner]`: *«le ponemos unas flechas para que el usuario sepa que hay que hacer slide, y si no
 * puede hacer slide con móvil entonces por accesibilidad necesitamos unas flechas»*.
 *
 * ❗❗ **Este módulo NO decide diseño ni decide si las flechas se ven**, que es la doctrina de `#195`
 * y la que ya sigue su hermano `rail-sails.js`. Quién las enseña es el CSS, a partir del hecho
 * `data-rail-scroll` que publica ese otro módulo: un carril que cabe entero no las pinta, y sin
 * JavaScript no hay atributo y tampoco flechas. Aquí solo vive la CONDUCTA — mover el carril y decir
 * cuándo una flecha ya no lleva a ninguna parte.
 *
 * ⚠️⚠️ **El paso es una FICHA, no una pantalla.** Con `scroll-snap-type: x mandatory` el carril
 * aterriza solo en la parada más cercana, así que un paso que no coincida con el ancho de una ficha
 * no descuadra nada… pero un paso de pantalla completa **se salta fichas enteras** en escritorio,
 * donde caben tres. Se mide la ficha REAL (la primera del carril) más el hueco entre dos, que es la
 * distancia entre dos paradas; si el carril está vacío no hay nada que mover y se sale.
 *
 * ⚠️⚠️ **EL «CÓMO» SE MUEVE NO SE DECIDE AQUÍ: lo dice el CSS** con `scroll-behavior` sobre el carril,
 * y ahí es donde vive también su excepción de `prefers-reduced-motion`. Pasar `behavior: 'smooth'` en
 * la llamada **gana a la hoja**, así que el movimiento reducido dejaría de respetarse sin que nada
 * fallara — y de paso el tempo del sitio dejaría de ser una decisión de diseño.
 */

/** ¿Cuánto avanza un toque? La ficha más el hueco, o sea la distancia entre dos paradas. */
function paso(track) {
    const ficha = track.firstElementChild;

    if (!ficha) return 0;

    const hueco = parseFloat(getComputedStyle(track).columnGap) || 0;

    return ficha.getBoundingClientRect().width + hueco;
}

/**
 * Las dos flechas dicen la verdad sobre los extremos.
 *
 * ⚠️⚠️ **El margen de 1 px no es superstición, es la misma tolerancia que `rail-sails.js` paga por
 * otro motivo**: `scrollLeft` es fraccionario y `scrollWidth − clientWidth` se redondea, así que un
 * carril desplazado hasta el final puede quedarse en 0,4 px de su tope y la flecha seguiría
 * encendida ofreciendo un movimiento que ya no existe.
 */
function marcar({ track, prev, next }) {
    const tope = track.scrollWidth - track.clientWidth;

    prev.disabled = track.scrollLeft <= 1;
    next.disabled = track.scrollLeft >= tope - 1;
}

export function initRailArrows(raiz = document) {
    const navs = [...raiz.querySelectorAll('[data-rail-nav]')];

    if (navs.length === 0) return;

    for (const nav of navs) {
        // El carril es el del envoltorio donde vive la nave. Se busca así —y no por un `id`— porque
        // este bloque se pinta una vez por zona y un `id` fijo se duplicaría (lo dice el componente).
        const track = nav.parentElement?.querySelector('.addons-rail__track');
        const prev = nav.querySelector('[data-rail-prev]');
        const next = nav.querySelector('[data-rail-next]');

        if (!track || !prev || !next) continue;

        const par = { track, prev, next };

        const mover = (signo) => {
            const salto = paso(track);

            if (salto === 0) return;

            track.scrollBy({ left: signo * salto });
        };

        prev.addEventListener('click', () => mover(-1));
        next.addEventListener('click', () => mover(1));

        // ⚠️ Una medida por fotograma como mucho: el scroll emite decenas de eventos por gesto, y
        // deslizar con el dedo dispara los dos extremos varias veces.
        let pendiente = 0;
        const programar = () => {
            if (pendiente) return;

            pendiente = requestAnimationFrame(() => {
                pendiente = 0;
                marcar(par);
            });
        };

        marcar(par);
        track.addEventListener('scroll', programar, { passive: true });

        // ⚠️⚠️ **Lo que cabe cambia sin que nadie desplace nada**: las fuentes de la instalación
        // llegan después del primer pintado y ensanchan cada ficha, y abrir el panel de otra zona
        // cambia el ancho de la columna sin mover la ventana. Es la misma lección que `#256` (el
        // motor del minijuego cacheaba el ancho del lienzo) y la que ya paga `rail-sails.js`.
        document.fonts?.ready?.then(() => marcar(par));

        if ('ResizeObserver' in window) new ResizeObserver(programar).observe(track);
    }
}
