/**
 * **EL INTERRUPTOR DEL TITULAR VUELVE A SALTAR AL VOLVER EL HERO** (`DECISIONES #280`).
 *
 * `[DECIDIDO owner, 2026-08-30]`: el interruptor **para tras unos ciclos** —el CSS lo hace solo,
 * acotando las iteraciones y dejándolo en reposo ENCENDIDO— **y vuelve a arrancar cuando el hero
 * regresa al viewport**. Lo primero es del CSS; esto es solo lo segundo.
 *
 * ▶ **Este módulo no decide NADA de diseño.** Ni una duración, ni una curva, ni cuántos ciclos:
 * eso vive en `--dur-switch`, `--ease-entra` y `--switch-cycles`, que es donde una instalación
 * puede tocarlo. Lo único que hace aquí el JavaScript es **volver a disparar** lo que el CSS ya
 * describe — el mismo criterio que `#254` §12.3 le puso al hero: *si los números vivieran en el
 * `.js` serían la única parte del tema que un cliente no puede tocar*.
 *
 * ⚠️⚠️ **El rearranque NO se puede hacer con la Web Animations API, y está medido.** Una animación
 * CSS que ha terminado con `fill: none` deja de ser «relevante» y **desaparece de
 * `getAnimations()`**: medido sobre la portada, la lista pasa de 1 a **0** en cuanto para. No hay
 * objeto al que pedirle `play()`. Lo que sí funciona es obligar al motor de estilo a **descartar y
 * recrear** la animación, que es lo que hace `rearranca()`.
 *
 * ⚠️ **Con movimiento reducido esto es un no-op y a propósito no hay una segunda puerta.** El
 * `@media (prefers-reduced-motion: reduce)` de `site.css` deja las tres piezas en `animation: none`,
 * así que quitar y devolver el `animation-name` en línea devuelve… `none`. Una comprobación de
 * `matchMedia` aquí no cambiaría el resultado y habría que mantenerla sincronizada con el CSS —dos
 * fuentes para la misma regla— además de escuchar sus cambios para no quedarse desfasada.
 *
 * ▶ **Sin JavaScript la pieza sigue completa**: la animación está declarada en la regla base, así
 * que salta sus ciclos al cargar y descansa encendida. Lo único que se pierde es la repetición.
 */

/** Las tres piezas que animan. El envoltorio (`.hero__switch`) no anima: solo coloca. */
export const PIEZAS = '.hero__switch-sw, .hero__switch-knob, .hero__switch-on';

/** Estado inicial: el hero está a la vista y la carga de la página YA ha animado la pieza. */
export const INICIAL = { fuera: false, rearranca: false };

/**
 * **Cuándo toca volver a saltar** — y la respuesta no es «cada vez que se ve».
 *
 * ⚠️ Un `IntersectionObserver` con umbral 0 dispara en el filo: basta un temblor de un píxel, el
 * ajuste del imán de scroll (`scroll-magnet.js`) o el crecimiento del documento al cargar las
 * imágenes perezosas para que lluevan entradas «visible». Rearrancar con cada una convertiría en
 * un tic justo la animación que se acaba de acotar para que dejara de serlo.
 *
 * ▶ Por eso hay que **haber salido del todo** antes de volver a entrar: el rearranque se ARMA al
 * perder el hero de vista y se gasta al recuperarlo. Y por eso el estado inicial NO está armado —
 * si lo estuviera, la primera entrada del observador al cargar la página dispararía un segundo
 * pase encima del que el CSS ya está haciendo.
 *
 * @param {{fuera: boolean}} estado
 * @param {boolean} visible
 * @returns {{fuera: boolean, rearranca: boolean}}
 */
export function siguiente(estado, visible) {
    if (! visible) {
        return { fuera: true, rearranca: false };
    }

    return { fuera: false, rearranca: estado.fuera === true };
}

/**
 * **Descartar y recrear las animaciones CSS de las tres piezas.**
 *
 * ⚠️⚠️ **El `offsetWidth` de en medio no es un truco supersticioso: sin él no pasa nada.** El
 * navegador agrupa las dos escrituras de estilo del mismo fotograma, así que ve el valor final
 * —el de siempre— y concluye que `animation-name` no ha cambiado: no descarta la animación
 * terminada y no crea ninguna. Leer una propiedad de disposición fuerza el recálculo en medio y
 * parte el cambio en dos.
 *
 * ▶ Se escribe `animation-name` y no el atajo `animation` **porque la regla base ya no lleva el
 * número de iteraciones dentro del atajo**: lo pone una declaración aparte que lee
 * `--switch-runs`. Tocar solo el nombre deja intacto todo lo demás, y borrarlo devuelve la pieza
 * entera a la cascada.
 *
 * @param {Element[]} piezas
 */
export function rearranca(piezas) {
    if (! piezas.length) {
        return;
    }

    piezas.forEach((p) => { p.style.animationName = 'none'; });
    void piezas[0].offsetWidth;
    piezas.forEach((p) => { p.style.animationName = ''; });
}

/**
 * Engancha el observador al envoltorio del interruptor.
 *
 * Devuelve `null` —sin tocar nada— en las diez vistas que no tienen hero con interruptor y en
 * cualquier navegador sin `IntersectionObserver`: en las dos situaciones la pieza ya está completa
 * sin esto.
 *
 * @returns {{ desconecta: () => void }|null}
 */
export function installHeroSwitch({ raiz = document } = {}) {
    const envoltorio = raiz.querySelector('.hero__switch');

    if (! envoltorio) {
        return null;
    }

    const piezas = [...envoltorio.querySelectorAll(PIEZAS)];

    if (! piezas.length) {
        return null;
    }

    // El suelo del navegador antiguo. ⚠️ Esta puerta va la ÚLTIMA y las tres van separadas a
    // propósito: juntas, un caso que quiera ejercitar «hay envoltorio pero está vacío» saldría
    // verde por la puerta equivocada en cualquier entorno sin observador —empezando por el de los
    // tests, donde `IntersectionObserver` no existe— y estaría vigilando la nada.
    if (typeof IntersectionObserver !== 'function') {
        return null;
    }

    let estado = INICIAL;

    const observador = new IntersectionObserver((entradas) => {
        entradas.forEach((entrada) => {
            estado = siguiente(estado, entrada.isIntersecting);

            if (estado.rearranca) {
                rearranca(piezas);
            }
        });
    });

    observador.observe(envoltorio);

    return { desconecta: () => observador.disconnect() };
}
