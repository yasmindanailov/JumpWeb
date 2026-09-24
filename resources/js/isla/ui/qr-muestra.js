/**
 * El QR DE MUESTRA del sistema de diseño (`QrPass.jsx`, su `pjQrDraw`): un dibujo con aspecto de QR sacado del
 * código, con sus tres esquinas, su alineación y sus líneas de tiempo. NO se lee: el QR real de una persona es su
 * carné, que dibuja el servidor (`GET /me/card/png`, los mismos bytes que el correo). Existe para pintar el marco
 * de `PaseQr` sin carné (el banco de la T3b, una vista previa) exactamente como el diseño.
 *
 * `celdas()` es la parte pura (qué celdas se pintan) y `dibujar()` la pinta en un lienzo, en el mismo orden y con
 * los mismos redondeos que el diseño: un solo píxel de diferencia en `m` movería medio QR.
 */
const N = 25;
const ESQUINAS = [[0, 0], [0, N - 7], [N - 7, 0]];

/** Las celdas negras del dibujo, en el orden en que el diseño las pinta. */
export function celdas(codigo) {
    const fuera = [];
    let h = 2166136261;
    for (let i = 0; i < codigo.length; i += 1) { h ^= codigo.charCodeAt(i); h = Math.imul(h, 16777619); }
    const azar = () => {
        h += 0x6d2b79f5;
        let t = h;
        t = Math.imul(t ^ (t >>> 15), t | 1);
        t ^= t + Math.imul(t ^ (t >>> 7), t | 61);
        return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
    };
    const esquina = (r, c) => ESQUINAS.some(([fr, fc]) => r >= fr - 1 && r <= fr + 7 && c >= fc - 1 && c <= fc + 7);
    ESQUINAS.forEach(([fr, fc]) => {
        for (let r = 0; r < 7; r += 1) {
            for (let c = 0; c < 7; c += 1) {
                if (r === 0 || r === 6 || c === 0 || c === 6 || (r >= 2 && r <= 4 && c >= 2 && c <= 4)) fuera.push([fr + r, fc + c]);
            }
        }
    });
    const al = N - 9;
    for (let r = 0; r < 5; r += 1) {
        for (let c = 0; c < 5; c += 1) {
            if (r === 0 || r === 4 || c === 0 || c === 4 || (r === 2 && c === 2)) fuera.push([al + r, al + c]);
        }
    }
    for (let r = 0; r < N; r += 1) {
        for (let c = 0; c < N; c += 1) {
            if (esquina(r, c) || (r >= al && r < al + 5 && c >= al && c < al + 5)) continue;
            if (r === 6 || c === 6) { if ((r + c) % 2 === 0) fuera.push([r, c]); continue; }
            if (azar() < 0.5) fuera.push([r, c]);
        }
    }
    return fuera;
}

/** Pinta el dibujo en el lienzo, a `px` de lado y a la densidad de la pantalla, con `tinta` sobre blanco. */
export function dibujar(lienzo, codigo, px, tinta) {
    if (! lienzo) return;
    const densidad = (typeof window !== 'undefined' && window.devicePixelRatio) || 1;
    lienzo.width = Math.round(px * densidad);
    lienzo.height = Math.round(px * densidad);
    const ctx = lienzo.getContext('2d');
    if (! ctx) return;
    const m = lienzo.width / N;
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, lienzo.width, lienzo.height);
    ctx.fillStyle = tinta;
    for (const [r, c] of celdas(codigo)) ctx.fillRect(Math.round(c * m), Math.round(r * m), Math.ceil(m), Math.ceil(m));
}
