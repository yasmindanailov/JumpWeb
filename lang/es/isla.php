<?php

// La ISLA: la carcasa de compra del sistema de diseño nuevo (`specs/isla-y-landing-nueva.md` §4.9).
// El español es el LITERAL del diseño (su `ParkIsland.jsx`): la isla se juzga contra él píxel a píxel, así
// que una coma distinta es una diferencia. Las horas no se escriben aquí: llegan del horario (`:hora`).
return [
    'accion' => [
        'reservar' => 'Reservar',
        'reservar_hoy' => 'Reservar para hoy',
        'seguir' => 'Sigue con tu reserva',
        'pagar_senal' => 'Reservar y pagar la señal',
        'pagar_bizum' => 'Pagar con Bizum',
        'reintentar_tarjeta' => 'Volver a intentar con tarjeta',
        'manual' => 'O lo reservamos nosotros y pagas por Bizum',
        // T4e: con la calculadora de la página a medias, la acción es el paso que falta (`pagina.jsx`).
        'elige_dia' => 'Elige el día',
        'elige_hora' => 'Elige la hora',
        // T6b·3: la calculadora de la fiesta pregunta antes la edad (elige el pack).
        'elige_edad' => 'Elige la edad',
    ],
    'hoy' => [
        'antes' => 'Hoy abrimos a las :hora.',
        'antes_con_huecos' => 'Hoy abrimos a las :hora. Quedan huecos esta tarde.',
        // [Hoy] vive en la isla desde la llegada (Z6a): antes de abrir, la frase entera que decía la cabecera.
        'antes_de_a' => "Hoy abrimos de :abre\u{00A0}a\u{00A0}:cierra.",
        'antes_de_a_con_huecos' => "Hoy abrimos de :abre\u{00A0}a\u{00A0}:cierra. Quedan huecos esta tarde.",
        'abierto' => 'Abierto hasta las :hora.',
        'abierto_con_huecos' => 'Abierto hasta las :hora. Quedan huecos.',
        'completo' => 'Hoy está completo. Mira mañana.',
        'cerrado' => 'Abrimos mañana a las :hora.',
        // Situación 15, las páginas que no venden (Visítanos, Normas): el horario de hoy, entero y sin punto.
        'apoyo' => "Hoy abrimos de :abre\u{00A0}a\u{00A0}:cierra",
        // Las CORTAS del experimento B3 (Z6c, `readToday` del diseño): van dentro de la acción, en su segundo renglón, y las
        // horas que no caben, en lo que se abre. Hasta 22 caracteres a 360 px.
        'corta_huecos' => 'Quedan huecos',
        'corta_de_a' => 'Hoy, :abre–:cierra',
        'corta_desde' => 'Hoy, desde las :hora',
        'corta_hasta' => 'Hasta las :hora',
        'corta_completo' => 'Hoy, completo',
        'corta_manana' => 'Mañana, a las :hora',
    ],
    'pago' => [
        'no_cobrado' => 'No se ha cobrado nada.',
    ],
    // Los banners de la isla que siguen a la compra (`#867`): mientras llega en el primer toque, y bajo «¡Reservado!» al
    // cerrar «Listo» (el titular es el de `compra.listo`).
    'banner' => [
        'preparando' => 'Preparando tu reserva',
        'ver_qr' => 'Toca para ver tu QR',
    ],
    'control' => [
        'menu' => 'Menú',
        // La cuenta, el control de la derecha de la barra (Z6a): sin sesión y con sesión.
        'cuenta' => 'Cuenta',
        'mi_qr' => 'Mi QR',
        'volver' => 'Volver',
        'cerrar' => 'Cerrar',
        // El aviso a isla entera (Z6b·2) se quita al tocarlo: lo que se lee con el foco en él, tras el aviso.
        'cerrar_aviso' => 'Toca para cerrar',
    ],
    'panel' => [
        'menu' => 'Menú',
        'planes' => '¿Qué quieres reservar?',
        'plan_desde' => 'desde',
        'calculo' => 'Tu cálculo',
        'qr' => 'Mi QR',
        'ayuda' => '¿Lo hablamos?',
        'cuenta' => 'Mi cuenta',
        // La segunda capa de las cookies (T4e: el mockup no la dibuja; el owner la encargó con el sistema).
        'cookies' => 'Tus cookies',
    ],
    'menu' => [
        'portada' => 'Portada',
        'whatsapp' => 'WhatsApp',
        'ayuda_en_horario' => 'Te contestamos en un rato',
        'ayuda_fuera' => 'Te contestamos mañana por la tarde',
        'cookies' => 'Cookies',
        // Los iconos del pie del menú (Z6a): su nombre para el lector y al pasar el ratón.
        'llamar' => 'Llamar al :tel',
        'idioma' => 'Idioma · :idioma',
    ],
    'cookies' => [
        // `#860`: `:para` y `:cortas` son SOLO las finalidades que esta instalación pide (las del `<body>`), unidas en su orden
        // (`isla/pagina/pagina.js`, `avisoDeCookies`): el aviso no nombra lo que no hay.
        'texto' => 'Usamos cookies para que la web funcione y para contar las visitas. Con tu permiso, también para :para. Puedes aceptarlas, rechazarlas o configurarlas.',
        // Abajo (móvil), el aviso compacto (zip del 27-09): una frase, con «Configurar» y «Política» dentro.
        'texto_corto' => 'Cookies necesarias y, con tu permiso, :cortas.',
        'para' => [
            'maps' => 'enseñarte el mapa de Google',
            'social' => 'enseñarte nuestras redes sociales',
            'analytics' => 'entender cómo usas la web con tu cuenta',
            'marketing' => 'enseñarte nuestros anuncios en otras webs',
        ],
        'cortas' => ['maps' => 'mapa', 'social' => 'redes', 'analytics' => 'análisis', 'marketing' => 'anuncios'],
        'y' => ' y ',
        'aceptar' => 'Aceptar',
        'rechazar' => 'Rechazar',
        'configurar' => 'Configurar',
        'politica' => 'Política de cookies',
        'politica_corta' => 'Política',
        'guardado' => 'Guardado',
        'si' => 'Sí',
        'no' => 'No',
    ],
    'resumen' => [
        'cambiar' => 'Cambiar',
    ],
    'cuenta' => [
        'texto' => 'Entra para ver tus reservas, tus facturas y tu QR.',
        'ir' => 'Ir a mi cuenta',
        'entrar' => 'Entrar',
    ],
    // Mi cuenta SIN sesión (T5a, `isla-y-landing-nueva.md` §4.13): crear la cuenta desde Entrar. Viaja con la isla a
    // todo visitante, porque es justo quien no ha entrado quien la ve (`paginas/mi-cuenta/datos.js`, su `propuesta`).
    'mi_cuenta_alta' => [
        // Llegando de «Entra» con un correo sin cuenta, en la compra y en Mi cuenta (Z6g·1, el zip (6)): por qué está aquí, y
        // la salida para quien esperaba entrar. Ya no hay «¿Es tu primera vez?» en Entra: el correo decide (`#849`).
        'nueva' => 'No hay ninguna cuenta con :correo. La creas ahora, en un minuto.',
        'otra' => '¿Ya tienes una con otro correo?',
        'otra_enlace' => 'Entra con ese',
        'crear_titulo' => 'Crea tu cuenta',
        'crear_boton' => 'Crear mi cuenta',
        'creando' => 'Creando tu cuenta',
        'ya_existe' => 'Ya hay una cuenta con este correo: entra con él.',
        // Sin conexión (T5f), en toda la capa de Mi cuenta, con sesión o sin ella: arriba, y lo que guarda deja reintentar.
        'red' => [
            'sin' => 'Sin conexión',
            'sin_texto' => 'Lo que ves sigue aquí. Cuando vuelva la conexión, podrás guardar.',
            'fallo' => 'No se ha podido guardar: no hay conexión. No has perdido nada.',
            'reintentar' => 'Volver a intentarlo',
        ],
    ],
    'ayuda' => [
        'en_horario' => 'Te contestamos en un rato.',
        'fuera' => 'Te contestamos mañana a partir de las :hora.',
        'whatsapp' => 'Escribir por WhatsApp',
    ],
    // Lo que las piezas del sistema de diseño escribían a mano (T3b): el campo, las horas, la cantidad, el
    // resumen, la carga, entrar con Google o Apple y el QR.
    'pieza' => [
        'opcional' => 'opcional',
        // «¿Querías decir …?», bajo un campo de correo mal escrito (el correo propuesto va en medio, en negrita).
        'sugerencia_antes' => '¿Querías decir ',
        'sugerencia_despues' => '?',
        'completo' => 'Completo',
        'quedan' => 'Quedan :n',
        'libres' => ':n libres',
        'quitar_uno' => 'Quitar uno',
        'anadir_uno' => 'Añadir uno',
        'total' => 'Total',
        'desde' => 'Desde',
        'cargando' => 'Cargando',
        // Las formas de pago con sus logotipos (`#784`): el nombre de la lista, para quien no la ve.
        'formas_pago' => 'Formas de pago',
        'continuar_google' => 'Continuar con Google',
        'continuar_apple' => 'Continuar con Apple',
        'entrar_google' => 'Entrar con Google',
        'entrar_apple' => 'Entrar con Apple',
        // Entre Google/Apple y los campos (`#857`): «— o —».
        'o' => 'o',
        'qr' => 'QR',
        'qr_de' => 'QR :codigo',
        'copiado' => 'Enlace copiado',
        // El calendario de mes (`AvailabilityCalendar.jsx`; T4d): los nombres del mes y de los días, del idioma.
        'calendario' => [
            'mes_anterior' => 'Mes anterior',
            'mes_siguiente' => 'Mes siguiente',
            'dia' => ':n de :mes',
            'aria_hoy' => ', hoy',
            'aria_libre' => ', libre',
            'aria_especial' => ', libre, :tarifa',
            'aria_completo' => ', completo',
            'aria_cerrado' => ', cerrado',
            'hoy' => 'hoy',
            'libre' => 'libre',
            'completo' => 'completo',
            'tarifa_especial' => 'Tarifa especial',
        ],
    ],
    // La calculadora de la página (T4d, `paginas/entradas/pieza-3.jsx`): lo GENÉRICO —qué falta, el eco de la hora, las
    // líneas del recibo—. Lo que nombra lo del parque (sus preguntas, sus calcetines, su mensaje) lo trae la página.
    'calculadora' => [
        'falta_dia' => 'Elige el día para ver el total',
        'falta_hora' => 'Elige la hora para ver el total',
        'espera_hora' => 'Elige antes el día: cada día tiene sus horas libres.',
        'por' => ':precio por :persona',
        'desde' => 'desde :precio',
        'eco_dia_antes' => ':tarifa: ',
        'eco_dia_despues' => ' por :persona',
        'eco_hora' => 'De :desde a :hasta',
        'eco_hora_cierre' => 'De :desde al cierre, a las :cierre',
        'a_las' => ':dia a las :hora',
        'linea_entradas' => 'Entradas · :n × :precio',
        'linea_complemento' => ':nombre · :n × :precio',
        // La calculadora de la FIESTA (T6b·3): la edad elige el pack, y lo que alarga la fiesta se ve si cabe a esa hora.
        'falta_edad' => 'Elige cuántos años cumple para ver el total',
        'linea_pack' => ':pack · :n × :precio',
        'menu_incluido' => 'Incluido',
        'menu_mas' => '+:precio por :persona',
        'extra_mas' => '+:precio',
        'extra_por' => '+:precio por :persona',
        'extra_total' => '+:precio en total',
        'extra_sin_hora' => 'Elige la hora para saber si cabe.',
        'extra_no_cabe' => 'A las :hora no cabe.',
    ],
    // Las pantallas de la COMPRA (T3c), con el literal de su diseño (`paginas/compra/datos.js`). Lo que depende de
    // los datos del parque —nombres de zona, precios, las preguntas de su widget, los plazos de sus condiciones—
    // no vive aquí: llega a la pantalla como dato.
    'compra' => [
        'paso' => 'Paso :n de :total',
        'cuando' => [
            'banda' => 'Cuándo y cuántos',
            'titulo_hoy' => 'Para hoy',
            'zona' => '¿Qué zona?',
            'otra_zona' => '¿Alguien va a la otra zona? Añádelo a la misma reserva',
            // La otra zona (K2 de `otra-zona.md`, `#878`): con una sola, se nombra (D3-B); su tarjeta y sus «no».
            'otra_zona_de' => '¿Alguien va a :zona? Añádelo a la misma reserva',
            'otra_no_vende' => ':zona no se vende este día',
            'otra_no_cabe' => 'En :zona no caben a esta hora',
            'otra_llena' => 'En :zona ya no queda sitio a esa hora. Elige otra hora o quítala.',
            'aviso_otra' => ':zona: :aviso',
            // K2·b (`#882`): bajo el nombre de su zona, para quién es (las edades de su fila, del panel).
            'edades_de_a' => 'De :min a :max años',
            'edades_desde' => 'Desde :min años',
            'edades_hasta' => 'Hasta :max años',
            // K3: en una tarjeta, el tiempo que YA está en la reserva (el del pedido o el de otra tarjeta): más gente en la suya.
            'ya_en_la_reserva' => 'Ya está en la reserva',
            // K3: en «Añadir otra entrada», el tiempo que ya está en la reserva se puede elegir: suma gente a su línea.
            'se_suma' => 'Ya está en la reserva: se suma a ella',
            // Bajo la tira de días, el calendario de meses para lo que ella no enseña (`#830`).
            'mas_fechas' => 'Más fechas',
            'menos_fechas' => 'Cerrar el calendario',
            'quitar' => 'Quitar',
            'otra_titulo' => 'Añadir otra entrada',
            'buscando_horas' => 'Buscando horas libres',
            'ahorro' => ':importe menos que dos de 1 hora',
            'continuar' => 'Continuar',
            // T3e (§4.10): lo que la compra de la isla escribe con los DATOS del motor mientras la página no traiga
            // sus propias preguntas (T4). Las cifras (el precio del par) llegan del complemento.
            'titulo_zona' => 'Entrada :zona',
            'hoy' => 'hoy',
            'tarifa_especial' => 'tarifa especial',
            'pregunta_dia' => '¿Qué día venís?',
            'pregunta_hora' => '¿A qué hora?',
            'pregunta_tiempo' => '¿Cuánto tiempo?',
            'pregunta_cuantos' => '¿Cuántos venís?',
            'pregunta_calcetines' => '¿Calcetines antideslizantes?',
            'pista_calcetines' => ':precio el par. Si ya los tenéis, traedlos.',
            'entrada' => 'entrada',
            'entradas' => 'entradas',
            'par' => 'par',
            'pares' => 'pares',
            'no_disponible' => 'No se vende este día',
            // T6c·3: un PACK sin edad (una excursión) por esta pantalla: su gente, su «desde» (el tramo más barato) y lo
            // que pide al reservar (`#839`), con las etiquetas del panel.
            'persona' => 'persona',
            'personas' => 'personas',
            'desde_precio' => 'desde :precio',
            'pregunta_datos' => 'Datos de la reserva',
            // T3e·5: la pantalla 0 de una FIESTA (`PjcCuandoCumple`), con el literal del diseño; las cifras (el mínimo,
            // las horas de ajuste, los tramos de edad, el precio del menú) llegan de los datos.
            'titulo_fiesta' => 'Un cumpleaños',
            'pregunta_edad' => '¿Cuántos años cumple?',
            'pregunta_ninos' => '¿Cuántos niños vienen?',
            'pregunta_dia_fiesta' => '¿Qué día?',
            'pregunta_menu' => '¿Qué menú?',
            // `#876`·7: al reservar, lo que queda para DESPUÉS —solo si el pack lleva la lista (`guest_form`)—: que nadie
            // deje de reservar por no tenerlo todo decidido.
            'fiesta_despues' => 'Los invitados y los detalles de la fiesta, después y sin prisa, en tu lista de invitados.',
            'nino' => 'niño',
            'ninos' => 'niños',
            'minimo' => 'Mínimo :n.',
            'ajusta' => '¿Aún no sabes cuántos seréis? Reserva con :n y ajusta hasta :horas h antes. Si vienen menos, pagas menos.',
            'pack_de_a' => ':pack, de :min a :max años',
            'pack_desde' => ':pack, desde :min años',
            'menu_incluido' => ':menu, incluido',
            'menu_mas' => ':menu, :precio más por niño',
            // `#880`: los complementos que se venden al reservar, todos. Su nombre y su precio llegan del servidor; aquí, por
            // qué uno está apagado y la unidad de lo que se suma.
            'extra_sin_hora' => 'Elige la hora para saber si cabe.',
            'extra_no' => 'Ese día, a las :hora, no se puede añadir.',
            'extra_requiere' => 'Requiere :nombre.',
            'unidad' => 'unidad',
            'unidades' => 'unidades',
        ],
        // M2 de `#880` (`#881`): lo que falta para continuar, encima del botón mientras falte y, al pulsar, en rojo en su
        // pregunta (`compra/falta.js`). Una frase por cosa, la misma en los dos sitios.
        'falta' => [
            'zona' => 'Elige la zona para continuar',
            'dia' => 'Elige el día para continuar',
            'hora' => 'Elige la hora para continuar',
            'edad' => 'Elige cuántos años cumple para continuar',
            'datos' => 'Rellena los datos de la reserva para continuar',
            'correo' => 'Escribe tu correo para continuar',
            'codigo' => 'Escribe las :n cifras del código',
            'hora_libre' => 'Elige una de estas horas para continuar',
            'otra' => ':zona no se vende ese día: quítala o elige otro día',
            'otra_tiempo' => 'Ese tiempo de :zona no se vende ese día: elige otro',
            'tiempo' => 'Elige cuánto tiempo para continuar',
        ],
        'datos' => [
            'banda' => 'Tus datos',
            'titular' => 'Tus datos',
            'ya' => '¿Ya has venido?',
            'entra' => 'Entra',
            'hola' => 'Hola, :nombre',
            'nombre' => 'Nombre y apellidos',
            'correo' => 'Correo',
            'telefono' => 'Teléfono',
            'pista_telefono' => 'Para avisarte de tu reserva. No lo usamos para nada más.',
            // `#792`: la del titular, entera y opcional. Se pide como su CUMPLEAÑOS, que es para lo que sirve, y la pista dice
            // solo que es opcional (el owner, 29-09).
            'nacimiento' => 'Tu cumpleaños',
            'pista_nacimiento' => 'Opcional',
            // El de la fecha de un hijo (`mi_cuenta.hijos.pista_fecha`), aquí porque la compra no recibe los de Mi cuenta.
            'formato_fecha' => 'DD/MM/AAAA',
            'google' => 'Continuar con Google',
            'apple' => 'Continuar con Apple',
            'existe' => 'Esta cuenta ya existe.',
            // El acceso con código (A3, `#848`/`#849`): la cuenta que ya existe entra con el código que le llega al correo. Sus
            // seis casillas (Z6g·1) y, en «ya existe», lo que hace el código (el zip (6)).
            'codigo' => 'Código de 6 cifras',
            'existe_codigo' => 'Te hemos enviado un código a tu correo: con él entras y no rellenas nada más.',
            'codigo_enviado' => 'Te hemos enviado un código a :correo.',
            'codigo_otro_enviado' => 'Te hemos enviado otro código a :correo.',
            'otro_codigo' => 'Pedir otro código',
            // Recordar el dispositivo, SOLO si se pide (`#858`, el owner): la cookie persistente no está exenta de consentimiento.
            'recordar' => 'Mantener la sesión iniciada en este dispositivo',
            'casilla' => 'He leído y acepto el descargo de responsabilidad.',
            'leer' => 'Leer el descargo de responsabilidad',
            // Quién firma el descargo (el zip (6), Z6g·2): una sola pista junto a la casilla, sin dar nada por hecho —la compra
            // no sabe para quién es cada entrada—. La segunda frase, solo en entradas con la firma dentro.
            'pista_descargo' => 'Lo firma todo el que salta, una vez y para siempre.',
            'pista_quien' => 'A los menores a tu cargo los añades a tu cuenta después de pagar, y firmas por ellos; los demás adultos, cada uno el suyo.',
            'revisa_uno' => 'Revisa 1 campo',
            'revisa' => 'Revisa :n campos',
            'llena' => 'Esa hora ya no está libre. Estas sí:',
            'elegir' => 'Elegir esta hora',
            'continuar' => 'Continuar al pago',
            'cargando' => 'Comprobando tus datos y guardando tu hora',
            'descargo' => 'Descargo de responsabilidad',
            // `#785`: la cuenta NUEVA que vuelve de Google completa su alta aquí, dentro de la compra (antes, en Mi cuenta).
            'google_cuenta' => 'Con tu cuenta de Google: :correo',
            'google_caducada' => 'La vuelta de Google ha caducado: vuelve a continuar con Google o rellena tus datos.',
            'errores' => [
                'descargo_nuevo' => 'El descargo acaba de cambiar: léelo y vuelve a marcar la casilla.',
                'nombre' => 'Escribe tu nombre y apellidos.',
                'correo' => 'Revisa el correo: falta algo.',
                'telefono' => 'Revisa el teléfono: son 9 cifras.',
                'nacimiento' => 'Revisa la fecha: día, mes y año.',
                'descargo' => 'Marca la casilla para seguir.',
                'codigo' => 'Escribe el código que te hemos enviado.',
                'codigo_mal' => 'El código no es correcto o ha caducado. Pide otro.',
                'espera' => 'Espera :n segundos para pedir otro código.',
            ],
        ],
        'entrar' => [
            'titular' => 'Entra',
            // Mi cuenta sin sesión (el zip (6), Z6g·1): el mismo camino entra o crea la cuenta; el correo decide (`#849`).
            'titular_cuenta' => 'Entra o crea tu cuenta',
            // `#695` `[DECIDIDO owner]`: solo correo. ⚠️ El diseño dice «Te enviamos un código a tu correo»; con `#849` un correo
            // nuevo no recibe ninguno (va a crear la cuenta), así que se dice cómo se entra, con las palabras de Ajustes.
            'texto' => 'Entras con un código a tu correo. Sin contraseña.',
            // La PUERTA, «Entra o crea tu cuenta» (la compra desde la M3 de `#880`, y Mi cuenta): el texto del owner.
            'texto_cuenta' => 'Entras con un código a tu correo. Sin contraseña. Si no tienes cuenta, la creas en 1 minuto con el correo que pongas aquí.',
            'correo' => 'Tu correo',
            'continuar' => 'Continuar',
            // Con el código (A3, `#849`): con el correo se pide; con el código, se entra. Su paso, del zip (6) (Z6g·1): a
            // quién se envió y QUIÉN lo manda —`:remitente` llega ya puesto: el nombre del negocio, el de la bandeja—, sin
            // decir cuánto dura (`#812`).
            'codigo_titular' => 'Revisa tu correo',
            'codigo_texto' => 'Te hemos enviado un código de 6 cifras a :correo.',
            'codigo_otro' => 'Te hemos enviado otro código a :correo. El anterior ya no vale.',
            'codigo_pista' => 'Te llega de :remitente. Si no lo ves, mira en el correo no deseado.',
            'entrar' => 'Entrar',
            'enviando' => 'Enviando el código',
            'google' => 'Continuar con Google',
            'apple' => 'Continuar con Apple',
            'cargando' => 'Entrando',
        ],
        'pagar' => [
            'banda' => 'Pagar',
            'titular' => 'Repasa y paga',
            // `#785`: sin «Tus datos» delante (con sesión y sin nada que pedir), con qué cuenta se compra, en una línea.
            'como' => 'Reservas como :nombre',
            'total' => 'Total',
            'otra' => 'Añadir otra entrada',
            'tarjeta' => 'Pagar :importe con tarjeta',
            'bizum' => 'Pagar :importe con Bizum',
            'pasarela' => 'Pago en la pasarela de tu banco. Tu tarjeta no se guarda.',
            'condiciones' => 'Al pagar aceptas las ',
            'condiciones_enlace' => 'condiciones de reserva',
            'retencion' => 'Tu hora queda guardada hasta las :hora.',
            'senal' => 'Hoy pagas :senal de señal; el resto, :resto, el día de la fiesta.',
            // T6c·3: de un pack sin lista de invitados (una excursión), que no es una fiesta.
            'senal_visita' => 'Hoy pagas :senal de señal; el resto, :resto, el día de la visita.',
            'hoy_pagas' => 'Hoy pagas :importe',
            // T3e·3: el precio publicado bajo cada línea del recibo («8 € por entrada», «2 € el par»), como el diseño.
            'precio_por' => ':precio por :unidad',
            'precio_el' => ':precio el :unidad',
            'saliendo' => 'Te llevamos a la pasarela de tu banco. Si no salta en unos segundos, pulsa el botón.',
            'saliendo_boton' => 'Continuar al pago',
        ],
        'listo' => [
            'titular' => '¡Reservado!',
            'titular_fiesta' => '¡Fiesta reservada!',
            'pedido' => 'Nº de pedido :codigo',
            'qr' => 'Tu QR. Enséñalo en la entrada: ahí está todo.',
            'guardar' => 'Guardar en el móvil',
            'enviado' => 'Te lo hemos enviado a :correo.',
            'whatsapp' => 'Y por WhatsApp.',
            'antes' => 'Antes de venir',
            // Quién firma el descargo, ya pagado (Z6g·2): una sola tarjeta, por grupos; nadie se cuenta ni se da por hecho.
            'firmas' => [
                'titulo' => 'Todos los que saltan, con el descargo firmado',
                'menores' => 'Menores a tu cargo:',
                'menores_texto' => 'si aún no están en tu cuenta, añádelos a ella y firma el descargo en su nombre. Un minuto, y en la puerta solo enseñas tu QR.',
                'adultos' => 'Otros adultos:',
                'adultos_texto' => 'cada uno firma el suyo, desde casa o en el mostrador.',
                'boton' => 'Añadir menores',
            ],
            'fiesta_intro' => 'Ahora, dos cosas.',
            // T3e·5: sin invitación digital en ese producto, queda una; sin plazo publicado, la frase va sin fecha.
            'fiesta_intro_una' => 'Ahora, una cosa.',
            'fiesta_invitados' => 'Rellena el formulario de invitados, hasta el :fecha.',
            'fiesta_invitados_sin_fecha' => 'Rellena el formulario de invitados.',
            'fiesta_invitacion' => 'Comparte la invitación por WhatsApp: los padres confirman y firman ellos.',
            'fiesta_formulario' => 'Rellenar el formulario',
            'fiesta_compartir' => 'Compartir la invitación',
            'cuenta' => 'Tu cuenta ya está creada con tu correo. Para entrar, te enviamos un código: sin contraseña.',
            'mi_qr' => 'Ir a Mi QR',
            'otra' => 'Hacer otra reserva',
        ],
        'fallido' => [
            'titular' => 'El pago no se ha completado.',
            'texto' => 'Tu banco no ha autorizado el cobro y no se ha cargado nada. Tu hora sigue guardada hasta las :hora.',
            // T3e·3: la misma, cuando no se ha podido leer hasta cuándo (la red, el limitador): no se inventa la hora.
            'texto_sin_hora' => 'Tu banco no ha autorizado el cobro y no se ha cargado nada.',
            'motivo' => 'Motivo: :motivo',
            'bizum' => 'Pagar con Bizum',
            'tarjeta' => 'Volver a intentar con tarjeta',
            'ayuda' => 'Pagar con nuestra ayuda',
            'escribir' => 'Escribirnos',
        ],
        'verificando' => [
            'fuerte' => 'Tu banco ha procesado el pago y lo estamos confirmando con la pasarela; suele tardar unos segundos.',
            'texto' => 'Puedes cerrar esto sin perder nada: te escribimos en cuanto esté confirmado, y el estado también está en Mi cuenta.',
        ],
        'perdida' => [
            'titular' => 'Esa hora ya no está libre.',
            'texto' => 'No se ha cobrado nada. Estas sí:',
            // Al CONTINUAR de la pantalla 0 (`#822`): aún no se ha pedido ni cobrado nada (el «Estas sí:» de «Tus datos»).
            'texto_al_entrar' => 'Estas sí:',
            // K4 de `otra-zona.md`: con varias líneas, las cercanas son las de TODAS y la hora nueva, de toda la reserva; si la
            // que no cupo es una añadida, el título nombra su zona.
            'titular_zona' => 'En :zona ya no queda sitio a esa hora.',
            'texto_todos' => 'No se ha cobrado nada. Estas sí, para toda la reserva:',
            'texto_todos_al_entrar' => 'Estas sí, para toda la reserva:',
            'boton' => 'Elegir esta hora',
        ],
    ],
    // MI CUENTA en la isla (T5, `isla-y-landing-nueva.md` §4.13, `DECISIONES #773`): los textos del diseño
    // (`paginas/mi-cuenta/datos.js`, aprobados el 24-09). ⚠️ Viajan SOLO CON SESIÓN (`SidebarBoot::personal()`): sin
    // ella nadie los pinta, y mandarlos a todo visitante sería pagar sus bytes en cada página (`PERF-02`).
    'mi_cuenta' => [
        'titulo' => 'Mi cuenta',
        'hola' => 'Hola, :nombre',
        'qr' => [
            'titulo' => 'Tu QR',
            'mini' => 'Tu QR, para la puerta',
            'ensenar' => 'Enseñar mi QR',
            'de' => 'Tu QR. Código :codigo',
            'texto' => 'Enséñalo en la puerta: ahí está todo.',
            'guardar' => 'Guardar en el móvil',
            'guardado' => 'QR guardado en el móvil',
            'dicta' => 'Si la cámara falla, dicta este código:',
            'renovar' => 'Renovar mi QR',
            'renovar_aviso' => 'El anterior dejará de valer al instante, también el impreso.',
            'renovar_si' => 'Sí, renovar',
            'renovar_no' => 'Dejarlo como está',
            'renovado' => 'Tu QR se ha renovado',
            'como_llegar' => 'Cómo llegar',
        ],
        // Las reservas (T5b, `#775`): «Tu próxima reserva», «Otras reservas», «Tu reserva» y «Cambiar o cancelar».
        'proxima' => [
            'titulo' => 'Tu próxima reserva',
            'numero' => 'Nº :code',
            'aria' => ':dia a las :hora',
            'ver_pago' => 'Ver el pago',
            'cambiar' => 'Cambiar o cancelar',
            'senal' => 'Señal pagada: :importe',
            'resto' => 'El día de la fiesta: :importe',
            'resto_visita' => 'El día de la visita: :importe',
            'senal_rotulo' => 'Señal pagada',
            'resto_rotulo' => 'El día de la fiesta',
            'resto_rotulo_visita' => 'El día de la visita',
            // Un complemento sin aviso escrito en el panel (`#775`): su nombre y su cantidad, sin prometer nada.
            'complemento' => ':nombre · :cantidad',
            'plazo' => 'Puedes cambiar o cancelar hasta el :dia a las :hora.',
            'fuera' => 'Quedan menos de :tramo: ya no se puede cambiar ni cancelar. Si ha pasado algo, escríbenos y lo vemos.',
            'fuera_sin_tramo' => 'Ya no se puede cambiar ni cancelar. Si ha pasado algo, escríbenos y lo vemos.',
            'total' => 'Total',
            'incluido' => 'Incluido',
        ],
        'otras' => [
            'titulo' => 'Otras reservas',
            'historial' => 'Ver el historial',
            'ocultar' => 'Ocultar el historial',
            'mas' => 'Ver más',
            'estado' => [
                'pasada' => 'Pasada',
                'cancelada' => 'Cancelada',
                'devuelta' => 'Devuelta',
                'sin_pagar' => 'Sin pagar',
            ],
        ],
        'reserva' => [
            'titulo' => 'Tu reserva',
        ],
        'cambiar' => [
            'banda' => 'Cambiar o cancelar',
            'titulo' => 'Cambiar o cancelar tu reserva',
            'texto' => 'Para tu reserva del :dia a las :hora, :que, número :code. Puedes cambiar la fecha o la hora, o cancelar, :plazo: escríbenos y lo hacemos contigo.',
            // Solo si el producto promete devolver la señal en plazo (`#775`, el interruptor del panel).
            'devolvemos' => ', y te devolvemos la señal',
            'texto_sin_plazo' => 'Para tu reserva del :dia a las :hora, :que, número :code. Escríbenos y lo vemos contigo.',
            'boton' => 'Escribirnos por WhatsApp',
            'mensaje' => 'Hola, quiero cambiar o cancelar mi reserva :code del :dia a las :hora.',
            'mensaje_titulo' => 'Te lo dejamos escrito:',
            'llamar' => 'Llamar al :telefono',
        ],
        // T5f: «Reservar otra vez» (la compra, ya situada en la última visita), la bienvenida de la cuenta sin reservas y
        // el hueco de un bloque que no se pudo pintar. Los de sin conexión, en `mi_cuenta_alta`: salen también sin sesión.
        'otra_vez' => [
            'titulo' => 'Reservar otra vez',
            'boton' => 'Elegir día',
        ],
        'bienvenida' => [
            'titulo' => 'Tu cuenta está lista',
            'texto' => 'Tu QR ya vale: con él entras siempre, sin papeles. Solo falta elegir cuándo venir.',
            'boton' => 'Reserva tu primera visita',
        ],
        'hueco' => '«:nombre» no se ha podido cargar. Recarga la página.',
        // «Antes de venir» (T5c, `#776`): las tareas de la reserva, con su plazo real. Los de cada tarea (título, nota,
        // texto, botón) los compone el SERVIDOR (`Http\Cuenta\AntesDeVenir`) con estos textos, y la isla de las páginas
        // dice los mismos; el bloque solo pinta.
        'antes' => [
            'titulo' => 'Antes de venir',
            'siguiente' => 'Siguiente',
            'siguiente_chip' => 'Siguiente: :n',
            'hechas' => ':a de :b hecho',
            'ver_otra' => 'Ver la otra',
            'ver_mas' => 'Ver las :n',
            'ver_menos' => 'Ver menos',
            'hecho' => 'Hecho',
            'todo_listo' => 'Todo listo para el :dia',
            'para_el' => 'Para el :dia',
            'hoy' => 'Hoy a las :hora',
            'formulario' => [
                'titulo' => 'Formulario de invitados',
                'nota' => 'Hasta el :dia',
                'texto' => 'Formulario de invitados, hasta el :dia: quién viene, edades y alergias.',
                'texto_sin_plazo' => 'Formulario de invitados: quién viene, edades y alergias.',
                'boton' => 'Rellenar',
                'boton_hecho' => 'Ver o cambiar',
                'linea' => 'Rellena el formulario de invitados, hasta el :dia.',
                'linea_sin_plazo' => 'Rellena el formulario de invitados.',
                'boton_isla' => 'Rellenar el formulario',
            ],
            'invitacion' => [
                'titulo' => 'Invitación',
                'nota' => ':si de :total confirmados',
                'texto' => 'Invitación: los padres confirman y firman ellos. :si de :total confirmados.',
                'boton' => 'Compartir por WhatsApp',
                'crear' => 'Crear la invitación',
                'linea' => 'Comparte la invitación: :si de :total confirmados.',
                'boton_isla' => 'Compartir la invitación',
            ],
            'extras' => [
                'titulo' => 'Extras',
                'texto' => 'Y si quieres: :lista. Se pagan el día de la fiesta.',
                'hasta' => ':nombres, hasta el :dia',
                'mismo_dia' => ':nombres, hasta el mismo día',
                'y' => ' y ',
                // Con más de tres, cuántos y cuándo cierra el primero (el mockup nombra tres; ocho nombres saturan).
                'muchos' => 'Y si quieres: :n extras para la fiesta, que se añaden hasta el :dia. Se pagan el día de la fiesta.',
                'muchos_mismo_dia' => 'Y si quieres: :n extras para la fiesta, que se añaden hasta el mismo día. Se pagan el día de la fiesta.',
                'muchos_plazos' => 'Y si quieres: :n extras para la fiesta, cada uno con su plazo; el primero cierra el :dia. Se pagan el día de la fiesta.',
                'boton' => 'Añadir extras',
            ],
            // «Añade a los menores» (T5d, `#777`; por grupos desde el zip (6), Z6g·2: «a tu cargo», no «tus hijos»): en una
            // entrada, si la instalación firma el descargo dentro.
            'hijos' => [
                'titulo' => 'Añade a los menores',
                'nota' => 'Un minuto',
                'texto' => 'Añade a los menores a tu cargo: nombre y fecha de nacimiento, y firmas por ellos. Un minuto, y en la puerta solo enseñas el QR.',
                'boton' => 'Añadir',
                'linea' => 'Añade a los menores a tu cargo y firma por ellos: en la puerta solo enseñas el QR.',
                'boton_isla' => 'Añadir menores',
                // `#825`: en un producto en el que puede entrar un adulto, opcional (ni «Siguiente» ni el punto del menú).
                'opcional' => '¿Vienen menores? Firma por ellos antes y en la puerta solo enseñas el QR.',
            ],
            'autorizaciones' => [
                'texto' => 'Autorizaciones: :firmadas de :total firmadas. Las que falten se firman en la puerta.',
                'texto_sin_total' => '{1} Autorizaciones: 1 firmada. Las que falten se firman en la puerta.|[2,*] Autorizaciones: :firmadas firmadas. Las que falten se firman en la puerta.',
                'texto_ninguna' => 'Autorizaciones: aún no hay ninguna firmada. Se firman con la invitación o en la puerta.',
                'boton' => 'Ver quién falta',
            ],
        ],
        // Quién viene contigo, Añade a los menores y la ficha de cada uno (T5d, `#777`; Z6g·2): los del mockup (`PMC.T.quien`,
        // `hijos`) y, para la ficha —que no dibuja—, los del sistema. Sin apellidos (`#773`·a); las cinco relaciones (b).
        'quien' => [
            'titulo' => 'Quién viene contigo',
            'hijos' => 'Menores a tu cargo',
            'anadir' => 'Añadir',
            'adultos' => 'Otros adultos: cada uno firma el suyo, desde casa o en el mostrador.',
            'firmado' => 'firmado',
            'falta_firma' => 'falta su firma',
            'anio' => ':n año',
            'anios' => ':n años',
        ],
        'hijos' => [
            'titulo' => 'Añade a los menores a tu cargo',
            'nombre' => 'Nombre',
            'nacimiento' => 'Fecha de nacimiento',
            'pista_fecha' => 'DD/MM/AAAA',
            'soy_su' => 'Soy su',
            'relaciones' => [
                'father' => 'Padre',
                'mother' => 'Madre',
                'legal_guardian' => 'Tutor o tutora legal',
                'grandparent' => 'Abuelo o abuela',
                'other' => 'Otra relación',
            ],
            'otro' => 'Añadir otro menor',
            'quitar' => 'Quitar',
            'hijo_n' => 'Menor :n',
            'casilla' => 'Acepto el descargo de responsabilidad en su nombre.',
            'leer' => 'Leer el descargo',
            'boton' => 'Guardar',
            'guardando' => 'Guardando',
            'listo' => 'Guardado. En la puerta salen con tu QR.',
            'errores' => [
                'nombre' => 'Escribe su nombre.',
                'fecha' => 'Revisa la fecha: día, mes y año.',
                'relacion' => 'Elige qué eres suyo.',
                'descargo' => 'Marca la casilla para guardar.',
                'descargo_nuevo' => 'El descargo ha cambiado: léelo y vuelve a marcar la casilla.',
            ],
        ],
        'hijo' => [
            'nacido' => 'nació el :fecha',
            'relacion' => 'eres su :relacion',
            'adulto' => ':nombre ya tiene 18 años: el descargo lo firma por su cuenta.',
            'firmada' => 'Descargo firmado el :fecha · versión :version',
            'anterior' => 'El descargo ha cambiado desde que lo firmaste: vuelve a firmarlo en su nombre.',
            'sin_firma' => 'Aún no has firmado el descargo en nombre de :nombre.',
            'verificar' => 'Para firmar en nombre de :nombre, confirma antes tu correo con el enlace que te enviamos.',
            'pdf' => 'Descargar el descargo firmado',
            'firmar' => 'Firmar en su nombre',
            'firmando' => 'Firmando',
            'firmado_ok' => 'Descargo firmado en nombre de :nombre',
            'fallo' => 'No se ha podido firmar. Inténtalo de nuevo.',
            'quitar' => 'Quitar de tu cuenta',
            'pregunta' => '¿Quitar a :nombre de tu cuenta?',
            'pregunta_texto' => 'Dejará de salir con tu QR. Si firmaste su descargo, la firma se conserva, como exige la ley.',
            'quitar_si' => 'Sí, quitar',
            'quitar_no' => 'Dejarlo como está',
            'quitado' => ':nombre ya no está en tu cuenta',
        ],
        // Ajustes y sus pasos (T5e, `#778`): los del mockup (`PMC.T.ajustes` y `propuesta`) y, con las piezas del sistema
        // (`#773`·d), los que la verdad añade: el código que confirman cuatro gestiones (A3b, `#857`; antes, la contraseña),
        // el correo pendiente, desvincular Google, la analítica y la encuesta, tu descargo y por qué no se borra con una
        // reserva viva. Sin Apple ni «Descargar el recibo» (`#773`·c).
        'ajustes' => [
            'titulo' => 'Ajustes',
            'datos' => 'Tus datos',
            'acceso' => 'Acceso',
            'privacidad' => 'Privacidad',
            'recibos' => 'Recibos',
            'nombre' => 'Nombre',
            'telefono' => 'Teléfono',
            'idioma' => 'Idioma',
            'idioma_pista' => 'En el que te escribimos.',
            'guardar' => 'Guardar los cambios',
            'guardando' => 'Guardando',
            'guardado' => 'Guardado',
            'no_guardado' => 'No se ha podido guardar. Inténtalo de nuevo.',
            'correo' => 'Correo',
            'correo_pista' => 'El correo se cambia con un código al nuevo buzón.',
            'correo_pendiente' => 'Esperando a que confirmes :correo',
            'cambiar' => 'Cambiar',
            // Los nombres ENTEROS de los enlaces cortos de las filas (para el lector de pantalla: WCAG 2.5.3).
            'cambiar_correo' => 'Cambiar el correo',
            'firmar_descargo' => 'Firmar tu descargo',
            'descargar_descargo' => 'Descargar tu descargo firmado',
            'google_vinculado' => 'Google: vinculado',
            'google_vincular' => 'Vincular Google',
            'vincular' => 'Vincular',
            'desvincular' => 'Desvincular',
            // La primera fila de «Acceso» (el zip (6), Z6g·2): cómo se entra, dicho una vez, sin nada que cambiar.
            'acceso_codigo' => 'Entras con un código a tu correo',
            'acceso_sin' => 'Sin contraseña',
            'otras' => 'Cerrar sesión en otros dispositivos',
            'cerrar_corto' => 'Cerrar',
            'novedades' => 'Novedades del parque',
            'analitica' => 'Tu navegación, con tu cuenta',
            'analitica_pista' => 'Unimos lo que haces en la web a tu cuenta para mejorarla, solo si aceptaste «análisis» en las cookies. Apágalo y lo separamos, y pedimos que se borre.',
            'encuestas' => 'La encuesta después de venir',
            'encuestas_pista' => 'Un correo al día siguiente, sin ofertas: qué tal fue.',
            'si' => 'Sí',
            'no' => 'No',
            'descargo' => 'Tu descargo firmado',
            'firmado_el' => 'Firmado el :fecha · versión :version',
            'firmar' => 'Firmar',
            'descargar' => 'Descargar',
            'mis_datos' => 'Descargar mis datos',
            'tus_datos' => 'Tus datos',
            'descargado' => ':que: descargado',
            'borrar' => 'Borrar mi cuenta',
            'sin_recibos' => 'Aún no hay recibos.',
            'cerrar' => 'Cerrar sesión',
        ],
        // Confirmar con un código al correo lo sensible de Ajustes (A3b del acceso con código, `#857`; antes, la contraseña):
        // la acción del paso lo pide y después lo usa.
        'codigo' => [
            'para' => 'Para confirmarlo, te enviaremos un código a :correo.',
            'enviar' => 'Enviarme el código',
        ],
        // El correo nuevo, en tres tiempos: el nuevo y el código que confirma que eres tú (al de ahora); después, el que
        // llega al NUEVO. El cambio pendiente dura 60 min; cada código, 10.
        'correo' => [
            'titulo' => 'Correo',
            'texto' => 'Primero confirmas que eres tú con un código a tu correo de ahora; después, el nuevo con el suyo. Hasta entonces sigues entrando con el de siempre.',
            'nuevo' => 'Correo nuevo',
            'enviar' => 'Enviar el código al correo nuevo',
            'enviando' => 'Enviando el código',
            'enviado' => 'Falta confirmar :nuevo con el código que le hemos enviado. Hasta entonces sigues entrando con :actual.',
            'caduca' => 'El cambio caduca en :minutos min.',
            'caducado' => 'El cambio ha caducado: pide otro código.',
            'confirmar' => 'Confirmar el correo',
            'confirmando' => 'Confirmando',
            'confirmado' => 'Correo cambiado',
            'cancelar' => 'Cancelar el cambio',
            'cancelado' => 'Cambio de correo cancelado',
            'mismo' => 'Es el correo que ya tienes.',
        ],
        'otras_sesiones' => [
            'titulo' => 'Cerrar sesión en otros dispositivos',
            'texto' => 'Si crees que alguien más usa tu cuenta, ciérrala en los demás móviles y ordenadores. En este sigues dentro.',
            'boton' => 'Cerrar las otras sesiones',
            'cerrando' => 'Cerrando',
            'hecho' => 'Hemos cerrado la sesión en tus otros dispositivos',
        ],
        'desvincular' => [
            'titulo' => 'Desvincular Google',
            'texto' => 'Ya no podrás entrar con Google (:correo). Seguirás entrando con un código a tu correo.',
            'boton' => 'Desvincular',
            'cargando' => 'Desvinculando',
            'hecho' => 'Google: desvinculado',
        ],
        'descargo' => [
            'titulo' => 'Tu descargo',
            'sin_firma' => 'Aún no lo has firmado.',
            'anterior' => 'El descargo ha cambiado desde que lo firmaste: vuelve a firmarlo.',
            'firmar' => 'Firmar',
            'firmado' => 'Descargo firmado',
            'errores' => [
                'casilla' => 'Marca la casilla para firmar.',
            ],
        ],
        // Los avisos de la cuenta arriba de Mi cuenta (T5e·2, `#779`), los del índice del cajón con las piezas del sistema
        // (`#773`·d): confirmar el correo (con el reenvío y su cupo), firmar tu descargo y el de los menores a tu cargo.
        'avisos' => [
            'verificar' => 'Confirma tu correo con el enlace que te enviamos.',
            'verificar_descargo' => 'Tu descargo quedará firmado al confirmarlo.',
            'reenviar' => 'Reenviar el correo',
            'reenviar_en' => 'Reenviar en :s s',
            'quedan' => 'Reenvíos que quedan: :n.',
            'limite' => 'Has llegado al límite de reenvíos: mira en el correo no deseado o inténtalo más tarde.',
            'reenviado' => 'Te hemos reenviado el correo',
            'firmar' => 'Te falta firmar el descargo de responsabilidad.',
            'firmar_nuevo' => 'El descargo ha cambiado desde que lo firmaste: vuelve a firmarlo.',
            'firmar_boton' => 'Firmar',
            'hijos' => 'Falta la firma del descargo de alguno de los menores a tu cargo.',
            'hijos_boton' => 'Ver a los menores',
        ],
        'borrar' => [
            'titulo' => 'Borrar tu cuenta',
            // Lo que hace `User::anonymize()` (`RGPD-01`), no lo que decía el mockup («se borran tus reservas y tus recibos»:
            // los pedidos se conservan sin tu identidad, para las facturas).
            'texto' => 'Borramos tu nombre, tu correo y tu teléfono; los menores a tu cargo salen de la cuenta y se cierra tu sesión. De tus pedidos guardamos lo mínimo para las facturas, sin tu nombre, como pide la ley. No se puede deshacer.',
            'reserva' => 'Tienes una reserva el :dia a las :hora. Mientras tengas una por celebrar, la cuenta no se puede borrar: cuando pase, o si se cancela, sí.',
            'casilla' => 'Entiendo que no se puede deshacer.',
            'boton' => 'Borrar mi cuenta',
            'borrando' => 'Borrando tu cuenta',
            'no' => 'Dejarlo como está',
        ],
    ],
];
