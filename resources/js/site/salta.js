/**
 * **«SALTA LA CIUDAD» — el minijuego del hero del cierre** (`#231`, del mockup `Landing PJP Modos`).
 *
 * Un corredor infinito sobre las almenas: el muñeco corre solo, tú decides cuándo salta y cuánto.
 *
 * ▶ **Se carga APARTE, y no es una optimización cosmética.** El bundle de la landing pesa 19 KB;
 * esto son ~15 más. Meterlo dentro lo casi duplicaría para todos los visitantes por algo que solo
 * se alcanza **al final del todo** de la portada. `app.js` lo trae con un `import()` dinámico la
 * primera vez que el lienzo se acerca a la pantalla, así que Vite lo emite en su propio trozo.
 *
 * ▶ **El motor NO conoce Alpine ni el DOM de la página.** Recibe el lienzo y un puñado de
 * devoluciones de llamada, y devuelve un mando (`arranca`, `salta`, `suelta`, `destruye`). Así se
 * puede probar la física sin montar media web, y la capa de estado —qué fase, qué se pinta— vive
 * donde vive el resto del estado de la landing.
 *
 * ⚠️ **La PALETA sale de los tokens del tema, no de los hex del mockup.** Un lienzo no hereda CSS,
 * así que se leen una vez con `getComputedStyle` sobre el propio `<canvas>` —que está dentro del
 * ámbito de tinta del cierre— y se cachean. Con los valores del mockup como respaldo: si una
 * instalación no declara alguno, el juego se ve como el del cliente en vez de romperse.
 *
 * ⚠️ **El mundo es procedural pero NO aleatorio a ciegas: cada hueco y cada escalón salen de la
 * FÍSICA.** El alcance de un salto es `v · 2·imp/grav` y su altura `imp²/(2·grav)`; los huecos son
 * una fracción del primero y los desniveles del segundo. Por eso nunca aparece un salto imposible,
 * y por eso las constantes de `FISICA` no se pueden tocar de una en una: mueven el terreno con ellas.
 */

/** Constantes del mockup, verificadas contra su `JU`. Cambiar una mueve también la generación. */
const FISICA = {
    vel0: 232,      // velocidad inicial, px/s a escala 1
    velMax: 505,
    acel: 4.2,      // aceleración por segundo de partida
    grav: 2200,
    imp: 700,       // impulso del salto
    corte: 0.44,    // al soltar, la subida se recorta a esta fracción → salto de altura variable
    reb: 900,       // impulso de la cama elástica
    tol: 22,        // tolerancia de aterrizaje
    techo: 8,
    xJug: 0.30,     // dónde va el muñeco, en fracción del ancho
    altMin: 42,
    altMax: 126,
};

/** El muñeco, en rejilla de píxeles. Tres poses; la letra es la clave de la paleta. */
const POSES = {
    salto: [
        '....CCCC....', '...CCCCCC...', '...CCCCCC...', '...SSSSSS...',
        '...SSSSSS...', '....SSSS....', 'S..BBBBBB..S', 'S.BBBBBBBB.S',
        '..BBBBBBBB..', '..BBBBBBBB..', '...PPPPPP...', '..PPP..PPP..',
        '..PP....PP..', '.PPP....PPP.', '.ZZZ....ZZZ.', '.ZZZ....ZZZ.',
    ],
    caida: [
        '............', '....CCCC....', '...CCCCCC...', '...CCCCCC...',
        '...SSSSSS...', '...SSSSSS...', '..SBBBBBBS..', '.SSBBBBBBSS.',
        '..BBBBBBBB..', '..BBBBBBBB..', '..PPPPPPPP..', '..PPP..PPP..',
        '.PPP....PPP.', '.ZZZZ..ZZZZ.', '.ZZZZ..ZZZZ.', '............',
    ],
    corre: [
        '............', '....CCCC....', '...CCCCCC...', '...CCCCCC...',
        '...SSSSSS...', '...SSSSSS...', '...BBBBBBS..', '..SBBBBBBB..',
        '..BBBBBBBBS.', '..BBBBBBBB..', '..PPPPPPPP..', '...PPPPPP...',
        '..PPP..PPP..', '..ZZZ..ZZZZ.', '.ZZZZ...ZZZ.', '............',
    ],
};

/**
 * Lee la paleta del TEMA. Las claves de una letra son las del muñeco; el resto, el escenario.
 * ⚠️ Se lee UNA vez: `getComputedStyle` en cada fotograma costaría más que todo el pintado.
 */
function leePaleta(cv) {
    const cs = getComputedStyle(cv);
    const t = (nombre, respaldo) => {
        const v = cs.getPropertyValue(nombre).trim();
        return v || respaldo;
    };
    return {
        // El muñeco. `S` y `Z` son sus dos neutros; `B` y `P` los dos colores de marca.
        C: t('--zone-1-dark', '#084C76'),
        S: t('--paper-bg', '#F4F4F1'),
        B: t('--zone-1', '#1AA9DE'),
        P: t('--zone-2', '#A3C21C'),
        Z: t('--paper-fg', '#101418'),
        // El escenario.
        cielo: t('--bg-card', '#1A1F25'),
        lejos: t('--bg-soft', '#22282E'),
        piedra: '#3A342B',
        piedraAlta: '#4A4234',
        aviso: t('--attn', '#F5C400'),
        tinta: t('--paper-fg', '#101418'),
        polvo: t('--fg-mute', '#626A72'),
        chispa: t('--paper-bg', '#F4F4F1'),
    };
}

/**
 * Monta el juego sobre un `<canvas>`.
 *
 * @param {HTMLCanvasElement} cv
 * @param {{alto:() => number, marcador:(m:number,p:number,r:number)=>void, fin:(m:number,p:number,record:boolean)=>void, reduce:boolean}} opciones
 */
export function montaSalta(cv, opciones) {
    const O = opciones;
    const PAL = leePaleta(cv);
    const CLAVE_RECORD = 'pjp-salta-record';

    let j = null;            // el estado de la partida
    let k = 1;               // escala: todo se mide contra un alto de 300 px
    let w = 0, h = 0, x0 = 0;
    let raf = null, prev = 0, acum = 0;
    let anchoCv = 0, recalc = true;
    let visible = false, jugando = false, quieto = false;
    /**
     * ⚠️⚠️ **El ancho del lienzo está CACHEADO, y desde `#256` puede cambiar mientras se pinta.**
     * Antes el motor se cargaba con la tarjeta ya a pantalla completa (`q > 0,985`), así que medía
     * el ancho definitivo y ahí se quedaba. Ahora arranca en el punto ESTÁTICO —la tarjeta mide su
     * columna, 1176 px— y luego crece hasta el ancho de la ventana: sin volver a medir, el búfer
     * se queda en 1176 y el navegador lo estira. A 1920 son **62 % de más**.
     * ▶ Se resuelve con un observador de tamaño y NO leyendo `clientWidth` en cada fotograma: esa
     * lectura fuerza el cálculo de estilo dentro del bucle, que es justo lo que la caché evitaba.
     * ▶ Sin `ResizeObserver` se vuelve a medir en cada fotograma (`mide()`): más caro, pero
     * correcto — un dibujo estirado se ve, y un `clientWidth` de más no.
     * ⚠️ Se declara **aquí, con el resto del estado**, y no junto a `observador.observe()`: `mide()`
     * lo lee, y una `const` declarada más abajo que su lector es la clase de trampa que este repo
     * ya ha pagado (`auth-en-cajon.md` §8.ter).
     */
    const observador = typeof ResizeObserver === 'function' ? new ResizeObserver(() => { recalc = true; }) : null;
    let pulsado = false, pintado = -1;
    let record = 0;
    try { record = parseInt(window.localStorage.getItem(CLAVE_RECORD) || '0', 10) || 0; } catch { record = 0; }

    // ── el mundo ─────────────────────────────────────────────────────────────────────────────
    function reinicia(demo) {
        j = {
            demo: !!demo, cam: 0, pie: 0, vy: 0, vel: FISICA.vel0,
            suelo: true, coyote: 9, buffer: 0, hold: false, holdT: 0,
            sq: 0, paso: 0, giro: 0, muerto: false, finT: 0, rebote: false,
            metros: 0, pulseras: 0, shake: 0, camaT: 0, camaX: -1e9,
            plats: [], items: [], part: [], notas: [], xFin: 0, ultAlt: 0, trasCama: 0,
        };
        pintado = -1; quieto = false;
    }

    const limAlt = (a) => Math.max(FISICA.altMin * k, Math.min(FISICA.altMax * k, a));
    const platEn = (x) => {
        for (let i = j.plats.length - 1; i >= 0; i--) if (x >= j.plats[i].x0 && x <= j.plats[i].x1) return j.plats[i];
        return null;
    };
    const platTras = (x) => {
        for (let i = 0; i < j.plats.length; i++) if (j.plats[i].x0 > x) return j.plats[i];
        return null;
    };

    /**
     * ⚠️ **Cada hueco y cada escalón se derivan de la física**, no de rangos inventados: `alcance`
     * es lo que vuela el muñeco a la velocidad de AHORA y `apex` lo que sube. Por eso el terreno
     * endurece con los metros (`d`) sin volverse nunca imposible.
     */
    function genera() {
        const C = FISICA;
        if (!j.plats.length) {
            const alt = 58 * k;
            j.plats.push({ x0: -260 * k, x1: x0 + 520 * k, alt, t: 'muro' });
            j.xFin = j.plats[0].x1; j.ultAlt = alt; j.pie = h - alt;
        }
        const limite = j.cam + w + 340 * k;
        let g = 0;
        while (j.xFin < limite && g++ < 14) {
            const d = Math.min(1, j.metros / 560);
            const v = j.vel * k;
            const alcance = v * (2 * C.imp / C.grav);
            const apex = C.imp * C.imp / (2 * C.grav) * k;
            const prevAlt = j.ultAlt;
            let hueco, an, alt, tipo = null;

            if (j.trasCama) {
                // Tras una cama elástica el salto es mucho más largo: el tramo siguiente se
                // dimensiona con el rebote, no con el impulso normal.
                j.trasCama = 0;
                hueco = 0;
                an = 1.15 * (v * (2 * C.reb / C.grav));
                alt = limAlt(prevAlt + (Math.random() * 2 - 0.9) * apex * 0.40);
            } else {
                const cama = prevAlt < 104 * k && Math.random() < 0.19 + 0.1 * d;
                const seguido = !cama && Math.random() < 0.15;
                hueco = seguido ? 0 : (0.22 + 0.50 * d + Math.random() * 0.10) * alcance;
                const subeMax = (0.40 + 0.45 * d) * apex;
                let delta = (Math.random() * 2 - 1) * apex * (0.34 + 0.55 * d);
                if (seguido) delta = -Math.abs(delta);
                delta = delta > 0 ? Math.min(delta, subeMax) : Math.max(delta, -0.55 * apex);
                alt = limAlt(prevAlt + delta);
                // Subir cuesta altura, así que el hueco se acorta: subir y saltar largo a la vez
                // sería el único caso injusto de toda la generación.
                if (delta > 0) hueco *= 1 - 0.45 * (delta / subeMax);
                an = Math.max(96 * k, 0.76 * v - hueco) + Math.random() * 110 * k;
                if (cama) { tipo = 'cama'; an = Math.max(an, 124 * k); alt = Math.min(alt, 92 * k); j.trasCama = 1; }
            }

            if (!tipo) tipo = alt > 108 * k ? 'torreon' : (alt > 84 * k ? 'torre' : 'muro');
            const px0 = j.xFin + hueco;
            j.plats.push({ x0: px0, x1: px0 + an, alt, t: tipo });
            j.xFin = px0 + an; j.ultAlt = alt;

            // Las pulseras premian el riesgo: sobre el hueco, o a media plataforma larga.
            if (hueco > 70 * k && Math.random() < 0.82) {
                j.items.push({ x: px0 - hueco / 2, alt: Math.max(prevAlt, alt) + (104 + Math.random() * 26) * k });
            } else if (an > 250 * k && tipo !== 'cama' && Math.random() < 0.55) {
                j.items.push({ x: px0 + an * 0.55, alt: alt + 34 * k });
            }
            if (j.plats.length > 24) j.plats.splice(0, j.plats.length - 24);
            if (j.items.length > 20) j.items.splice(0, j.items.length - 20);
        }
    }

    // ── efectos ──────────────────────────────────────────────────────────────────────────────
    function chispas(sx, y, col, n, disp) {
        for (let i = 0; i < n; i++) {
            j.part.push({
                x: j.cam + sx, y, vx: (Math.random() * 2 - 1) * (disp || 120) * k,
                vy: -(40 + Math.random() * 190) * k, t: 0, vida: 0.3 + Math.random() * 0.3,
                c: col, s: Math.max(1, Math.round((1.4 + Math.random() * 2) * k)),
            });
        }
        if (j.part.length > 70) j.part.splice(0, j.part.length - 70);
    }
    function nota(sx, y, txt) {
        j.notas.push({ x: j.cam + sx, y, t: 0, txt });
        if (j.notas.length > 5) j.notas.shift();
    }

    function guardaRecord() {
        if (!j || j.demo) return false;
        const previo = record;
        if (j.metros > previo) {
            record = j.metros;
            try { window.localStorage.setItem(CLAVE_RECORD, String(record)); } catch { /* modo privado */ }
        }
        return j.metros > previo && previo > 0;
    }

    function mata(motivo) {
        if (j.muerto) return;
        j.muerto = true; j.finT = 0; j.hold = false; j.shake = 7 * k;
        if (motivo === 'choque') {
            j.vy = -270 * k;
            chispas(x0, j.pie - 18 * k, PAL.piedraAlta, 12, 160);
        }
    }

    function salta() {
        if (!j || j.muerto) return;
        j.hold = true;
        if (j.suelo || j.coyote < 0.11) {
            j.vy = -FISICA.imp * k;
            j.suelo = false; j.coyote = 9; j.sq = 0.1; j.rebote = false;
            chispas(x0 - 5 * k, j.pie, PAL.polvo, 5, 70);
        } else {
            // ⚠️ **Buffer de salto**: pulsar un poco ANTES de tocar suelo cuenta igual. Sin esto,
            // un juego de un solo botón se siente injusto sin que el jugador sepa por qué.
            j.buffer = 0.15;
        }
    }
    function suelta() {
        pulsado = false;
        if (!j) return;
        j.hold = false;
        // El salto es de altura VARIABLE: soltar pronto recorta la subida.
        const c = -FISICA.imp * FISICA.corte * k;
        if (j.vy < c) j.vy = c;
    }

    /** Piloto automático del modo demo: salta al borde, con margen si el siguiente escalón sube. */
    function ia(dt) {
        if (j.holdT > 0) {
            j.holdT -= dt;
            if (j.holdT <= 0) { j.hold = false; const c = -FISICA.imp * FISICA.corte * k; if (j.vy < c) j.vy = c; }
        }
        if (!(j.suelo || j.coyote < 0.1)) return;
        const px = j.cam + x0, v = j.vel * k;
        const p = platEn(px), sig = platTras(px);
        if (!p || !sig) return;
        if ((p.x1 - px) / v < (sig.alt > p.alt ? 0.24 : 0.17)) { salta(); j.hold = true; j.holdT = 0.42; }
    }

    // ── la física ────────────────────────────────────────────────────────────────────────────
    function paso(dt) {
        const C = FISICA;
        if (!j.muerto) {
            j.vel = Math.min(C.velMax, j.vel + C.acel * dt * (j.demo ? 0.4 : 1));
            j.cam += j.vel * k * dt;
            genera();
            if (j.demo) ia(dt);
        }
        const px = j.cam + x0;
        // Mantener pulsado FLOTA: la gravedad pesa un tercio menos mientras subes.
        const flota = j.hold && j.vy < 0 && !j.rebote ? 0.66 : 1;
        j.vy += C.grav * k * flota * dt;
        const prevPie = j.pie;
        j.pie += j.vy * dt;

        if (!j.muerto) {
            const p = platEn(px);
            const top = p ? h - p.alt : 0;
            // ⚠️ El margen crece con la velocidad: a 500 px/s un fotograma son 8 px, y sin esto el
            // muñeco atravesaría el canto de la plataforma en vez de posarse.
            const margen = Math.max(C.tol * k, Math.abs(j.vy) * dt + 2);
            if (p && j.pie >= top && prevPie <= top + margen) {
                j.pie = top;
                if (p.t === 'cama') {
                    const sup = (j.buffer > 0 || pulsado) && !j.demo ? 1.2 : 1;
                    j.vy = -C.reb * k * sup;
                    j.camaT = 0.18; j.camaX = p.x0; j.buffer = 0; j.rebote = true;
                    j.suelo = false; j.coyote = 9; j.sq = 0.14; j.shake = (sup > 1 ? 7 : 4) * k;
                    chispas(x0, top, sup > 1 ? PAL.aviso : PAL.P, sup > 1 ? 14 : 9, 170);
                    if (sup > 1) nota(x0, top - 34 * k, 'BOING');
                } else if (j.buffer > 0) {
                    j.vy = -C.imp * k; j.buffer = 0; j.rebote = false;
                    j.suelo = false; j.coyote = 9; j.sq = 0.1; j.hold = pulsado;
                } else {
                    if (j.vy > 300 * k) { chispas(x0 - 4 * k, top, PAL.polvo, 6, 90); j.sq = 0.1; }
                    j.vy = 0; j.suelo = true; j.coyote = 0; j.rebote = false;
                }
            } else if (p && j.pie > top + margen) {
                mata('choque');
            } else {
                j.suelo = false; j.coyote += dt;
            }
            if (j.pie < C.techo * k) { j.pie = C.techo * k; if (j.vy < 0) j.vy = 0; }
            if (j.pie > h + 60 * k) mata('hueco');
            j.buffer = Math.max(0, j.buffer - dt);
            if (j.suelo) j.paso += j.vel * dt / 17;
            j.metros = Math.floor(j.cam / (30 * k));
        } else {
            j.finT += dt; j.giro += dt * 5.5;
            j.vel *= Math.pow(0.05, dt);
            j.cam += j.vel * k * dt;
            if (j.finT > 0.55 && jugando) {
                jugando = false;
                const nuevo = guardaRecord();
                O.fin(j.metros, j.pulseras, nuevo);
            }
            if (j.demo && j.finT > 0.9) { reinicia(true); genera(); return; }
        }

        // pulseras
        for (let i = j.items.length - 1; i >= 0; i--) {
            const it = j.items[i];
            if (it.x < j.cam - 80) { j.items.splice(i, 1); continue; }
            if (it.got != null) { it.got += dt; if (it.got > 0.25) j.items.splice(i, 1); continue; }
            if (j.muerto) continue;
            const dx = (it.x - px) / (36 * k), dy = ((h - it.alt) - (j.pie - 24 * k)) / (66 * k);
            if (dx * dx + dy * dy < 1) {
                it.got = 0; j.pulseras++;
                chispas(x0 + dx, h - it.alt, PAL.aviso, 8, 140);
                nota(x0 + dx, h - it.alt - 8 * k, '+1');
            }
        }
        for (let i = j.part.length - 1; i >= 0; i--) {
            const q = j.part[i];
            q.t += dt;
            if (q.t > q.vida) { j.part.splice(i, 1); continue; }
            q.x += q.vx * dt; q.y += q.vy * dt; q.vy += 900 * k * dt;
        }
        for (let i = j.notas.length - 1; i >= 0; i--) { j.notas[i].t += dt; if (j.notas[i].t > 0.7) j.notas.splice(i, 1); }
        j.camaT = Math.max(0, j.camaT - dt);
        j.sq = Math.max(0, j.sq - dt);
        j.shake *= Math.pow(0.02, dt);

        // ⚠️ El marcador se escribe solo cuando CAMBIA. Tocar el DOM 60 veces por segundo para
        // escribir el mismo número es la forma más fácil de que un juego de 400 líneas vaya a tirones.
        const sello = j.metros * 1000 + j.pulseras;
        if (!j.demo && pintado !== sello) {
            pintado = sello;
            O.marcador(j.metros, j.pulseras, Math.max(record, j.metros));
        }
    }

    // ── el dibujo ────────────────────────────────────────────────────────────────────────────
    function prepara() {
        if (!cv) return null;
        const anc = anchoCv || cv.clientWidth;
        if (!anc) { recalc = true; return null; }
        const alto = O.alto();
        if (cv.style.height !== alto + 'px') cv.style.height = alto + 'px';
        const dpr = Math.min(2, window.devicePixelRatio || 1);
        const bw = Math.round(anc * dpr), bh = Math.round(alto * dpr);
        if (cv.width !== bw || cv.height !== bh) { cv.width = bw; cv.height = bh; }
        const ctx = cv.getContext('2d');
        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
        return { ctx, w: anc, h: alto };
    }

    function almena(ctx, x, y, ancho, pasoX, alto, grueso) {
        let cx = x + 6;
        while (cx + grueso <= x + ancho - 4) {
            ctx.fillRect(Math.round(cx), Math.round(y - alto), grueso, alto + 2);
            cx += pasoX;
        }
    }

    function muro(ctx, sx, an, top, alto, tipo) {
        ctx.fillStyle = PAL.piedra;
        ctx.fillRect(Math.round(sx), Math.round(top), Math.ceil(an) + 1, Math.ceil(alto - top) + 2);
        ctx.fillStyle = PAL.piedraAlta;
        ctx.fillRect(Math.round(sx), Math.round(top), Math.ceil(an) + 1, Math.max(1, Math.round(3 * k)));
        ctx.fillStyle = PAL.piedra;
        almena(ctx, sx, top, an, 26 * k, 12 * k, 13 * k);
        ctx.fillStyle = PAL.tinta;
        if (tipo === 'torreon') {
            ctx.fillRect(Math.round(sx + an / 2 - 15 * k), Math.round(top + 42 * k), Math.round(9 * k), Math.round(21 * k));
            ctx.fillRect(Math.round(sx + an / 2 + 6 * k), Math.round(top + 42 * k), Math.round(9 * k), Math.round(21 * k));
            ctx.fillStyle = 'color-mix(in srgb, ' + PAL.aviso + ' 40%, transparent)';
            ctx.fillRect(Math.round(sx + an / 2 - 5 * k), Math.round(top + 92 * k), Math.round(10 * k), Math.round(24 * k));
        } else if (tipo === 'torre') {
            ctx.fillRect(Math.round(sx + an / 2 - 4 * k), Math.round(top + 34 * k), Math.round(8 * k), Math.round(20 * k));
        }
    }

    function cama(ctx, sx, an, top, alto, sag) {
        const hCama = 30 * k, yMuro = top + hCama;
        ctx.fillStyle = PAL.piedra;
        ctx.fillRect(Math.round(sx), Math.round(yMuro), Math.ceil(an) + 1, Math.ceil(alto - yMuro) + 2);
        ctx.fillStyle = PAL.piedraAlta;
        ctx.fillRect(Math.round(sx), Math.round(yMuro), Math.ceil(an) + 1, Math.max(1, Math.round(3 * k)));
        const margen = 15 * k, izq = sx + margen, der = sx + an - margen;
        const hund = (sag / 0.18) * 13 * k;
        const yL = top + hund, gr = 6 * k, pata = Math.max(3, 5 * k);
        ctx.fillStyle = PAL.piedra;
        [0, 0.34, 0.66, 1].forEach((fr) => {
            const x = izq + (der - izq - pata) * fr;
            ctx.fillRect(Math.round(x), Math.round(yL + gr), Math.round(pata), Math.round(yMuro - yL - gr + 2));
        });
        ctx.fillStyle = PAL.B;
        ctx.beginPath();
        ctx.moveTo(izq, yL);
        ctx.quadraticCurveTo((izq + der) / 2, yL + hund * 1.5, der, yL);
        ctx.lineTo(der, yL + gr);
        ctx.quadraticCurveTo((izq + der) / 2, yL + gr + hund * 1.5, izq, yL + gr);
        ctx.closePath();
        ctx.fill();
        ctx.fillStyle = PAL.P;
        ctx.fillRect(Math.round(izq - 5 * k), Math.round(yL - 1), Math.round(9 * k), Math.round(gr + 2));
        ctx.fillRect(Math.round(der - 4 * k), Math.round(yL - 1), Math.round(9 * k), Math.round(gr + 2));
    }

    function muneco(ctx, cx, pie, pose, esc, squash, giro) {
        const cols = pose[0].length;
        const an = cols * esc, al = pose.length * esc;
        ctx.save();
        ctx.translate(cx, pie);
        if (giro) ctx.rotate(giro);
        ctx.scale(1 / squash, squash);
        const px0 = Math.round(-an / 2), py0 = Math.round(-al);
        for (let r = 0; r < pose.length; r++) {
            for (let c = 0; c < cols; c++) {
                const ch = pose[r][c];
                if (ch === '.') continue;
                ctx.fillStyle = PAL[ch];
                ctx.fillRect(px0 + c * esc, py0 + r * esc, esc, esc);
            }
        }
        ctx.restore();
    }

    function pinta() {
        const base = prepara();
        if (!base) return;
        const ctx = base.ctx, W = base.w, H = base.h;
        ctx.clearRect(0, 0, W, H);
        ctx.save();
        if (j.shake > 0.3) ctx.translate((Math.random() * 2 - 1) * j.shake, (Math.random() * 2 - 1) * j.shake * 0.6);

        // Dos capas de fondo a distinta velocidad: colinas y muralla lejana.
        const pas0 = 320 * k, o0 = -((j.cam * 0.26) % pas0);
        ctx.fillStyle = PAL.cielo;
        for (let i = -1; i * pas0 + o0 < W + pas0; i++) {
            ctx.beginPath();
            ctx.ellipse(i * pas0 + o0 + pas0 / 2, H + 24 * k, 240 * k, 96 * k, 0, Math.PI, 0);
            ctx.fill();
        }
        const pas1 = 190 * k, o1 = -((j.cam * 0.5) % pas1);
        for (let i = -1; i * pas1 + o1 < W + pas1; i++) {
            const x = i * pas1 + o1, m = ((i % 3) + 3) % 3, al = (52 + m * 30) * k;
            ctx.fillStyle = PAL.lejos;
            ctx.fillRect(Math.round(x), Math.round(H - al), Math.round(132 * k), Math.ceil(al));
            almena(ctx, x, H - al, 132 * k, 22 * k, 9 * k, 10 * k);
        }

        for (let i = 0; i < j.plats.length; i++) {
            const p = j.plats[i], sx = p.x0 - j.cam, an = p.x1 - p.x0;
            if (sx > W + 6 || sx + an < -6) continue;
            if (p.t === 'cama') cama(ctx, sx, an, H - p.alt, H, j.camaX === p.x0 ? j.camaT : 0);
            else muro(ctx, sx, an, H - p.alt, H, p.t);
        }

        ctx.lineWidth = Math.max(1.5, 3 * k);
        for (let i = 0; i < j.items.length; i++) {
            const it = j.items[i], sx = it.x - j.cam, y = H - it.alt;
            if (sx < -24 || sx > W + 24) continue;
            if (it.got != null) {
                ctx.globalAlpha = Math.max(0, 1 - it.got / 0.25);
                ctx.strokeStyle = PAL.chispa;
                ctx.beginPath(); ctx.arc(sx, y - it.got * 40 * k, (9 + it.got * 58) * k, 0, 6.2832); ctx.stroke();
                ctx.globalAlpha = 1;
            } else {
                ctx.strokeStyle = PAL.aviso;
                ctx.beginPath();
                ctx.arc(sx, y + Math.sin(j.cam * 0.02 + i * 1.7) * 3 * k, 9 * k, 0, 6.2832);
                ctx.stroke();
            }
        }

        const esc = Math.max(1.4, 2.2 * (H / 220));
        const pj = platEn(j.cam + x0);
        if (pj && !j.muerto) {
            // La sombra se encoge y se aclara con la altura: es lo único que dice a qué distancia
            // del suelo estás cuando el fondo no da referencia.
            const top = H - pj.alt, d = Math.max(0, Math.min(1, (top - j.pie) / (150 * k)));
            ctx.globalAlpha = 0.32 * (1 - d * 0.8);
            ctx.fillStyle = PAL.tinta;
            ctx.beginPath();
            ctx.ellipse(x0, top + 2 * k, (8 - 2.6 * d) * esc, 3.2 * k, 0, 0, 6.2832);
            ctx.fill();
            ctx.globalAlpha = 1;
        }
        const pose = j.muerto ? POSES.salto
            : (j.suelo ? (Math.floor(j.paso) % 2 ? POSES.corre : POSES.caida)
                : (j.vy < 0 ? POSES.salto : POSES.caida));
        let squash = j.sq > 0 ? 1 - 0.18 * (j.sq / 0.14) : (j.vy < -260 * k ? 1.05 : 1);
        squash = Math.max(0.72, Math.min(1.18, squash));
        if (j.muerto) ctx.globalAlpha = 0.78;
        muneco(ctx, x0, j.pie, pose, esc, squash, j.muerto ? j.giro : 0);
        ctx.globalAlpha = 1;

        for (let i = 0; i < j.part.length; i++) {
            const q = j.part[i];
            ctx.globalAlpha = Math.max(0, 1 - q.t / q.vida);
            ctx.fillStyle = q.c;
            ctx.fillRect(Math.round(q.x - j.cam), Math.round(q.y), q.s, q.s);
        }
        ctx.globalAlpha = 1;

        if (j.notas.length) {
            ctx.font = '700 ' + Math.round(20 * k) + 'px "JetBrains Mono", monospace';
            ctx.textAlign = 'center';
            for (let i = 0; i < j.notas.length; i++) {
                const n = j.notas[i];
                ctx.globalAlpha = Math.max(0, 1 - n.t / 0.7);
                ctx.fillStyle = PAL.aviso;
                ctx.fillText(n.txt, n.x - j.cam, n.y - n.t * 46 * k);
            }
            ctx.globalAlpha = 1;
            ctx.textAlign = 'left';
        }

        // Rayas de velocidad: solo jugando de verdad y solo por encima de 330 px/s.
        if (!j.demo && !j.muerto && j.vel > 330) {
            const it = Math.min(1, (j.vel - 330) / 98);
            ctx.globalAlpha = 0.13 * it;
            ctx.fillStyle = PAL.chispa;
            for (let i = 0; i < 3; i++) {
                const y = Math.round(H * (0.16 + i * 0.15));
                const x = W - ((j.cam * 1.6 + i * 340 * k) % (W + 260 * k));
                ctx.fillRect(Math.round(x), y, Math.round(70 * k * it), Math.max(1, Math.round(k)));
            }
            ctx.globalAlpha = 1;
        }
        ctx.restore();
    }

    // ── el bucle ─────────────────────────────────────────────────────────────────────────────
    function mide() {
        if (!cv) return;
        if (recalc || !anchoCv || !observador) { anchoCv = cv.clientWidth || 0; recalc = false; }
        const alto = O.alto();
        const nk = alto / 300;
        if (j && k && Math.abs(nk / k - 1) > 0.004) {
            // Reescalar en caliente: todo el estado está en píxeles, así que se multiplica.
            // ⚠️ **`pie` NO se multiplica: se recoloca.** Es una coordenada desde ARRIBA, y el
            // lienzo cambia de alto justo al empezar a jugar (150 → 358). Multiplicándola, el
            // muñeco aparecía a media pantalla en vez de sobre la almena. Lo que se conserva es su
            // altura SOBRE EL SUELO, que es lo que significa.
            const f = nk / k;
            const sobreSuelo = h - j.pie;
            j.cam *= f; j.vy *= f; j.xFin *= f; j.ultAlt *= f;
            j.plats.forEach((p) => { p.x0 *= f; p.x1 *= f; p.alt *= f; });
            j.items.forEach((i2) => { i2.x *= f; i2.alt *= f; });
            j.pie = alto - sobreSuelo * f;
            j.part.length = 0; j.notas.length = 0;
        }
        w = anchoCv; h = alto; k = nk;
        x0 = Math.round(w * FISICA.xJug);
    }

    function aseguraPartida(demo) {
        if (!j || !!j.demo !== !!demo) { reinicia(demo); genera(); }
    }

    function bucle(ts) {
        raf = null;
        if (!visible || document.hidden) return;
        const ahora = ts || performance.now();
        let dt = (ahora - (prev || ahora)) / 1000;
        prev = ahora;
        dt = Math.min(0.048, Math.max(0.001, dt));

        if (!jugando) {
            // ⚠️ **Con movimiento reducido el fondo NO se mueve**: se pinta UN fotograma quieto y
            // el bucle se para. El juego sigue siendo jugable —eso es una acción del usuario, no
            // una animación que le imponemos— pero nadie ve un muñeco corriendo sin haberlo pedido.
            if (O.reduce) {
                if (!quieto) { mide(); aseguraPartida(true); pinta(); quieto = true; }
                return;
            }
            // En demo basta con ~30 fps: es fondo, no juego.
            acum += dt;
            if (acum < 0.0333) { raf = requestAnimationFrame(bucle); return; }
            dt = Math.min(0.05, acum); acum = 0;
        }
        mide();
        aseguraPartida(!jugando);
        paso(dt);
        pinta();
        raf = requestAnimationFrame(bucle);
    }

    function despierta() {
        if (raf != null) return;
        if (!visible || document.hidden) return;
        prev = 0;
        raf = requestAnimationFrame(bucle);
    }
    function duerme() {
        if (raf != null) { cancelAnimationFrame(raf); raf = null; }
    }

    /**
     * ⚠️ **La visibilidad se mide con el rect propio, no con `IntersectionObserver`.** El lienzo
     * vive dentro de una tarjeta que se pega y CRECE con el scroll; un observador sobre un elemento
     * que cambia de tamaño y de posición da entradas y salidas espurias. Un `getBoundingClientRect`
     * en el mismo bucle que ya corre es más barato y no miente.
     */
    function vigila() {
        if (!cv) { if (visible) { visible = false; duerme(); } return; }
        const r = cv.getBoundingClientRect();
        const v = r.width > 0 && r.bottom > -140 && r.top < (window.innerHeight || 0) + 140;
        if (v === visible) return;
        visible = v; recalc = true; quieto = false;
        if (v) despierta(); else duerme();
    }

    const onVis = () => { if (document.hidden) duerme(); else if (visible) despierta(); };
    document.addEventListener('visibilitychange', onVis);
    if (observador) observador.observe(cv);

    return {
        get record() { return record; },
        get jugando() { return jugando; },
        vigila,
        /** Empieza una partida de verdad (sale del modo demo). */
        arranca() {
            reinicia(false);
            recalc = true; jugando = true; pulsado = true;
            vigila(); despierta();
        },
        /** Un toque: saltar si se juega. */
        pulsa() { pulsado = true; if (jugando) salta(); },
        suelta,
        /** Sale de la partida y guarda el récord. */
        para() { guardaRecord(); jugando = false; quieto = false; },
        destruye() {
            duerme();
            document.removeEventListener('visibilitychange', onVis);
            observador?.disconnect();
            j = null;
        },
    };
}
