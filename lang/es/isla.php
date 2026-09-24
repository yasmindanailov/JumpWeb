<?php

// La ISLA: la carcasa de compra del sistema de diseño nuevo (`specs/isla-y-landing-nueva.md` §4.9).
// El español es el LITERAL del diseño (su `ParkIsland.jsx`): la isla se juzga contra él píxel a píxel, así
// que una coma distinta es una diferencia. Las horas no se escriben aquí: llegan del horario (`:hora`).
return [
    'accion' => [
        'reservar' => 'Reservar',
        'reservar_hoy' => 'Reservar para hoy',
        'ver_qr' => 'Ver mi QR',
        'seguir' => 'Sigue con tu reserva',
        'pagar_senal' => 'Reservar y pagar la señal',
        'pagar_bizum' => 'Pagar con Bizum',
        'reintentar_tarjeta' => 'Volver a intentar con tarjeta',
        'manual' => 'O lo reservamos nosotros y pagas por Bizum',
    ],
    'hoy' => [
        'antes' => 'Hoy abrimos a las :hora.',
        'antes_con_huecos' => 'Hoy abrimos a las :hora. Quedan huecos esta tarde.',
        'abierto' => 'Abierto hasta las :hora.',
        'abierto_con_huecos' => 'Abierto hasta las :hora. Quedan huecos.',
        'completo' => 'Hoy está completo. Mira mañana.',
        'cerrado' => 'Abrimos mañana a las :hora.',
    ],
    'pago' => [
        'no_cobrado' => 'No se ha cobrado nada.',
    ],
    'control' => [
        'menu' => 'Menú, cuenta y Mi QR',
        'volver' => 'Volver',
        'cerrar' => 'Cerrar',
    ],
    'panel' => [
        'menu' => 'Menú',
        'planes' => '¿Qué quieres reservar?',
        'calculo' => 'Tu cálculo',
        'qr' => 'Mi QR',
        'ayuda' => '¿Lo hablamos?',
        'cuenta' => 'Mi cuenta',
    ],
    'menu' => [
        'qr' => 'Mi QR',
        'qr_nota' => 'Entradas, reservas y autorizaciones',
        'cuenta' => 'Mi cuenta',
        'cuenta_nota' => 'Reservas, facturas y datos',
        'entrar' => 'Entrar o crear cuenta',
        'entrar_nota' => 'Para tener tus reservas y tu QR a mano',
        'portada' => 'Portada',
        'whatsapp' => 'WhatsApp',
        'ayuda_en_horario' => 'Te contestamos en un rato',
        'ayuda_fuera' => 'Te contestamos mañana por la tarde',
        'cookies' => 'Cookies',
    ],
    'cookies' => [
        'texto' => 'Usamos cookies propias y de terceros para medir las visitas y enseñarte nuestros anuncios en otras webs. Puedes aceptarlas, rechazarlas o configurarlas.',
        'aceptar' => 'Aceptar',
        'rechazar' => 'Rechazar',
        'configurar' => 'Configurar',
        'politica' => 'Política de cookies',
    ],
    'resumen' => [
        'cambiar' => 'Cambiar',
    ],
    'cuenta' => [
        'texto' => 'Entra para ver tus reservas, tus facturas y tu QR.',
        'ir' => 'Ir a mi cuenta',
        'entrar' => 'Entrar',
    ],
    'ayuda' => [
        'en_horario' => 'Te contestamos en un rato.',
        'fuera' => 'Te contestamos mañana a partir de las :hora.',
        'whatsapp' => 'Escribir por WhatsApp',
    ],
    // Lo que las piezas del sistema de diseño escribían a mano (T3b): el campo, las horas, la cantidad, el
    // resumen, la carga, entrar con Google o Apple y el QR.
    'pieza' => [
        'mostrar_clave' => 'Mostrar la contraseña',
        'ocultar_clave' => 'Ocultar la contraseña',
        'completo' => 'Completo',
        'quedan' => 'Quedan :n',
        'libres' => ':n libres',
        'quitar_uno' => 'Quitar uno',
        'anadir_uno' => 'Añadir uno',
        'total' => 'Total',
        'desde' => 'Desde',
        'cargando' => 'Cargando',
        'continuar_google' => 'Continuar con Google',
        'continuar_apple' => 'Continuar con Apple',
        'entrar_google' => 'Entrar con Google',
        'entrar_apple' => 'Entrar con Apple',
        'qr' => 'QR',
        'qr_de' => 'QR :codigo',
    ],
];
