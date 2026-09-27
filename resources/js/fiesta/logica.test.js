import test from 'node:test';
import assert from 'node:assert/strict';

import { caducaEn, capitalizar, choice, clave, cuentas, cubrir, deLaFiesta, estadoFicha, euros, importe, limpiar, misRespuestas, soloEdad, sugerirCorreo, vistaInvitacion } from './logica.js';

// F9 (§4.15, el zip tercero `#780`): el correo mal escrito, como `forms/Field.jsx` del diseño.
test('el correo mal escrito se corrige: una letra, dos cambiadas, la terminación que no existe, el punto que falta', () => {
    assert.equal(sugerirCorreo('ana@gmial.com'), 'ana@gmail.com', 'dos letras cambiadas');
    assert.equal(sugerirCorreo('ana@gmail.con'), 'ana@gmail.com', 'la terminación que no existe');
    assert.equal(sugerirCorreo('ana@gmail.es'), 'ana@gmail.com', 'gmail solo tiene .com');
    assert.equal(sugerirCorreo('ana@hotmial.es'), 'ana@hotmail.es', 'conserva la terminación que el proveedor tiene');
    assert.equal(sugerirCorreo('ana@outlok.es'), 'ana@outlook.es');
    assert.equal(sugerirCorreo('ana@gmailcom'), 'ana@gmail.com', 'el punto que falta');
    assert.equal(sugerirCorreo('  Ana.Pérez@GMIAL.com '), 'Ana.Pérez@gmail.com', 'la parte local, tal cual');
});

test('lo que no se toca: bien escrito, reales parecidos a otros, otra terminación real y lo que no es un correo', () => {
    // «lve» y «ona» están a una letra de «live» y «ono», pero los de cuatro letras o menos no se corrigen nunca (el arnés).
    for (const bien of ['ana@gmail.com', 'ana@hotmail.es', 'ana@ya.com', 'ana@me.com', 'ana@mail.com', 'ana@yahoo.es', 'ana@orange.fr', 'ana@empresa.com', 'ana@gmx.es', 'ana@lve.com', 'ana@ona.com']) {
        assert.equal(sugerirCorreo(bien), null, bien);
    }
    for (const nada of ['', 'ana', '@gmail.com', 'ana@', 'ana @gmial.com', null, undefined]) {
        assert.equal(sugerirCorreo(nada), null, String(nada));
    }
});

// F6b (§4.12): «Tus respuestas», las de este móvil.
const AHORA = 1_790_000_000_000;
const R = (o) => Object.assign({ fiesta: '7', id: 1, nombre: 'Hugo', url: 'https://x/invitacion/recibo/1?expires=1790086400&signature=a', hasta: AHORA + 3_600_000 }, o);

test('tus respuestas: lo caducado y lo que no tiene forma se tira; la nueva se guarda una sola vez', () => {
    const lista = [R(), R({ id: 2, nombre: 'Lía', hasta: AHORA - 1 }), { basura: true }, null, R({ id: 3, nombre: 'Leo', fiesta: '' })];
    assert.deepEqual(misRespuestas(lista, AHORA).map((r) => r.nombre), ['Hugo'], 'caducada, sin forma y sin fiesta, fuera');
    assert.deepEqual(misRespuestas('no es una lista', AHORA), []);
    // Volver al mismo recibo la RENUEVA en su sitio: ni la duplica ni la mueve (los chips no cambian de orden bajo el dedo).
    const otra = misRespuestas([R(), R({ id: 4, nombre: 'Lola' })], AHORA, R({ nombre: 'Hugo', hasta: AHORA + 7_200_000 }));
    assert.deepEqual(otra.map((r) => r.nombre), ['Hugo', 'Lola']);
    assert.equal(otra[0].hasta, AHORA + 7_200_000);
    // Y una nueva va al final.
    assert.deepEqual(misRespuestas([R()], AHORA, R({ id: 5, nombre: 'Leo' })).map((r) => r.nombre), ['Hugo', 'Leo']);
});

test('tus respuestas: solo las de esta fiesta, y caducan con la firma del enlace', () => {
    assert.deepEqual(deLaFiesta([R(), R({ fiesta: '8', id: 9 })], '7').map((r) => r.id), [1], 'otra fiesta en el mismo móvil no sale');
    assert.equal(caducaEn('https://x/invitacion/recibo/1?expires=1790086400&signature=a'), 1_790_086_400_000);
    assert.equal(caducaEn('https://x/invitacion/recibo/1?signature=a'), null, 'sin `expires` no se guarda');
    assert.equal(caducaEn('no es una url'), null);
});

// F5 (`#749`): los combos y los cubos del diseño (`datos.js → EXTRAS.padres`), en céntimos.
const COMBOS = [{ para: 6, precio: 3900, max: 5 }, { para: 10, precio: 5900, max: 5 }];

test('lo de los padres: la cuenta más barata que cubre a los adultos, como el diseño', () => {
    assert.deepEqual(cubrir(COMBOS, 8), { q: [0, 1], coste: 5900, uds: 1 }, 'para 8, un combo de 10 (59 €) y no dos de 6 (78 €)');
    assert.deepEqual(cubrir(COMBOS, 6), { q: [1, 0], coste: 3900, uds: 1 });
    assert.deepEqual(cubrir(COMBOS, 12), { q: [2, 0], coste: 7800, uds: 2 }, '78 € frente a 98 € (6+10) o 118 € (10+10)');
    // A igual precio, la de menos unidades.
    assert.deepEqual(cubrir([{ para: 5, precio: 1000 }, { para: 10, precio: 2000 }], 10), { q: [0, 1], coste: 2000, uds: 1 });
});

test('lo de los padres: el tope de cada complemento manda, y sin adultos no se propone nada', () => {
    assert.deepEqual(cubrir([{ para: 6, precio: 3900, max: 1 }, { para: 10, precio: 5900, max: 1 }], 14), { q: [1, 1], coste: 9800, uds: 2 });
    assert.equal(cubrir([{ para: 6, precio: 3900, max: 1 }], 14), null, 'ni con el tope se llega: no se inventa una cuenta');
    assert.equal(cubrir(COMBOS, 0), null);
    assert.equal(cubrir([], 8), null);
    assert.equal(cubrir([{ para: 0, precio: 100 }], 8), null, 'una variante sin «para cuántas» no entra en la cuenta');
});

test('la clave de un nombre ignora tildes, mayúsculas y espacios de más', () => {
    assert.equal(clave('  Álex   Romero '), 'alex romero');
    assert.equal(clave('MARÍA josé'), 'maria jose');
});

test('capitalizar solo toca un nombre escrito todo en minúsculas', () => {
    assert.equal(capitalizar('mateo gil'), 'Mateo Gil');
    assert.equal(capitalizar('Mateo  de la Fuente'), 'Mateo de la Fuente');
});

test('pegar una lista quita viñetas, numeraciones y teléfonos, y no repite', () => {
    const texto = '1. Hugo Martín 655 120 387\n- carla gómez\n• Leo\nHugo Martín\n\n3) Nora +34 600 11 22 33\nx';
    const r = limpiar(texto, ['Leo']);
    assert.deepEqual(r.nombres, ['Hugo Martín', 'Carla Gómez', 'Nora']);
    assert.equal(r.repetidos, 2, 'Leo ya estaba y Hugo se repite');
});

test('pegar una lista en una sola línea con comas parte por comas', () => {
    assert.deepEqual(limpiar('Lucía, Mateo; Hugo').nombres, ['Lucía', 'Mateo', 'Hugo']);
});

test('el estado de una ficha: completa, con datos y qué le falta', () => {
    const campos = (nombre, edad) => [
        { required: true, filled: nombre, label: 'Nombre' },
        { required: true, filled: edad, label: 'Edad' },
        { required: false, filled: false, label: 'Alergias' },
    ];
    assert.deepEqual(estadoFicha(campos(true, true)), { completa: true, conDatos: true, falta: null });
    assert.deepEqual(estadoFicha(campos(true, false)), { completa: false, conDatos: true, falta: 'Edad' });
    assert.deepEqual(estadoFicha(campos(false, false)), { completa: false, conDatos: false, falta: null });
    assert.equal(estadoFicha(campos(true, true), true).completa, false, 'una edad sin producto no está completa');
});

test('las cuentas de la zona 1 no cuentan las fichas vacías', () => {
    const c = cuentas([
        { vacia: false, respuesta: 'si' }, { vacia: false, respuesta: 'si' }, { vacia: false, respuesta: 'no' },
        { vacia: false, respuesta: null }, { vacia: true, respuesta: null },
    ]);
    assert.deepEqual(c, { confirmados: 2, noPueden: 1, sinContestar: 1, enLista: 4 });
});

test('choice resuelve el plural de Laravel con intervalos y marcadores', () => {
    assert.equal(choice('{1} Queda 1 plaza libre.|[2,*] Quedan :count plazas libres.', 1), 'Queda 1 plaza libre.');
    assert.equal(choice('{1} Queda 1 plaza libre.|[2,*] Quedan :count plazas libres.', 7), 'Quedan 7 plazas libres.');
    assert.equal(choice('{0} Añadir|{1} Añadir 1 niño|[2,*] Añadir :count niños', 0), 'Añadir');
    assert.equal(choice(':name, en la lista.', 1, { name: 'Mateo' }), 'Mateo, en la lista.');
});

test('euros escribe como el servidor', () => {
    const NBSP = String.fromCharCode(160);
    assert.equal(euros(1600), `16${NBSP}€`);
    assert.equal(euros(1695), `16,95${NBSP}€`);
});

test('importe escribe como Money::format (lo cobrado: la tarta y los complementos)', () => {
    // Los valores, leídos de `Money::format()` el 26-09 (byte a byte: el espacio es NORMAL, no el duro de `euros`).
    assert.equal(importe(2500), '25,00 €');
    assert.equal(importe(1695), '16,95 €');
    assert.equal(importe(123450), '1.234,50 €');
    assert.equal(importe(100000000), '1.000.000,00 €');
    assert.equal(importe(5), '0,05 €');
    assert.equal(importe(-1250), '-12,50 €');
    assert.equal(importe(2500).charCodeAt(5), 32, 'espacio normal');
});

// ── La vista previa de «Personalizar» (F2): las mismas reglas que la tarjeta del servidor ──
const TEXTOS = { restoCon: ' cumple :age años y te invita a saltar', restoSin: ' te invita a saltar' };

test('la edad tecleada: solo cifras, dos como mucho', () => {
    assert.equal(soloEdad('7 años'), '7');
    assert.equal(soloEdad('123'), '12');
    assert.equal(soloEdad(''), '');
    assert.equal(soloEdad(undefined), '');
});

test('la vista previa completa: chapa, titular, burbuja con la inicial de quien invita, pie con palabras y regalo', () => {
    const v = vistaInvitacion({ nombre: ' Vera ', edad: '7', invita: 'lucía, la madre de Vera', palabras: 'Traed ganas de saltar', pistas: 'Libros', telefono: true }, TEXTOS);
    assert.equal(v.nombre, 'Vera');
    assert.equal(v.conEdad, true);
    assert.equal(v.resto, ' cumple 7 años y te invita a saltar');
    assert.equal(v.figura, true);
    assert.equal(v.conPalabras, true);
    assert.equal(v.inicial, 'L', 'la inicial es la de quien invita, en mayúscula');
    assert.deepEqual([v.pieCon, v.pieSin], [true, false], 'con palabras, el pie va bajo la burbuja');
    assert.equal(v.telefono, true);
    assert.deepEqual([v.conPistas, v.pistas], [true, 'Libros']);
});

test('la vista previa sin edad, sin palabras ni pistas: sin chapa, titular corto, pie en su forma sin burbuja', () => {
    const v = vistaInvitacion({ nombre: 'Vera', edad: 'x', invita: 'Lucía', palabras: '   ', pistas: '' }, TEXTOS);
    assert.equal(v.conEdad, false);
    assert.equal(v.resto, ' te invita a saltar');
    assert.equal(v.figura, true, 'quien invita sigue teniendo su pie');
    assert.equal(v.conPalabras, false, 'solo espacios no es una palabra');
    assert.deepEqual([v.pieCon, v.pieSin], [false, true]);
    assert.equal(v.conPistas, false);
    assert.equal(v.telefono, false);
});

test('sin quien invita ni palabras no hay figura, y la inicial cae en quien cumple', () => {
    const vacia = vistaInvitacion({ nombre: 'Álvaro', invita: '', palabras: '' }, TEXTOS);
    assert.equal(vacia.figura, false);
    assert.deepEqual([vacia.pieCon, vacia.pieSin], [false, false]);
    const soloPalabras = vistaInvitacion({ nombre: 'álvaro', invita: '', palabras: 'Hola' }, TEXTOS);
    assert.equal(soloPalabras.inicial, 'Á', 'la inicial de quien cumple, con su tilde');
    assert.deepEqual([soloPalabras.pieCon, soloPalabras.pieSin], [false, false], 'sin quien invita no hay pie');
});
