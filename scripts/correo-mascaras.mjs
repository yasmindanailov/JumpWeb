// LAS MÁSCARAS DE LOS ICONOS DE CORREO (`specs/correos-rediseno.md` §4.1.3, la R1b): cada icono de Lucide que pinta un
// correo, rasterizado UNA vez a una máscara PNG de PALETA —256 entradas del mismo color, cada una con su alfa en `tRNS`, y el
// índice de cada píxel = su alfa—, para que el producto la TIÑA reescribiendo solo su `PLTE` (`MailIcons`), sin GD ni
// Imagick en producción.
//
//   docker compose exec -u sail -T laravel.test node scripts/correo-mascaras.mjs map-pin clock phone message-circle mail
//
// Lee el SVG de `resources/icons/lucide/icons/` (el paquete fijado por versión e integridad, `#686`), lo pinta con el
// Chromium del contenedor en un lienzo de LADO px (3× de 24: nítido en pantallas densas) y escribe
// `resources/correo/iconos/<nombre>.png` y su `MANIFIESTO.json` (versión, lado, bytes y sha256 de cada máscara).
// ⚠️ Los nombres que se le pasan SON el set: el manifiesto se reescribe con ellos (un icono que no se pasa, sale).
// ⚠️ `npm install` poda `playwright-core` (va sin guardar): `npm i --no-save playwright-core@1.49.0` si falta.
import { chromium } from 'playwright-core';
import { createHash } from 'node:crypto';
import { mkdir, readFile, writeFile, readdir, unlink } from 'node:fs/promises';
import { deflateSync } from 'node:zlib';

const LADO = 72;
const ORIGEN = 'resources/icons/lucide';
const DESTINO = 'resources/correo/iconos';
const nombres = [...new Set(process.argv.slice(2))].sort();

if (nombres.length === 0 || nombres.some((n) => !/^[a-z0-9]+(?:-[a-z0-9]+)*$/.test(n))) {
    console.error('uso: node scripts/correo-mascaras.mjs <icono> [<icono>…]   (nombres de Lucide en kebab-case)');
    process.exit(1);
}

const lucide = JSON.parse(await readFile(`${ORIGEN}/MANIFIESTO.json`, 'utf8'));

// ── Un PNG de paleta, escrito a mano (Node trae zlib; el CRC, su tabla) ───────────────────────────────────────────────
const tabla = new Int32Array(256).map((_, n) => {
    let c = n;
    for (let k = 0; k < 8; k++) c = c & 1 ? 0xedb88320 ^ (c >>> 1) : c >>> 1;
    return c;
});
const crc = (buf) => {
    let c = -1;
    for (const b of buf) c = tabla[(c ^ b) & 0xff] ^ (c >>> 8);
    return (c ^ -1) >>> 0;
};
const trozo = (tipo, datos) => {
    const t = Buffer.from(tipo, 'ascii');
    const largo = Buffer.alloc(4);
    largo.writeUInt32BE(datos.length);
    const suma = Buffer.alloc(4);
    suma.writeUInt32BE(crc(Buffer.concat([t, datos])));
    return Buffer.concat([largo, t, datos, suma]);
};
const pngDePaleta = (alfa) => {
    const ihdr = Buffer.alloc(13);
    ihdr.writeUInt32BE(LADO, 0);
    ihdr.writeUInt32BE(LADO, 4);
    ihdr.set([8, 3, 0, 0, 0], 8); // 8 bits · color de paleta · deflate · sin filtro adaptativo · sin entrelazado
    const filas = [];
    for (let y = 0; y < LADO; y++) filas.push(Buffer.from([0, ...alfa.slice(y * LADO, (y + 1) * LADO)]));

    return Buffer.concat([
        Buffer.from([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a]),
        trozo('IHDR', ihdr),
        trozo('PLTE', Buffer.alloc(768, 0)), // negro: el producto lo reescribe con el color del rol
        trozo('tRNS', Buffer.from([...Array(256).keys()])),
        trozo('IDAT', deflateSync(Buffer.concat(filas), { level: 9 })),
        trozo('IEND', Buffer.alloc(0)),
    ]);
};

// ── El alfa de cada icono, del Chromium ───────────────────────────────────────────────────────────────────────────────
await mkdir(DESTINO, { recursive: true });
const navegador = await chromium.launch();
const page = await navegador.newPage();
const iconos = {};

for (const nombre of nombres) {
    const svg = (await readFile(`${ORIGEN}/icons/${nombre}.svg`, 'utf8')).replace('stroke="currentColor"', 'stroke="#000"');
    const alfa = await page.evaluate(async ({ svg, lado }) => {
        const img = new Image();
        img.src = 'data:image/svg+xml;base64,' + btoa(svg);
        await img.decode();
        const lienzo = document.createElement('canvas');
        lienzo.width = lado;
        lienzo.height = lado;
        const g = lienzo.getContext('2d');
        g.drawImage(img, 0, 0, lado, lado);
        const d = g.getImageData(0, 0, lado, lado).data;
        const a = [];
        for (let i = 3; i < d.length; i += 4) a.push(d[i]);
        return a;
    }, { svg, lado: LADO });

    if (!alfa.some((a) => a > 0)) {
        console.error(`✗ ${nombre}: el lienzo sale vacío`);
        process.exit(1);
    }
    const png = pngDePaleta(alfa);
    await writeFile(`${DESTINO}/${nombre}.png`, png);
    iconos[nombre] = { bytes: png.length, sha256: createHash('sha256').update(png).digest('hex') };
    console.log(`✓ ${nombre}: ${png.length} B · ${alfa.filter((a) => a > 0).length} píxeles con tinta`);
}
await navegador.close();

// Fuera las máscaras que ya no están en el set.
for (const f of await readdir(DESTINO)) {
    if (f.endsWith('.png') && !nombres.includes(f.slice(0, -4))) {
        await unlink(`${DESTINO}/${f}`);
        console.log(`− ${f.slice(0, -4)}: fuera del set`);
    }
}

// La versión: cambia si cambia CUALQUIER máscara (rompe las cachés de los correos, `MailIcons::url()`).
const version = createHash('sha256').update(JSON.stringify(iconos)).digest('hex').slice(0, 12);
await writeFile(`${DESTINO}/MANIFIESTO.json`, JSON.stringify({
    origen: `${lucide.paquete}@${lucide.version}`,
    licencia: lucide.licencia,
    lado: LADO,
    version,
    iconos,
}, null, 2) + '\n');
console.log(`manifiesto: ${nombres.length} iconos · versión ${version}`);
