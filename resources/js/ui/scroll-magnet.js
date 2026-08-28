/**
 * **EL IMÁN DE LOS DOS PUNTOS ESTÁTICOS** (`docs/specs/tema-por-instalacion.md` §20).
 *
 * `[DECIDIDO owner, 2026-08-29]`: los dos heroes de la portada —el de cabecera y el del cierre—
 * tienen **un punto del recorrido en el que todo está en su sitio**: arriba, el hero ya encogido a
 * tarjeta con el logotipo, el par de CTA, la hamburguesa y la tira de marca colocados; abajo, la
 * tarjeta del cierre en reposo con el pie entero debajo. *«Quiero que ese punto tenga como un imán,
 * que al hacer scroll se pare ahí por un momento, para que no ocurra que el cliente hace scroll y
 * se pierde ese punto donde se ve todo en su sitio.»*
 *
 * ▶ **Es lógica pura y por eso vive aquí y no dentro del componente de Alpine**: la decisión tiene
 * seis casos de borde y ninguno se puede ejercitar desde `app.js`, que no cubre ningún test. Mismo
 * criterio que `nav-choreography.js`, y por el mismo motivo.
 *
 * ⚠️⚠️ **NO es el `freno` del mockup, y la diferencia es lo que pidió el owner.** El suyo encaja
 * hacia **el lado al que ibas**: bajando por el cierre te lleva a pantalla completa. Aquí bajando
 * te lleva **primero al punto estático**, y solo un segundo empujón deliberado —pasado el umbral—
 * completa la transición: *«que no se ejecute la transición de full viewport sin querer»*. El resto
 * —el retardo, la curva, el enfriamiento y que cualquier gesto lo cancele— sí es suyo.
 */

/** Cuánto recorrido del cierre hay que haber consumido para que el imán deje de retenerte. */
export const UMBRAL_CIERRE = 0.45;

/** Zona muerta en los extremos: dentro de ella ya estás donde el imán te llevaría. */
export const MUERTA = 12;

/** Por debajo de esto no se mueve nada: el viaje no compensa el sobresalto. */
export const MINIMO_UTIL = 8;

/** Movimiento mínimo desde la última parada para creerse que hay un gesto detrás. */
export const GESTO_MINIMO = 24;

/**
 * **Hacia dónde va el visitante — y esto NO se puede sacar del último evento de scroll.**
 *
 * ⚠️⚠️ La primera versión comparaba cada evento con el anterior y **falló en el navegador con los
 * tests en verde**: al llegar al final de la portada el documento **crece 34 px** (algo carga
 * tarde), el anclaje de scroll del navegador compensa moviendo la posición, y eso llega como un
 * evento de scroll hacia abajo. Resultado medido: subiendo desde el fondo, el imán leía «va
 * bajando» y devolvía al visitante a pantalla completa — justo lo contrario de lo que se le pidió.
 * ▶ El rumbo es el movimiento NETO desde la última parada. Un reflujo de 34 px en medio de un
 * gesto de 200 ya no cambia el signo, y un reflujo SOLO —sin gesto— se queda por debajo del
 * mínimo y no decide nada.
 *
 * @returns {0|1|-1} `0` = no ha habido gesto, no hay nada que decidir.
 */
export function rumbo(y, yReposo) {
    const delta = y - yReposo;

    if (Math.abs(delta) < GESTO_MINIMO) {
        return 0;
    }

    return delta > 0 ? 1 : -1;
}

/**
 * ¿A dónde tiene que encajar el scroll, si es que tiene que encajar?
 *
 * @param {object} s
 * @param {number} s.y             posición actual del scroll
 * @param {number} s.vh            alto de la ventana
 * @param {number} s.maxY          scroll máximo del documento
 * @param {number} s.heroRunway    recorrido del hero de cabecera (0 si esta página no tiene)
 * @param {number} s.cierreRunway  recorrido del hero del cierre (0 si esta página no tiene)
 * @param {number} s.dir           +1 bajando · −1 subiendo
 * @param {boolean} [s.locked]     hay un superpuesto abierto (menú, cajón): el imán no manda
 * @param {boolean} [s.reduce]     el visitante ha pedido menos movimiento
 * @returns {number|null} el destino en píxeles, o `null` si aquí no hay nada que encajar.
 */
export function destinoIman({ y, vh, maxY, heroRunway, cierreRunway, dir, locked = false, reduce = false }) {
    // ⚠️ Con un superpuesto abierto NUNCA: el scroll que se está oyendo es el suyo, no el de la
    // página, y mover la página debajo de un menú abierto es mover algo que nadie está mirando.
    if (locked || reduce) {
        return null;
    }

    const destino = candidato({ y, vh, maxY, heroRunway, cierreRunway, dir });

    // Ya estás donde te llevaría: no hay viaje que hacer.
    if (destino === null || Math.abs(destino - y) < MINIMO_UTIL) {
        return null;
    }

    return destino;
}

function candidato({ y, vh, maxY, heroRunway, cierreRunway, dir }) {
    // ── EL HERO DE CABECERA ────────────────────────────────────────────────────────────────────
    // Dentro de su recorrido solo hay dos estados que signifiquen algo: la primera pantalla entera
    // (arriba del todo) y el punto estático (el hero ya encogido, con el armazón colocado). Un
    // punto intermedio es un hero a medio encoger, que no es un estado: es un fotograma.
    if (heroRunway > 0 && y > MUERTA && y < heroRunway - MUERTA) {
        return dir < 0 ? 0 : heroRunway;
    }

    if (cierreRunway <= 0 || maxY <= vh) {
        return null;
    }

    // ── EL HERO DEL CIERRE ─────────────────────────────────────────────────────────────────────
    // Su punto estático es donde el pie acaba justo en el borde inferior de la ventana, que es el
    // scroll a partir del cual la tarjeta empieza a crecer: `maxY − recorrido`. De ahí sale
    // también el progreso crudo, el mismo número que publica la coreografía.
    const estatico = maxY - cierreRunway;
    const q = (y - estatico) / Math.max(1, maxY - estatico);

    if (dir < 0) {
        // Subiendo se sale de la pantalla completa, y el siguiente estado hacia arriba es el punto
        // estático. Nunca se empuja hacia abajo a quien está subiendo.
        return y > estatico + MUERTA && y < maxY - MUERTA ? estatico : null;
    }

    // ⚠️ **Bajando, el imán CAPTURA UN POCO ANTES del punto estático.** Sin esa banda, quien se
    // para a 150 px de él se queda con el pie cortado y el imán no se entera: el punto estático
    // solo existe si se llega a él.
    const banda = Math.min(260, cierreRunway * 0.5);

    if (y <= estatico - banda || y >= maxY - MUERTA) {
        return null;
    }

    return q >= UMBRAL_CIERRE ? maxY : estatico;
}

/* ══ LA MITAD QUE TOCA EL DOM ═══════════════════════════════════════════════════════════════════
   Vive aquí, y no en `app.js`, por lo mismo que el cerrojo de scroll: así el núcleo de arriba
   sigue sin conocer el DOM y los tests pueden importarlo con `node --test`. */

/** Retardo desde el último evento de scroll antes de encajar. Es el del mockup. */
export const REPOSO = 170;

/** Enfriamiento tras encajar (o tras cancelar): sin él, el imán vuelve a tirar en el acto. */
export const ENFRIAMIENTO = 1000;

/**
 * Instala el imán sobre la ventana.
 *
 * @param {{locked: () => boolean, runways: () => {heroRunway: number, cierreRunway: number}}} opciones
 * @returns {() => void} para desinstalarlo.
 */
export function installScrollMagnet({ locked, runways }) {
    const reduce = () => !! window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;

    // ⚠️ **El REPOSO, no la posición anterior.** Ver `rumbo()`: el rumbo sale del movimiento neto
    // desde la última vez que el scroll se paró, y no del último evento.
    let yReposo = window.scrollY || 0;
    let temporizador = null;
    let raf = null;
    let hasta = 0;              // hasta cuándo dura el enfriamiento
    let viajando = false;

    // ⚠️ **Cualquier gesto CANCELA, y esto no es opcional**: un imán que sigue tirando mientras el
    // visitante empuja es la peor versión de esta idea. Se escuchan los tres del mockup más
    // `pointerdown`, que es el arrastre de una barra de scroll.
    const gestos = ['wheel', 'touchstart', 'keydown', 'pointerdown'];
    let cancelado = false;
    const cancela = () => { cancelado = true; };

    const para = () => {
        if (raf !== null) cancelAnimationFrame(raf);
        raf = null;
        viajando = false;
        gestos.forEach((ev) => window.removeEventListener(ev, cancela));
        hasta = Date.now() + ENFRIAMIENTO;
        yReposo = window.scrollY || 0;
    };

    const viaja = (destino) => {
        const y0 = window.scrollY || 0;
        // La duración sale de la distancia: un salto corto que dure lo mismo que uno largo se
        // siente perezoso, y al revés se siente brusco. Son los números del mockup.
        const dur = Math.min(620, Math.max(280, Math.abs(destino - y0) * 1.1));
        const t0 = performance.now();
        cancelado = false;
        viajando = true;
        gestos.forEach((ev) => window.addEventListener(ev, cancela, { passive: true, once: true }));

        const paso = () => {
            if (cancelado) { para(); return; }
            const p = Math.min(1, (performance.now() - t0) / dur);
            // Sale rápido y se POSA. Es la curva con la que el armazón entra y se retira, así que
            // el gesto del imán se siente parte de la misma coreografía y no de otra.
            window.scrollTo(0, Math.round(y0 + (destino - y0) * (1 - Math.pow(1 - p, 3))));
            if (p < 1) { raf = requestAnimationFrame(paso); return; }
            para();
        };

        raf = requestAnimationFrame(paso);
    };

    const encaja = () => {
        if (viajando) return;

        const y = window.scrollY || 0;
        const dir = rumbo(y, yReposo);
        // Ésta es la nueva parada, se decida o no: si el reposo solo se moviera al encajar, un
        // recorrido largo sin encaje dejaría el rumbo midiendo contra un punto de hace media
        // página, y el imán leería «baja» a quien lleva rato subiendo.
        yReposo = y;

        if (dir === 0 || Date.now() < hasta) return;

        const { heroRunway, cierreRunway } = runways();
        const destino = destinoIman({
            y,
            vh: window.innerHeight,
            maxY: Math.max(document.documentElement.scrollHeight, document.body.scrollHeight) - window.innerHeight,
            heroRunway,
            cierreRunway,
            dir,
            locked: locked(),
            reduce: reduce(),
        });

        if (destino !== null) viaja(destino);
    };

    const alDesplazar = () => {
        if (viajando) return;              // el scroll lo estamos escribiendo nosotros
        clearTimeout(temporizador);
        temporizador = setTimeout(encaja, REPOSO);
    };

    window.addEventListener('scroll', alDesplazar, { passive: true });

    return () => {
        window.removeEventListener('scroll', alDesplazar);
        clearTimeout(temporizador);
        para();
    };
}
