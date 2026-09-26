<?php

/**
 * LOS MODELOS DEL DISEÑO — el `datos.js` de cada página mapeado A MANO a la forma de los presentadores (`App\Http\Fiesta`),
 * para el lado B del banco (`scripts/banco-fiesta.php`) y para su guarda (`FiestaModeloTest`: la MISMA forma que
 * produce el controlador, clave a clave y tipo a tipo). Si un presentador cambia una clave, el test lo dice antes
 * que el banco; si el banco pinta con una clave que el controlador no da, también.
 *
 * ⚠️ Solo los datos del diseño (`#767`): el logotipo del diseño, la fiesta de Vera, la reserva `R-5F2K8`. Lo que el
 *    producto pide y el diseño no (nacimiento, relación, el descargo en el flujo, la privacidad…) va con texto neutro.
 * ⚠️ El estado `guardado` de la lista se mapea AJUSTADO a lo que HAY (spec §1.4): sin los «no» del diseño (k7, k8:
 *    «Al final viene» FALTA), con las edades a 7 (la nota de las edades FALTA), sin palabras ni pistas (FALTA), sin
 *    fecha de guardado (la barra dice «Nada que guardar todavía»). Quien cumple NO es una fila (FALTA): nueve con datos
 *    y la décima plaza vacía, como las produce el presentador; el número dice «10 de 10» porque en el diseño quien cumple
 *    cuenta como uno más. k9, k10 y k12 son «mano» con «sí» en el diseño, que para el presentador es `origen: invitacion`.
 *
 * @return array{lista: callable(string): array<string, mixed>, invitacion: callable(bool): array<string, mixed>, autorizacion: callable(string, bool): array<string, mixed>}
 */

use App\Domain\Booking\Models\PartyInvitation;
use App\Http\Fiesta\Temas;

$NB = "\u{a0}";
$LOGO = ['src' => '../assets/logo/logo-playjump.png', 'alt' => 'Play Jump Park'];
$IDIOMAS = ['actual' => 'es', 'actual_corto' => 'ES', 'lista' => [
    ['clave' => 'es', 'corto' => 'ES', 'nombre' => 'Español', 'enlace' => '#es'],
    ['clave' => 'en', 'corto' => 'EN', 'nombre' => 'English', 'enlace' => '#en'],
    ['clave' => 'fr', 'corto' => 'FR', 'nombre' => 'Français', 'enlace' => '#fr'],
]];
$tema = static fn (string $clave): array => ['clave' => $clave, 'tinte' => Temas::de($clave)['tint'], 'acento' => Temas::de($clave)['accent']];
// `datos.js → RESERVA` de la lista, con las claves del presentador (`ListaDeInvitados::reserva()`).
$RESERVA = [
    'codigo' => 'R-5F2K8', 'dia' => 'Sábado 26 de septiembre', 'dia_corto' => 'sábado 26', 'corto' => 'Sáb 26 sep',
    'hora' => '17:00', 'fin' => '19:00', 'pack' => 'Pack Kids', 'reservados' => 10,
    'telefono' => '641 99 57 14', 'tel' => '+34641995714', 'lugar' => 'Play Jump Park, Lorca',
    'enlace' => 'https://playjump.es/i/7K2P4V', 'enlace_corto' => 'playjump.es/i/7K2P4V', 'anfitriona' => '655 120 387',
];
$TEMAS = [['value' => 'confeti', 'label' => 'Confeti', 'note' => 'Por defecto'], ['value' => 'fiesta', 'label' => 'Fiesta', 'note' => null], ['value' => 'sereno', 'label' => 'Sereno', 'note' => null]];

return [
    // ── LA LISTA (`lista-invitados/datos.js`): `recien` es la primera pantalla; `guardado`, la lista completa ──────────
    'lista' => static function (string $estado) use ($NB, $LOGO, $RESERVA, $TEMAS): array {
        $guardado = $estado === 'guardado';
        $cumple = ['nombre' => $guardado ? 'Vera' : '', 'edad' => '7'];
        $titular = $cumple['nombre'].__('fiesta.invitacion.rest', ['age' => $cumple['edad']]);
        $cuando = __('fiesta.lista.cuando', ['dia' => $RESERVA['dia'], 'hora' => $RESERVA['hora'], 'fin' => $RESERVA['fin'], 'lugar' => $RESERVA['lugar']]);
        $mensaje = __('fiesta.lista.invitacion.mensaje', ['titular' => $titular, 'cuando' => $cuando, 'enlace' => $RESERVA['enlace']]);

        $nino = static fn (int $i, string $nombre = '', string $edad = '', string $alergias = '', bool $firmada = false): array => [
            'id' => 'g'.$i, 'indice' => $i, 'nombre' => $nombre, 'edad' => $edad, 'alergias' => $alergias,
            'vacia' => $nombre === '',
            'origen' => $nombre === '' ? 'mano' : 'invitacion',
            'respuesta' => $nombre === '' ? null : 'si',
            'pendiente' => false, 'reply_id' => null, 'repetida' => false, 'firmada' => $firmada,
            'completa' => $nombre !== '', 'falta' => null, 'sin_producto' => false, 'regimen' => null,
            'campos' => ['name' => 'guests['.$i.'][name]', 'age' => 'guests['.$i.'][age]', 'allergies' => 'guests['.$i.'][allergies]'],
            'extra' => [], 'editable' => true,
        ];
        $conDatos = $guardado ? [
            ['Hugo Martín Sáez', '7', '', true], ['Carla Gómez Ruiz', '6', 'Sin lactosa', true], ['Álex Romero Gil', '7', '', true],
            ['Nora Jiménez Vidal', '7', 'Frutos secos', true], ['Daniel Ortiz Mora', '7', '', true], ['Irene Castillo Rey', '7', '', true],
            ['Pablo Ruiz Navarro', '7', '', true], ['Sofía Navarro Pons', '7', 'Celiaca', false], ['Lola Pérez Soto', '6', 'Huevo', false],
        ] : [];
        $ninos = [];
        for ($i = 0; $i < $RESERVA['reservados']; $i++) {
            $ninos[] = isset($conDatos[$i]) ? $nino($i, ...$conDatos[$i]) : $nino($i);
        }
        $confirmados = count($conDatos);

        // `EXTRAS.padres` del diseño (combos y cubos), con las claves de `ListaDeInvitados::extras()`.
        $addon = static fn (int $i, string $nombre, string $linea, int $precio, int $cantidad): array => [
            'indice' => $i, 'id' => $i + 1, 'nombre' => $nombre, 'linea' => $linea, 'que_lleva' => [$linea], 'regalos' => [],
            'precio' => $precio.$NB.'€', 'precio_unidad' => $precio * 100,
            'plazo' => 'Hasta el sábado 26', 'cambia' => 'Lo cambias hasta el sábado 26',
            'cantidad' => $cantidad, 'tope' => 60, 'cerrado' => false, 'motivo' => '',
            'total' => $cantidad > 0 ? ($precio * $cantidad).$NB.'€ en total' : '',
        ];
        $extras = [
            $addon(0, 'Combo café', 'Café o infusión y bollería', 39, 0),
            $addon(1, 'Combo picoteo', 'Refrescos, café y algo de picar', 59, $guardado ? 1 : 0),
            $addon(2, 'Cubo de 6', 'Refrescos o aguas, con hielo', 16, 0),
            $addon(3, 'Cubo de 10', 'Refrescos o aguas, con hielo', 24, $guardado ? 1 : 0),
        ];

        return [
            'accion' => '#guardar',
            'testigo' => 'v1',
            'solo_lectura' => false,
            'marca' => 'Play Jump Park',
            'logo' => $LOGO,
            'reserva' => $RESERVA,
            'cumple' => $cumple,
            'primero' => ! $guardado,
            'invitacion' => [
                'tema' => $guardado ? 'fiesta' : 'confeti',
                'temas' => $TEMAS,
                'invita' => $guardado ? 'Lucía, la madre de Vera' : 'Lucía',
                'palabras' => '',
                'pistas' => '',
                'palabras_max' => PartyInvitation::FAMILY_WORDS_MAX,
                'pistas_max' => PartyInvitation::GIFT_HINTS_MAX,
                'telefono' => false,
                'url' => $RESERVA['enlace'],
                'compartible' => $guardado,
                'respuestas_abiertas' => true,
                'plazo' => 'viernes 25 a las 17:00',
                'compartida' => $guardado,
                'whatsapp' => 'https://wa.me/?text='.rawurlencode($mensaje),
                'mensaje' => $mensaje,
                'accion' => '#invitacion',
                'descartar' => '#descartar',
                'recordatorio' => '#recordatorio',
                'faltan' => 0,
                'recordado_el' => '',
                'recordado_veces' => 0,
                'texto_recordatorio' => null,
                'no_caben' => 0,
                'no_vienen' => [],
                'texto_rechazado' => false,
                'descartada' => false,
                'guardada' => false,
                'honoree_max' => PartyInvitation::HONOREE_NAME_MAX,
                'host_max' => PartyInvitation::HOST_LINE_MAX,
            ],
            'ninos' => $ninos,
            'columnas' => ['name' => 'name', 'age' => 'age', 'allergies' => 'allergies', 'extra' => [], 'labels' => ['name' => 'Nombre', 'age' => 'Edad', 'allergies' => 'Alergias o menú especial']],
            'cuentas' => ['confirmados' => $confirmados, 'no_pueden' => 0, 'sin_contestar' => 0, 'en_lista' => $confirmados],
            'numero' => [
                'valor' => $RESERVA['reservados'], 'suelo' => 8, 'techo' => null, 'editable' => true, 'motivo' => null,
                'pista' => 'Hasta el viernes 25',
                'en_lista' => $guardado ? 10 : 1, 'libres' => $guardado ? 0 : 9, 'lleno' => $guardado,
            ],
            'extras' => [
                'lista' => $extras, 'total' => $guardado ? '83'.$NB.'€' : '', 'elegidos' => $guardado ? 2 : 0, 'alguno_abierto' => true,
                'telefono' => $RESERVA['telefono'], 'tel' => $RESERVA['tel'],
            ],
            'generales' => [],
            'avisos' => [],
            'progreso' => ['done' => $confirmados, 'total' => $RESERVA['reservados']],
            'guardar' => ['estado' => 'clean'],
            'plazos' => ['respuestas' => true, 'numero' => true, 'extras' => true],
            'privacidad' => '#privacidad',
        ];
    },

    // ── LA INVITACIÓN (`invitacion/datos.js`: `FIESTA`, `T.es`, `P.es`), en reposo: viva (abierta) o cerrada ──────────
    'invitacion' => static fn (bool $abierta): array => [
        'titulo_pagina' => 'Vera cumple 7 años y te invita a saltar · Play Jump Park',
        'marca' => $LOGO,
        'idiomas' => $IDIOMAS,
        'tema' => $tema('confeti'),
        'cumple' => ['nombre' => 'Vera', 'edad' => '7'],
        'fecha' => 'Sábado 26 de septiembre',
        'hora' => 'De 17:00 a 19:00',
        'lugar' => 'Play Jump Park, Lorca',
        // Con `opc` encendido en el diseño (desde F1: palabras, pistas, teléfono y merienda son dato).
        'anfitrion' => ['linea' => 'Lucía, la madre de Vera', 'nombre' => 'Lucía', 'telefono' => '655 120 387', 'tel' => 'tel:+34655120387', 'palabras' => 'Traed ganas de saltar', 'pistas' => 'Le encantan los libros de animales'],
        'enlaces' => ['mapa' => 'https://www.google.com/maps/search/?api=1&query=Play+Jump+Park+Lorca', 'calendario' => '#calendario', 'ics' => 'cumple-vera.ics'],
        'texto' => ['Dos horas saltando en su zona, con monitores, merienda y tarta. Los padres podéis quedaros en la cafetería o venir a recogerlos.'],
        // «Ver el parque» (F1c) con `foto` encendida en el diseño: la foto del diseño, su vídeo, la nota y «Vamos» si se contesta.
        'parque' => [
            'video' => '../assets/media/hero-playjump.mp4', 'poster' => '../assets/media/foto-127-1200.jpg', 'nombre' => 'Play Jump Park',
            'linea' => 'Lorca · 23 atracciones, cada edad en su zona', 'ver' => 'Ver el parque', 'cerrar' => 'Cerrar', 'rotulo' => 'Vídeo: Play Jump Park',
            'nota' => ['valor' => '4,9', 'texto' => '4,9 en Google · 155 reseñas', 'aria' => '4,9 sobre 5 en Google'],
            'accion' => $abierta ? ['label' => 'Vamos', 'href' => '#rsvp-nino'] : null,
        ],
        'merienda' => [
            ['icono' => 'cup-soda', 'rotulo' => 'Para beber', 'cosas' => ['Refresco o zumo', 'Agua']],
            ['icono' => 'sandwich', 'rotulo' => 'Para comer', 'cosas' => ['Snacks', 'Sándwich mixto', 'Sándwich dulce']],
            ['icono' => 'candy', 'rotulo' => 'Y para terminar', 'cosas' => ['Cono de chuches']],
        ],
        'merienda_alergias' => '¿Alergias o menú especial? Lo apuntas al contestar, y lo ven Lucía y los monitores.',
        'respuestas' => ['abiertas' => $abierta, 'plazo' => 'Confirma antes del viernes 25 a las 17:00.', 'accion' => '#contestar', 'cerrado' => 'El plazo pasó: habla con Lucía.', 'error' => ''],
        'aviso' => null,
        'turnstile' => ['activo' => false, 'clave' => '', 'rotulo' => ''],
        'og' => ['sitio' => 'Play Jump Park', 'title' => '', 'description' => '', 'image' => null, 'width' => null, 'height' => null],
        'privacidad' => ['texto' => 'Lucía verá el nombre de tu hijo, su edad y sus alergias para organizar la fiesta; el parque, para atenderle. Lo borramos a los 14 días de la fiesta.', 'politica' => 'Política de privacidad', 'enlace' => '#privacidad'],
        'recibo' => null,
    ],

    // ── LA AUTORIZACIÓN (`autorizacion/datos.js`): el formulario en reposo (`recibo`) o el Listo (`firmada`) ───────────
    'autorizacion' => static fn (string $estado, bool $diagnostico): array => [
        'diagnostico' => $diagnostico,
        'titulo_pagina' => 'Autorización para la fiesta de Vera · Play Jump Park',
        'marca' => $LOGO,
        'idiomas' => $IDIOMAS,
        'tema' => $tema('confeti'),
        'tarjeta' => [
            'edad' => '7',
            'titulo' => 'Autorización para la fiesta de Vera',
            'linea' => 'Sábado 26 de septiembre · 17:00 · Play Jump Park, Lorca.',
            'que' => 'Si dejas a tu hijo en la fiesta y no te quedas, queda a cargo de Lucía, como en cualquier cumpleaños. Esta autorización lo dice por escrito, e incluye el descargo de responsabilidad: la hoja que firma todo el que entra a saltar, con las normas y los riesgos.',
        ],
        'anfitrion' => ['etiqueta' => 'Va con', 'linea' => '', 'nombre' => 'Lucía', 'telefono' => '', 'tel' => ''],
        'bloqueado' => null,
        'listo' => $estado !== 'firmada' ? null : ['texto' => 'Firmada. El día de la fiesta lo acompañas hasta la puerta y listo, sin esperas.', 'quien' => 'Hugo Martín Sáez', 'firmante' => 'Ana Sáez Ruiz · 611 204 118'],
        'aviso' => null,
        'formulario' => $estado === 'firmada' ? null : [
            'accion' => '#firmar', 'documento_id' => 0, 'respuesta_id' => null, 'desde_invitacion' => '', 'menores' => [],
            'valores' => ['ninoNombre' => '', 'ninoApellidos' => '', 'nombre' => '', 'telefono' => '', 'correo' => '', 'casilla' => ''],
            'fallos' => ['ninoNombre' => '', 'ninoApellidos' => '', 'nombre' => '', 'telefono' => '', 'correo' => '', 'casilla' => ''],
            'nacimiento' => ['label' => 'Fecha de nacimiento', 'hint' => 'La usamos para saber su edad el día de la visita.', 'value' => '', 'error' => ''],
            'relacion' => ['label' => 'Relación con el menor', 'opciones' => [['value' => '', 'label' => 'Elige una opción'], ['value' => 'mother', 'label' => 'Madre']], 'value' => '', 'error' => ''],
            'descargo' => ['titulo' => 'El descargo', 'version' => '', 'cuerpo' => [['h' => '', 'p' => 'Aquí va el texto del descargo, el que da el parque.']]],
            'casilla' => 'Como su padre, madre o tutor, autorizo a que se quede a cargo de Lucía durante la fiesta y acepto el descargo de responsabilidad en su nombre.',
        ],
        'privacidad' => ['texto' => 'Lucía verá el nombre de tu hijo y que su autorización está firmada; el parque, tus datos para atenderle.', 'datos' => 'Los datos que escribes aquí los declaras tú y no los comprobamos con ningún documento. Se conservan como prueba de esta autorización.', 'politica' => 'Política de privacidad', 'enlace' => '#privacidad'],
        'turnstile' => ['activo' => false, 'clave' => '', 'rotulo' => ''],
    ],
];
