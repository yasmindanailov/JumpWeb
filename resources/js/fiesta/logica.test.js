import test from 'node:test';
import assert from 'node:assert/strict';

import { asoma, caducaEn, capitalizar, caraDeLaLista, choice, clave, cubrir, deLaFiesta, estadoFicha, euros, huecoIsla, importe, islaSale, limpiar, misRespuestas, racionesTarta, soloEdad, sugerirCorreo, vistaInvitacion } from './logica.js';

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

// K2 (§4.17, `#807`): varias tartas a la vez, sus raciones contra los niños.
test('la tarta: las raciones de todas las pedidas suman, y cubren a los niños o no', () => {
    const tartas = (a, b) => [{ uds: a, serves: 12 }, { uds: b, serves: 20 }];
    assert.deepEqual(racionesTarta(tartas(1, 0), 14), { cubre: false, uds: 1, raciones: 12 }, 'una de 12 no llega para 14');
    assert.deepEqual(racionesTarta(tartas(1, 1), 14), { cubre: true, uds: 2, raciones: 32 }, 'dos tipos a la vez suman');
    assert.deepEqual(racionesTarta(tartas(2, 0), 24), { cubre: true, uds: 2, raciones: 24 }, 'justas, llega');
    assert.deepEqual(racionesTarta(tartas(2, 0), 25), { cubre: false, uds: 2, raciones: 24 }, 'una menos que los niños, no');
});

test('la tarta: sin nada pedido, sin niños o con una sin raciones, no se dice nada', () => {
    assert.equal(racionesTarta([{ uds: 0, serves: 12 }], 14), null, 'nada pedido');
    assert.equal(racionesTarta([{ uds: 1, serves: 12 }], 0), null, 'sin niños');
    assert.equal(racionesTarta([{ uds: 1, serves: 12 }, { uds: 1, serves: null }], 14), null, '«Traemos la nuestra»: su cuenta no se sabe');
    // CONTROL: la que no dice raciones pero NO se pidió no calla la cuenta.
    assert.deepEqual(racionesTarta([{ uds: 1, serves: 12 }, { uds: 0, serves: null }], 14), { cubre: false, uds: 1, raciones: 12 });
    assert.equal(racionesTarta([], 14), null);
    assert.equal(racionesTarta(undefined, 14), null);
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

// La isla de las páginas de enlace (§4.18, `#814`; `LinkIsland.jsx` del zip (6)): sus cinco reglas, sin DOM.
test('la isla está a la vista cuando ha llegado, tiene algo que hacer y nada la tapa', () => {
    assert.equal(islaSale({}), false, 'por defecto, a la vista');
    assert.equal(islaSale({ llegada: false }), true, 'antes de que caiga el confeti, aún no');
    assert.equal(islaSale({ vista: true }), true, 'la página ya enseña lo que diría (regla 3)');
    assert.equal(islaSale({ sin: true }), true, 'nada que hacer: se va (regla 5)');
    assert.equal(islaSale({ capa: true }), true, 'con el vídeo o el descargo abiertos, no va encima');
});

test('al escribir en un campo de la página se aparta; si el campo es SUYO (la respuesta), se queda (regla 4)', () => {
    assert.equal(islaSale({ escribiendo: true }), true);
    assert.equal(islaSale({ escribiendo: true, suya: true }), false);
    assert.equal(islaSale({ escribiendo: true, suya: true, capa: true }), true, 'una capa manda aunque el campo sea suyo');
});

test('«se ve ya» cuenta solo lo que asoma por ENCIMA de la franja de la isla y no está escondido', () => {
    const alto = 844;
    assert.equal(asoma([{ top: 100, bottom: 140, height: 40 }], alto), true, 'arriba de la pantalla: se ve');
    assert.equal(asoma([{ top: 760, bottom: 800, height: 40 }], alto), false, 'en la franja de la isla (los últimos 96 px): no cuenta');
    assert.equal(asoma([{ top: -60, bottom: 4, height: 64 }], alto), false, 'pasado por arriba: ya no se ve');
    assert.equal(asoma([{ top: 100, bottom: 100, height: 0 }], alto), false, 'escondido (alto 0): no cuenta');
    assert.equal(asoma([{ top: 900, bottom: 940, height: 40 }, { top: 300, bottom: 340, height: 40 }], alto), true, 'basta uno');
    assert.equal(asoma([], alto), false);
});

// L2 · la isla de la lista: el único Guardar y lo que dice.
const TX_LISTA = {
    corto: (n) => (n === 1 ? '1 cambio' : `${n} cambios`),
    respuestas: (n) => (n === 1 ? '1 respuesta por repasar' : `${n} respuestas por repasar`),
    movil: 'borrador en este móvil',
    recuperado: 'borrador recuperado',
};

test('con algo que guardar, el Guardar en naranja con lo EXACTO', () => {
    assert.deepEqual(caraDeLaLista({ cambios: 2 }, TX_LISTA), { cara: 'guardar', primary: true, ocupada: false, sub: '2 cambios · borrador en este móvil' });
    assert.equal(caraDeLaLista({ cambios: 1, recuperado: true }, TX_LISTA).sub, '1 cambio · borrador recuperado');
    assert.equal(caraDeLaLista({ cambios: 1, repasar: 3 }, TX_LISTA).sub, '1 cambio · 3 respuestas por repasar', 'con respuestas que repasar, eso en vez del borrador');
    assert.equal(caraDeLaLista({ repasar: 1 }, TX_LISTA).sub, '1 respuesta por repasar', 'sin cambios, solo lo que hay que repasar');
    assert.equal(caraDeLaLista({ cambios: 1, tarta: 'La tarta se guarda hasta hoy a las 17:00' }, TX_LISTA).sub, 'La tarta se guarda hasta hoy a las 17:00', 'la tarta que cierra manda');
});

test('guardando, el Guardar ocupado; sin nada que guardar, enviar si aún no salió, y si no, nada', () => {
    assert.deepEqual(caraDeLaLista({ cambios: 2, guardando: true }, TX_LISTA), { cara: 'guardar', primary: true, ocupada: true, sub: '' });
    assert.deepEqual(caraDeLaLista({ abiertas: true, compartida: false }, TX_LISTA), { cara: 'enviar' });
    assert.deepEqual(caraDeLaLista({ abiertas: true, compartida: true }, TX_LISTA), { cara: null }, 'ya salió: nada que hacer, se va');
    assert.deepEqual(caraDeLaLista({ abiertas: false, compartida: false }, TX_LISTA), { cara: null }, 'fuera de plazo no se envía');
    assert.deepEqual(caraDeLaLista({}, TX_LISTA), { cara: null });
});

test('el hueco al pie: el alto de la isla, su borde y el aire de debajo; sin nada que hacer, nada', () => {
    assert.equal(huecoIsla(140), 'calc(142px + var(--island-inset) * 2 + env(safe-area-inset-bottom, 0px))');
    assert.equal(huecoIsla(140, true), '0px');
    assert.equal(huecoIsla(null), '0px', 'sin medir todavía, nada');
});
