/**
 * **LOS MOVIMIENTOS DE LA ISLA que no caben en un estilo** (Z3 de `specs/isla-y-landing-nueva.md` §4.14, `#782`): los de
 * `ParkIsland.jsx` del 26-09 que el diseño hace con la Web Animations API —el panel que entra fila a fila, el que se va
 * en 100ms, el nombre del plan que viaja a la compra— y el relevo entre islas (`relevo.js`), que en el diseño no existe
 * porque allí la isla es una. Con «reducir movimiento», ninguno: la isla cambia sin moverse y todo sigue funcionando.
 *
 * Las cifras son las del laboratorio (02b): filas 260ms, +140ms y 30ms entre ellas; cerrar, contenido 100ms; el nombre
 * del plan, 420ms. La curva es `--ease-out` escrita (la API no lee variables).
 */
export const EASE_OUT = 'cubic-bezier(0.2, 0.8, 0.2, 1)';

export const quieto = (win = globalThis.window) => Boolean(win?.matchMedia?.('(prefers-reduced-motion: reduce)').matches);

/**
 * Las FILAS de un panel, en el orden en que se leen: lo de antes del cuerpo (el título) y los hijos del cuerpo, con las
 * listas (`ul`/`ol`) abiertas en sus elementos. Con una sola fila, el panel entero. Catorce como mucho: las demás ya
 * están fuera de la vista cuando llegan.
 */
export function filasDePanel(pn) {
    const hijos = Array.from(pn.children);
    const cuerpo = hijos[hijos.length - 1];
    const filas = hijos.slice(0, -1);

    if (cuerpo) Array.from(cuerpo.children).forEach((c) => { if (c.tagName === 'UL' || c.tagName === 'OL') filas.push(...c.children); else filas.push(c); });

    return (filas.length > 1 ? filas : [pn]).slice(0, 14);
}

/** Al abrir un panel, su contenido sube desde la acción fila a fila (arriba baja; abajo sube): lo primero que se lee, primero. */
export function entradaPorFilas(pn, top) {
    if (! pn?.animate || quieto()) return;
    const dy = top ? -8 : 8;

    filasDePanel(pn).forEach((el, i) => el.animate(
        [{ opacity: 0, transform: `translateY(${dy}px)` }, { opacity: 1, transform: 'none' }],
        { duration: 260, delay: 140 + i * 30, easing: EASE_OUT, fill: 'backwards' },
    ));
}

/**
 * Cerrar es más corto que abrir: el contenido se va en 100ms y después la isla vuelve a su tamaño (`--dur-close`).
 * ⚠️ Se cierra cuando la salida TERMINA, no a los 100ms de reloj: en un móvil lento el primer fotograma llega tarde
 * (medido sin GPU a 1280: más de 50ms) y el reloj cortaba el fundido. Con un tope, por si la pestaña no pinta (oculta).
 */
export function salidaDePanel(pn, hecho) {
    if (! pn?.animate || quieto()) { hecho(); return; }
    let una = false;
    const acabar = () => { if (! una) { una = true; hecho(); } };
    const a = pn.animate([{ opacity: 1 }, { opacity: 0 }], { duration: 100, easing: EASE_OUT, fill: 'forwards' });

    a.finished.then(acabar, acabar);
    setTimeout(acabar, 400);
}

/**
 * Cuánto hay que mover la isla nueva para que EMPIECE donde estaba la otra, sabiendo cómo se ancla: centrada en
 * horizontal y pegada por ARRIBA (escritorio) o por ABAJO (móvil). Al cambiar de tamaño, una isla anclada abajo sube su
 * borde de arriba; por eso se casa el borde que no se mueve y el centro, no la esquina (medido: casando la esquina, la
 * compra en móvil empezaba en y = 1482 en vez de 741). Puro: recibe dos cajas `{ x, y, w, h }`.
 */
export function desplazamientoDesde(caja, fin, arriba) {
    return {
        dx: (caja.x + caja.w / 2) - (fin.x + fin.w / 2),
        dy: arriba ? caja.y - fin.y : (caja.y + caja.h) - (fin.y + fin.h),
    };
}

/**
 * **El relevo**: la isla que acaba de montarse crece (o encoge) desde la caja de la que se fue, en calma: `--dur-slow`
 * al abrir la compra o Mi cuenta, `--dur-close` al volver a la píldora. Anima el tamaño —como el morfeo del diseño— y la
 * posición, con `translate`, porque la píldora de la página y la capa grande no se pegan al mismo borde.
 * ⚠️ Solo el fotograma de SALIDA: el de llegada es el valor vivo de la isla (fotograma implícito), que sigue a su tamaño
 * si el contenido termina de llegar mientras crece (medido: fijando el final, la compra saltaba de 247 a 379px de alto
 * al acabar). Al terminar, la isla queda con sus estilos de siempre.
 */
export function crecerDesde(el, caja, { abrir, arriba }) {
    if (! el?.animate || ! caja || quieto()) return null;
    const r = el.getBoundingClientRect();
    const cs = getComputedStyle(el);
    const { dx, dy } = desplazamientoDesde(caja, { x: r.left, y: r.top, w: r.width, h: r.height }, arriba);
    // ⚠️ Probado y DESCARTADO (`#783`): revelar la capa grande con un recorte (`clip-path`) en vez de animar su tamaño.
    // Sin recolocar nada debía costar menos, y medido con la CPU ×4 no mejoraba en móvil (15 fotogramas lentos de 203) y
    // empeoraba en escritorio (48 de 125, seis de más de 50ms): recortar un elemento con cristal cuesta más que recolocarlo.
    const bordes = (parseFloat(cs.borderLeftWidth) || 0) + (parseFloat(cs.borderRightWidth) || 0);
    const bordesV = (parseFloat(cs.borderTopWidth) || 0) + (parseFloat(cs.borderBottomWidth) || 0);

    // `offset: 0`: un fotograma suelto es el de LLEGADA para la API (medido: la compra encogía hasta la píldora).
    return el.animate(
        [{ offset: 0, width: `${caja.w - bordes}px`, height: `${caja.h - bordesV}px`, transform: `translate(${dx}px, ${dy}px)`, borderRadius: `${caja.radio}px` }],
        { duration: abrir ? 380 : 300, easing: EASE_OUT },
    );
}

/**
 * **Del selector a la compra, el nombre del plan viaja a la cabecera del paso** (02b, 420ms): se ve qué se está pagando.
 * `v` lo dejó el selector (`{ texto, r, font, color, px }`); `destino` es la cabecera, que se mueve mientras la isla
 * crece, así que se lee en cada fotograma. Acaba a 14px (la letra de la cabecera) y se funde en el último 30 %.
 */
export function volar(v, destino, doc = document) {
    if (! v || ! destino || quieto()) return () => {};
    const c = doc.createElement('span');

    c.textContent = v.texto;
    c.setAttribute('aria-hidden', 'true');
    Object.assign(c.style, { position: 'fixed', left: `${v.r.left}px`, top: `${v.r.top}px`, zIndex: '200', pointerEvents: 'none', font: v.font, color: v.color, whiteSpace: 'nowrap', transformOrigin: '0 0', willChange: 'transform, opacity' });
    doc.body.appendChild(c);
    const t0 = performance.now();
    const fin = 14 / v.px;
    let f = 0;
    const paso = (ahora) => {
        const q = Math.min(1, (ahora - t0) / 420);
        const e = 1 - Math.pow(1 - q, 3);
        const d = destino.getBoundingClientRect();

        c.style.transform = `translate(${(d.left - v.r.left) * e}px,${(d.top - v.r.top) * e}px) scale(${1 + (fin - 1) * e})`;
        c.style.opacity = String(q < 0.7 ? 1 : 1 - (q - 0.7) / 0.3);
        if (q < 1) f = requestAnimationFrame(paso); else c.remove();
    };

    f = requestAnimationFrame(paso);

    return () => { cancelAnimationFrame(f); c.remove(); };
}

/** Lo que el selector deja para el vuelo: el nombre (su `<b>`) tal como se ve, con su letra y su color. */
export function vueloDesde(boton, texto) {
    const b = boton?.querySelector?.('b');

    if (! b) return null;
    const cs = getComputedStyle(b);

    return { texto, r: b.getBoundingClientRect(), font: cs.font, color: cs.color, px: parseFloat(cs.fontSize) || 16 };
}
