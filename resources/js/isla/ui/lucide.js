/**
 * Las sustituciones del `Icon` del diseño sobre un SVG de Lucide (`Icon.jsx`), en su orden y SOLO en la primera
 * aparición, como `String.prototype.replace` con una cadena. Es la gemela de `Lucide::transform()` del lado
 * PHP (`DECISIONES #686`): las dos pintan el mismo icono, la una en Blade y la otra en Vue.
 */
function primera(buscar, poner, texto) {
    const i = texto.indexOf(buscar);
    return i === -1 ? texto : texto.slice(0, i) + poner + texto.slice(i + buscar.length);
}

export function transformar(svg, relleno = false) {
    let s = primera('width="24"', 'width="100%"', svg);
    s = primera('height="24"', 'height="100%"', s);
    return relleno ? primera('fill="none"', 'fill="currentColor"', s) : s;
}
