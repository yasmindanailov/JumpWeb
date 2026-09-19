<?php

return [
    // Subtítulo bajo el logotipo del panel (#215; el texto lo fija `#461`, del owner).
    'panel_subtitle' => 'Administración',

    'clusters' => [
        'configuracion' => 'Configuración',
    ],

    // #219 — «Ajustes»: la puerta ÚNICA a las pantallas de puesta en marcha, que salieron
    // del menú lateral. Cada tarjeta lleva una línea de qué hace: a estas pantallas se entra
    // dos veces al año y su nombre, solo, no basta para saber cuál es la que buscas.
    // #224 — el buscador del panel. «screens» es la categoría de resultados que Filament
    // no trae de serie: pantallas, no registros.
    'search' => [
        'screens' => 'Pantallas',
        'screen_detail' => 'Qué es',
    ],

    'hub' => [
        'nav_label' => 'Ajustes',
        'title' => 'Ajustes',
        'subheading' => 'Lo que se configura una vez y no hace falta en el día a día.',
        'areas' => [
            'sales' => 'Precios y productos',
            'schedule' => 'Horarios y aforo',
            'web' => 'Contenido web',
            'system' => 'Sistema',
        ],
        'items' => [
            'catalog' => ['label' => 'Catálogo', 'description' => 'Entradas, packs y complementos que se venden.'],
            'rate_types' => ['label' => 'Tarifas', 'description' => 'Tipos de precio y a qué días se aplica cada uno.'],
            'zones' => ['label' => 'Zonas', 'description' => 'Áreas del recinto, su aforo y su color.'],
            'weekly_schedule' => ['label' => 'Horario semanal', 'description' => 'A qué hora se abre y se cierra cada día de la semana.'],
            'seasons' => ['label' => 'Temporadas', 'description' => 'Periodos con un horario distinto del habitual.'],
            'special_dates' => ['label' => 'Fechas especiales', 'description' => 'Días sueltos: festivos, cierres y horarios excepcionales.'],
            'slots' => ['label' => 'Franjas', 'description' => 'Las sesiones concretas que se ponen a la venta.'],
            'slot_templates' => ['label' => 'Plantillas de franja', 'description' => 'La receta con la que se generan las franjas de cada semana.'],
            'attractions' => ['label' => 'Atracciones', 'description' => 'Lo que se enseña de cada zona en la web.'],
            'landing_services' => ['label' => 'Servicios (web)', 'description' => 'Las secciones de la página de servicios.'],
            'faqs' => ['label' => 'Preguntas frecuentes', 'description' => 'Las preguntas y respuestas que se publican.'],
            'testimonials' => ['label' => 'Opiniones propias', 'description' => 'Lo que ve quien no acepta cookies de terceros.'],
            'offers' => ['label' => 'Ofertas', 'description' => 'Promociones informativas del aviso flotante.'],
            'bar_images' => ['label' => 'El bar', 'description' => 'La carta del bar y la foto del local que se publican en la web.'],
            'park_rules' => ['label' => 'Normas', 'description' => 'Las normas del recinto que se publican en la web.'],
            'pages' => ['label' => 'Páginas legales', 'description' => 'Aviso legal, privacidad, cookies y condiciones.'],
            'settings' => ['label' => 'Configuración', 'description' => 'Datos del negocio, fiscales, venta, puerta y pagos.'],
            // `#320`: la puerta sale del menú lateral y su puerta de entrada pasa a ser ésta. La
            // descripción menciona «entrada», «validar» y «escanear» porque el buscador global busca
            // también dentro de la descripción, y nadie recuerda cómo se llama una pantalla.
            'puerta' => ['label' => 'Puerta', 'description' => 'Validar la entrada al parque: escanear el carné y ver la ficha del cliente.'],
            'team' => ['label' => 'Equipo', 'description' => 'Las cuentas de quien trabaja aquí y entra al panel.'],
            'roles' => ['label' => 'Roles y permisos', 'description' => 'Qué puede hacer cada rol dentro del panel.'],
            'audit' => ['label' => 'Incidencias', 'description' => 'Registro de acciones críticas y avisos del sistema.'],
            'maintenance' => ['label' => 'Mantenimiento', 'description' => 'Apagar la web, las reservas o una página concreta.'],
        ],
    ],

    'puerta' => [
        // #219: el rotulo del MENU es «Puerta» (el sitio); «title» sigue siendo
        // el titulo de la pantalla, que describe la accion.
        'nav_label' => 'Puerta',
        'validar' => [
            'title' => 'Validar registro',
            'intro' => 'Introduce el email o el teléfono del cliente para comprobar si está registrado y si ha firmado el descargo.',
            'back_to_panel' => 'Volver al panel',
            'input_placeholder' => 'Email o teléfono',
            'button' => 'Verificar',
            'button_loading' => 'Verificando…',
            'new_search' => 'Nueva búsqueda',
            'searched_for' => 'Resultado para',

            // Resultados (3 estados, decisión #126). El estado 2-en-1 `registered` se usa cuando la
            // comprobación de waiver está DESACTIVADA (#216): solo importa si tiene cuenta o no.
            'registered_with_waiver' => 'Registrado',
            'waiver_date' => 'Descargo aceptado el :date.',
            'registered_no_waiver' => 'Registrado, falta firmar el descargo',
            'registered_no_waiver_cta' => 'Pásale la tablet al cliente para que firme el descargo antes de saltar.',
            'registered' => 'Cliente registrado',
            'registered_sub' => 'Tiene cuenta en el sistema.',
            'not_registered' => 'No registrado',

            // Edge cases.
            'invalid_input' => 'Introduce un email o un teléfono válido.',
            'rate_limited' => 'Demasiadas búsquedas seguidas. Espera un minuto e inténtalo de nuevo.',

            // Fase 6 · subsistema A (`specs/identidad-qr-puerta.md` §4.5, §4.6, §9.2 A·5): el QR del
            // cliente por el mismo input, el limitador de la búsqueda tecleada y la FICHA.
            // ⚠️ De cara a personas la palabra es «QR» (`[DECIDIDO owner]`, `#217`); las CLAVES siguen
            // diciendo `card` porque el nombre técnico —`customer_cards`, `puerta.card_scanned`— no
            // cambia. Y aquí se dice «QR del cliente» y no «QR» a secas: en esta pantalla convive con
            // el QR de la ENTRADA, y el empleado tiene que saber cuál se le está nombrando.
            'input_placeholder_card' => 'Escanea el QR del cliente, o escribe el email o el teléfono',
            'card_query' => 'QR escaneado',
            'card_revoked' => 'QR caducado',
            'card_revoked_cta' => 'Este QR ya no vale (se renovó o se revocó). Busca al cliente por email o teléfono.',
            'card_unknown' => 'QR no reconocido',
            'card_unknown_cta' => 'El código tiene forma de QR de cliente pero no está en el sistema. Busca por email o teléfono.',
            'lookup_limited' => 'Demasiadas búsquedas tecleadas en una hora. El escaneo sigue funcionando; para buscar por email o teléfono espera o avisa a un responsable.',
            'profile' => [
                'title' => 'Ficha de puerta',
                'waiver_signed' => 'Descargo firmado el :date',
                'waiver_outdated' => 'versión anterior — deja pasar',
                'waiver_missing' => 'Sin descargo firmado',
                'waiver_pending_hint' => 'Aceptó el descargo al registrarse y solo le falta verificar su correo. Puedes darlo por firmado con la persona delante.',
                'waiver_declare' => 'Dar por firmada',
                'waiver_declare_confirm' => '¿:name está delante y te dice que ha leído y acepta el descargo de responsabilidad? Quedará firmado y con tu nombre como quien da fe.',
                'waiver_declare_stale' => 'El texto del descargo ha cambiado desde que lo aceptó: hay que pasarle la tablet para que lea y firme el nuevo.',
                'card_active' => 'QR activo',
                'card_revoked' => 'QR revocado',
                'card_none' => 'Sin QR',
                'today' => 'Hoy',
                'today_empty' => 'Sin reserva hoy. Puede comprar en puerta.',
                'window' => 'Otros días (±:days)',
                'window_note' => 'Tiene reserva, pero otro día: no es «no tiene nada».',
                'entries' => '{1}:n × entrada|[2,*]:n × entradas',
                'guests' => '{1}:n invitado|[2,*]:n invitados',
                'pending_gate' => 'Pendiente de cobrar en puerta: :amount €',
                'paid' => 'Pagado: :amount € (:method)',
                'nothing_pending' => 'Nada pendiente de cobrar',
                // El LIBRO (T3·2): el saldo de la RESERVA por clase. «A devolver» es dinero que el
                // empleado tiene que devolver y lleva la MISMA alerta que «a cobrar» (D-T3·8).
                'refund_at_gate' => 'Pendiente de devolver en puerta: :amount €',
                'under_review' => 'Dinero en revisión: el libro de este pedido no cuadra. Consúltalo en el panel antes de cobrar o devolver.',
                // Fiesta MIXTA (T3 · E, `specs/cumple-mixto.md` §23.2): lo ESCRITO del suplemento —
                // es lo que se cobra (`PAY-19`) y ya está sumado dentro de `pending_gate`. Desde la
                // T4, también el DESCUENTO (neto con signo) y el «a tu favor», que se liquida en mano.
                'mixed_party_line' => ':count × :name · :unit € por invitado',
                'mixed_party_total' => 'Suplemento fiesta mixta: :amount €',
                'mixed_party_discount_total' => 'Descuento fiesta mixta: −:amount €',
                'booked_on' => 'Reservado el :when',
                'paid_on' => 'pagado el :when',
                'method_desk' => 'mostrador',
                'method_redsys' => 'web',
                // ⚠️ `minors_on_line` («Menores en esta línea: :list») se retiró el 2026-08-28 con el
                // rediseño: componía la lista en UNA cadena y hoy cada menor es su propia píldora con
                // su `data-gate-minor-*`. Lo que queda es solo el rótulo.
                'minors_on_line_label' => 'Menores en esta línea',
                'minors' => 'Menores a cargo',
                'guest_minors' => 'Menores INVITADOS (justificante)',
                'minors_empty' => 'Sin menores declarados.',
                'minor' => ':age años',
                // ⚠️ `#320`: `minor_waiver_current` YA NO SE PINTA (solo se rotula la excepción,
                // `WaiverStatus::minorStateIsNoteworthy()`) y aun así SE CONSERVA: la clave se compone
                // dinámicamente (`'minor_waiver_'.$estado`), así que si algún día vuelve a pasar
                // `current` —otra superficie, o el owner revirtiendo— sin fila se pintaría el
                // identificador en crudo en la pantalla de puerta. Es el suelo de un `__()` dinámico.
                'minor_waiver_current' => 'descargo ✓',
                'minor_waiver_outdated' => 'descargo de versión anterior',
                'minor_waiver_missing' => 'sin descargo',
                // La INVITACIÓN en la puerta (T6·4, `specs/celebracion-e-invitacion.md` §4.8 y
                // §4.5·10). ⚠️ Los tres estados y NINGUNO en rojo: aquí nadie ha roto nada y ninguno
                // impide la fiesta — «sin resolver» es trabajo que se hará en el mostrador si nadie
                // lo adelanta. ⚠️ La clave se compone dinámicamente (`'guest_entry_'.$estado`), así
                // que las tres tienen que existir o se pintaría el identificador en crudo.
                'guest_minors_count' => ':signed de :expected con justificante',
                'guest_entry_signed' => 'firmado',
                'guest_entry_with_adult' => 'viene con un adulto',
                'guest_entry_unresolved' => 'sin resolver',
                'visit_register' => 'Registrar visita',
                'visit_registered' => 'Visita registrada hoy',
                'visit_hint' => 'Es lo que acredita que ha venido (JumpPoints). Una vez por día; volver a pulsar no suma.',
                'veil' => 'Ficha oculta por inactividad — toca para seguir',
                'expires' => 'La ficha se cierra sola a los :minutes min.',

                // Las CABECERAS de las tarjetas de la ficha y cómo se abrió (rediseño del 2026-08-28,
                // `identidad-qr-puerta.md` §9.7 C·5). Cortas a propósito: son títulos de tarjeta, y con
                // la clave en crudo se salían del marco.
                'waiver_section' => 'Descargo',
                'card_section' => 'QR del cliente',
                'visit_section' => 'Visita',
                'via_card' => 'Abierta por QR',
                'via_lookup' => 'Abierta por búsqueda',
                'waiver_disabled' => 'Esta instalación no comprueba el descargo en la puerta.',
            ],
        ],
    ],

    // Calendario unificado (Fase 7.4, decisión #14).
    'calendar' => [
        'nav_label' => 'Calendario',
        'title' => 'Calendario',
        'filter' => [
            'all' => 'Todo',
            'entries' => 'Entradas',
            'packs' => 'Cumpleaños',
        ],
        'legend' => [
            // Disparador de la leyenda plegable (decisión clienta 2026-06-13): la leyenda baja al pie
            // del calendario, plegada por defecto, y se abre con este enlace.
            'toggle' => '¿Qué significan los colores e iconos?',
            'zones' => 'Zonas',
            'packs_note' => '(cumpleaños = color de su zona)',
            // P8: tipos de producto por su icono en el calendario.
            'entry' => 'Entrada',
            'pack' => 'Cumpleaños',
            'finished' => 'Finalizado',
        ],
        // Estado del formulario de reserva post-reserva (#217): tooltip del badge del card Y leyenda.
        'guest_form_ok' => 'Formulario de reserva completo',
        'guest_form_pending' => 'Formulario de reserva pendiente',
        'item_modal' => [
            'heading' => 'Detalle del producto',
            'close' => 'Cerrar',
            'not_found' => 'No se ha encontrado el producto.',
            'field_date' => 'Fecha',
            'field_time' => 'Horario',
            'field_duration' => 'Duración',
            'field_quantity' => 'Cantidad',
            'section_event_data' => 'Datos del evento',
            'section_guests' => 'Formulario de reserva',
            'guests_hint' => 'Imprime la hoja de reserva o abre el pedido para ver el formulario.',
            'guests_hint_empty' => 'El cliente aún no ha rellenado el formulario de reserva.',
            // «Ver formulario»: despliega los datos por-niño en el modal cuando el post-form está completo.
            'guest_form_show' => 'Ver formulario',
            'guest_form_hide' => 'Ocultar formulario',
            'section_customer' => 'Datos del cliente',
            'field_customer_name' => 'Nombre',
            'field_customer_email' => 'Email',
            'field_customer_phone' => 'Teléfono',
            'print_slip' => 'Imprimir reserva',
            'view_order' => 'Ver pedido :code',
        ],
        // Resumen del día imprimible (PDF A4 horizontal, decisión #184). El PDF se
        // renderiza SIEMPRE en español (lo fuerza el controlador); en el panel solo
        // se muestran las claves del botón/modal (btn, modal_*, date_*, type_*).
        'day_summary' => [
            'btn' => 'Imprimir resumen del día',
            'modal_heading' => 'Imprimir resumen del día',
            'submit' => 'Imprimir',
            'date_label' => 'Fecha',
            'type_label' => 'Qué incluir',
            'type_all' => 'Todas',
            'type_packs' => 'Solo cumpleaños',
            'type_entries' => 'Solo entradas',
            // PDF.
            'title' => 'Resumen del día',
            'count_reservations' => '{0}Sin reservas|{1}:count reserva|[2,*]:count reservas',
            'total_guests' => ':count invitados',
            'total_entries' => ':count entradas',
            'col_time' => 'Hora',
            'col_type' => 'Tipo',
            'col_product' => 'Producto',
            'col_customer' => 'Cliente',
            'col_phone' => 'Teléfono',
            'col_qty' => 'Cantidad',
            'col_celebrant' => 'Homenajeado',
            'type_pack' => 'Cumpleaños',
            'type_entry' => 'Entrada',
            'empty' => 'No hay reservas para este día.',
            'printed_at' => 'Impreso el :when',
        ],
    ],

    // Dashboard del panel (Fase 7.4 iter2, decisión #14): widgets operativos +
    // filtro de periodo compartido (hoy / esta semana / este mes).
    'dashboard' => [
        // #219: «Escritorio» no decia que hay dentro; esta pantalla contesta la
        // pregunta con la que se abre el panel cada manana.
        'nav_label' => 'Hoy',
        'title' => 'Hoy',
        'period' => [
            'label' => 'Periodo',
            'today' => 'Hoy',
            'week' => 'Esta semana',
            'month' => 'Este mes',
        ],
        'stats' => [
            'reservations' => 'Reservas',
            'occupancy' => 'Ocupación',
            'occupancy_value' => ':count plazas',
        ],
        'reservations' => [
            'heading' => 'Reservas',
            'empty' => 'No hay reservas en este periodo.',
            'col_time' => 'Cuándo',
            'col_product' => 'Producto',
            'col_customer' => 'Cliente',
            'col_quantity' => 'Cantidad',
            'col_status' => 'Estado',
            'col_form' => 'Formulario',
        ],
        'status' => [
            'active' => 'Activa',
            'finished' => 'Finalizado',
        ],
        // Estado del formulario de reserva por-niño (#217) en la tabla del escritorio.
        'form_status' => [
            'ok' => 'Enviado',
            'pending' => 'Pendiente',
        ],
    ],

    // Pedidos (Fase 7.1b, decisión #127).
    'orders' => [
        'nav_label' => 'Pedidos',
        'model_label_singular' => 'pedido',
        'model_label_plural' => 'Pedidos',

        // Pedido manual desde back-office (Fase 7.3, #120).
        'create_manual' => [
            'nav_label' => 'Crear pedido',
            'title' => 'Crear pedido manual',
            'step_customer' => 'Cliente',
            'step_products' => 'Producto',
            // `#462` — los pasos nuevos. «Cuándo» agrupa cuántos + qué día + qué hora: son las tres
            // preguntas de una misma cosa, y la cantidad va delante porque las horas se ofrecen con
            // sus plazas (`[DECIDIDO owner]` D1).
            'step_when' => 'Cuándo',
            'step_details' => 'Datos',
            'step_extras' => 'Extras',
            'step_cart' => 'Carrito',
            'step_skipped' => 'sin nada que rellenar',
            'step_payment' => 'Pago',
            'customer' => 'Cliente',
            'customer_search_placeholder' => 'Busca por email, teléfono o nombre',
            'customer_help' => 'Selecciona al cliente para el que creas el pedido. Si tiene email, recibirá la confirmación por correo.',
            'customer_no_email' => '(sin email)',
            'customer_phone' => 'Teléfono del cliente',
            'customer_phone_help' => 'Esta cuenta no tiene teléfono. Pídeselo al cliente y escríbelo: es como el parque contacta con él el día de la visita.',
            'customer_phone_required' => 'Antes de añadir nada al carrito, escribe el teléfono del cliente en el primer paso.',
            'customer_phone_missing_warning' => 'El pedido se ha creado, pero este cliente sigue sin teléfono. Si puedes, pídeselo: es como el parque contacta con él el día de la visita.',
            'register_cta' => '¿No tiene cuenta? Registrar al cliente',
            'register_heading' => 'Registrar cliente nuevo',
            'register_description' => 'Crea la cuenta del cliente al momento. El email es opcional: si lo indicas, le llegará un correo con una contraseña temporal para ver sus pedidos. Sin email, la reserva queda guardada en el panel con su teléfono (no recibe correos).',
            'register_name' => 'Nombre del cliente',
            'register_email' => 'Email del cliente',
            'register_email_optional' => 'Opcional. Si no lo tienes, déjalo vacío: la reserva quedará en el sistema con su teléfono (no recibirá correos y el enlace del formulario se copia desde el icono del producto).',
            'register_phone' => 'Teléfono del cliente',
            'register_privacy' => 'He informado al cliente de la política de privacidad y crea su cuenta con su consentimiento.',
            'register_privacy_required' => 'Debes confirmar que has informado al cliente de la política de privacidad.',
            'register_waiver' => 'Le he enseñado al cliente el descargo de responsabilidad vigente y declara que lo acepta.',
            'register_waiver_text' => 'Texto vigente del descargo de responsabilidad (v:version) — enséñaselo al cliente',
            'register_waiver_help' => 'Solo en modo interno con versión publicada. Sin marcarla NO se registra ninguna firma: el cliente firmará desde su cuenta, y la puerta le pedirá la tablet mientras tanto.',
            'register_submit' => 'Crear cuenta',
            'register_done' => 'Cuenta creada para :email y seleccionada. Le hemos enviado su contraseña por correo.',
            'register_no_email_done' => 'Cliente «:name» creado sin email y seleccionado. No recibirá correos; si es un cumpleaños, comparte el enlace del formulario por WhatsApp desde el icono del producto.',
            'register_exists' => 'Ese email ya tenía cuenta: la hemos seleccionado (no se ha enviado ningún correo).',
            'register_throttled' => 'Demasiados intentos. Inténtalo más tarde.',
            'register_failed' => 'No se pudo crear la cuenta. Inténtalo de nuevo en un momento.',
            'register_email_failed' => 'La cuenta se creó, pero no se pudo enviar el email de bienvenida con la contraseña. Avisa al cliente o resetea su contraseña cuando el correo vuelva a funcionar.',
            'register_phone_required' => 'El teléfono es obligatorio: es el identificador del cliente cuando no hay email.',
            // Aviso de duplicado por teléfono (alta sin email): «avisar y dejar elegir».
            'phone_match_title' => 'Ya existe un cliente con ese teléfono',
            'phone_match_help' => 'Encontramos cliente(s) con el teléfono :phone. ¿Quieres usar uno existente o crear uno nuevo?',
            'phone_match_use' => 'Usar a :name',
            'phone_match_create_new' => 'Crear cliente nuevo de todos modos',
            'phone_match_dismiss' => 'Cancelar',
            'phone_match_used' => 'Cliente existente seleccionado.',
            'add_product' => 'Añadir producto',
            'product' => 'Producto',
            'date' => 'Fecha',
            'time' => 'Franja horaria',
            'time_help' => 'Solo se muestran las franjas con plazas disponibles.',
            // `#240`: unos chips sin opciones no pintan nada, así que el hueco lo explica la ayuda.
            'time_pick_date_first' => 'Elige primero un día para ver las franjas.',
            // `#464`: el día se elige en el calendario, que es el único control de fecha desde que
            // la tira se retiró. Las flechas saltan al mes OFRECIBLE anterior/siguiente, así que su
            // nombre dice «mes» y no «anterior» a secas.
            'calendar_prev' => 'Mes anterior con fechas',
            'calendar_next' => 'Mes siguiente con fechas',
            'no_times_for_date' => 'No hay franjas disponibles para esta fecha. Regenera o abre franjas, o elige otro día.',
            'quantity' => 'Cantidad',
            'guests' => 'Invitados',
            'qty_out_of_range' => 'La cantidad debe estar entre :min y :max.',
            // `#588`, `[DECIDIDO owner]`: en el mostrador la edad fuera de tramo AVISA y deja añadir.
            'celebrant_age_warning' => 'La edad del cumpleañero no encaja en este pack',
            // `#329` — el gemelo de D7 al CREAR el pedido. La ayuda dice el precio a propósito: por
            // debajo del mínimo la escala de tramos no baja más, y el operador tiene que saberlo
            // ANTES de vender, no al ver el total.
            'below_minimum_label' => 'Vender por debajo del mínimo del pack (queda registrado)',
            'guardian_label' => 'Viene algún menor que no está a cargo del cliente',
            'guardian_help' => 'Su padre, madre o tutor tendrá que firmar una autorización. Al cobrar le mandamos al cliente el enlace para pasárselo.',
            'guardian_required' => 'Este producto necesita SIEMPRE la autorización firmada del padre, madre o tutor de cada menor. Al cobrar le mandamos el enlace al cliente.',
            'below_minimum_help' => 'El mínimo de este producto es :min. Al activarlo puedes bajar hasta 1; se cobra al precio del tramo más bajo y la excepción queda en el historial del pedido.',
            'below_minimum_active' => 'Mínimo rebajado a 1 (el del producto es :min). Se cobra al precio del tramo de :min y queda registrado.',
            'seats' => ':n plazas',
            'addons' => 'Complementos',
            'addon' => 'Complemento',
            'addon_qty' => 'Cantidad',
            'add_addon' => 'Añadir complemento',
            'add_to_cart' => 'Añadir al carrito',
            'line_added' => 'Producto añadido al pedido.',
            'line_incomplete' => 'Completa producto, fecha, franja y cantidad antes de añadir.',
            'cart_title' => 'Resumen del pedido',
            'cart_empty_hint' => 'Aún no has añadido productos.',
            'cart_total' => 'Total',
            // P5 (#225, display): aviso de señal en el carrito del alta manual.
            'deposit_line_note' => 'Señal: :amount € · resto en el parque',
            'pay_now_deposit' => 'A cobrar ahora',
            'pay_at_park' => 'Falta por cobrar',
            'deposit_hint' => 'Los productos con señal cobran ahora solo la señal; el resto se cobra en el parque el día del evento.',
            'remove_line' => 'Quitar',
            'payment_method' => 'Método de pago',
            'method_cash' => 'Efectivo',
            'method_datafono' => 'Datáfono',
            'back' => 'Anterior',
            'next' => 'Siguiente',
            // `#462`, `[DECIDIDO owner]`: en el carrito el botón deja de decir «Siguiente» y dice a
            // dónde lleva; y al lado, la puerta para seguir añadiendo.
            'go_to_pay' => 'Ir a pagar',
            'add_more_products' => 'Añadir más productos',

            // `#462` T2 — las TARJETAS de producto. El agrupado por tipo es lo que cierra
            // el crítico C1: en el desplegable plano una entrada y un pack se parecían.
            'product_group' => [
                'entry' => 'Entradas',
                'pack' => 'Packs y celebraciones',
            ],
            'product_minutes' => ':n min',
            'product_guest_range' => ':min–:max invitados',
            'product_from' => 'desde',
            'product_more_info' => 'Más info',
            'product_info_close' => 'Cerrar',
            'product_none' => 'No hay ningún producto a la venta. Revisa el catálogo y las zonas activas.',
            'product_guests' => 'Invitados',
            'confirm' => '¿Cobrar y crear el pedido?',
            'confirm_description' => 'Se registrará el cobro y se creará la reserva del cliente.',
            'submit' => 'Cobrar y crear pedido',
            'no_customer' => 'Selecciona un cliente.',
            'cart_empty' => 'Añade al menos un producto al pedido.',
            'invalid_method' => 'Método de pago no válido.',
            'reservation_failed' => 'No se pudo crear el pedido.',
            // `#466` — EL DESENLACE. La clave `created` del *toast* murió con la redirección a la
            // ficha: lo que decía en una línea que se desvanece lo dice ahora la pantalla entera.
            'done_title' => 'Pedido creado y cobrado',
            'done_charged' => 'Cobrado ahora · :method',
            // `#467` — UNA lista de lo que el cliente tiene que recibir, cada cosa con su estado.
            'done_delivery_title' => 'Lo que recibe el cliente',
            'done_item_confirmation' => 'Confirmación del pedido',
            'done_item_guest_form' => 'Formulario de invitados',
            'done_item_guardian' => 'Justificante de un menor invitado',
            'done_state_sent' => 'Enviado a :email',
            // ⚠️ Dice lo que hay que HACER, no solo que falló: es lo que el operador lee de un vistazo.
            'done_state_by_hand' => 'Entrégalo tú',
            // ⚠️ Sin enlace no hay nada que entregar: la confirmación simplemente no ha salido.
            'done_state_not_sent' => 'No enviado',
            // ⚠️ Este texto es el encargo: con un cliente de agenda (solo teléfono) NO se envía nada,
            // y el operador tiene que enterarse ANTES de despedirlo.
            'done_no_mail_title' => 'Este cliente no tiene correo.',
            'done_no_mail_body' => 'No se le ha enviado nada: ni la confirmación, ni el formulario de invitados, ni el justificante. Entrégale los enlaces por WhatsApp o SMS.',
            'done_dependents_hint' => 'Asígnalos desde la ficha del pedido, en «Asignar menores».',
            'done_another' => 'Crear otro pedido',
            'done_view_order' => 'Ver el pedido',
        ],

        // Columnas de la tabla.
        'col_code' => 'Código',
        'col_customer' => 'Cliente',
        'col_status' => 'Estado',
        'col_operative' => 'Operativa',
        // P3: tooltips de los dos badges de estado en la H1 del detalle (al pasar el ratón).
        'status_badge_tooltip' => 'Estado del pedido: si está pagado, pendiente, cancelado o reembolsado.',
        'operative_badge_tooltip' => 'Estado respecto al evento: si aún no ha empezado, está en curso o ya finalizó.',
        // P1/P10: estado de pago en el Resumen cuando aún NO se ha cobrado. La etiqueta del método ya
        // cobrado (Pagado online / Cobrado (efectivo/datáfono)) vive en `order_financial`, consciente del método.
        'not_paid_yet' => 'Pendiente de pago',
        'col_total' => 'Total',
        'col_paid_at' => 'Pagado el',
        'col_created_at' => 'Creado el',
        'col_expires_at' => 'Caduca el',
        'col_refunded' => 'Reembolso',
        // #179: columna "Pagado" (lo realmente cobrado).
        'col_collected' => 'Pagado',
        // #179: icono de calendario en la card del producto → día de la reserva.
        'btn_view_in_calendar' => 'Ver en el calendario',
        'filter_status' => 'Estado',
        // Filtro "Con reembolso" en la tabla de pedidos (#146): TernaryFilter
        // con etiquetas operativas. El operador puede aislar pedidos con
        // devolución registrada (total o parcial futuro) o excluirlos.
        'filter_refunded' => 'Con reembolso',
        'filter_refunded_all' => 'Todos',
        'filter_refunded_yes' => 'Solo con reembolso',
        'filter_refunded_no' => 'Solo sin reembolso',
        // Badge secundario aditivo al status (#146): aparece en heading del Order
        // y en la columna de la tabla cuando hay reembolso anotado.
        'refunded_badge' => 'Reembolsado',

        // Status del Order (paid/pending/cancelled/refunded/expired).
        // #145: "Completado" (no "Pagado") porque un Order completado puede llevar
        // reembolso registrado y "Pagado + Reembolsado" choca semánticamente. La
        // constante `STATUS_PAID = 'paid'` no cambia; solo el texto operativo.
        // El estado del cobro (Payment) sigue mostrándose como "Pagado" en la card
        // Pagos (`admin.orders.payments.status.paid`) — son dos dimensiones distintas
        // (servicio del pedido vs cobro de la pasarela).
        'status' => [
            'pending' => 'Pendiente',
            // `#588`, `[DECIDIDO owner]`: «Completado» se leía como «ya pasó»; el panel dice lo mismo que el cliente.
            'paid' => 'Confirmado',
            'cancelled' => 'Cancelado',
            'refunded' => 'Reembolsado',
            'expired' => 'Caducado',
        ],

        // Estado operativo del pedido (derivado de si la franja de cada item ya pasó):
        // activa (aún no empezó/terminó) · en curso (alguna finalizada) · finalizada (todas).
        'operative' => [
            'active' => 'Activa',
            'in_progress' => 'En curso',
            'finished' => 'Finalizada',
        ],

        // Página de detalle.
        'heading_id_label' => 'ID',  // label inline antes del código (#136).
        'heading_pedido' => 'Pedido',  // título H1 + browser tab (#132).
        'section_summary' => 'Resumen',
        'section_items' => 'Productos del pedido',
        'no_items' => 'Este pedido no tiene productos.',

        // Badge por item.
        'item_status' => [
            'finished' => 'Finalizado',
            'cancelled' => 'Cancelado',
        ],

        // Hoja de reserva imprimible (PDF A4, decisión #183). El PDF es un
        // documento OPERATIVO para el personal del parque: se renderiza SIEMPRE
        // en español (lo fuerza el controlador) y NUNCA incluye datos de cobro
        // sensibles. `btn_print` es lo único que se muestra en el idioma del
        // panel (tooltip del icono en la sub-card del item).
        'slip' => [
            'btn_print' => 'Imprimir hoja de reserva',
            'print_operational' => 'Hoja de sala (sin precios)',
            'print_with_prices' => 'Con precios y desglose',
            'title' => 'Hoja de reserva',
            'order_ref' => 'Pedido',
            'created_at' => 'Creado el :when',
            'printed_at' => 'Impreso el :when',
            'datetime_heading' => 'Fecha y hora',
            'duration_heading' => 'Duración',
            'guests_heading' => 'Invitados',
            'entries_heading' => 'Entradas',
            'entries_count' => '{1}:count entrada|[2,*]:count entradas',
            'no_slot' => 'Sin franja asignada',
            'addons_heading' => 'Complementos',
            'reservation_data_heading' => 'Datos de la reserva',
            'guests_heading' => 'Formulario de reserva',
            'guests_pending' => 'PENDIENTE: el cliente aún no ha rellenado el formulario. Complétalo a mano.',
            // T6·4: una fila con * trae lo que contestó un padre por la invitación y el anfitrión
            // todavía no ha repasado. ⚠️ En papel se marca con un signo, no con color: la hoja se
            // imprime en blanco y negro y un tono no sobrevive a la fotocopia.
            'guests_proposed' => '* Por la invitación, sin repasar por el cliente.',
            'guest_minors_heading' => 'Menores invitados con justificante',
            'guest_minor' => 'Menor',
            'guest_minor_guardian' => 'Autoriza',
            'guest_minor_state' => 'Justificante',
            'guest_minors_overflow' => 'OJO: :count justificantes firmados para :capacity plazas del pedido. Revísalo con el responsable del grupo.',
            'guest_minor_waiver_current' => 'Firmado',
            'guest_minor_waiver_outdated' => 'Versión anterior — deja pasar',
            'guest_minor_waiver_missing' => 'FALTA',
            'guardian_label' => 'Padre/madre o tutor legal',
            'client_label' => 'Nombre del cliente',
            'prepared_check' => 'Preparado',
            // #225: el resto de la señal (no cobrado online) en el breakdown de la caja.
            // Caja prominente de devolución pendiente (#200), simétrica a la de
            // "A cobrar en puerta" — solo si queda algo por devolver.
            'cancelled_notice' => 'Reserva cancelada',
            // Fiesta MIXTA (T3 · E): la cabecera del bloque; las líneas y avisos reutilizan
            // `admin.orders.mixed_party.*` para que la hoja y la ficha no puedan divergir.
            'mixed_party_heading' => 'Fiesta mixta',
            // T5 adenda 3 (`[DECIDIDO owner]`): la hoja OPERATIVA lleva los HECHOS de la mezcla y
            // ni un euro — los importes, solo en «Con precios y desglose».
            'mixed_party_fact_line' => ':count × :name',
        ],

        // Datos del evento por item (#86, sub-fase 7.2a).
        'event_data_section' => 'Datos del evento',
        'party_data_section' => 'Formulario de reserva',
        'show_more' => 'Ver más',
        'show_less' => 'Ver menos',
        // #225 F3: toggle del desglose ↳ de «A cobrar en el parque» (oculto por defecto).
        'show_breakdown' => 'Ver desglose',
        'hide_breakdown' => 'Ocultar desglose',
        // Badge corto junto al título «Formulario de reserva» (sin duplicarlo); icono ✓/! coherente.
        'guest_badge_ok' => 'Completado',
        // Fiesta MIXTA (`docs/specs/cumple-mixto.md` §12). El suplemento se recalcula SOLO con las
        // edades declaradas; aquí no hay nada que aprobar. Lo que el operador tiene que poder leer
        // son cuatro cosas distintas y por eso son cuatro textos: cuánto está aplicado, si lo
        // escrito se ha quedado atrás, si falta el producto que lo lleva, y si el veredicto aún
        // está a medias.
        'mixed_party' => [
            'title' => 'Fiesta MIXTA: hay invitados de otro tramo de edad',
            'line' => ':count × :name · :unit por invitado',
            // T5 (§25.6·1): la línea del veredicto en la FICHA lleva etiqueta de origen — es la
            // única superficie que mezcla derivado y escrito, y sin ella «2 × Jump · 9,00 €» pegado
            // al aplicado se leía como contradicción (el hallazgo del T0). La hoja y la puerta
            // siguen con `line`: allí todo es ESCRITO y no hay dos fuentes que distinguir.
            'conditions_line' => 'Según las condiciones de esta reserva: :count × :name · :unit por invitado',
            'applied' => 'Suplemento aplicado: :amount · se cobra en el parque',
            // T4 (`specs/cumple-mixto.md` §24.5): el descuento es REAL — la frase de la línea la
            // compone el dominio (`breakdownLabel`); estas tres acompañan al importe.
            'net' => 'Neto por edades: :amount · se liquida en el parque',
            'missing_credit_carrier' => 'No se puede aplicar el descuento: falta el producto que lo lleva («Descuento fiesta mixta» en el catálogo). Mientras falte, el importe queda a favor del cliente y se liquida en el parque.',
            'drift' => 'El suplemento escrito es :written y, con las edades declaradas hoy y las condiciones de esta reserva, correspondería :derived. No se recalcula solo: lo escrito es lo que se le comunicó al cliente.',
            'missing_carrier' => 'No se puede aplicar el suplemento: falta el producto que lo lleva («Suplemento fiesta mixta» en el catálogo). Mientras falte, esta fiesta no cobra nada.',
            'unpriced' => 'No se puede calcular el suplemento: falta el precio de algún pack para ese día.',
            'without_age' => 'Faltan :count edades por declarar: el veredicto todavía puede cambiar.',
            // T5 (§25.6·4): en NEUTRO — lo congelado puede ser un suplemento O un descuento (desde
            // la T4 `$mixHasWritten` incluye el crédito), y «Suplemento congelado» sobre un
            // descuento afirmaba lo contrario de lo que había.
            'frozen' => '{1} Importe por edades congelado: falta :count edad por declarar. No se recalcula —ni arriba ni abajo— hasta que el cliente la complete.|[2,*] Importe por edades congelado: faltan :count edades por declarar. No se recalcula —ni arriba ni abajo— hasta que el cliente las complete todas.',
            'out_of_range' => ':count invitados con una edad sin producto en las condiciones de esta reserva: no se cobra nada por ellos, el cliente tiene que llamar y se resuelve en el parque.',
            'orphaned' => 'Esta reserva no lleva sellada ninguna condición por edad (nació antes de que existiera el sello), así que no hay veredicto que comparar. El suplemento de arriba sigue vivo: es el que se le comunicó al cliente y es lo que se cobra en el parque.',
            'stale_seal' => 'El sello de condiciones de esta reserva no corresponde a su pack o a su fecha: se movió sin re-sellarla. Mientras no se revise no se calcula ni se mueve ningún suplemento; lo escrito, si lo hay, se conserva.',
        ],
        'guest_badge_pending' => 'Pendiente',
        'guests_empty' => 'El cliente aún no ha rellenado el formulario de reserva.',

        // Menores a cargo en la ficha del pedido y en el alta manual (Fase 6 · C, tanda 5,
        // `specs/menores-a-cargo.md` §9.10 D14). El operador ve el NOMBRE porque ya lo ve en el
        // registro del waiver; la puerta (subsistema A) solo verá edad y estado de la exención.
        'dependents' => [
            'for' => 'Para:',
            'age' => ':age años',
            // ⚠️ `#320`: `waiver_current` YA NO SE PINTA (ni aquí ni en la ficha del titular, que
            // reusa estas claves) y se conserva por lo mismo que su gemela de la puerta: la clave se
            // compone dinámicamente y sin fila se pintaría el identificador en crudo.
            'waiver_current' => 'descargo ✓',
            'waiver_outdated' => 'descargo de una versión anterior',
            'waiver_missing' => 'sin descargo firmado',
            'removed' => 'retirado de la cuenta',
            // La acción «Asignar menores» de la línea (P3).
            'btn_aria' => 'Asignar menores a :name',
            'modal_heading' => 'Asignar menores',
            'modal_description' => 'Marca a los menores del cliente que vienen con estas entradas; el resto de unidades son adultos.',
            'field_label' => '¿Para quién son estas entradas?',
            'limit_hint' => 'Como máximo :max (una entrada por menor).',
            'conserved_hint' => ':names ya no están en la cuenta del cliente pero conservan su entrada: cuentan para el tope.',
            'option_reason' => ':name · :age años (:reason)',
            'option' => ':name · :age años',
            'none' => 'El cliente no tiene menores a cargo declarados. Los declara desde su cuenta, en «Menores a cargo».',
            'save' => 'Guardar',
            'saved' => 'Menores asignados: :names.',
            'saved_none' => 'Sin menores asignados: todas las entradas son de adultos.',
            'unchanged' => 'Sin cambios.',
            'rejected' => 'No se ha guardado nada: :reasons.',
            'blocked' => 'No se pueden asignar menores: :reason.',
            'failed' => 'No se pudo guardar la asignación. Inténtalo de nuevo.',
            'reasons' => [
                'not_yours' => 'un menor no es de este cliente',
                'not_minor_on_date' => 'ya tiene 18 años el día de la visita',
                'waiver_unsigned' => 'sin descargo firmado y vigente',
                'too_many' => 'más menores que entradas',
                'entries_only' => 'los menores solo se asignan a entradas',
            ],
            // El alta manual (P4).
            'manual_hint' => 'Solo se pueden marcar los menores con el descargo firmado; el resto de unidades son adultos.',
            'manual_too_many' => 'Has marcado más menores que entradas: quita alguno o sube la cantidad.',
            'manual_check_failed' => 'No se ha creado ni cobrado nada: :reasons.',
            'manual_assign_failed' => 'El pedido se ha creado y cobrado, pero :count menor(es) no se pudieron asignar. Asígnalos desde la ficha del pedido.',
        ],

        // Cards "Detalles" (sub-fase 7.2a refinada #134): info de consulta espontánea,
        // collapsed por defecto. Sub-secciones con Fieldset.
        'section_details' => 'Detalles',
        'details_order' => 'Detalles del pedido',
        'details_customer' => 'Detalles del cliente',

        // Labels reutilizables de campos del cliente — viven ahora dentro de Resumen
        // (`customer_name`, `customer_phone`) o en el Fieldset "Detalles del cliente"
        // (`customer_email`, `customer_locale`, `customer_waiver`, etc).
        'customer_name' => 'Nombre',
        'customer_email' => 'Email',
        'customer_phone' => 'Teléfono',
        'customer_locale' => 'Idioma de contacto',
        'customer_locale_value' => [
            'es' => 'Español',
            'en' => 'English',
            'fr' => 'Français',
        ],
        'customer_waiver' => 'Descargo',
        'customer_waiver_missing' => 'No firmado',
        'customer_waiver_outdated' => 'versión anterior',

        // Total del pedido (sub-fase 7.2a refinada #131): mostrado dentro de la card
        // de Resumen, ya no en una card "Importes" propia. Subtotal/IVA siguen en BD
        // (`orders.subtotal`/`tax`) pero no se exponen en panel — si se necesita
        // desglose contable se reincorporará como sub-fase.
        'amount_total' => 'Total',
        // Resumen financiero (#144): la línea "Devuelto" se renderiza cuando ya hay
        // devolución registrada. ("Neto cobrado" se retiró en el rediseño valor-primero
        // #199: el valor lo da ahora "Valor final del pedido".)
        'amount_refunded' => 'Devuelto',

        // Sección de pagos (sub-fase 7.2a).
        'section_payments' => 'Pagos',
        'payments' => [
            'portal_intro' => 'Para reconciliar manualmente con el banco: abre el portal Redsys y busca por el número de pedido.',
            'portal_link' => 'Abrir portal Redsys (:env)',
            'env_test' => 'sandbox',
            'env_live' => 'producción',

            'empty' => 'Sin intentos de pago registrados.',
            'attempt_at' => 'Intento del :when',

            // Sub-fase 7.2e.1bis (decisión #154): la card "Pagos" se divide en
            // 2 sub-secciones para distinguir movimientos REALES en banco del
            // cliente (Redsys) vs apuntes internos (devoluciones manuales que
            // el operador registró pero ocurrieron por otro canal).
            'section_bank' => '📋 En banco del cliente (Redsys)',
            'section_bank_help' => 'Cobros y devoluciones que han movido dinero realmente en la tarjeta del cliente.',
            'section_internal' => '📝 Apuntes internos',
            'section_internal_help' => 'Movimientos fuera de Redsys: cobros manuales en el establecimiento (efectivo o datáfono) y devoluciones gestionadas por otro canal (portal del banco, efectivo, etc.).',

            // Status de Payment (no confundir con Order).
            'status' => [
                'pending' => 'Pendiente',
                'authorized' => 'Autorizado',
                'paid' => 'Pagado',
                'failed' => 'Denegado',
                'refunded' => 'Reembolsado',
            ],

            // Provider (mismas claves que `payments.provider`).
            'provider_redsys' => 'Redsys (tarjeta)',
            'provider_cash' => 'Efectivo',
            'provider_datafono' => 'Datáfono físico',

            // Campos Redsys (labels en lenguaje operativo, no jerga técnica — #130).
            'gateway_order' => 'Nº pedido Redsys',
            'auth_code' => 'Código autorización banco',
            'ds_response' => 'Resultado del cobro',
            'ds_response_authorized' => 'Autorizado',
            'bank_timestamp' => 'Fecha y hora del cobro',
            'card_brand' => 'Tarjeta',
            'card_brand_unknown' => 'Marca :code',
            'card_country_unknown' => 'País :code',
            'paid_at' => 'Confirmado el',

            // Botón copiar (vuelve verde al copiar para feedback visual).
            'copy' => 'Copiar',
            'copied' => 'Copiado',

            // Timeline de eventos Redsys (#144): cada sub-card lleva un badge de tipo
            // arriba para que el operador distinga visualmente "Pago" de "Devolución"
            // (mismo código de color del estado).
            'events' => [
                'type_payment' => 'Pago',
                'type_refund' => 'Devolución',
            ],

            // Devoluciones — cada sub-card es un evento del timeline (#144).
            'refunds' => [
                'status' => [
                    'pending' => 'En curso',
                    'succeeded' => 'Confirmada',
                    'failed' => 'Fallida',
                ],

                'mode_rest' => 'Automática (Redsys)',
                'mode_manual' => 'Registrada manualmente',

                // Sub-fase 7.2e.1bis5 (decisión #158, punto 6 feedback): nombre
                // del producto/complemento devuelto. Aparece como primera fila
                // de cada sub-card de devolución en la card "Pagos" para que el
                // operador identifique de un vistazo el sujeto físico de cada
                // movimiento financiero.
                'subject_label' => 'Producto devuelto',
                'subject_full_order' => 'Pedido completo',
                'subject_item_missing' => '— (producto eliminado del catálogo)',

                'auth_code' => 'Código autorización banco',
                'result_label' => 'Resultado',
                'code_refund_ok' => 'Devolución correcta',
                'result_manual' => 'Apuntada como ya devuelta fuera del sistema.',

                'failure_label' => 'Motivo del fallo',
                'failure_reason' => [
                    'transport_error_check_portal' => 'No se pudo contactar con el banco.',
                    'gateway_denied' => 'El banco no autorizó la devolución.',
                    'unknown' => 'Respuesta del banco no entendida.',
                ],
                'transport_hint' => 'Comprueba en el portal del banco si la devolución se procesó allí antes de volver a intentarla desde el panel.',

                'processed_at_label' => 'Procesada el',
            ],
        ],

        // Acciones administrativas sobre el Order (sub-fase 7.2b, decisión #138).
        // Cancel / Refund / Resend confirmation se invocan desde la cabecera del
        // detalle (Filament header actions). Resolver C1 se diferió a la sub-fase
        // de gestión por-item (ver 01-ROADMAP).
        'actions' => [
            // P11: etiqueta/tooltip del icono de lápiz que agrupa las acciones del pedido.
            'group_label' => 'Acciones del pedido',
            'cancel' => [
                'label' => 'Cancelar pedido',
                'modal_heading' => '¿Cancelar este pedido?',
                'modal_description_paid' => 'El pedido pasará a estado «cancelado» y el cliente recibirá un email. El cobro NO se devuelve con esta acción: si procede el reembolso, úsalo desde la acción «Reembolsar» por separado.',
                'modal_description_pending' => 'El pedido pasará a estado «cancelado» y la plaza volverá al pool. El cliente recibirá un email.',
                'submit' => 'Sí, cancelar',
                'success' => 'Pedido cancelado. Se ha enviado el aviso al cliente.',
                'blocked' => 'No se puede cancelar este pedido: :reason.',
            ],
            'refund' => [
                'label' => 'Reembolsar',
                // #143: lenguaje operativo simple, sin jerga técnica. El empleado lee
                // qué pasa con el cliente y con el dinero, no cómo se implementa.
                'modal_heading' => '¿Reembolsar este pedido?',
                // `#153`: el importe SE NOMBRA. Medido en `#149`: el operador usó esta acción para
                // devolver una diferencia de 10,00 € y salieron los 40,00 del pago entero — el
                // texto no decía cuánto iba a devolver.
                'modal_description' => 'Se devolverán :amount € — TODO lo cobrado de este pedido — y le mandamos un aviso por email cuando se confirme.',
                'modal_description_finished_service' => 'El cliente ya ha disfrutado del servicio (todos los productos finalizaron). Se le devolverán :amount € — todo lo cobrado —; el pedido se queda como pagado con el reembolso anotado.',
                'partial_hint' => '⚠️ Esto devuelve el pedido ENTERO. Si solo quieres devolver una parte (una diferencia de precio, una línea), cierra esto y usa «Reembolsar» dentro de «Gestionar» del producto: allí eliges el importe exacto.',
                'submit' => 'Reembolsar',

                // Modo (#142, simplificado #143).
                'mode_label' => '¿Cómo lo procesamos?',
                'mode_rest' => 'Devolver ahora (recomendado)',
                'mode_rest_desc' => 'Devolvemos el dinero en el banco del cliente. Si el banco confirma, actualizamos el pedido y le mandamos el email. Si hubiera algún problema, el pedido se queda como estaba y te decimos qué ha pasado.',
                'mode_manual' => 'Solo registrar (ya devuelto fuera)',
                'mode_manual_desc' => 'Usa esta opción solo si ya devolviste el dinero por otro sitio (por ejemplo desde el portal del banco). Aquí solo lo dejamos apuntado y mandamos el email al cliente.',
                'intent_label' => '¿Por qué se le devuelve el dinero?',
                'intent_compensation' => 'Es una compensación: no nos debe nada',
                'intent_compensation_desc' => 'Le devolvemos el dinero y conserva su reserva. Lo que exceda lo que se le debe queda en su desglose como «Descuento por cortesía»; tu motivo se guarda en el historial y el cliente no lo ve.',
                'intent_paid_in_person' => 'Lo pagará en persona, en recepción',
                'intent_paid_in_person_desc' => 'Le devolvemos lo que pagó por la web porque abonará el importe al llegar. En su desglose aparecerá como pendiente de pagar en el parque.',
                // T4 del libro (`DECISIONES #316`): el MOTIVO manda. «Devolver lo que se le debe» no puede
                // exceder lo debido; esta acción devuelve el pago ENTERO, así que solo cabe cuando lo
                // debido lo cubre — si no, la opción se deshabilita y dice por qué.
                'intent_value_returned' => 'Devolver lo que se le debe',
                'intent_value_returned_desc' => 'Se le deben :owed €, y esta acción devuelve el pago entero (:amount €): exactamente eso. No es un descuento: el Total no cambia.',
                'intent_value_returned_nothing_owed' => 'No se le debe nada: registra antes la bajada o la cancelación, o elige compensación.',
                'intent_value_returned_partial' => 'Se le deben :owed € y esta acción devuelve el pago entero (:amount €). Devuélvelo por línea desde «Gestionar» del producto —allí eliges el importe—, o elige compensación.',
                'intent_value_returned_over_owed' => 'Esta acción devuelve el pago entero (:amount €) y solo se le deben :owed €: devuélvelo por línea desde «Gestionar», o elige compensación.',
                'note_label' => 'Motivo',
                'note_help' => 'Opcional. Queda en el historial del pedido; el cliente no lo ve.',
                'note_help_compensation' => 'Obligatorio con una compensación (5–200 caracteres): es lo que justifica el descuento por cortesía. Queda en el historial; el cliente no lo ve.',
                'excess_hint' => 'De estos :amount €, :owed € devuelven lo que se le debe y :excess € son un descuento por cortesía (bajan el Total).',
                'excess_hint_none' => 'Los :amount € no superan lo que se le debe (:owed €): no habrá descuento por cortesía.',

                'also_cancel' => 'También cancelar el pedido',
                'also_cancel_help' => 'Si lo dejas activo, el pedido también queda cancelado. Desactívalo solo si quedasteis con el cliente en que se quedaba con el servicio (por ejemplo, canje en persona por entradas físicas).',

                'success_with_cancel_rest' => '✓ Reembolso confirmado y pedido cancelado. El cliente ya tiene el aviso por email.',
                'success_only_rest' => '✓ Reembolso confirmado. El cliente ya tiene el aviso por email.',
                'success_with_cancel_manual' => '✓ Reembolso anotado y pedido cancelado. El cliente ya tiene el aviso por email.',
                'success_only_manual' => '✓ Reembolso anotado. El cliente ya tiene el aviso por email.',

                'blocked' => 'No se puede reembolsar este pedido: :reason.',

                // Mensajes de fallo (#143 lenguaje claro).
                'failed_title' => 'No se ha podido completar el reembolso',
                'transport_error' => 'No hemos podido contactar con el banco ahora mismo. El pedido NO se ha modificado. IMPORTANTE: es posible que el banco sí haya procesado la devolución por su lado — entra en el portal del banco a comprobarlo antes de volver a intentarlo. Si ves la devolución allí, vuelve aquí y elige "Solo registrar".',
                'gateway_denied' => 'El banco no ha podido devolver el dinero (código :code). Revisa el portal del banco para ver el motivo. El pedido NO se ha modificado.',
                'inflight_title' => 'Ya hay un reembolso en curso',
                'inflight_body' => 'Otro intento de reembolso de este mismo pago está aún procesándose. Espera unos segundos y mira el historial antes de volver a intentarlo.',
                'no_paid_payment' => 'No encontramos el cobro original confirmado de este pedido, así que no podemos devolver el dinero desde aquí.',
            ],
            // Reenvío unificado (#140): una acción "Reenviar email" con Select de tipos.
            // Solo aparecen los tipos cuyo evento subyacente ya ocurrió — reenviar un
            // email sobre algo que no pasó sería comunicación falsa al cliente.
            'resend' => [
                'label' => 'Reenviar email',
                'modal_heading' => '¿Qué email quieres reenviar al cliente?',
                'modal_description' => 'Se reenviará al cliente (:email) el correo seleccionado tal y como lo recibió la primera vez. Solo aparecen los emails cuyo evento subyacente ya ocurrió en el pedido. Cada envío queda registrado en el historial.',
                'type_label' => 'Tipo de email',
                'submit' => 'Reenviar',
                'success' => ':type reenviado a :email.',
                'blocked' => 'El email seleccionado ya no aplica al estado actual del pedido. Cierra el modal y vuelve a abrirlo.',

                // Etiquetas de los tipos disponibles en el Select.
                'types' => [
                    'confirmation' => 'Confirmación de compra',
                    'refund' => 'Notificación de reembolso',
                    'cancellation' => 'Notificación de cancelación',
                    'payment_retry' => 'Recordatorio para completar el pago',
                    'guest_form' => 'Enlace del formulario de reserva',
                    // ⚠️ Se ofrece en TODO pedido pagado, marcado o no: es la única salida del caso
                    // «el cliente no sabía que hacía falta» (`specs/waiver-por-reserva.md` §12.3).
                    'guardian' => 'Enlace del justificante de menores invitados',
                ],
            ],
            'reasons' => [
                'already_cancelled' => 'el pedido ya está cancelado',
                'already_refunded' => 'el pedido ya está reembolsado',
                'expired' => 'el pedido caducó sin pago',
                'not_paid' => 'el pedido no está pagado',
                'already_finished' => 'todos los productos del pedido ya finalizaron (servicio prestado)',
                // T4 del libro (`DECISIONES #316`): el motivo manda, y el dominio lo hace valer bajo lock.
                'exceeds_owed' => 'el importe supera lo que se le debe al cliente — «devolver lo que se le debe» no puede exceder lo debido; registra antes la bajada o la cancelación, o elige compensación',
                'compensation_without_note' => 'una compensación necesita un motivo escrito',
                'note_too_long' => 'el motivo supera los 200 caracteres',
            ],
        ],

        // Modal de detalle por OrderItem (sub-fase 7.2c). Abre desde el botón
        // "Ver / editar" en cada row de la lista de productos del pedido.
        // Dos tabs: "Detalles" (datos read-only + edición de event_data si es
        // pack) y "Historial" (snapshot de últimas N entradas del audit log
        // filtrado por este item + CTA al historial completo del pedido, que
        // entregará 7.2d).
        'item_detail' => [
            'btn_open' => 'Gestionar',
            'btn_aria' => 'Gestionar :name',
            'modal_heading' => 'Detalle del producto',
            'modal_close' => 'Cerrar',

            'tab_details' => 'Detalles',
            'tab_history' => 'Historial',
            // Tab "Datos del evento" — solo aparece si el item es pack con
            // eventFields configurados Y el operador tiene permiso (decisión #149).
            'tab_event_data' => 'Datos del evento',
            // Sufijo de la cantidad en items que NO son packs (entradas
            // estándar): "3 × entradas" en el header del summary del modal.
            'unit_entries' => 'entradas',
            // F13: rótulo de la cabecera del flat-summary (modales cancelar/
            // reembolsar). Aclara que es el importe COBRADO del producto, para
            // no confundirlo con el reembolsable real que muestra el checkbox.
            'product_amount' => 'Importe del producto',

            // Tab Detalles — bloque read-only de información del producto.
            'details_heading' => 'Información del producto',
            'details_ticket_type' => 'Tipo',
            'details_zone' => 'Zona',
            'details_duration' => 'Duración',
            // Formato humanizado de duración (decisión #149 iter — `App\Domain\Platform\Services\Duration::formatHumane`):
            // el empleado piensa en horas, no en minutos. "1h", "1h 30min", "45 min".
            'duration' => [
                'hours_only' => ':hoursh',
                'minutes_only' => ':minutes min',
                'hours_and_minutes' => ':hoursh :minutesmin',
            ],
            'details_slot' => 'Franja',
            'details_slot_capacity' => ':booked de :total plazas',
            'details_quantity' => 'Cantidad',
            'details_unit_price' => 'Precio unitario',
            'details_subtotal' => 'Subtotal',
            'details_seats' => 'Plazas que ocupa',
            'details_parent' => 'Complemento de',
            'details_children' => 'Complementos asociados',
            'details_no_slot' => 'Sin franja asignada',
            'details_addon_inherits' => 'Este complemento hereda el estado de preparado del producto principal.',

            // Tab Detalles — bloque de edición del event_data (solo packs).
            'event_data_heading' => 'Datos del evento',
            'event_data_intro' => 'Datos que el cliente proporcionó al reservar. Edítalos si necesitas corregir alguno (por ejemplo, el nombre del homenajeado).',
            'event_data_save' => 'Guardar cambios',
            'event_data_no_permission' => 'No tienes permiso para editar los datos del evento.',
            'event_data_no_fields' => 'Este producto no tiene datos del evento configurables.',
            'event_data_legacy_heading' => 'Campos antiguos',
            'event_data_legacy_intro' => 'Estos campos se guardaron al hacer la reserva pero ya no están en el esquema actual del producto. Permanecen visibles para que puedas borrarlos manualmente si lo crees oportuno.',
            'event_data_required_indicator' => '*',
            'event_data_optional_indicator' => '(opcional)',

            // Flash messages tras el submit. Patrón Filament Notification (decisión #147ter).
            'flash_saved' => 'Datos actualizados correctamente.',
            'flash_no_changes' => 'No había cambios que guardar.',
            'flash_stale' => 'Otro operador editó este producto entretanto. Recarga la página para ver los cambios actuales antes de volver a editar.',
            'flash_required_missing' => 'Faltan campos obligatorios: :missing',
            'flash_celebrant_age' => 'Guardado, pero la edad del cumpleañero no encaja en este pack',
            // Bloqueos del handler (cada caso lleva su propia clave para que el
            // operador sepa exactamente qué pasó). Genérico = fallback.
            'flash_blocked' => [
                'not_pack' => 'Este producto no es un pack: no tiene datos del evento que editar.',
                'no_event_fields' => 'Este pack no tiene campos del evento configurados.',
                'not_found' => 'No se encontró el producto en este pedido.',
                'generic' => 'No se pudo actualizar el producto.',
            ],

            // Tab Historial.
            'history_heading' => 'Historial reciente del producto',
            'history_intro' => 'Últimas :limit acciones registradas sobre este producto.',
            'history_empty' => 'Sin actividad registrada para este producto todavía.',
            'history_full_link' => 'Ver historial completo del pedido →',
            'history_full_link_pending' => '(historial completo del pedido — disponible en próxima sub-fase)',
            'history_by' => 'por :who',
            'history_by_unknown' => 'por usuario desconocido',

            // Acciones traducidas al lenguaje operativo.
            // La estructura es anidada (no strings con puntos literales) porque
            // `__()` descompone la clave por `.`. Las acciones se guardan en
            // `audit_logs.action` con formato `subject.verb` y se traducen
            // automáticamente en el blade vía el helper `actionKey`.
            'history_actions' => [
                'order_items' => [
                    'prepared' => 'Marcado como preparado',
                    'unprepared' => 'Marcado como sin preparar',
                    'toggle_blocked' => 'Intento de cambio bloqueado',
                    'event_data_updated' => 'Datos del evento actualizados',
                    'event_data_blocked' => 'Edición de datos bloqueada',
                ],
            ],

            // Detalles del diff por clave (usados en la tab Historial cuando la
            // acción es `event_data_updated`).
            'history_diff_changed' => '· :label: «:old» → «:new»',
            'history_diff_added' => '· :label añadido: «:new»',
            'history_diff_removed' => '· :label eliminado (era «:old»)',
            'history_diff_reason' => '· motivo: :reason',
        ],

        // Sub-fase 7.2d (decisión #151) — CTA al final de la card Detalles
        // que abre el modal con el audit log agregado del Order.
        'audit_cta' => [
            'title' => 'Historial del pedido',
            'description' => 'Consulta todas las acciones registradas sobre este pedido y sus productos: cambios de estado, preparaciones, ediciones, reembolsos y reenvíos de email.',
            'button' => 'Ver historial completo',
        ],

        // Modal del audit log agregado (decisión #151). Cada entrada distingue
        // entre acciones a nivel Order vs a nivel OrderItem; el payload se
        // renderiza según la action (diff por clave, razón estructurada,
        // transición de estado, etc.).
        'audit_modal' => [
            'heading' => 'Historial del pedido',
            'description' => 'Todas las acciones registradas sobre este pedido y sus productos, en orden cronológico inverso (más recientes arriba).',
            'empty' => 'Sin actividad registrada para este pedido todavía.',
            'target_order' => 'Pedido',
            'target_item' => 'Producto',
            'by' => 'por :who',
            'by_unknown' => 'por usuario desconocido',
            'context_item' => 'pedido :code',
            // Nombre del producto al que se refiere una entrada de OrderItem
            // (decisión #151bis): "Producto: Cumpleaños XL · vie 1 ene 15:00–16:30".
            'related_item' => 'Producto: :name',
            'showing_of' => 'Mostrando :shown de :total entradas',
            'load_more' => 'Cargar :count más',
            'reason' => 'Motivo: :reason',
            'previous_status' => 'Estado anterior: :status',
            'previous_prepared_at' => 'Estaba marcado preparado desde :when',
            'previously_unprepared' => 'No estaba marcado como preparado',
            'refund_mode' => 'Modo: :mode',
            'refund_amount' => 'Importe: :amount €',
            'resend_type' => 'Tipo: :type',

            'diff_changed' => '· :label: «:old» → «:new»',
            'diff_added' => '· :label añadido: «:new»',
            'diff_removed' => '· :label eliminado (era «:old»)',

            // Detalle de una edición de reserva (`DECISIONES #145`). El registro guardaba estos
            // datos desde el principio y no los pintaba nadie.
            'slot_move' => 'Fecha y hora: :from → :to',
            // T5 adenda: la forma que #145 dejó fuera — «Cambió: cantidad» sin los números.
            'quantity_move' => 'Cantidad: :from → :to',
            'unit_price_move' => 'Precio unitario: :from → :to',
            'price_diff' => 'Diferencia: :amount',
            'adjustment_amount' => 'Importe: :amount',
            'changed_what' => 'Cambió: :what',
            'change_kinds' => [
                'slot_change' => 'la fecha y la hora',
                'product_change' => 'el producto',
                'quantity_change' => 'la cantidad',
                'addon_change' => 'los complementos',
                // `#150`: la re-tarificación viaja como cambio estructurado propio.
                'unit_price_change' => 'el precio unitario',
            ],

            // Acciones traducidas. Estructura anidada por subject (`orders` y
            // `order_items`) para que `__('admin.orders.audit_modal.actions.orders.cancelled')`
            // resuelva correctamente — Laravel descompone por `.`.
            //
            // ⚠️⚠️ **El grupo tiene que casar con el PREFIJO REAL de la acción que el código
            // emite, no con el tipo del target** (`DECISIONES #145`). Cuatro etiquetas vivían
            // bajo `order_items.*` mientras el código escribía `orders.item_*`, así que estaban
            // escritas y no se usaban NUNCA: el registro enseñaba la clave cruda teniendo la
            // traducción a un palmo. Solo `event_data_*` se emite de verdad con prefijo
            // `order_items.`.
            //
            // ⚠️ **La lista completa la impone `AuditActionCatalogTest`** contra
            // `AuditLog::orderActions()`, en las dos direcciones: ninguna acción sin etiqueta y
            // ninguna etiqueta sin acción. Se retiraron cuatro que etiquetaban un ciclo ya
            // retirado (preparado / sin preparar) y un evento que nadie audita
            // (`processed_after_expiration`: lo que se registra es la incidencia de cobro).
            'actions' => [
                'orders' => [
                    'cancelled' => 'Pedido cancelado',
                    'cancel_blocked' => 'Intento de cancelación bloqueado',
                    'created_manual' => 'Pedido creado a mano',
                    'customer_registered' => 'Cliente dado de alta con el pedido',
                    'refunded' => 'Reembolso confirmado',
                    'refund_blocked' => 'Intento de reembolso bloqueado',
                    'refund_failed' => 'Reembolso fallido',
                    'email_resent' => 'Email reenviado',
                    'email_resent_blocked' => 'Reenvío de email bloqueado',
                    'guest_form_link_rotated' => 'Enlace del formulario rotado',
                    'guest_form_link_rotate_blocked' => 'Rotación del enlace bloqueada',
                    'guest_form_submitted' => 'Formulario de invitados enviado',
                    'invitation_reply_received' => 'Respuesta recibida en la invitación',
                    'invitation_link_rotated' => 'Enlace de la invitación anulado',
                    'invitation_link_rotate_blocked' => 'Anulación del enlace bloqueada',
                    // `#440` · el teléfono del cliente en el mostrador. El primero es el rastro de que
                    // se vendió sin él —que se avisa pero NO se bloquea, `[DECIDIDO owner]`— y el
                    // segundo, el de que el operador lo consiguió y lo escribió.
                    'manual_created_without_phone' => 'Pedido creado sin teléfono del cliente',
                    'manual_customer_phone_added' => 'Teléfono del cliente añadido en el mostrador',
                    'payment_init_failed' => 'No se pudo iniciar el cobro',
                    'guest_count_changed' => 'Invitados de la reserva actualizados',
                    'postform_addons_changed' => 'Extras del formulario actualizados',
                    'slip_printed' => 'Hoja de reserva impresa',

                    // Dinero de una gestión (T1 del libro, `specs/desglose-libro.md` §4.2): cada
                    // edición deja UN hecho con su delta entero, y el historial dice si fue un cargo
                    // o una bajada — son los que el operador tiene que poder explicar con el cliente
                    // delante. (Hasta la T1 una bajada se auditaba como uno o dos «abonos» sobre
                    // cubos de puerta, `gate_credit_applied` / `deposit_remainder_credit_applied`.)
                    'extra_due_applied' => 'Cargo añadido a cobrar en el parque',
                    'value_reduction_applied' => 'Bajada del importe registrada',
                    // El suplemento de fiesta mixta se recalcula solo con las edades declaradas; este
                    // rastro es lo ÚNICO que deja ver «declaró 8 el día 3 y lo bajó a 6 el día 20».
                    'mixed_party_surcharge_synced' => 'Suplemento de fiesta mixta recalculado',

                    // Reservas dentro del pedido. ⚠️ El código las emite con prefijo `orders.`
                    // aunque hablen de un producto, y el target es el PEDIDO.
                    'item_edited' => 'Producto editado',
                    'item_cancelled' => 'Producto cancelado',
                    'item_refunded' => 'Producto reembolsado',
                    'item_slot_changed' => 'Fecha y hora del producto cambiadas',
                    'item_edit_blocked' => 'Edición de producto bloqueada',
                    'item_cancel_blocked' => 'Cancelación de producto bloqueada',
                    'item_refund_blocked' => 'Reembolso de producto bloqueado',
                    'item_refund_failed' => 'Reembolso de producto fallido',
                ],
                'order_items' => [
                    'event_data_updated' => 'Datos del evento actualizados',
                    'event_data_blocked' => 'Edición de datos del evento bloqueada',
                ],
            ],

            // Motivo de un ajuste de dinero, legible. Antes se pintaba la clave en crudo
            // («Motivo: item_edit_reduction»), que es lo que el owner encontró en `R-S9XDYB`.
            // Un motivo sin entrada aquí cae a su propia clave, nunca rompe.
            'reasons' => [
                'item_edit' => 'subida por editar el producto',
                'item_edit_reduction' => 'bajada por editar el producto',
                'addon_edit' => 'cambio de complementos',
                'addon_per_guest_rescale' => 'reajuste de complementos por invitado',
                'addon_per_guest_rescale_reduction' => 'bajada por reajuste de complementos por invitado',
                'deposit_split' => 'reparto de la señal al nacer: el resto se paga en el parque',
            ],
        ],

        // #263 — Modal «Enlace del formulario de invitados»: lo abre el icono de enlace de cada
        // reserva con post-form (lista de productos). Muestra el enlace firmado para copiarlo y
        // enviarlo por WhatsApp/SMS (útil sobre todo si el cliente no tiene email).
        'guest_minors' => [
            'section' => 'Menores invitados con justificante',
            'count' => '{1}:count justificante firmado|[2,*]:count justificantes firmados',
            'signed_by' => 'Autoriza :name (:relationship)',
            'waiver_outdated' => 'Versión anterior — deja pasar',
            'waiver_missing' => 'Falta la firma',
            'copy_link' => 'Copiar enlace para los padres',
            'modal_heading' => 'Enlace del justificante',
            'modal_description' => 'Compártelo con los padres o tutores de los menores invitados. Cada uno rellena SUS datos y no ve los de los demás.',
            'btn_aria' => 'Enlace del justificante de menores invitados',
            /*
             * La T7 (`specs/waiver-por-reserva.md` §12.1, §12.6).
             *
             * ⚠️ `empty` existe porque la sección ya NO se oculta cuando no hay justificantes: antes
             * se ocultaba **con el botón del enlace dentro**, así que en un pedido nuevo el operador
             * no tenía por dónde empezar. Un estado vacío que dice qué hacer es la mitad útil de esta
             * sección hasta que alguien firma.
             *
             * ⚠️ `overflow` es el aviso de §12.6: bajar la cantidad NO borra justificantes —son
             * firmas con valor probatorio— así que un pedido puede acabar con más papeles que plazas.
             * Antes no lo decía nadie y la hoja de sala imprimía los cincuenta tan tranquila.
             */
            'capacity' => '{1}:count plaza en la reserva|[2,*]:count plazas en la reserva',
            // ⚠️ Esta clave se USÓ antes de existir y **ningún test lo vio**: solo se pinta cuando la
            // reserva tiene menores a cargo asignados, y ninguna guarda montaba ese caso. Lo cazó el
            // owner leyendo `admin.orders.guest_minors.assigned` en pantalla.
            'assigned' => '{1}:count de ellas para un menor a tu cargo|[2,*]:count de ellas para menores a tu cargo',
            'empty' => 'Todavía no ha firmado ningún padre o tutor. Cópiale el enlace al cliente o envíaselo para que lo reparta.',
            'overflow' => 'Ojo: hay :count justificantes firmados y el pedido tiene :capacity plazas. No se borra ninguno (son firmas), pero conviene revisarlo antes de la visita.',
            'send_link' => 'Enviárselo al cliente',
            'send_heading' => 'Enviar el enlace del justificante',
            'send_description' => 'Se le manda a :email un correo con el enlace para que lo reparta entre los padres de los menores invitados.',
        ],
        // El enlace del post-form es una CREDENCIAL: abre sin sesión, viaja por correo y se reenvía.
        // Rotarlo lo retira en el acto (`specs/complementos-post-reserva.md` §4.6.bis, `#413` D14).
        'rotate_guest_form' => [
            'btn_aria' => 'Rotar el enlace del formulario',
            'modal_heading' => '¿Rotar el enlace de este formulario?',
            'modal_description' => 'El enlace que se envió deja de funcionar en el acto y hay que mandarle uno nuevo al cliente. Úsalo si el enlace ha circulado por donde no debía. Lo que ya se rellenó o se añadió con el enlace anterior NO se borra.',
            'submit' => 'Rotar el enlace',
            'success' => 'Enlace rotado. Cópialo de nuevo y envíaselo al cliente.',
            'blocked' => 'No se ha podido rotar el enlace de esta reserva.',
        ],

        'rotate_invitation' => [
            'btn_aria' => 'Anular el enlace de la invitación',
            'modal_heading' => '¿Anular el enlace de esta invitación?',
            'modal_description' => 'El enlace que el cliente repartió deja de funcionar en el acto, y tendrá que compartir el nuevo. Úsalo si ha circulado por donde no debía. Lo que los padres ya han contestado NO se borra.',
            'submit' => 'Anular el enlace',
            'success' => 'Enlace anulado. El cliente tiene que compartir el nuevo.',
            'blocked' => 'No se ha podido anular el enlace de esta reserva.',
        ],

        // La INVITACIÓN en la ficha del pedido (T6·5, `specs/celebracion-e-invitacion.md` §4.8).
        // ⚠️ Es OTRO enlace que el del formulario: éste abre la tarjeta pública que el cliente reparte
        // al grupo de clase, así que se nombra por lo que es y no «el enlace» a secas.
        'copy_invitation' => [
            'btn_aria' => 'Enlace de la invitación',
            'modal_heading' => 'Enlace de la invitación',
            'modal_description' => 'Es el enlace que el cliente reparte a las familias: abre la tarjeta de la fiesta y desde ahí contestan. Cópialo y mándaselo si lo ha perdido.',
            'close' => 'Cerrar',
        ],
        // «N vienen · M no pueden · K por repasar» en la línea del pedido.
        'invitation_summary' => 'Invitación: :yes vienen · :no no · :pending por repasar',

        'copy_guest_form' => [
            'btn_aria' => 'Enlace del formulario de invitados',
            'modal_heading' => 'Enlace del formulario de invitados',
            'modal_description' => 'Copia el enlace y envíaselo al cliente por WhatsApp o SMS. Abre el formulario sin necesidad de cuenta y caduca tras el evento.',
            'copy' => 'Copiar',
            'copied' => '¡Copiado!',
            'hint' => 'Pulsa «Copiar» (o selecciona el enlace) y pégalo en el mensaje al cliente.',
            'close' => 'Cerrar',
        ],

        // Sub-fase 7.2e.2 — modal "Gestionar producto" (decisión #159).
        // Reconvierte el viejo modal "Ver / editar" de 7.2c en un editor real
        // con Tab 1 (Producto y reserva: fecha+hora + datos del evento si
        // pack) y Tab 2 (Complementos, placeholder hasta 7.2e.4).
        'manage_item' => [
            'modal_heading' => 'Gestionar producto',
            'save' => 'Guardar cambios',
            // #171/#172: botones de reembolso y cancelación al pie del modal.
            'refund_button' => 'Reembolsar',
            'cancel_button' => 'Cancelar producto',

            // #173: reestructura de tabs — "Reserva" (fecha+franja) + "Editar
            // producto" (secciones Producto y Complementos). Las claves antiguas
            // (tab_product/tab_event_data/tab_addons) quedan por compatibilidad.
            'tab_reservation' => 'Reserva',
            'tab_edit_product' => 'Editar producto',
            'section_product' => 'Producto',
            'section_addons' => 'Complementos',
            'tab_product' => 'Producto y reserva',
            'tab_event_data' => 'Datos del evento',
            'tab_addons' => 'Complementos',

            // ── T3 · F (`specs/cumple-mixto.md` §23.3): la pestaña «Invitados» ─────────────
            'tab_guests' => 'Invitados',
            'guests_intro' => 'Las fichas que declaró el cliente en el formulario de invitados. Si corriges una edad, el suplemento de fiesta mixta se recalcula solo y el cliente recibe el aviso firmado por el parque.',
            'guests_saved' => '✓ Fichas de invitados guardadas.',
            'guest_regime' => 'Régimen: :name',
            'guest_regime_no_product' => 'Sin producto para esta edad',
            'guest_regime_no_age' => 'Sin edad declarada',

            // ── T3 · D7 (§23.4): bajar del mínimo del pack ─────────────────────────────────
            'below_minimum_label' => 'Bajar del mínimo del pack (queda registrado)',
            'below_minimum_help' => 'El mínimo de este pack es :min. Al activarlo puedes bajar hasta 1 invitado; la excepción queda en el historial del pedido.',
            'field_guests_help_below_minimum' => 'Mínimo rebajado a 1 (el del pack es :min). Queda registrado.',

            'field_date' => 'Fecha',
            'field_time' => 'Hora',
            'current_marker' => '(actual)',
            // Plural rule de Laravel — debe invocarse con trans_choice($key, $count)
            // (NO __()) para que el plural se procese. Bug arreglado en 7.2e.2bis6.
            'seats_available' => '{0}sin plazas libres|{1}1 plaza libre|[2,*]:count plazas libres',

            'permission_denied' => 'No tienes permiso para editar este producto.',
            'blocked' => 'No se puede guardar el cambio: :reason.',

            'success_slot_changed' => '✓ Fecha y hora del producto actualizadas. Le hemos avisado al cliente por email.',

            // Banner explicativo cuando el item NO es editable (en Tab 1).
            'read_only_title' => 'Este producto no se puede editar ahora.',
            'read_only_intro' => 'Motivo: :reason. Puedes ver los datos en este modal pero los campos están bloqueados.',

            // Banner de la Tab "Editar producto" cuando NO aplica (item no
            // editable o el propio item es un complemento). #170 + #173.
            'addons_placeholder_title' => 'Producto no editable',
            'addons_placeholder_body' => 'Este producto no se puede editar ahora (ya finalizado o cancelado, o es un complemento que se gestiona desde su producto principal).',

            // ── 7.2e.4 (#170): gestión de complementos (Tab 2) ─────────────
            'addons_intro' => 'Añade o quita complementos de este producto. Las subidas se cobran en puerta; al quitar un complemento queda pendiente de reembolso (devuélvelo con el botón ↩ de la lista de productos).',
            'addons_current' => 'Complementos actuales',
            'group_choice_label' => 'Menú',
            'group_choice_hint' => 'Elige una opción. Al guardar, sustituye al menú actual (la diferencia se cobra en puerta).',
            'addons_current_empty' => 'Este producto no tiene complementos.',
            'addons_add' => 'Añadir complemento',
            'addons_add_button' => 'Añadir complemento',
            'addons_add_none' => 'No hay complementos compatibles para añadir.',
            'addon_name' => 'Complemento',
            'addon_quantity' => 'Cantidad',
            'addon_remove_hint' => 'Pon la cantidad a 0 para quitar el complemento.',
            'addon_locked_hint' => 'Incluido en el producto: no se puede quitar. Cambia el menú añadiendo otra opción del grupo abajo.',
            'addon_min_hint' => 'Incluido: no baja de :min. Súbelo para añadir extras de pago.',
            'addon_add_per_guest_hint' => 'La cantidad es automática: una unidad por invitado (no se edita).',
            'addon_add_per_guest_qty' => 'Uno por invitado · :count invitados.',
            'addon_add_group_hint' => 'Forma parte de un grupo de elección: al guardar reemplaza al otro complemento del mismo grupo si ya estaba.',
            'addon_add_requires_hint' => 'Requiere «:name»: debe estar en la reserva (añádelo primero, o en la misma tanda).',
            'addons_price_heading' => 'Complementos: importe del cambio',
            'addons_upcharge_extra' => '▲ Se cobrarán :amount € adicionales en puerta por los complementos.',
            'addons_upcharge_removed' => '▼ Quitas complementos: quedará pendiente de reembolso (devuélvelo con el botón ↩).',
            'addons_upcharge_none' => 'Sin cambios de importe en los complementos.',

            // 7.2e.2bis6 (#160) — Calendario visual.
            'calendar_prev' => 'Mes anterior',
            'calendar_next' => 'Mes siguiente',
            'calendar_back_to_current' => '↩ Volver al actual',
            'calendar_legend_selected' => 'Seleccionado',
            'calendar_legend_current' => 'Franja actual',
            // Sub-fase 7.2e.2bis10 (#164): heatmap de saturación por día.
            'calendar_legend_sat_high' => 'Mucha disponibilidad',
            'calendar_legend_sat_medium' => 'Disponibilidad media',
            'calendar_legend_sat_low' => 'Casi lleno',
            'calendar_empty_month' => 'No hay datos del calendario para mostrar.',
            'times_for_day' => 'Horas disponibles del :date',
            'times_prev' => 'Franja anterior',
            'times_next' => 'Franja siguiente',
            'no_times_available' => 'No hay horas disponibles para ese día.',
            // P5: invitación cuando aún no se ha elegido fecha (columna derecha del modal).
            'pick_a_date' => 'Elige una fecha en el calendario para ver las franjas.',
            'selection_summary' => 'Nueva fecha y hora: :date · :time',
            // Aviso PREVIO del cambio de fecha (`#417`): lo que ese día le hace a los complementos.
            'addon_date_heading' => 'El día elegido cambia los extras de esta reserva:',
            'addon_date_withdrawn' => '«:name» no se vende ese día: se retirará y sus :amount quedarán a devolver en el parque.',
            'addon_date_repriced' => '«:name» pasa de :from a :to, que es su precio ese día.',
            'addon_date_balance' => 'En total, el cliente tiene :amount a su favor, que se liquidan en el parque.',

            // ── 7.2e.3 (#167): cambio de cantidad + producto del item ──────
            'field_product' => 'Producto',
            'field_quantity' => 'Cantidad',
            'field_guests' => 'Número de invitados',
            'field_guests_help' => 'Entre :min y :max invitados.',
            // Nota bajo el selector de producto: el alcance está acotado a
            // mismo tipo + misma zona (cambios mayores → pedido manual 7.3).
            'product_scope_note' => 'Solo se listan productos de la misma zona y tipo. Para cambiar de zona o entre entrada y pack, usa un pedido manual (próximamente).',

            // Recálculo en vivo del diff de precio (Placeholder reactivo).
            'price_heading' => 'Importe del producto',
            'price_current' => 'Actual',
            'price_new' => 'Nuevo',
            'price_diff_extra' => '▲ Se cobrarán :amount € adicionales en puerta.',
            'price_diff_refund' => '▼ Se reembolsarán :amount € al cliente.',
            // #225 (D8): bajar = solo cancelar; NO se reembolsa automáticamente.
            'price_diff_reduce' => '▼ Se cancelarán las unidades retiradas (−:amount €). No se reembolsa automáticamente; usa «Reembolsar» si procede.',
            'price_diff_none' => 'El importe no cambia.',

            // Avisos previos al guardado.
            'orphan_addons_warning' => 'El producto nuevo no admite estos complementos: :list. Quítalos en la pestaña «Complementos» (pon su cantidad a 0) en este mismo guardado.',
            'event_data_legacy_warning' => 'El producto nuevo usa otros datos del evento. Revisa la pestaña «Datos del evento» tras guardar.',

            // Mensajes de éxito (variantes según movimiento de dinero).
            'success_edited' => '✓ Producto actualizado. Le hemos avisado al cliente por email.',
            'success_edited_extra_due' => '✓ Producto actualizado. Se cobrarán :amount € en puerta. Le hemos avisado al cliente por email.',
            'success_edited_refunded' => '✓ Producto actualizado y reembolsados :amount € al cliente. Le hemos avisado al cliente por email.',
            'success_edited_mixed' => '✓ Producto actualizado. Se cobrarán :extra € en puerta y se reembolsan :refund € al cliente. Le hemos avisado por email.',
            'success_edited_refund_failed' => '⚠ Producto actualizado, pero el reembolso de :amount € NO se completó. Reintenta con el botón ↩ de la lista de productos y revisa el historial.',
            // #225 (D8): una bajada solo cancela; el reembolso, si procede, es aparte.
            'success_edited_reduced' => '✓ Producto actualizado: unidades canceladas. NO se ha reembolsado nada automáticamente. Para devolver el importe, usa «Reembolsar». Le hemos avisado al cliente por email.',
            // `#150` (D2 de `#146`): la bajada por RE-TARIFICACIÓN no cancela ninguna unidad — decir
            // «unidades canceladas» era mentira cada vez que se movía una fecha a la baja.
            'success_edited_reduced_price' => '✓ Producto actualizado: el nuevo precio es más bajo y la diferencia queda pendiente de devolver. NO se ha reembolsado nada automáticamente. Para devolverla, usa «Reembolsar». Le hemos avisado al cliente por email.',
            'success_edited_mixed_reduced' => '✓ Producto actualizado. Se cobrarán :extra € en puerta; las unidades retiradas se cancelaron SIN reembolso automático (usa «Reembolsar» si procede). Le hemos avisado por email.',
        ],

        // Sub-fase 7.2e.1bis — acción 🗑️ "Cancelar item" (decisión #154).
        // SOLO cancela — NO toca Redsys. Alineado con el patrón del Order completo
        // (#139). Si procede devolución, el operador usa Reembolsar después.
        'cancel_item' => [
            'label' => 'Cancelar este producto',
            'tooltip' => 'Cancelar este producto',
            'modal_heading' => '¿Cancelar este producto del pedido?',
            'modal_description' => 'El producto se marcará como cancelado y la plaza se liberará. **El importe NO se devuelve con esta acción**: si procede el reembolso, úsalo desde el botón Reembolsar por separado. El cliente recibirá un email indicando la cancelación.',
            'submit' => 'Sí, cancelar producto',
            'success' => '✓ Producto cancelado. Le hemos avisado al cliente por email.',
            'meta' => 'Cancelado el :when por :who',

            'blocked' => 'No se puede cancelar este producto: :reason.',

            // Sub-fase 7.2e.1bis5 (decisión #158, punto 5 feedback): cuando el
            // producto a cancelar tiene complementos, el modal los lista para
            // que el operador confirme con contexto completo el alcance de
            // la cancelación (cascada de #157).
            'cascade_intro' => 'Al cancelar este producto se cancelarán también sus complementos (:count):',
            'cascade_total' => 'Total que se cancela',
        ],

        // Sub-fase 7.2e.1bis — acción ↩️ "Reembolsar item" (decisión #154).
        // SIN importe libre. Lista de checkboxes con el item + sus complementos
        // (los que aún tengan refundable remainder). Cada marcado → soft-cancel
        // + refund REST de su importe completo.
        'refund_item' => [
            'label' => 'Reembolsar este producto',
            'tooltip' => 'Reembolsar este producto',
            'modal_heading' => '¿Reembolsar este producto?',
            // ⚠️ Este texto decía «cada uno se marcará como cancelado» y era FALSO desde la decisión
            // #157 (reembolsar NO cancela; se midió en `#149`): el operador leía una cancelación que
            // no iba a ocurrir. Corregido al escribir la elección de importe (D5, `#146`).
            'modal_description' => 'Marca qué productos devolver y elige cuánto. Reembolsar NO cancela la reserva: el cliente la conserva (cancelar tiene su propio botón). Si marcas el producto principal, sus complementos quedarán también marcados.',
            'submit' => 'Reembolsar lo seleccionado',

            'mode_label' => '¿Cómo procesamos la devolución?',
            'mode_rest' => 'Devolver ahora (recomendado)',
            'mode_rest_desc' => 'Devolvemos el dinero al banco del cliente. Si algo falla, te avisamos y no se modifica nada del resto.',
            'mode_manual' => 'Solo registrar (ya devuelto fuera)',
            'mode_manual_desc' => 'Solo lo dejamos apuntado (úsalo si ya devolviste el dinero por otro sitio: portal del banco, efectivo, etc.).',
            'intent_label' => '¿Por qué se le devuelve el dinero?',
            'intent_compensation' => 'Es una compensación: no nos debe nada',
            'intent_compensation_desc' => 'Le devolvemos el dinero y conserva su reserva. Lo que exceda lo que se le debe queda en su desglose como «Descuento por cortesía»; tu motivo se guarda en el historial y el cliente no lo ve.',
            'intent_paid_in_person' => 'Lo pagará en persona, en recepción',
            'intent_paid_in_person_desc' => 'Le devolvemos lo que pagó por la web porque abonará el importe al llegar. En su desglose aparecerá como pendiente de pagar en el parque.',
            // T4 del libro (`DECISIONES #316`): el MOTIVO manda. «Devolver lo que se le debe» se capa a lo
            // que el libro de ESTA reserva dice que se le debe (el modal lo capa, el dominio lo bloquea);
            // con 0 debido la opción se deshabilita y dice por qué. La compensación exige motivo escrito.
            'intent_value_returned' => 'Devolver lo que se le debe',
            'intent_value_returned_desc' => 'Se le deben :owed € por esta reserva. El importe no puede superar esa cifra; no es un descuento: el Total no cambia.',
            'intent_value_returned_nothing_owed' => 'No se le debe nada por esta reserva: registra antes la bajada o la cancelación, o elige compensación.',
            'intent_value_returned_over_owed' => 'Con «devolver lo que se le debe» no puede devolverse más de :owed € (vas a devolver :amount €): baja el importe con «Otro importe», o elige compensación.',
            'note_label' => 'Motivo',
            'note_help' => 'Opcional. Queda en el historial del pedido; el cliente no lo ve.',
            'note_help_compensation' => 'Obligatorio con una compensación (5–200 caracteres): es lo que justifica el descuento por cortesía. Queda en el historial; el cliente no lo ve.',
            'excess_hint' => 'De estos :amount €, :owed € devuelven lo que se le debe y :excess € son un descuento por cortesía (bajan el Total).',
            'excess_hint_none' => 'Los :amount € no superan lo que se le debe (:owed €): no habrá descuento por cortesía.',

            'items_label' => 'Productos a reembolsar',
            'items_help' => 'Solo aparecen los productos que aún tienen importe pendiente de devolver. Si un producto ya se ha reembolsado completamente, no se lista.',

            // D5 (`#146`): CUÁNTO se devuelve. Sin esta elección el botón devolvía siempre el
            // remanente entero de la línea — y tras una bajada de precio eso regalaba dinero.
            'amount_mode_label' => '¿Cuánto devolvemos?',
            'amount_mode_remainder' => 'Todo lo que queda de las líneas marcadas',
            'amount_mode_remainder_desc' => 'El remanente completo de cada línea (el importe que ves junto a cada una).',
            'amount_mode_custom' => 'Otro importe (solo con UNA línea marcada)',
            'amount_mode_custom_desc' => 'Escribe el importe exacto — por ejemplo, la diferencia que se le debe tras cambiar a una fecha más barata. Nunca puede superar el remanente de la línea.',
            'custom_amount_label' => 'Importe a devolver',
            'custom_amount_help_pending' => 'Se le deben :pending € por esta reserva: es lo que el libro dice, y el tope de «devolver lo que se le debe».',
            'custom_amount_help' => 'No se le debe nada por esta reserva: lo que devuelvas solo puede ser una compensación (con motivo) o «lo pagará en recepción».',

            // Resultados — todos OK
            'success_all_rest' => '✓ Devueltos :count producto(s) al cliente · Total :amount €. Le hemos avisado por email.',
            'success_all_manual' => '✓ Anotados :count producto(s) como devueltos · Total :amount €. Le hemos avisado por email.',

            // Resultados — éxito parcial (uno o varios fallaron)
            'success_partial_rest' => '⚠ Devueltos :count_ok producto(s) · Total :amount €. :count_failed quedaron sin devolver — revisa el historial y reintenta cuando puedas.',
            'success_partial_manual' => '⚠ Anotados :count_ok producto(s) · Total :amount €. :count_failed quedaron sin anotar — revisa el historial.',

            'blocked' => 'No se puede reembolsar este producto: :reason.',

            // Mensajes de fallo individuales (mismo patrón que refund Order, #143).
            'failed_title' => 'No se ha podido completar el reembolso',
            'transport_error' => 'No hemos podido contactar con el banco ahora mismo. El producto NO se ha modificado. IMPORTANTE: es posible que el banco sí haya procesado la devolución por su lado — entra en el portal del banco a comprobarlo antes de volver a intentarlo. Si ves la devolución allí, vuelve aquí y elige "Solo registrar".',
            'gateway_denied' => 'El banco no ha podido devolver el dinero (código :code). Revisa el portal del banco para ver el motivo. El producto NO se ha modificado.',
            'inflight_title' => 'Ya hay un reembolso en curso',
            'inflight_body' => 'Otro intento de reembolso sobre este mismo producto está aún procesándose. Espera unos segundos y mira el historial antes de volver a intentarlo.',
        ],

        // Sub-fase 7.2e.1bis2 — bloque "Totales del producto" + badges
        // financieros DENTRO. Feedback empírico 2026-05-30: el operador
        // necesita ver SIEMPRE el desglose principal + complementos + total
        // del producto, en una sección agregada abajo (separada de inventario).
        'item_financial' => [
            // T3·2 del LIBRO: las LÍNEAS de producto (qué se compró) siguen aquí; el dinero movido lo
            // pintan las claves `book.*`. `deposit_remainder_line` la lee aún `Order::reservationGateLines`
            // (el modelo viejo, oráculo hasta la T3·4).
            'heading' => 'Totales del producto',
            'principal' => 'Producto principal',
            'addons' => 'Complementos',
            'total' => 'Total del producto',
        ],

        // El SELLO DEL MODO (`specs/hora-extra.md` §12.8, `#448`): la línea se vendió con una unidad
        // —bloques o personas— distinta de la que su enganche declara hoy. No es un error: es lo que
        // protege a esa reserva de un cambio de catálogo. Lo que hay que decir es la CONSECUENCIA
        // práctica, porque es la que el operador se encuentra: no puede subirla.
        // ⚠️⚠️ **La primera redacción PROMETÍA dos cosas que el dominio prohíbe** («se puede bajar o
        // quitar, pero no subir»): bajar parcialmente devuelve `addon_partial_reduce_unsupported`
        // para TODA hija, sin excepción, y en la dirección «vendida por invitados, enganche hoy fijo»
        // la línea sale además `locked`, así que tampoco se puede quitar. La pastilla se pinta igual
        // en las dos direcciones, así que el texto **no puede prometer una salida**: dice el HECHO y
        // deja la vía al operador, que es quien ve si el control está disponible.
        'addon_unit_diverges' => 'Vendido con otra unidad',
        'addon_unit_diverges_hint' => 'Este complemento se vendió con una unidad distinta de la que tiene ahora el catálogo, así que conserva la suya y su cantidad no se ajusta a la configuración actual.',

        // Sub-fase 7.2e.1bis5 (decisión #158, punto 4 feedback): bloque
        // compacto de totales DEL PEDIDO al final de la card Resumen,
        // diferenciado del "item_financial" del sub-card de cada producto.
        // EL LIBRO del pedido (`DECISIONES #305`; T3·2 de `specs/desglose-libro.md` §6.3.2): el
        // panel, la hoja y la puerta pintan `Booking\Services\OrderBook`. Las ETIQUETAS de cada
        // línea (movimientos y liquidaciones) las compone el dominio en `tickets.journal.*` —las
        // mismas que lee el cliente—; aquí viven solo los títulos y los rótulos del SALDO, en
        // tercera persona, que es la voz del operador.
        'book' => [
            // T4 del libro (D-T4·1): el MOTIVO de un descuento por cortesía, solo en el panel.
            'movement_note' => 'Motivo: :note',
            // `#318` (`[DECIDIDO owner]`): el libro va PLEGADO —Total · Pagado · saldo de un vistazo— y UN CTA abre el detalle.
            'expand' => 'Ver el desglose',
            'collapse' => 'Cerrar el desglose',
            'movements' => 'Movimientos',
            'settlements' => 'Pagos y devoluciones',
            'total' => 'Total',
            'paid' => 'Pagado',
            // El saldo por CLASE (spec §4.4): la clase la decide el libro; el rótulo la nombra.
            'balance_pay_at_park' => 'A pagar en el parque',
            'balance_refund_at_park' => 'A devolver en el parque',
            'balance_refund_pending' => 'Pendiente de devolución',
            'balance_pay_online' => 'Pendiente de pagar por web',
            'balance_rest_at_park' => '+ :amount en el parque',
            'balance_settled' => 'Nada pendiente',
            'balance_expired' => 'Caducado sin cobro',
            'balance_under_review' => 'En revisión: el libro no cuadra',
        ],

        'order_financial' => [
            // T3·2 del LIBRO (`specs/desglose-libro.md` §6.3.2): el bloque «Totales del pedido» pinta
            // `OrderBook` con las claves `book.*`; de los canales de dos ejes quedan el título y el aviso.
            'heading' => 'Totales del pedido',
            'no_cuadra_title' => 'Este desglose no cuadra.',
            'no_cuadra_body' => 'Las cifras de abajo no cierran entre sí, así que alguna es falsa: revisa los pagos, los reembolsos y los ajustes de este pedido antes de fiarte de ellas. El cliente NO ve este desglose: ve el importe que se le cobró y un aviso de que lo estamos revisando.',
        ],

        // Razones de bloqueo per-item (compartidas entre cancel_item y refund_item).
        // El handler las traduce vía `__('admin.orders.item_actions.reasons.:reason')`.
        'item_actions' => [
            // Sub-fase 7.2e.1bis5 (decisión #158, punto 7B feedback): banners
            // contextuales en la card "Productos del pedido" cuando el Order
            // ENTERO está cancelado o reembolsado completamente. Refuerza
            // visualmente que las acciones cancel/refund por producto YA NO
            // aplican (el backend también bloquea, esto es UX explicativo).
            'banner' => [
                'order_cancelled' => 'Este pedido está cancelado y no queda nada por devolver. Las acciones individuales de cancelar o reembolsar productos ya no aplican.',
                // `#152`: cancelar cancela el PRODUCTO; el dinero cobrado se sigue debiendo, y se
                // devuelve por LÍNEA. El texto viejo afirmaba «reembolsar ya no aplica» y era el
                // cartel del callejón medido en `#150`.
                'order_cancelled_with_debt' => 'Este pedido está cancelado y quedan :pendiente por devolver al cliente. Usa «Reembolsar» (↩️) en cada línea para devolverlo — puedes elegir el importe. Cancelar productos ya no aplica.',
                'order_fully_refunded' => 'Este pedido se ha reembolsado por completo: ya no queda importe que reembolsar. Cancelar productos sí sigue disponible (cancelar ≠ reembolsar).',
            ],

            'reasons' => [
                'not_found' => 'no encontramos este producto',
                'not_in_order' => 'el producto no pertenece a este pedido',
                'item_cancelled' => 'el producto ya está cancelado',
                'item_finished' => 'el producto ya finalizó (servicio prestado)',
                'item_is_addon' => 'los complementos se gestionan desde el producto principal',
                'order_not_operational' => 'el pedido no permite cambios en sus productos en este momento',
                'order_not_paid' => 'el pedido no está pagado',
                'no_paid_payment' => 'no hay un cobro confirmado al que asociar la devolución',
                'insufficient_refundable' => 'ya se ha devuelto suficiente del pedido como para no cubrir el importe del producto',
                'already_fully_refunded' => 'ya se ha devuelto todo lo que se cobró del pedido',
                'item_already_fully_refunded' => 'este producto ya tiene todo su importe devuelto',
                'expired' => 'el pedido caducó',
                'invalid_amount' => 'el importe a devolver no es válido',
                'stale_item_version' => 'el producto se modificó mientras tenías el modal abierto; ciérralo y vuelve a intentarlo',
                'capacity_changed' => 'la capacidad de devolución del pedido cambió mientras tenías el modal abierto; ciérralo y vuelve a intentarlo',
                'no_items_selected' => 'no has marcado ningún producto para reembolsar',
                'invalid_item_selection' => 'la selección de productos a reembolsar no es válida; ciérralo y vuelve a intentarlo',
                // D5 (`#146`): el importe elegido del modal Reembolsar.
                'custom_amount_requires_single_item' => 'para elegir el importe marca UNA sola línea (con varias no sabríamos a cuál atribuir la devolución)',
                'invalid_custom_amount' => 'el importe a devolver tiene que ser mayor que cero',
                'exceeds_item_refundable' => 'el importe supera lo que queda por devolver de esa línea',
                // T4 del libro (`DECISIONES #316`): el motivo manda, y el dominio lo hace valer bajo lock.
                'exceeds_owed' => 'el importe supera lo que se le debe por esa reserva — «devolver lo que se le debe» no puede exceder lo debido; registra antes la bajada, baja el importe o elige compensación',
                'compensation_without_note' => 'una compensación necesita un motivo escrito',
                'note_too_long' => 'el motivo supera los 200 caracteres',

                // Sub-fase 7.2e.2 (decisión #159): razones específicas del
                // cambio fecha/hora (manageItemAction). Reusan la convención
                // de claves en minúsculas y prosa operativa.
                'invalid_slot_selection' => 'la fecha y hora seleccionadas no existen en el sistema',
                'cross_zone_change_forbidden' => 'no puedes cambiar a una hora de otra zona desde aquí (cambia de producto)',
                'slot_closed' => 'la franja seleccionada está cerrada al público',
                'slot_in_past' => 'la fecha seleccionada ya pasó',
                'park_closed' => 'el parque está cerrado ese día',
                'product_window' => 'el producto no se ofrece en esa franja',
                'insufficient_capacity_at_save' => 'ya no quedan plazas suficientes en esa franja',
                // La HORA EXTRA (`specs/hora-extra.md`): la hija ocupa la franja SIGUIENTE al padre.
                'addon_occupancy_at_destination' => 'la hora extra de esta reserva no cabe detrás del destino: la franja siguiente no existe, está cerrada o está completa',
                'addon_stay_exceeds_quantity' => 'no pueden quedarse más personas (hora extra) de las que entran; baja primero las horas extra',
                'beyond_horizon' => 'la fecha está más allá del horizonte de reservas permitido',

                // Sub-fase 7.2e.3 (decisión #167): razones del cambio de
                // cantidad + producto (executeItemEdit).
                'cross_type_change_forbidden' => 'no puedes cambiar entre entrada y pack desde aquí; usa un pedido manual',
                'cross_zone_change_forbidden_product' => 'solo puedes cambiar a un producto de la misma zona; para cambiar de zona usa un pedido manual',
                'invalid_product' => 'el producto seleccionado no es válido o no está a la venta',
                'invalid_quantity' => 'la cantidad indicada no es válida',
                'pack_quantity_range' => 'el número de invitados está fuera del rango permitido para este pack',

                // T3 · F: razones del guardado de fichas por invitado (pestaña «Invitados»).
                'not_pack' => 'este producto no lleva formulario de invitados',
                'no_guest_fields' => 'este pack no define campos por invitado',
                'product_unavailable_on_date' => 'el producto nuevo no tiene precio para la fecha seleccionada',
                'orphan_addons' => 'el producto nuevo no admite alguno de los complementos del producto actual; quítalos primero',

                // Sub-fase 7.2e.4 (decisión #170): razones de la gestión de
                // complementos (Tab 2 del modal Gestionar).
                'addon_not_in_parent' => 'uno de los complementos no pertenece a este producto',
                'addon_incompatible_with_product' => 'ese complemento no es compatible con este producto',
                'addon_already_added' => 'ese complemento ya está en el producto',
                'addon_quantity_invalid' => 'la cantidad del complemento no es válida',
                'addon_partial_reduce_unsupported' => 'para reducir un complemento, quítalo (cantidad 0) y vuelve a añadirlo con la cantidad deseada; el reembolso se hace aparte',
                'addon_locked' => 'ese complemento viene incluido en el producto y no se puede quitar ni reducir (cambia el menú eligiendo otra opción del grupo)',
                'addon_no_extra' => 'ese complemento viene incluido y no admite unidades de pago por encima de lo incluido',
                'addon_group_conflict' => 'solo se puede elegir un complemento de cada grupo',
                'addon_unavailable_on_date' => 'el complemento no tiene precio para la fecha seleccionada',
                'addon_requires_missing' => 'ese complemento necesita que otro esté en la reserva (p. ej. la 2.ª tarta requiere la tarta); añade primero el complemento requerido',
            ],
        ],
    ],

    // Fase 7.5 — Gestión de usuarios (RGPD del día a día), decisión #180.
    'users' => [
        // #219: el menu dice «Clientes» porque es lo que se busca a diario; el EQUIPO
        // es la otra pestana de esta misma pantalla y se entra por Ajustes.
        'nav_label' => 'Clientes',
        'tabs' => [
            'clients' => 'Clientes',
            'team' => 'Equipo',
        ],
        'title_clients' => 'Clientes',
        'title_team' => 'Equipo',
        'model_label_singular' => 'usuario',
        'model_label_plural' => 'Usuarios',
        'heading' => 'Usuario',

        'col_name' => 'Nombre',
        'col_email' => 'Email',
        'col_phone' => 'Teléfono',
        'col_roles' => 'Rol',
        'col_status' => 'Estado',
        'col_verified' => 'Email verificado',
        'col_last_login' => 'Último acceso',
        'col_created_at' => 'Alta',
        'col_locale' => 'Idioma',
        'col_marketing' => 'Comunicaciones comerciales',
        // `#347`: cómo entra este cliente. Sin el `sub` — el operador necesita saber CÓMO entra,
        // no el identificador, que solo viaja en el export del art. 20.
        'col_google' => 'Entra con Google',
        'google_linked' => 'Sí, desde el :date',

        'verified' => 'Verificado',
        'unverified' => 'Sin verificar',
        'never' => 'Nunca',
        'yes' => 'Sí',
        'no' => 'No',

        'status' => [
            'active' => 'Activa',
            'anonymized' => 'Anonimizada',
        ],

        'roles' => [
            'admin' => 'Administrador',
            'customer' => 'Cliente',
            'staff' => 'Empleado',
        ],

        'locale_value' => [
            'es' => 'Español',
            'en' => 'Inglés',
            'fr' => 'Francés',
        ],

        'section_data' => 'Datos de la cuenta',
        'section_roles' => 'Roles',
        'section_consents' => 'Consentimientos',
        // Fase 6 · C (`specs/menores-a-cargo.md` §9.11 D·3): los menores del titular en la ficha, de
        // SOLO LECTURA — el panel no los declara ni los edita (`[DECIDIDO owner, 2026-08-28]`).
        'section_dependents' => 'Menores a cargo',
        'section_orders' => 'Pedidos',

        'orders_summary' => [
            'empty' => 'Este cliente todavía no tiene pedidos.',
        ],

        // ⚠️ El ESTADO de la exención NO se redacta aquí: se reutilizan
        // `admin.orders.dependents.waiver_{current,outdated,missing}`, los mismos que la ficha del
        // PEDIDO, para que el operador lea la misma frase en las dos pantallas.
        'dependents' => [
            'empty' => 'Este cliente no tiene menores a cargo declarados. Los declara él mismo desde su cuenta, en «Menores a cargo».',
            // ⚠️ Tras anonimizar, las filas que conservan una exención firmada SOBREVIVEN desvinculadas
            // bajo el régimen restringido de `RGPD-01`: su sitio es la acción «Registro del waiver»
            // (con permiso propio y cada consulta auditada), no una lista de la ficha.
            'anonymized' => 'Cuenta anonimizada: los menores que conserven un descargo firmado siguen en régimen restringido y solo se consultan desde «Registro del descargo».',
            'col_name' => 'Nombre',
            'col_relationship' => 'Relación',
            'relationship_father' => 'Padre',
            'relationship_mother' => 'Madre',
            'relationship_legal_guardian' => 'Tutor/a legal',
            'relationship_grandparent' => 'Abuelo/a',
            'relationship_other' => 'Otra',
            'col_age' => 'Edad',
            'col_waiver' => 'Descargo',
            'col_since' => 'Declarado',
            'col_removed' => 'Retirado',
            // ⚠️ «(hoy)» no es adorno: en la ficha del PEDIDO la edad es la del día de la visita, y sin
            // esta palabra el mismo menor parece tener dos edades distintas en dos pantallas del panel.
            'age_today' => ':age años (hoy)',
            'adult' => 'ya tiene 18 años',
            'removed_on' => 'retirado el :date',
        ],
        'consents' => [
            'empty' => 'Esta cuenta no tiene consentimientos registrados.',
            'types' => [
                'privacy' => 'Política de privacidad',
                'terms' => 'Términos y condiciones',
                'waiver' => 'Descargo de responsabilidad',
                'marketing' => 'Comunicaciones comerciales',
            ],
        ],

        'filter_anonymized' => 'Cuentas anonimizadas',
        'filter_anonymized_all' => 'Todas',
        'filter_anonymized_yes' => 'Solo anonimizadas',
        'filter_anonymized_no' => 'Solo activas',

        'actions' => [
            'send_reset' => [
                'label' => 'Enviar enlace de contraseña',
                'modal_heading' => 'Enviar enlace de cambio de contraseña',
                'modal_description' => 'Se enviará un correo a :email con un enlace para que el cliente establezca una nueva contraseña.',
                'submit' => 'Enviar enlace',
                'success' => 'Enlace de cambio de contraseña enviado a :email.',
                'throttled' => 'Ya se envió un enlace hace poco. Espera un minuto antes de volver a enviarlo.',
                'blocked' => 'No se puede enviar el enlace a esta cuenta.',
            ],
            // El carné QR (Fase 6 · A, `specs/identidad-qr-puerta.md` §9.6 B·5): rotar desde la ficha.
            // ⚠️ La palabra de cara a personas es «QR» (`[DECIDIDO owner, 2026-08-28]`, `#217`); la
            // CLAVE sigue siendo `rotate_card` porque el nombre técnico —`CustomerCards::rotate()`,
            // `cards.rotated`, `POST /me/card/rotate`— no cambia, y renombrarla rompería la auditoría
            // sin ganar nada. Aquí se dice «del cliente» porque el operador ve muchos QR, no el suyo.
            'rotate_card' => [
                'label' => 'Renovar QR del cliente',
                'modal_heading' => 'Renovar el QR del cliente',
                'modal_description_active' => 'El QR actual (emitido el :date) dejará de valer EN EL ACTO: el del correo y cualquier copia impresa. Se emite uno nuevo, que el cliente verá en «Mi QR» y en su próxima confirmación de pedido.',
                'modal_description_none' => 'Este cliente todavía no tiene QR. Se emitirá uno nuevo, que verá en «Mi QR» y en su próxima confirmación de pedido.',
                'submit' => 'Renovar QR',
                'success' => 'QR renovado: el anterior ya no vale y el cliente tiene uno nuevo.',
                'blocked' => 'No se puede renovar el QR de esta cuenta.',
            ],
            'anonymize' => [
                'label' => 'Anonimizar',
                'modal_heading' => 'Anonimizar usuario (RGPD)',
                'modal_description' => 'Esta acción es IRREVERSIBLE. Se borran los datos personales (nombre, email, teléfono), se eliminan los consentimientos y se desvinculan los roles. Los pedidos se conservan por obligación fiscal. La cuenta no podrá iniciar sesión y su email quedará libre para reuso.',
                'reason' => 'Motivo (queda en el registro de auditoría)',
                'submit' => 'Anonimizar definitivamente',
                'success' => 'Usuario anonimizado correctamente.',
                'blocked' => 'No se puede anonimizar esta cuenta.',
                // T5 · D8: con reservas por celebrar la supresión espera (las tres vías, `cumple-mixto.md` §25.4).
                'blocked_upcoming' => 'No se puede anonimizar: el cliente tiene reservas por celebrar. Cancélalas primero o espera a que pasen.',
            ],
        ],
    ],

    'audit' => [
        'nav_label' => 'Incidencias',
        'model_label_singular' => 'incidencia',
        'model_label_plural' => 'Incidencias',
        'col_when' => 'Cuándo',
        'col_action' => 'Evento',
        'col_actor' => 'Usuario',
        'actor_system' => 'Sistema',
        'col_target' => 'Asociado a',
        'target_order' => 'Pedido :code',
        'col_detail' => 'Detalle',
        'col_ip' => 'IP',
        'filter_critical_only' => 'Solo incidencias críticas',
        'actions' => [
            'payments_duplicate_capture' => 'Cobro duplicado/huérfano',
            'payments_overbooked_capture' => 'Cobro tras caducar',
            'orders_refund_failed' => 'Fallo de reembolso',
            'orders_item_refund_failed' => 'Fallo de reembolso (línea)',
            'orders_payment_init_failed' => 'Fallo al iniciar el pago',
            'users_anonymize_blocked' => 'Anonimización bloqueada (RGPD)',
            'access_user_roles_update_blocked' => 'Cambio de roles bloqueado',
            'registrations_validate_rate_limited' => 'Límite de validaciones (puerta)',
            'puerta_lookup_rate_limited' => 'Límite de búsquedas tecleadas (ficha de puerta)',
        ],
    ],

    'access' => [
        'nav_label' => 'Roles y permisos',
        'model_label_singular' => 'rol',
        'model_label_plural' => 'Roles y permisos',
        'edit_title' => 'Permisos del rol: :role',

        'col_role' => 'Rol',
        'col_name' => 'Identificador',
        'col_permissions' => 'Permisos',
        'all_permissions' => 'Todos',

        'section_identity' => 'Identidad',
        'field_name' => 'Identificador técnico',
        'field_name_hint' => 'Inmutable: lo usa el código (no se puede renombrar).',
        'field_display' => 'Rol',

        'admin_notice_title' => 'Administrador',
        'admin_notice' => 'El administrador es super-usuario: tiene TODOS los permisos automáticamente, así que aquí no se editan.',
        'customer_notice_title' => 'Cliente',
        'customer_notice' => 'El rol de cliente no accede al panel de administración; sus permisos no aplican aquí.',
        'access_manage_admin_only' => 'Reservado al administrador (no se puede conceder a otros roles).',

        'groups' => [
            'operativa' => 'Operativa diaria',
            'gestion' => 'Gestión y configuración',
            'sistema' => 'Sistema',
        ],

        'permissions' => [
            'registrations_validate' => 'Validar registro/descargo en puerta',
            'puerta_profile' => 'Ver la ficha de puerta del cliente y registrar su visita',
            'orders_view' => 'Ver pedidos',
            'orders_create_manual' => 'Crear pedido manual (back-office)',
            'orders_cancel' => 'Cancelar pedido',
            'orders_refund' => 'Reembolsar pedido',
            'orders_edit_event_data' => 'Editar datos del evento del pedido (homenajeado, edad, notas)',
            'orders_edit_guest_data' => 'Editar los datos por invitado desde el panel',
            'orders_edit_item' => 'Editar producto del pedido (fecha, cantidad, producto, datos, complementos)',
            // `#329` — la CLAVE sigue diciendo `edit_item` y el rótulo ya no, a propósito: el permiso
            // gobierna las DOS puertas (crear un pedido manual y editar una reserva) y renombrar la
            // clave obligaría a migrar la tabla de permisos y los roles ya asignados en la
            // instalación del cliente, a cambio de nada que el operador vea. Lo que el operador lee
            // es esto.
            'orders_edit_item_below_minimum' => 'Vender o dejar un pack por debajo de su mínimo de invitados, al crear y al editar (queda registrado)',
            'orders_cancel_item' => 'Cancelar un producto suelto del pedido',
            'orders_refund_item' => 'Reembolsar un producto suelto del pedido',
            'calendar_view' => 'Ver calendario y escritorio',
            'users_search_minimal' => 'Búsqueda mínima de usuario (RGPD-safe)',
            'catalog_manage' => 'Gestionar catálogo (entradas, packs, complementos)',
            'slots_manage' => 'Gestionar franjas y aforo',
            'prices_manage' => 'Gestionar tarifas y precios',
            'content_manage' => 'Gestionar contenido (zonas, atracciones, FAQ, normas, páginas)',
            'settings_manage' => 'Gestionar configuración y datos fiscales',
            'users_manage' => 'Gestionar usuarios (ficha, anonimizar, contraseña)',
            'users_anonymize' => 'Anonimizar usuario (RGPD)',
            'consents_view' => 'Ver consentimientos de usuario',
            'waiver_view' => 'Ver el registro probatorio del descargo (firmas y PDF)',
            'reports_view' => 'Ver informes y exportaciones',
            'audit_view' => 'Ver registro de auditoría',
            'access_manage' => 'Gestionar roles y permisos',
        ],

        'user_roles' => [
            'label' => 'Gestionar roles',
            'modal_heading' => 'Roles del usuario',
            'modal_description' => 'Marca los roles de esta cuenta. Asignar «Empleado» o «Administrador» le da acceso al panel; quitarlos se lo retira.',
            'field' => 'Roles asignados',
            'submit' => 'Guardar roles',
            'success' => 'Roles actualizados.',
            'blocked' => 'No se pueden gestionar los roles de esta cuenta.',
            'blocked_self' => 'No puedes quitarte a ti mismo el rol de administrador.',
            'blocked_last_admin' => 'No se puede quitar el último administrador del sistema.',
        ],
    ],

    'catalog' => [
        'nav_label' => 'Catálogo',
        'model_label_singular' => 'producto',
        'model_label_plural' => 'Catálogo',

        'col_name' => 'Nombre',
        'col_type' => 'Tipo',
        'col_zone' => 'Zona',
        'col_duration' => 'Duración',
        'col_price' => 'Precio',
        'col_featured' => 'Destacado',
        'col_active' => 'Activo',
        'col_sellable' => 'Vendible',
        'col_position' => 'Orden',

        'types' => [
            'entry' => 'Entrada',
            'pack' => 'Pack',
            'addon' => 'Complemento',
        ],

        'active_yes' => 'Activo',
        'active_no' => 'Inactivo',
        'sellable_yes' => 'En venta',
        'sellable_no' => 'Fuera de venta',
        'duration_unlimited' => 'Ilimitada',

        'price_from' => 'desde :amount',
        'price_readonly_hint' => 'El precio se edita en la ficha de cada producto (sección «Precio»).',

        'edit_title' => 'Editar: :name',
        'create_title' => 'Crear producto',
        'created_draft_hint' => 'Producto creado. Para venderlo, márcalo «En venta online» y ponle precio.',
        'type_create_hint' => 'Elige el tipo de producto. No se podrá cambiar después de crearlo (afecta al aforo y a los pedidos).',
        'type_locked_hint' => 'El tipo no se puede cambiar (afecta al aforo y a los pedidos).',

        'section_classification' => 'Clasificación y estado',
        'section_classification_hint' => 'El tipo no se cambia (afecta al aforo y a los pedidos). El orden de aparición se ajusta arrastrando en el listado del catálogo.',
        'section_operational' => 'Aforo y horario',
        'section_operational_hint' => 'Cuánto dura, cuántas plazas ocupa cada unidad y en qué tramo del horario se puede empezar.',
        'section_pack' => 'Pack (cumpleaños)',
        'section_pack_hint' => 'Invitados, señal y tiempos de montaje/limpieza. Nota: la señal hoy solo se muestra en la web; el cobro actual es el importe total (pendiente de conectar el cobro parcial).',
        'section_price' => 'Precio',
        'price_section_hint' => 'El precio del día se decide por la tarifa aplicable. Deja un importe vacío para no vender ese producto los días de esa tarifa.',
        'price_rate_normal_hint' => 'Precio para los días normales.',
        'price_rate_special_hint' => 'Precio para festivos, fines de semana y vísperas.',
        'section_price_tiers' => 'Precio por cantidad (tramos)',
        'price_tiers_hint' => 'Opcional. «Desde N unidades, cada una cuesta X» — el tramo llega hasta que empieza el siguiente, así que no hace falta un máximo. El precio es UNIFORME: si el tramo de 70 son 13 €, un grupo de 70 paga 70 × 13 €. Sin tramos, manda el precio de arriba.',
        'price_tiers_blocked' => 'Este producto declara una familia de edades (fiesta mixta), así que no puede tener tramos por cantidad: la reserva congela el precio de cada edad al venderse y un tramo lo movería después.',
        'price_tier_add' => 'Añadir tramo',
        'price_tier_rate' => 'Tarifa',
        'price_tier_min_qty' => 'Desde (unidades)',
        'price_tier_min_qty_hint' => 'Inclusive.',
        'price_tier_amount' => 'Precio por unidad',
        'price_no_rates' => 'No hay tarifas activas. Configúralas para poder fijar precios.',

        'lang' => [
            'es' => 'Español',
            'en' => 'Inglés',
            'fr' => 'Francés',
        ],

        'field_name' => 'Nombre',
        'field_period_label' => 'Unidad de precio',
        'period_label_hint' => 'Ej.: "por persona", "por niño".',
        'field_description' => 'Descripción',
        'field_badge' => 'Etiqueta destacada',
        'badge_hint' => 'Texto corto tipo "Top" sobre la tarjeta. Vacío = sin etiqueta.',
        'field_features' => 'Ventajas',
        'features_hint' => 'Una ventaja por línea.',
        'field_gifts' => 'Regalos',
        'gifts_hint' => 'Un regalo por línea: lo que se da sin cobrar («Cono de chuches», «Calcetines antideslizantes para todos»). La web y el cajón destacan cada uno en una etiqueta amarilla, así que no lo repitas en las ventajas.',

        'field_is_active' => 'Visible en la web',
        'is_active_hint' => 'Si se desactiva, deja de mostrarse en la web pública.',
        'field_is_sellable' => 'En venta online',
        'is_sellable_hint' => 'Debe tener precio para poder venderse.',
        'field_featured' => 'Destacado',
        'featured_hint' => 'Resalta el producto en la web.',
        'field_icon' => 'Icono del producto',
        'icon_placeholder' => 'El que le toca por su tipo',
        'icon_hint' => 'Marca el producto en la cesta, en el resumen y en «Mis pedidos». Si lo dejas vacío se usa el de su tipo: tarta para los packs y entrada para el resto.',
        'field_image' => 'Foto del producto',
        'field_image_hint' => 'webp, jpg o png; máx. 3 MB. Es la foto de la ficha: la publica la API y la pinta quien venda desde fuera de esta web (la app o una landing propia). Opcional — sin foto, no se publica el campo. Al cambiarla se borra la anterior.',
        /*
         * ⚠️⚠️ **CUATRO de las once opciones NO tenían rótulo y el selector enseñaba la CLAVE CRUDA**
         * (`admin.catalog.icon_option.ticket`, y lo mismo `gift`, `party` y `school-trip`).
         * Llegó con `#258`, que amplió `ProductIcon::CHOICES` de 6 a 11 sin tocar esta lista, y **no
         * lo veía ninguna guarda**: `ProductIconSingleSourceTest` comprobaba que el icono existe y
         * que el cajón sabe dibujarlo, nunca que tuviera nombre. Reproducido y corregido en
         * `#475`, con guarda (`test_every_offered_icon_has_a_label`).
         *
         * ▶ Los rótulos separan las DOS familias, porque hay dos tartas y dos entradas: las
         * `ic-*`/`socks`/`ticket-tear-off` son ILUSTRACIONES del cliente de origen en su propia
         * escala, y el resto son glifos del set de diseño en la rejilla de 24. No se retira ninguna
         * (`#258`: retirar una clave degradaría en silencio todo producto que la tuviera guardada).
         *
         * ⚠️ No se traducen a `zh_CN` a propósito: ese idioma existe para el EMPLEADO DE MOSTRADOR
         * (`AdminPanelProvider`), y el catálogo es admin-only, así que nadie con ese idioma abre
         * esta pantalla. Inventar la traducción sería mantener texto que no lee nadie.
         */
        'icon_option' => [
            // Ilustraciones heredadas, en su propia escala.
            'ic-b1' => 'Tarta de cumpleaños (ilustración clásica)',
            'ic-b7' => 'Cañón de confeti',
            'ic-e2' => 'Par de entradas',
            'ic-e5' => 'Taco de entradas',
            'ticket-tear-off' => 'Entrada troquelada',
            'socks' => 'Calcetines',
            // Set de diseño, rejilla 24.
            'ticket' => 'Entrada',
            'gift' => 'Regalo',
            'pack' => 'Pack',
            'party' => 'Fiesta',
            'school-trip' => 'Excursión de colegio',
            'cake' => 'Tarta',
            'ice-bucket' => 'Cubo de refrescos',
            'snacks' => 'Tapas',
            'drink' => 'Bebida',
            'clock-plus' => 'Hora extra',
        ],

        'zone_hint' => 'Zona a la que da acceso.',
        'zone_locked_sold' => 'No se puede cambiar la zona: el producto ya tiene ventas (movería su aforo).',

        'field_duration_min' => 'Duración (minutos)',
        'duration_min_hint' => 'Vacío = ilimitada (todo el día).',
        'duration_locked_sold' => 'No se puede cambiar la duración: el producto ya tiene ventas (re-interpretaría el aforo de lo ya vendido).',

        /*
         * La HORA EXTRA (`specs/hora-extra.md`): un complemento que OCUPA la franja siguiente al
         * tramo de su producto. Los rótulos hablan del CASO («quedarse más tiempo»), no del mecanismo.
         */
        'section_occupancy' => 'Complemento que ocupa aforo',
        'section_occupancy_hint' => 'La «hora extra»: quien lo compra se queda en la franja siguiente a su entrada, y esas plazas cuentan y se reservan. La cantidad son ENTRADAS que se quedan (el precio es por persona).',
        'field_occupies_after_parent' => 'Ocupa la franja siguiente',
        'occupies_after_parent_hint' => 'Apagado (lo normal): el complemento no toca el aforo, como una camiseta. Encendido: cada unidad vendida se queda ocupando plaza detrás de su entrada.',
        'field_occupies_duration_min' => 'Cuánto ocupa (minutos)',
        'occupies_duration_min_hint' => 'La duración de la estancia extra (60 = una hora). Obligatoria si ocupa: sin ella no se puede ofrecer.',
        'occupancy_locked_sold' => 'No se puede cambiar: hay ventas hechas con esta configuración y el aforo de lo vendido se re-interpretaría.',
        // La hora extra de un PACK (`specs/hora-extra.md` §10): el hermano del de arriba. Su rótulo
        // dice «alarga la fiesta» y no «ocupa» a propósito: lo que se vende es tiempo de SALA, no
        // plazas para quien se queda — y de esa diferencia sale que su cantidad sean horas.
        'field_extends_parent_stay' => 'Alarga la fiesta (hora extra de sala)',
        'extends_parent_stay_hint' => 'Solo para complementos de un PACK. Encendido: cada unidad vendida alarga la fiesta —la sala sigue ocupada por ese grupo—, así que la cantidad son BLOQUES DE TIEMPO, no personas. No se puede combinar con «Ocupa la franja siguiente», que se vende por persona.',
        'field_extends_duration_min' => 'Cuánto alarga cada bloque (minutos)',
        'extends_duration_min_hint' => 'Lo que alarga UNA unidad (60 = una hora). Obligatoria: sin ella se estaría vendiendo una hora extra que no ocupa nada.',
        'stay_extension_locked_sold' => 'No se puede cambiar: hay fiestas vendidas con esta hora extra, y apagarla acortaría su duración en la siguiente edición.',
        'field_seats_per_unit' => 'Plazas por unidad',
        'seats_per_unit_hint' => 'Plazas de aforo que consume cada unidad vendida.',
        'field_available_after_open_min' => 'Disponible tras la apertura',
        'available_after_open_hint' => 'Minutos tras abrir el parque antes de poder empezar (0 = sin restricción).',
        'field_available_before_close_min' => 'Cierre de venta antes del cierre',
        'available_before_close_hint' => 'Minutos antes del cierre en que deja de poder empezar (0 = sin restricción).',
        'minutes' => 'min',
        'field_min_advance' => 'Antelación mínima de reserva',
        'min_advance_hint' => 'Con cuánta antelación respecto a HOY se puede reservar (0 = sin restricción). Es distinto de la ventana horaria de arriba: aquí son días/horas ANTES de la visita.',
        'field_min_advance_unit' => 'Unidad de la antelación',
        'min_advance_units' => [
            'days' => 'días (no el mismo día)',
            'hours' => 'horas (antes de la franja)',
        ],

        /*
         * El JUSTIFICANTE de un menor invitado (`specs/waiver-por-reserva.md` §12.2).
         * Los rótulos hablan del CASO, no del mecanismo: quien configura un producto no tiene por qué
         * saber qué es un «waiver offshore», pero sí sabe lo que es el amigo de su hijo.
         */
        'field_guardian_authorization' => 'Justificante para menores invitados',
        'guardian_authorization_hint' => 'Para menores que NO son menores a cargo de quien reserva (el amigo del hijo, una excursión de colegio). Su padre, madre o tutor firma el descargo desde un enlace, sin necesitar cuenta.',
        'guardian_modes' => [
            'none' => 'No se ofrece',
            'optional' => 'Opcional: el cliente marca si viene alguno',
            'required' => 'Obligatorio: este producto siempre lo necesita',
        ],

        'field_guest_invitation' => 'Invitación digital',
        'guest_invitation_hint' => 'El cliente comparte un enlace y cada padre contesta si su hijo viene, con su nombre. Solo en packs con datos por invitado que tengan una columna de nombre, y no se puede combinar con el justificante obligatorio. Con la invitación encendida el cliente ya no ve la casilla del justificante: lo dice cada padre al contestar.',

        'field_min_qty' => 'Mín. invitados',
        'min_qty_hint' => 'Número mínimo de invitados para reservar.',
        'field_max_qty' => 'Máx. invitados',
        'max_qty_hint' => 'No puede ser menor que el mínimo.',
        'max_qty_below_min' => 'El máximo de invitados no puede ser menor que el mínimo.',
        'field_deposit_type' => 'Tipo de señal',
        'deposit_types' => [
            'none' => 'Sin señal (pago total)',
            'percent' => 'Porcentaje del total',
            'fixed' => 'Importe fijo',
        ],
        'field_deposit_value' => 'Valor de la señal',
        'deposit_value_percent' => 'Porcentaje (%) del total a cobrar como señal.',
        'deposit_value_fixed' => 'Importe fijo en céntimos (3000 = 30,00 €).',
        'deposit_value_none' => 'No se aplica (pago total).',
        'field_prep_before_min' => 'Montaje (minutos antes)',
        'prep_before_hint' => 'Tiempo de preparación que bloquea el cupo antes de la fiesta.',
        'field_prep_after_min' => 'Limpieza (minutos después)',
        'prep_after_hint' => 'Tiempo de limpieza que bloquea el cupo después de la fiesta.',

        'field_event_fields' => 'Datos del evento (esquema)',
        'event_fields_hint' => 'Campos que se piden al reservar este pack (p. ej. nombre del homenajeado).',
        'event_field_key' => 'Clave',
        'event_field_key_hint' => 'Identificador técnico: minúsculas, números, guion o guion bajo.',
        'event_field_type' => 'Tipo',
        'event_field_types' => [
            'text' => 'Texto',
            'number' => 'Número',
            'textarea' => 'Texto largo',
            'age' => 'Edad',
            // `#588`: una por fiesta; con la edad fuera del tramo del pack, la web no deja reservarlo.
            'celebrant_age' => 'Edad del cumpleañero (se comprueba con el tramo del pack)',
        ],
        'event_field_required' => 'Obligatorio',
        'event_field_label' => 'Etiqueta',
        'event_field_add' => 'Añadir campo',
        'event_field_duplicate' => 'La clave ":key" está repetida en los datos del evento.',
        'event_field_stage' => 'Cuándo se pide',
        'event_field_stages' => [
            'booking' => 'Al reservar',
            'postform' => 'Formulario posterior',
        ],
        'event_field_stage_hint' => '«Al reservar» se pide durante la compra; «Formulario posterior» se pide después, junto a los datos por niño.',

        'field_guest_fields' => 'Datos por niño (esquema)',
        'guest_fields_hint' => 'Columnas que se piden de CADA invitado en el formulario posterior a la reserva (por defecto: nombre, alergia, observaciones, menú especial).',
        'guest_field_add' => 'Añadir columna',
        'guest_field_duplicate' => 'La clave ":key" está repetida en los datos por niño.',

        // Familia y tramo de edad (cumpleaños MIXTO, `docs/specs/cumple-mixto.md` §9).
        'field_guest_age_family' => 'Familia por edad',
        'guest_age_family_hint' => 'Conecta este pack con los que son el MISMO servicio en otro tramo de edad (p. ej. escribe «cumple» en el infantil y en el juvenil). Solo minúsculas, números y guiones. Vacío = este producto no distingue edades y no propone suplementos. Las fiestas ya vendidas conservan la familia, los tramos y los precios con los que se compraron: cambiar esto solo afecta a las siguientes.',
        'field_guest_age_min' => 'Edad mínima',
        'guest_age_min_hint' => 'Primera edad que cubre este pack, INCLUIDA. Vacío = sin tope por abajo.',
        'field_guest_age_max' => 'Edad máxima',
        'guest_age_max_hint' => 'Última edad que cubre este pack, INCLUIDA: con 6, el niño de 6 entra y el de 7 corresponde al pack siguiente. Vacío = sin tope por arriba.',
        'guest_age_range_required' => 'Si el pack declara una familia por edad, tiene que declarar también su tramo (al menos la edad mínima o la máxima).',
        'guest_age_range_inverted' => 'La edad máxima no puede ser menor que la mínima.',
        'guest_age_range_overlap' => 'El tramo de edad pisa al de «:name», que está en la misma familia. Dos packs no pueden cubrir la misma edad: no habría forma de saber a cuál corresponde un invitado.',
        'warn_sellable_no_price' => 'Producto marcado como vendible pero sin precio en la tarifa base: no se podrá vender hasta fijar su precio en Tarifas y precios (7.8).',

        'actions' => [
            'create' => 'Crear producto',
            'delete' => [
                'label' => 'Borrar producto',
                'modal_heading' => 'Borrar este producto del catálogo',
                'modal_description' => 'Solo se puede borrar un producto que NUNCA se haya vendido. Se eliminarán también sus precios y su asociación de complementos. Esta acción es irreversible. Si el producto tiene ventas, desactívalo en lugar de borrarlo.',
                'submit' => 'Borrar definitivamente',
                'blocked' => 'No se puede borrar: el producto tiene ventas. Desactívalo en su lugar.',
                'success' => 'Producto borrado del catálogo.',
            ],
        ],

        'addons' => [
            'title' => 'Complementos aplicables',
            'col_name' => 'Complemento',
            'col_status' => 'Estado',
            'col_price' => 'Precio',
            'col_position' => 'Orden',
            'col_config' => 'Cómo se ofrece',
            'status_visible' => 'Se ofrece',
            'status_hidden' => 'Oculto (inactivo o fuera de venta)',
            'attach' => 'Añadir complemento',
            'attach_heading' => 'Añadir un complemento a este producto',
            'attach_select' => 'Complemento',
            'position' => 'Orden',
            'empty' => 'Este producto todavía no tiene complementos. Añade los que apliquen.',
            'already_attached' => 'Ese complemento ya está añadido a este producto.',
            'configure' => 'Configurar',
            'configure_heading' => 'Cómo se ofrece este complemento',
            'configured' => 'Complemento configurado.',
            'stage' => 'Cuándo se vende',
            'stage_hint' => 'Al reservar (lo normal) o DESPUÉS, desde el formulario post-reserva del cliente. Un complemento de venta posterior no nace nunca con el pedido: se añade luego y se paga en el parque.',
            'stage_booking' => 'Al reservar',
            'stage_postform' => 'Después, en el formulario post-reserva',
            'cutoff' => 'Plazo de corte (horas antes)',
            'cutoff_hint' => 'Cuántas horas antes de la reserva deja de poder añadirse o quitarse. Por ejemplo 48 para las tapas y 2 para un cubo de refrescos. 0 = hasta que empiece la fiesta.',
            'badge_postform' => 'Venta posterior',
            'badge_postform_cutoff' => 'Venta posterior · hasta :hours h antes',
            'postform_without_form' => 'Este producto no tiene formulario post-reserva',
            'postform_without_form_body' => 'El complemento queda guardado, pero el cliente no podrá elegirlo: solo un operador podrá añadirlo desde «Gestionar». Los formularios post-reserva los tienen hoy los packs con fichas de invitados.',
            'show_in_invitation' => 'Se enseña en la invitación',
            'show_in_invitation_hint' => 'Marca aquí el menú (o lo que quieras que vean los padres en la invitación digital de este producto). El mismo complemento puede enseñarse en un pack y no en otro.',
            'is_included' => 'Incluido (gratis)',
            'is_included_hint' => 'Las primeras unidades vienen gratis con el producto. Las que excedan se cobran a su precio.',
            'included_quantity' => 'Unidades incluidas',
            'included_quantity_hint' => 'Cuántas unidades van gratis (p. ej. 1 = la primera tarta).',
            'is_mandatory' => 'Obligatorio (siempre activo)',
            'is_mandatory_hint' => 'No se puede quitar ni bajar del mínimo en la compra.',
            'quantity_mode' => 'Cantidad',
            'quantity_mode_hint' => 'Fija = el cliente elige cuántas. Por invitado = una por cada invitado del pack.',
            'mode_fixed' => 'Cantidad fija (+ extras)',
            'mode_per_guest' => 'Una por invitado',
            // La HORA EXTRA cobrada POR INVITADO (`#443`, `specs/hora-extra.md` §11.5.3). En un
            // extensor la misma opción significa otra cosa: la fiesta se alarga UNA hora —una hora es
            // una hora, la compren 8 invitados o 20— y lo que escala con los invitados es el PRECIO.
            'mode_per_guest_stay' => 'Se cobra por invitado',
            'quantity_mode_stay_hint' => 'Fija = precio por cada hora extra. Se cobra por invitado = una hora más para toda la fiesta, al precio del complemento por cada invitado.',
            'quantity_mode_locked_sold' => 'No se puede cambiar: este complemento tiene reservas vendidas que todavía se pueden editar, y cambiar el modo las re-preciaría. Crea un complemento nuevo.',
            'allow_extra' => 'Permitir añadir más (a precio normal)',
            'allow_extra_hint' => 'Si está activo, el cliente puede añadir unidades por encima de las incluidas.',
            'max_qty' => 'Máximo por reserva',
            'max_qty_hint' => 'Tope de unidades de este complemento por reserva (solo cantidad fija). Déjalo vacío = sin límite.',
            'choice_group' => 'Grupo de elección',
            'choice_group_hint' => 'Misma clave en varios complementos del producto = el cliente elige solo uno (p. ej. menu: Menú 1 ⊻ Menú 2).',
            'requires_addon' => 'Requiere otro complemento',
            'requires_addon_hint' => 'El cliente solo puede añadir este complemento si el indicado ya está elegido (p. ej. «Segunda tarta» requiere «Tarta»). Solo cantidad fija.',
            'requires_addon_none' => 'Sin dependencia',
            'requires_cleared' => 'Se quitó la dependencia «requiere» de :count complemento(s) que dependían del que has desenganchado.',
            'badge_included' => 'Incluido',
            'badge_mandatory' => 'Obligatorio',
            'badge_per_guest' => 'Por invitado',
            'badge_group' => 'Grupo: :group',
            'badge_requires' => 'Requiere: :name',
        ],
    ],

    'rate_types' => [
        'nav_label' => 'Tarifas',
        'model_label_singular' => 'tarifa',
        'model_label_plural' => 'Tarifas',

        'col_label' => 'Tarifa',
        'col_weekdays' => 'Días',
        'col_priority' => 'Prioridad',
        'col_special' => 'Especial',
        'col_prices_count' => 'Precios',
        'col_active' => 'Activa',
        'prices_count_hint' => 'Nº de precios de productos definidos para esta tarifa. Si tiene precios, no se puede borrar (desactívala).',

        'active_yes' => 'Activa',
        'active_no' => 'Inactiva',

        'weekdays' => [
            0 => 'Domingo',
            1 => 'Lunes',
            2 => 'Martes',
            3 => 'Miércoles',
            4 => 'Jueves',
            5 => 'Viernes',
            6 => 'Sábado',
        ],
        'weekdays_short' => [
            0 => 'Dom',
            1 => 'Lun',
            2 => 'Mar',
            3 => 'Mié',
            4 => 'Jue',
            5 => 'Vie',
            6 => 'Sáb',
        ],

        'lang' => [
            'es' => 'Español',
            'en' => 'Inglés',
            'fr' => 'Francés',
        ],

        'section_identity' => 'Identificación',
        'section_identity_hint' => 'El identificador técnico y la etiqueta que verás en el catálogo y los precios.',
        'field_key' => 'Identificador',
        'key_create_hint' => 'Identificador técnico: minúsculas, números y guion bajo (p. ej. "finde", "festivo"). No se podrá cambiar después.',
        'key_locked_hint' => 'El identificador no se puede cambiar (lo usan la web y el cálculo de precios).',
        'field_label' => 'Etiqueta',

        'section_applicability' => 'Cuándo aplica',
        'section_applicability_hint' => 'Días de la semana que activan esta tarifa. Si un día coincide con varias tarifas, gana la de mayor prioridad. Para festivos y vísperas concretos se usan las fechas especiales (en Aforo y calendario).',
        'field_weekdays' => 'Días de la semana',
        'weekdays_hint' => 'Marca los días en que se aplica esta tarifa. Déjalo vacío para la tarifa base (la que se usa cuando ningún otro día ni fecha especial aplica).',
        'field_priority' => 'Prioridad',
        'priority_hint' => 'Si un día coincide con varias tarifas activas, gana la de mayor prioridad. La tarifa base normalmente lleva 0.',

        'section_status' => 'Estado',
        'field_is_special' => 'Tarifa especial',
        'is_special_hint' => 'Marca informativa (festivo/finde/víspera). No cambia el cálculo; solo ayuda a identificarla.',
        'field_is_active' => 'Activa',
        'is_active_hint' => 'Si se desactiva, deja de aplicarse por día de la semana (los productos no se venderán con ella). Es la forma segura de retirar una tarifa en uso sin borrarla.',

        'create_title' => 'Crear tarifa',
        'edit_title' => 'Editar tarifa: :name',
        'warn_deactivated_fallback' => 'Has desactivado la tarifa base. La web sigue funcionando, pero revisa que sea lo que querías: es la tarifa que se aplica cuando ningún otro día ni fecha especial encaja.',

        'actions' => [
            'create' => 'Crear tarifa',
            'delete' => [
                'label' => 'Borrar tarifa',
                'modal_heading' => 'Borrar esta tarifa',
                'modal_description' => 'Solo se puede borrar una tarifa que no se use: que no sea la tarifa base, que no tenga precios de productos y que no la referencie ninguna fecha especial. Esta acción es irreversible. Para retirar una tarifa en uso, desactívala en lugar de borrarla.',
                'submit' => 'Borrar definitivamente',
                'success' => 'Tarifa borrada.',
                'blocked' => [
                    'fallback_normal' => 'No se puede borrar la tarifa base: el cálculo de precios la necesita. Desactívala si no quieres usarla.',
                    'has_prices' => 'No se puede borrar: hay productos con precio en esta tarifa (se perderían). Quítales el precio o desactiva la tarifa.',
                    'referenced_by_special_dates' => 'No se puede borrar: hay fechas especiales que la usan. Cámbialas primero o desactiva la tarifa.',
                ],
            ],
        ],
    ],

    'special_dates' => [
        'nav_label' => 'Fechas especiales',
        'model_label_singular' => 'fecha especial',
        'model_label_plural' => 'Fechas especiales',

        'col_date' => 'Fecha',
        'col_note' => 'Nota',
        'col_state' => 'Estado',
        'col_window' => 'Horario',
        'col_rate' => 'Tarifa',

        'closed' => 'Cerrado',
        'open' => 'Abierto',
        'window_weekly' => 'Horario semanal',
        'window_weekly_short' => 'semanal',
        'rate_by_weekday' => 'Por día de la semana',
        'filter_all' => 'Todas',
        'filter_upcoming' => 'Solo próximas (desde hoy)',

        'lang' => [
            'es' => 'Español',
            'en' => 'Inglés',
            'fr' => 'Francés',
        ],

        'section_day' => 'Día',
        'field_date' => 'Fecha',
        'date_hint' => 'El día concreto que se sale de la norma semanal (festivo, cierre, horario o tarifa especial).',
        'field_note' => 'Nota interna',
        'note_hint' => 'Para identificar la fecha (p. ej. "Navidad", "Cerrado por mantenimiento"). No se muestra al cliente.',

        'section_state' => 'Estado del día',
        'field_is_closed' => 'Cerrado ese día',
        'is_closed_hint' => 'Si se marca, el parque NO vende ni genera franjas ese día (y bloquea reservas en franjas ya generadas).',
        'field_open_time' => 'Apertura',
        'open_time_hint' => 'Hora de apertura especial. Vacío = se usa el horario semanal.',
        'field_close_time' => 'Cierre',
        'close_time_hint' => 'Hora de cierre especial. Vacío = se usa el horario semanal. Debe ser posterior a la apertura.',
        'field_rate_type' => 'Tarifa del día',
        'rate_type_hint' => 'Qué tarifa de precios aplica ese día (p. ej. un festivo que cobra como finde).',
        'rate_type_placeholder' => 'Por día de la semana (sin tarifa especial)',
        'rate_inactive_suffix' => '(inactiva)',
        'close_before_open' => 'La hora de cierre debe ser posterior a la de apertura.',

        'create_title' => 'Añadir fecha especial',
        'edit_title' => 'Editar fecha: :date',

        'actions' => [
            'create' => 'Añadir fecha',
            'delete' => [
                'label' => 'Borrar fecha',
                'modal_heading' => 'Borrar esta fecha especial',
                'modal_description' => 'El día volverá a regirse por el horario y la tarifa normales de su día de la semana. Esta acción es irreversible. (No afecta a reservas ya pagadas para ese día.)',
                'submit' => 'Borrar',
                'success' => 'Fecha especial borrada.',
            ],
        ],
    ],

    // Mantenimiento — subsistema de disponibilidad (#218, fuera de roadmap).
    'maintenance' => [
        'nav_label' => 'Mantenimiento',
        'title' => 'Mantenimiento',
        'save' => 'Guardar cambios',
        'saved' => 'Mantenimiento actualizado.',
        'section_site' => 'Mantenimiento de toda la web',
        'section_site_hint' => 'Si lo activas, los visitantes ven una página de «Volvemos enseguida» (503). El panel SIEMPRE sigue accesible, y tú (personal) puedes seguir viendo la web con un aviso, para revisar antes y después.',
        'site_enabled' => 'Activar mantenimiento de toda la web',
        'site_enabled_hint' => 'Cierra la web pública para los visitantes (úsalo para actualizaciones puntuales). Los pagos en curso de Redsys NO se interrumpen y el panel NUNCA se bloquea.',
        'message' => 'Mensaje para el visitante (opcional)',
        'message_hint' => 'Si lo dejas vacío, se muestra el texto por defecto. Puedes indicar el motivo o hasta cuándo (p. ej. «Volvemos el 20 de junio»).',
        'section_reservations' => 'Reservas online',
        'section_reservations_hint' => 'Pausa SOLO el sistema de reservas online; el resto de la web sigue navegable. Útil cuando quieras gestionar las reservas por teléfono temporalmente.',
        'reservations_paused' => 'Pausar las reservas online',
        'reservations_paused_hint' => 'Al activarlo, los botones de «Reservar» de toda la web pasan a invitar a LLAMAR por teléfono (el número de Ajustes → Contacto) y se muestra un aviso. El pedido manual del panel y los pagos en curso de Redsys NO se ven afectados.',
        'reservations_title' => 'Título del aviso (opcional)',
        'reservations_title_hint' => 'Encabezado que ve el cliente en el panel de compra cuando las reservas están en pausa. Si lo dejas vacío, se muestra el texto por defecto.',
        'reservations_message' => 'Mensaje del aviso (opcional)',
        'reservations_message_hint' => 'Texto que ve el cliente en el panel de compra cuando las reservas están en pausa. Si lo dejas vacío, se muestra el texto por defecto.',
        'section_pages' => 'Páginas concretas',
        'section_pages_hint' => 'Pon en mantenimiento UNA página concreta: muestra «sección no disponible» con el nav y el pie, para que el visitante pueda navegar al resto de la web. Útil para retocar una página sin cerrar todo el sitio.',
        'page_home' => 'Inicio',
        'page_precios' => 'Precios',
        'page_cumpleanos' => 'Cumpleaños',
        'page_servicios' => 'Servicios',
        'page_normas' => 'Normas',
        'page_contacto' => 'Contacto',
        // `#536`: la página del bar. ⚠️ `atracciones` sigue SIN estar en `PAGE_KEYS` desde `#481`:
        // es un hueco preexistente, no de esta tanda — ficha en `DEUDA.md`.
        'page_bar' => 'El bar',
    ],

    'settings' => [
        'nav_label' => 'Configuración',
        'title' => 'Configuración',
        'save' => 'Guardar cambios',
        'saved' => 'Configuración guardada.',

        // Pestañas de nivel superior (Fase 3 · Plan B · L2): lo cotidiano primero, lo técnico al final.
        'tab_business' => 'Tu negocio',
        'tab_web' => 'Textos y aspecto web',
        'tab_fiscal' => 'Datos fiscales',
        'tab_advanced' => 'Avanzado',

        'section_identity' => 'Identidad',
        'section_identity_hint' => 'Nombre, ciudad y dominio que se muestran en la web.',
        'section_fiscal' => 'Datos fiscales',
        'section_fiscal_hint' => 'Identidad fiscal para la facturación y los textos legales (puede diferir del nombre comercial y de la dirección del parque).',
        'business_name' => 'Nombre comercial',
        'business_city' => 'Ciudad',
        'business_legal_name' => 'Razón social',
        'business_legal_name_hint' => 'Nombre fiscal de la empresa (para facturas). Distinto del nombre comercial.',
        'business_nif' => 'NIF / CIF',
        'business_domain' => 'Dominio del sitio',
        'business_domain_hint' => 'Dominio que aparece en los textos legales (p. ej. miparque.es). Si lo dejas vacío, se usa automáticamente el dominio desde el que se sirve la web.',
        'business_address' => 'Domicilio fiscal',
        'business_address_hint' => 'Dirección fiscal para la facturación (puede diferir de la del parque).',

        'section_contact_data' => 'Contacto',
        'section_contact_data_hint' => 'Email, teléfono y WhatsApp que se muestran en la web pública.',
        'section_address' => 'Dirección y mapa',
        'section_address_hint' => 'Dirección del parque y el mapa embebido de Google Maps.',
        'section_social' => 'Redes sociales',
        'section_social_hint' => 'Enlaces de redes y feed social «en directo».',
        'contact_email' => 'Email de contacto',
        'mail_from_address' => 'Correo remitente',
        'mail_from_address_hint' => 'Desde qué dirección salen los correos automáticos (confirmaciones, avisos…). No es el mismo que el de contacto: ése es al que te escriben. Si lo dejas vacío se usa el del servidor. ⚠️ Tiene que ser una dirección de tu dominio y estar autorizada (SPF/DKIM), o los correos acabarán en spam.',
        'contact_phone' => 'Teléfono',
        'address_line1' => 'Dirección (línea 1)',
        'address_line2' => 'Dirección (línea 2)',
        'address_maps_url' => 'Enlace de Google Maps',
        'address_maps_url_hint' => 'Enlace para abrir la ubicación en Google Maps (botón «Cómo llegar»). Es el de Compartir → «Enviar un enlace».',
        'address_maps_embed_url' => 'Mapa embebido (URL de inserción)',
        'address_maps_embed_url_hint' => 'Para mostrar el mapa dentro de la web. En Google Maps: Compartir → «Insertar un mapa» → copia el código del <iframe> (o solo su src, que empieza por https://www.google.com/maps/embed). Vacío = se muestra un marcador decorativo.',
        'maps_embed_not_recognized' => 'No se reconoció una URL de inserción de Google Maps válida en el campo «Mapa embebido»: el mapa NO se ha cambiado. Pega el código <iframe> de «Insertar un mapa» de Google Maps.',
        'contact_instagram' => 'Instagram (URL)',
        'contact_tiktok' => 'TikTok (URL)',
        'contact_whatsapp' => 'WhatsApp (número)',
        'contact_whatsapp_hint' => 'Número con prefijo internacional (p. ej. 34600112233). Se usa para el botón de WhatsApp en la página de contacto. Vacío = no se muestra.',
        'social_feed' => 'Feed social «en directo» (URL de inserción)',
        'social_feed_hint' => 'Muestra tus últimas publicaciones de Instagram/TikTok en esa sección de la web. Crea un widget GRATIS en SnapWidget o LightWidget conectando tu cuenta, copia su código <iframe> (o solo su src) y pégalo aquí. Vacío = se muestra la galería por defecto. Aviso RGPD: el feed solo se carga con consentimiento de cookies y transfiere datos al proveedor (posible EE. UU.) — confirma con tu asesoría la garantía de transferencia (Data Privacy Framework o cláusulas tipo).',
        'social_feed_not_recognized' => 'No se reconoció una URL de inserción válida en el campo «Feed social»: el feed NO se ha cambiado. Pega el <iframe> de un widget de SnapWidget o LightWidget.',

        'section_web_appearance' => 'Aspecto y opciones de la web',
        'section_web_appearance_hint' => 'Color de marca, imagen para compartir en redes, buscador del catálogo y banner de cookies.',
        'seo_og_image' => 'Imagen para compartir (URL)',
        'seo_og_image_hint' => 'Imagen que se muestra al compartir el sitio en redes (Open Graph). Vacío = sin imagen.',

        'section_landing_texts' => 'Textos de la landing',
        'section_landing_texts_hint' => 'Título de la pestaña/Google, eslogan del pie y coletilla del copyright. Editables por idioma; si dejas un idioma vacío, se usa el texto por defecto.',
        'lang_es' => 'Español',
        'lang_en' => 'Inglés',
        'lang_fr' => 'Francés',
        'seo_title' => 'Título web (pestaña y Google)',
        'seo_title_hint' => 'El título que se ve en la pestaña del navegador y en los resultados de Google para la portada. Ej.: «MI PARQUE - Parque de saltos». Vacío = título por defecto.',
        'landing_tagline' => 'Lema de la portada',
        'landing_tagline_hint' => 'Título de la pestaña de la portada cuando no hay «Título web». Ej.: «Parque de saltos para toda la familia · Murcia».',
        'landing_footer_rights' => 'Coletilla del copyright',
        'landing_footer_rights_hint' => 'Texto tras «© AÑO NOMBRE —» en el pie del formulario de invitados. Ej.: «Hecho para reír.».',
        'landing_zones_access' => 'Nota de acceso a las zonas',
        'landing_zones_access_hint' => 'Sale en una tarjeta bajo las zonas de la portada y al pie de /atracciones: lo que las tarjetas no dicen (con quién entran los pequeños, entre qué alturas). Vacía, no se pinta.',

        // ── /bar · los textos (`DECISIONES #536`) ────────────────────────────────────────────
        // ⚠️ Las IMÁGENES no están aquí: se suben en «Ajustes → El bar». Esta pantalla no sube
        // ficheros y la carta es una colección ordenable.
        'section_bar' => 'El bar',
        'section_bar_hint' => 'Lo que se publica en la página del bar. Las imágenes de la carta y la foto del local se suben en «Ajustes → El bar». ⚠️ Sin nombre, la página del bar no se publica.',
        'bar_name' => 'Nombre del bar',
        'bar_name_hint' => 'El titular de la página. Si lo dejas vacío en todos los idiomas, la página no se publica y el bar no sale ni en el menú ni en el pie.',
        'bar_lede' => 'Frase de presentación',
        'bar_lede_hint' => 'Una línea bajo el titular. Ej.: «Mesas con el parque a la vista y carta corta. Puedes comer sin saltar.».',
        'bar_photo_caption' => 'Pie de la foto del local',
        'bar_photo_caption_hint' => 'Qué contar de la foto. Ej.: «Desde la mesa se les ve saltar, así que puedes sentarte sin perderlos de vista.». Vacío: la foto sale sin pie.',
        'bar_free_entry' => '¿Se puede entrar solo al bar?',
        'bar_free_entry_hint' => 'Lo pregunta quien vive al lado y no viene a saltar. Sin decidir no se publica nada: es mejor callar que afirmar algo que no has decidido.',
        'bar_free_entry_unset' => 'Sin decidir — no se publica',
        'bar_free_entry_yes' => 'Sí, se puede entrar solo al bar',
        'bar_free_entry_no' => 'No, hace falta entrada al parque',

        'section_registration' => 'Registro (sistema externo)',
        'section_registration_hint' => 'El botón «Registro» del header de la web lleva a vuestro sistema externo de registro/descargo. Etiqueta y subtítulo editables por idioma; si la URL queda vacía, el botón abre el registro interno de reservas.',
        'registration_url' => 'URL del registro externo',
        'registration_url_hint' => 'Dirección completa (https://…) del sistema de registro. Se abre en una pestaña nueva. Vacío = se usa el registro interno.',
        'registration_label' => 'Etiqueta del botón',
        'registration_label_hint' => 'Texto principal del botón. Ej.: «Registro».',
        'registration_subtitle' => 'Subtítulo del botón',
        'registration_description' => 'Texto informativo (confirmación)',
        'registration_description_hint' => 'Párrafo que se muestra en la pantalla de «Reserva confirmada», junto al botón de registro. Si lo dejas vacío, se usa un texto por defecto.',

        'section_mixed_party' => 'Fiestas por edad (cumpleaños mixtos)',
        'section_mixed_party_hint' => 'Lo que lee el cliente en el formulario de invitados cuando declara una edad para la que no hay producto en las condiciones de su reserva. Tres casos, editables por idioma; vacío = texto por defecto. Escribe :phone donde quieras que salga el teléfono de contacto.',
        'mixed_party_below' => 'Edad por debajo del tramo más bajo',
        'mixed_party_above' => 'Edad por encima del tramo más alto',
        'mixed_party_gap' => 'Edad en un hueco entre dos tramos',
        'mixed_party_text_hint' => 'Ej.: «Para esa edad no tenemos cumpleaños. Llámanos al :phone y lo vemos contigo.»',

        'theme_brand' => 'Color de marca',
        'theme_brand_hint' => 'Color principal de la marca (formato #RRGGBB). No afecta a los colores de zona, que se configuran por zona.',
        'theme_brand_secondary' => 'Color de marca secundario',
        'theme_brand_secondary_hint' => 'Acento que acompaña al principal en detalles y decoraciones (formato #RRGGBB). Si lo dejas vacío se usa el del diseño por defecto.',
        'theme_action' => 'Color del botón de reservar',
        'theme_action_hint' => 'Relleno de los botones que hacen avanzar la compra: reservar, comprar y enviar (formato #RRGGBB). No es el color de marca: la marca tiñe acentos y decoración, y éste solo los botones. Si lo dejas VACÍO el botón se adapta al fondo de cada sección, que es como se ve hoy; si pones un color, será el mismo sobre fondo claro y sobre fondo oscuro. El tono al pasar el cursor se calcula solo.',

        'cookies_banner_enabled' => 'Mostrar el banner de cookies',
        'cookies_banner_enabled_hint' => 'Si lo desactivas, NO se oculta el bloqueo previo: el mapa y el feed social siguen sin cargarse hasta que el visitante consienta; solo se oculta el aviso. Déjalo activado salvo que gestiones el consentimiento por otra vía.',

        'catalog_search_min_items' => 'Mostrar el buscador a partir de N productos',
        'catalog_search_min_items_hint' => 'El buscador del catálogo aparece solo cuando el nº total de productos vendibles SUPERA este número. Déjalo vacío para el valor por defecto (12). 0 = el buscador se muestra siempre.',
        'low_availability_max' => 'Avisar de «Casi llena» cuando queden N plazas',
        'low_availability_max_hint' => 'En el paso de hora, una hora se marca «Casi llena» cuando le quedan ESTE número de plazas o menos. Déjalo vacío para el valor por defecto (8). 0 = no avisar nunca. No cambia el aforo: solo lo que ve el cliente.',

        'section_sales' => 'Ventas',
        'section_sales_hint' => 'Ajustes técnicos de la venta. Cámbialos con cuidado: un valor fuera de rango se rechaza al guardar.',
        'section_door' => 'Puerta',
        'section_door_hint' => 'Comportamiento de la validación en la puerta. Cámbialo con cuidado.',
        'section_capacity' => 'Aforo de cumpleaños',
        'section_capacity_hint' => 'Topes de aforo de cumpleaños por franja. Cámbialos con cuidado.',
        'hold_minutes' => 'Retención de plaza durante el pago (min)',
        'hold_minutes_hint' => 'Minutos que se reserva la plaza mientras el cliente paga. Debe ser ≥ el timeout del TPV.',
        'order_prefix' => 'Prefijo de los códigos de pedido',
        'order_prefix_hint' => 'Letras/números/guiones, máx. 8 (p. ej. «R-»). Solo aplica a pedidos NUEVOS; los emitidos no cambian.',
        'purchase_horizon_months' => 'Horizonte de compra (meses)',
        'purchase_horizon_months_hint' => 'Hasta cuántos meses por delante se puede reservar.',
        'incidents_alert_email' => 'Email para avisos de incidencias de cobro',
        'incidents_alert_email_hint' => 'A dónde avisar de un cobro duplicado, huérfano o tras caducar. Si lo dejas vacío, se usa el email de contacto.',
        'puerta_rate_limit' => 'Límite de validaciones de puerta (por minuto)',
        'puerta_rate_limit_hint' => 'Búsquedas de validación permitidas por minuto y empleado (freno anti-abuso).',
        // Fase 6 · subsistema A (`specs/identidad-qr-puerta.md` §4.6, §4.8): la ficha de puerta.
        'puerta_lookup_rate_limit' => 'Límite de búsquedas TECLEADAS que abren la ficha (por hora)',
        'puerta_lookup_rate_limit_hint' => 'Por empleado y hora. El escaneo del carné no cuenta aquí. Un empleado que teclea decenas de correos en una hora no está atendiendo: el rechazo avisa al operador.',
        'puerta_profile_ttl' => 'Caducidad de la ficha abierta (minutos)',
        'puerta_profile_ttl_hint' => 'La ficha se cierra sola en el SERVIDOR pasado este tiempo, aunque la pestaña siga abierta. Cualquier interacción reinicia el reloj.',
        'puerta_window_days' => 'Ventana de reservas (± días)',
        'puerta_window_days_hint' => 'Además de las de hoy, la ficha enseña en segundo plano las reservas de estos días alrededor (el que llega un día antes o después). 0 = solo hoy.',
        'puerta_waiver_check' => 'Comprobar el descargo en la puerta',
        'puerta_waiver_check_hint' => 'Activado: la puerta muestra si el cliente firmó el descargo (3 estados). Desactivado: solo muestra si está registrado (2 estados), útil si el descargo lo gestiona vuestro sistema externo.',
        'tax_rate' => 'IVA por defecto (%)',
        'tax_rate_hint' => 'Porcentaje de IVA por defecto (informativo).',
        'packs_max_per_slot' => 'Cumpleaños por franja (máx.)',
        'packs_cap_hint' => '0 = sin tope.',
        'packs_max_guests_per_slot' => 'Invitados totales por franja (máx.)',
        'packs_guest_count_cutoff_hours' => 'Plazo para cambiar invitados (horas)',
        'packs_guest_count_cutoff_hours_hint' => 'Cuántas horas antes del inicio de la fiesta deja de poder cambiarse el número de invitados desde el formulario del cliente. Vacío = 24 h.',
        'packs_prep_blocks_cupo' => 'El montaje/limpieza bloquea el cupo',
        'packs_prep_blocks_cupo_hint' => 'Si está activo, la ventana de montaje y limpieza ocupa cupo en las franjas vecinas (no solo la de inicio).',

        'section_redsys' => 'Pagos (Redsys)',
        'section_redsys_hint' => 'Configuración del TPV. La CLAVE SECRETA no se gestiona aquí (vive en el servidor por seguridad). Cambia estos valores solo si sabes lo que haces: un error puede impedir los cobros.',
        'redsys_live_requires_credentials' => 'No se puede cambiar a Real (producción) sin el Código de comercio y el Terminal del banco. Configúralos primero. No se ha guardado ningún cambio.',
        'redsys_live_requires_secret' => 'No se puede cambiar a Real (producción): la CLAVE SECRETA del banco no está configurada en el servidor (sigue siendo la de pruebas o no es válida). Debe instalarse en el servidor antes de activar producción. No se ha guardado ningún cambio.',
        'redsys_environment' => 'Entorno',
        'redsys_environment_hint' => '«Pruebas» (sandbox) o «Real» (producción). Cambiar a Real exige tener las credenciales reales configuradas.',
        'redsys_env_test' => 'Pruebas (sandbox)',
        'redsys_env_live' => 'Real (producción)',
        'redsys_currency' => 'Divisa (ISO-4217 numérico)',
        'redsys_currency_hint' => '978 = euros. Tres dígitos.',
        'redsys_merchant_code' => 'Código de comercio (FUC)',
        'redsys_merchant_code_hint' => 'Lo facilita el banco.',
        'redsys_terminal' => 'Terminal',
        'redsys_merchant_name' => 'Nombre del comercio',
        'redsys_merchant_url' => 'URL de notificación (MerchantURL)',
        'redsys_merchant_url_hint' => 'Endpoint que recibe la confirmación on-line del banco. Vacío = se usa la URL automática del sitio.',
    ],

    'weekly_schedule' => [
        'nav_label' => 'Horario',
        'title' => 'Horario semanal',
        'intro' => 'Horario general del parque por día de la semana. Es la base que usan las reservas y la web. Los días sueltos (festivos, cierres) se gestionan en «Fechas especiales»; los tramos largos (verano, etc.) en «Temporadas».',
        'save' => 'Guardar horario',
        'saved' => 'Horario guardado.',
        'closed' => 'Cerrado',
        'open_time' => 'Apertura',
        'close_time' => 'Cierre',
        'close_before_open' => 'En :day, la hora de cierre debe ser posterior a la de apertura.',
    ],

    'seasons' => [
        'nav_label' => 'Temporadas',
        'model_label_singular' => 'temporada',
        'model_label_plural' => 'Temporadas',

        'col_name' => 'Nombre',
        'col_range' => 'Fechas',
        'col_hours' => 'Horario',
        'col_active' => 'Activa',
        'active_yes' => 'Activa',
        'active_no' => 'Inactiva',

        'section_period' => 'Temporada',
        'section_period_hint' => 'Un tramo de fechas con un horario propio que sustituye al semanal mientras está vigente (p. ej. verano). Abre todos los días del rango con ese horario.',
        'field_name' => 'Nombre',
        'field_name_hint' => 'Para identificarla. Se muestra en la web (p. ej. "Horario de verano").',
        'field_start_date' => 'Desde',
        'field_end_date' => 'Hasta',

        'section_hours' => 'Horario de la temporada',
        'section_hours_hint' => 'Apertura y cierre que se aplican todos los días del rango.',
        'field_open_time' => 'Apertura',
        'field_close_time' => 'Cierre',
        'field_is_active' => 'Activa',
        'field_is_active_hint' => 'Si se desactiva, no se aplica (ni en reservas ni en la web), pero se conserva.',

        'end_before_start' => 'La fecha de fin no puede ser anterior a la de inicio.',
        'close_before_open' => 'La hora de cierre debe ser posterior a la de apertura.',

        'create_title' => 'Crear temporada',
        'edit_title' => 'Editar temporada: :name',

        'actions' => [
            'create' => 'Crear temporada',
            'delete' => [
                'label' => 'Borrar temporada',
                'modal_heading' => 'Borrar esta temporada',
                'modal_description' => 'Su rango de fechas volverá a regirse por el horario semanal. No afecta a reservas ya hechas. Esta acción es irreversible.',
                'submit' => 'Borrar',
                'success' => 'Temporada borrada.',
            ],
        ],
    ],

    'slots' => [
        'nav_label' => 'Franjas',
        'model_label_singular' => 'franja',
        'model_label_plural' => 'Franjas',

        'col_zone' => 'Zona',
        'col_date' => 'Fecha',
        'col_time' => 'Horario',
        'col_online_capacity' => 'Aforo online',
        'col_sale' => 'Venta online',
        'col_overridden' => 'Aforo',
        'by_cupo' => 'Por cupo',
        'sale_open' => 'Abierta',
        'sale_closed' => 'Cerrada',
        'overridden_yes' => 'Ajustado a mano',
        'filter_upcoming' => 'Solo próximas',

        'section_context' => 'Franja',
        'occupancy' => 'Ocupación actual',
        'occupancy_seats' => ':n de :cap plazas online ocupadas',
        'occupancy_guests' => ':n invitados reservados',

        'section_sale' => 'Venta y aforo',
        'section_sale_hint' => 'Abre o cierra la venta online de esta franja y ajusta su aforo online de forma puntual. El cierre de un día entero se gestiona en «Fechas especiales» u «Horario».',
        'field_sale' => 'Venta online abierta',
        'field_sale_hint' => 'Si se cierra, esta franja deja de venderse por la web (las reservas ya hechas no se tocan).',
        'field_online_capacity' => 'Aforo online',
        'field_online_capacity_hint' => 'Plazas que se pueden vender por la web en esta franja. Al cambiarlo, la franja queda «ajustada a mano» y «Regenerar franjas» no lo reescribe.',
        'pack_aforo_note' => 'El aforo de cumpleaños se gestiona por cupo (en Configuración), no por franja.',

        'edit_title' => 'Franja: :zone · :date :time',

        'errors' => [
            'negative' => 'El aforo no puede ser negativo.',
            'above_total' => 'El aforo online no puede superar el aforo total de la franja (:total).',
            'below_occupancy' => 'El aforo no puede ser menor que la ocupación actual (:occupancy).',
        ],

        'actions' => [
            'reset' => [
                'label' => 'Restablecer aforo a la plantilla',
                'modal_heading' => 'Restablecer el aforo de esta franja',
                'modal_description' => 'El aforo volverá al de la plantilla semanal y dejará de estar «ajustado a mano» (futuras regeneraciones podrán cambiarlo). No afecta a reservas ya hechas.',
                'submit' => 'Restablecer',
                'success' => 'Aforo restablecido a la plantilla.',
                'blocked_below_occupancy' => 'No se puede restablecer: el aforo de la plantilla (:template) es menor que la ocupación actual (:occupancy).',
            ],
        ],

        'regenerate' => [
            'btn' => 'Regenerar franjas',
            'modal_heading' => 'Regenerar franjas',
            'modal_description' => 'Crea y actualiza las franjas del rango según el horario vigente y las plantillas. Limpia las franjas obsoletas (fuera de horario) sin reservas; las que tengan reservas se cierran, no se borran. El aforo ajustado a mano se conserva. No afecta a fechas pasadas.',
            'submit' => 'Regenerar',
            'from_label' => 'Desde',
            'to_label' => 'Hasta',
            'invalid_range' => 'La fecha «hasta» debe ser igual o posterior a «desde».',
            'done' => 'Regeneración terminada: :generated creadas/actualizadas, :deleted obsoletas borradas, :closed cerradas (con reservas).',
        ],
    ],

    'slot_templates' => [
        'nav_label' => 'Plantillas de franja',
        'model_label_singular' => 'plantilla de franja',
        'model_label_plural' => 'Plantillas de franja',

        'col_zone' => 'Zona',
        'col_weekday' => 'Día',
        'col_time' => 'Horario',
        'col_duration' => 'Duración',
        'col_capacity' => 'Aforo total',
        'col_online_capacity' => 'Aforo online',
        'col_active' => 'Activa',
        'active_yes' => 'Activa',
        'active_no' => 'Inactiva',

        'section_when' => 'Cuándo',
        'section_when_hint' => 'Día de la semana y hora de la franja recurrente, por zona. De aquí se generan las franjas concretas. Cambiar una plantilla NO toca las franjas ya generadas: pulsa «Regenerar franjas» para aplicarlo.',
        'field_zone' => 'Zona',
        'field_weekday' => 'Día de la semana',
        'field_start_time' => 'Hora de inicio',
        'field_duration' => 'Duración (min)',
        'field_duration_hint' => 'Minutos de la franja (rejilla de aforo). Define también la hora de fin.',

        'section_capacity' => 'Aforo',
        'section_capacity_hint' => 'Aforo total de la franja y cuántas plazas se venden por la web (el resto es para la puerta). En la zona de cumpleaños el aforo va por cupo (Configuración), no por estas cifras.',
        'field_capacity' => 'Aforo total',
        'field_capacity_hint' => 'Plazas totales de la franja (web + puerta).',
        'field_online_capacity' => 'Aforo online',
        'field_online_capacity_hint' => 'Plazas vendibles por la web. No puede superar el aforo total.',
        'field_is_active' => 'Activa',
        'field_is_active_hint' => 'Si se desactiva, esta plantilla no genera franjas.',

        'errors' => [
            'online_above_total' => 'El aforo online no puede superar el aforo total.',
            'duplicate' => 'Ya existe una plantilla para esa zona, día y hora.',
        ],

        'generate' => [
            'btn' => 'Generar plantillas',
            'modal_heading' => 'Generar plantillas de franja',
            'modal_description' => 'Crea de una vez todas las plantillas de una zona: elige los días, el horario (inicio→cierre), la duración de cada franja y el aforo. Útil para arrancar una zona sin teclear plantilla a plantilla. No duplica las que ya existan.',
            'submit' => 'Generar',
            'weekdays' => 'Días de la semana',
            'start_time' => 'Primera franja (hora de inicio)',
            'end_time' => 'Cierre (la última franja termina a esta hora)',
            'end_time_hint' => 'No se crea ninguna franja que termine después de esta hora.',
            'interval' => 'Cada cuánto empieza una franja (min)',
            'interval_hint' => 'Déjalo vacío para franjas seguidas (= duración).',
            'replace' => 'Reemplazar las plantillas existentes de esos días',
            'replace_hint' => 'Borra primero las plantillas de la zona en los días elegidos y las recrea. Si lo dejas apagado, solo añade las que falten.',
            'regenerate_after' => 'Regenerar las franjas al terminar',
            'regenerate_after_hint' => 'Materializa las franjas en el calendario (de hoy al horizonte de venta) sin tener que pulsar «Regenerar franjas» aparte.',
            'no_weekdays' => 'Elige al menos un día de la semana.',
            'no_slots' => 'Con ese horario y esa duración no cabe ninguna franja. Revisa inicio, cierre y duración.',
            'done' => 'Listo: :created plantillas creadas, :skipped ya existían.',
        ],

        'create_title' => 'Crear plantilla de franja',
        'edit_title' => 'Editar plantilla de franja',

        'actions' => [
            'create' => 'Crear plantilla',
            'delete' => [
                'label' => 'Borrar plantilla',
                'modal_heading' => 'Borrar esta plantilla',
                'modal_description' => 'Dejará de generar franjas. Las franjas ya generadas no se tocan (límpialas con «Regenerar franjas» si procede). Esta acción es irreversible.',
                'submit' => 'Borrar',
                'success' => 'Plantilla borrada.',
            ],
        ],
    ],

    'zones' => [
        'nav_label' => 'Zonas',
        'model_label_singular' => 'zona',
        'model_label_plural' => 'Zonas',

        'col_name' => 'Nombre',
        'col_slug' => 'Identificador',
        'col_cupo' => 'Cupo (niños/franja)',
        'col_active' => 'Activa',
        'active_yes' => 'Activa',
        'active_no' => 'Inactiva',
        'cupo_global' => 'Global',

        'section_identity' => 'Identidad',
        'field_slug' => 'Identificador',
        'field_slug_hint' => 'Clave interna única (p. ej. "jump", "kids", "cumpleanos"). Solo letras, números y guiones.',
        'field_accent' => 'Color de marca (web)',
        'field_accent_hint' => 'Clase de color de la landing. "jump"/"kids" ya tienen estilo; otras requieren CSS (parte de 7.9).',
        'field_color' => 'Color',
        'field_color_hint' => 'Color de la zona en el panel (calendario, pulsera, hojas).',
        'field_position' => 'Orden',
        'field_area_sqm' => 'Superficie (m²)',
        'field_rides_count' => 'Nº de atracciones',
        'field_image' => 'Imagen (ruta)',
        'field_image_hint' => 'Ruta relativa a public/ (p. ej. images/attractions/park_jump.webp). La landing pinta la tarjeta de zona con esta foto; vacío → tarjeta sin foto. La subida de ficheros llegará con la galería.',
        'field_is_active' => 'Activa (opera)',
        'field_is_active_hint' => 'Si se desactiva, la zona deja de operar (no vende). Independiente de mostrarla en la web.',
        'field_show_in_landing' => 'Mostrar en la landing',
        'field_show_in_landing_hint' => 'Si aparece como tarjeta de zona en la web pública. Una zona puede operar sin salir en la landing (p. ej. cumpleaños).',

        'lang' => [
            'es' => 'Español',
            'en' => 'Inglés',
            'fr' => 'Francés',
        ],
        'field_name' => 'Nombre',
        'field_subtitle' => 'Subtítulo',
        'field_description' => 'Descripción',
        'field_age_label' => 'Etiqueta de edad',
        'field_age_range' => 'Rango de edad',

        /*
         * La REGLA DE ALTURA (`#478`). ⚠️ El texto de ayuda dice que vacío es una respuesta, porque
         * un campo numérico en blanco se lee como «se me ha olvidado» y aquí significa «esta zona no
         * restringe por altura» — que es el caso normal fuera de un parque de saltos.
         */
        'section_height' => 'Regla de altura',
        'section_height_hint' => 'La portada dice «manda la edad; si no cuadra, manda la altura» y dibuja este umbral. Déjalo vacío si esta zona no restringe por altura: entonces no se pinta nada.',
        'field_height_min_cm' => 'Altura mínima',
        'field_height_min_hint' => 'Hay que medir al menos esto para entrar. Ej.: 130.',
        'field_height_max_cm' => 'Altura máxima',
        'field_height_max_hint' => 'No se puede pasar de aquí. Ej.: 130 en la zona pequeña.',

        'section_cupo' => 'Aforo de packs (cupo) por zona',
        'section_cupo_hint' => 'Solo aplica si la zona aloja packs (cumpleaños). Vacío = usa el valor global de Configuración; un valor manda sobre el global. La ocupación se cuenta por zona.',
        'field_max_per_slot' => 'Fiestas por franja',
        'field_max_guests_per_slot' => 'Niños por franja',
        'field_cupo_hint' => 'Vacío = usa el valor global. 0 = sin tope.',
        'field_prep_blocks_cupo' => 'El montaje/limpieza bloquea cupo',
        'field_prep_blocks_cupo_hint' => 'Si el montaje y la limpieza ocupan también las franjas vecinas.',
        'section_schedule' => 'Horario propio de la zona',
        'section_schedule_hint' => 'Vacío = esta zona usa el horario del parque. Sirve para zonas que operan a otras horas, como las excursiones de colegio (vienen por la mañana, entre semana). ⚠️ La web pública sigue anunciando el horario del PARQUE, no el de la zona.',
        'field_opens_at' => 'Abre a las',
        'field_closes_at' => 'Cierra a las',
        'field_schedule_hint' => 'Vacío = hereda del parque.',
        'field_ignores_venue_closure' => 'Opera aunque el parque esté cerrado',
        'field_ignores_venue_closure_hint' => 'Permite vender esta zona los días que el parque descansa o está marcado como cerrado. ⚠️ Necesita que la zona tenga SU horario: sin horas propias, un día cerrado sigue cerrado. ⚠️ Para cerrar un día suelto, cierra sus franjas a mano en Franjas (el cierre manual se respeta al regenerar).',
        'cupo_use_global' => 'Usar valor global',
        'yes' => 'Sí',
        'no' => 'No',

        'create_title' => 'Crear zona',
        'edit_title' => 'Editar zona: :name',

        'actions' => [
            'create' => 'Crear zona',
            'delete' => [
                'label' => 'Borrar zona',
                'modal_heading' => 'Borrar esta zona',
                'modal_description' => 'Solo se puede borrar una zona sin productos ni franjas. Esta acción es irreversible.',
                'submit' => 'Borrar',
                'success' => 'Zona borrada.',
                'blocked' => 'No se puede borrar: la zona tiene productos o franjas. Desactívala en su lugar.',
            ],
        ],
    ],

    'attractions' => [
        'nav_label' => 'Atracciones',
        'model_label_singular' => 'atracción',
        'model_label_plural' => 'Atracciones',

        'col_zone' => 'Zona',
        'col_name' => 'Nombre',
        'col_badge' => 'Etiqueta',
        'col_complement' => 'Complemento',
        'col_special' => 'Especial',
        'special_yes' => 'Destacada',
        'special_no' => 'No',
        'col_active' => 'Activa',
        'active_yes' => 'Activa',
        'active_no' => 'Inactiva',

        'section_identity' => 'Identidad',
        'field_zone' => 'Zona',
        'field_zone_hint' => 'Solo las zonas mostradas en la landing exhiben sus atracciones.',
        'zone_hidden_suffix' => '(oculta en la landing)',
        'field_position' => 'Orden',
        'field_position_hint' => 'Orden de aparición dentro de su zona (menor primero).',
        'field_image' => 'Imagen (ruta)',
        'field_image_hint' => 'Ruta relativa a public/ (p. ej. images/attractions/attraction-01.jpg). La subida de ficheros llegará con la galería.',
        'field_is_active' => 'Activa',
        'field_is_active_hint' => 'Si se desactiva, la atracción no aparece en la landing.',
        'field_is_special' => 'Destacada',
        'field_is_special_hint' => 'Resalta esta atracción visualmente en la landing (independiente de si se vende).',
        'field_complement' => 'Complemento de pago',
        'field_complement_hint' => 'Complemento vendible vinculado (opcional). Si se elige, la landing muestra su precio y un botón «Comprar». Solo se listan complementos vendibles.',
        'complement_not_attached_warning' => 'Aviso: este complemento no se podrá comprar en la landing de esta zona (la atracción se mostrará solo informativa). Revisa en Catálogo que tenga precio y esté enganchado como complemento DE PAGO (no «incluido») a una entrada vendible de esta zona.',

        'lang' => [
            'es' => 'Español',
            'en' => 'Inglés',
            'fr' => 'Francés',
        ],
        'field_name' => 'Nombre',
        'field_description' => 'Descripción',
        'field_age' => 'Edad',
        'field_badge' => 'Etiqueta (sub-badge)',
        'field_badge_hint' => 'Etiqueta corta opcional para diferenciar atracciones similares (p. ej. "XL", "PRO"). Vacío = sin etiqueta.',

        'create_title' => 'Crear atracción',
        'edit_title' => 'Editar atracción: :name',

        'actions' => [
            'create' => 'Crear atracción',
            'delete' => [
                'label' => 'Borrar atracción',
                'modal_heading' => 'Borrar esta atracción',
                'modal_description' => 'Esta acción es irreversible.',
                'submit' => 'Borrar',
                'success' => 'Atracción borrada.',
            ],
        ],
    ],

    'landing_services' => [
        'nav_label' => 'Servicios (web)',
        'model_label_singular' => 'servicio',
        'model_label_plural' => 'Servicios de la web',

        'col_title' => 'Título',
        'col_slug' => 'Anchor',
        'col_pack' => 'Pack vinculado',
        'contact_only' => 'Solo contacto',
        'col_active' => 'En /servicios',
        'active_yes' => 'Visible',
        'active_no' => 'Oculto',

        'section_identity' => 'Identidad',
        'field_slug' => 'Anchor (slug)',
        'field_slug_hint' => 'Identificador estable de la sección: su dirección es /servicios#anchor. Se fija al crear y no se cambia (rompería enlaces).',
        'field_position' => 'Orden',
        'field_position_hint' => 'Orden de aparición en /servicios (menor primero).',
        'field_image' => 'Imagen (ruta)',
        'field_image_hint' => 'Ruta relativa a public/ (p. ej. images/attractions/park_jump.webp). La subida de ficheros llegará con la galería.',
        'field_pack' => 'Packs que se venden desde este servicio (opcional)',
        'field_pack_hint' => 'Cada pack vendible sale en la sección con su tabla de precios y su botón «Reservar». Sin packs, la sección muestra «Pedir información». Vincular un pack lo SACA de la sección Cumpleaños, y un pack solo puede estar en un servicio.',
        'pack_not_purchasable_warning' => 'Aviso: alguno de estos packs no se podrá comprar en la web y no saldrá su tabla. Revisa en Catálogo que esté en venta online, activo, con precio y en una zona operativa.',
        'pack_taken' => 'Alguno de estos packs ya se vende desde otro servicio: un pack solo puede estar en uno.',
        'field_is_active' => 'Visible en /servicios',
        'field_is_active_hint' => 'Si se desactiva, la sección no aparece en la página /servicios.',

        'lang' => [
            'es' => 'Español',
            'en' => 'Inglés',
            'fr' => 'Francés',
        ],
        'field_title' => 'Título',
        'field_accent_word' => 'Palabra de acento',
        'field_accent_word_hint' => 'Palabra grande en contorno sobre la imagen (p. ej. «Colegio»). No es una enumeración.',
        'field_zone_label' => 'Etiqueta de zona',
        'field_zone_label_hint' => 'Texto del badge/ficha (p. ej. «Kids + Jump» o «Parque completo»). No es una zona del catálogo.',
        'field_body' => 'Descripción',
        'field_specs' => 'Condiciones rápidas',
        'field_specs_hint' => 'Filas de la mini-ficha (etiqueta + valor): horario, grupo mínimo, etc.',
        'field_spec_label' => 'Etiqueta',
        'field_spec_value' => 'Valor',
        'add_spec' => 'Añadir condición',

        'create_title' => 'Crear servicio',
        'edit_title' => 'Editar servicio: :name',

        'actions' => [
            'create' => 'Crear servicio',
            'delete' => [
                'label' => 'Borrar servicio',
                'modal_heading' => 'Borrar este servicio',
                'modal_description' => 'Esta acción es irreversible. Si tenía un pack vinculado, volverá a la sección Cumpleaños.',
                'submit' => 'Borrar',
                'success' => 'Servicio borrado.',
            ],
        ],
    ],

    'faqs' => [
        'nav_label' => 'Preguntas frecuentes',
        'model_label_singular' => 'pregunta frecuente',
        'model_label_plural' => 'Preguntas frecuentes',

        'col_question' => 'Pregunta',
        'col_active' => 'Activa',
        'active_yes' => 'Activa',
        'active_no' => 'Inactiva',

        'section_classification' => 'Clasificación',
        'field_position' => 'Orden',
        'field_position_hint' => 'Orden de aparición (menor primero).',
        'field_is_active' => 'Activa',
        'field_is_active_hint' => 'Si se desactiva, la pregunta no aparece en la landing.',

        'lang' => [
            'es' => 'Español',
            'en' => 'Inglés',
            'fr' => 'Francés',
        ],
        'field_question' => 'Pregunta',
        'field_answer' => 'Respuesta',

        'create_title' => 'Crear pregunta frecuente',
        'edit_title' => 'Editar pregunta: :name',

        'actions' => [
            'create' => 'Crear pregunta',
            'delete' => [
                'label' => 'Borrar pregunta',
                'modal_heading' => 'Borrar esta pregunta',
                'modal_description' => 'Esta acción es irreversible.',
                'submit' => 'Borrar',
                'success' => 'Pregunta borrada.',
            ],
        ],
    ],

    // ❗❗❗ **Los textos de esta pantalla NO pueden decir «por si Google falla»**, y su spec lo
    // advierte expresamente (`specs/google-reviews.md` §4.4.bis): sería falso —sin consentimiento
    // de cookies no se puede servir NI UNA reseña de Google— y, sobre todo, **es la razón por la
    // que el parque las dejaría vacías**. Aquí se dice lo que son: lo que ve una parte de sus
    // visitantes cada día.
    'testimonials' => [
        'nav_label' => 'Opiniones propias',
        'model_label_singular' => 'opinión',
        'model_label_plural' => 'Opiniones propias',

        'col_author' => 'Quién',
        'col_text' => 'Opinión',
        'col_rating' => 'Nota',
        'col_active' => 'Activa',
        'active_yes' => 'Activa',
        'active_no' => 'Inactiva',

        'section_who' => 'Quién lo dice',
        'field_author' => 'Nombre',
        'field_author_hint' => 'Como quiera aparecer publicado. La inicial se pinta en el círculo.',
        'field_rating' => 'Nota (opcional)',
        'field_rating_hint' => 'De 1 a 5. Sin nota, la opinión sale sin estrellas.',
        'field_published_at' => 'Fecha (opcional)',
        'field_published_at_hint' => 'De aquí sale el «hace 2 meses». Sin fecha, no se escribe.',

        'section_classification' => 'Clasificación',
        'field_position' => 'Orden',
        'field_position_hint' => 'Orden de aparición (menor primero).',
        'field_is_active' => 'Activa',
        'field_is_active_hint' => 'Si se desactiva, la opinión no aparece. Sin ninguna activa, la sección entera desaparece de la portada.',

        'lang' => ['es' => 'Español', 'en' => 'Inglés', 'fr' => 'Francés'],
        'field_text' => 'Opinión',
        'field_text_hint' => 'Se publica ENTERA, sin recortar. Cortita se lee mejor: cabe en cuatro líneas.',

        'create_title' => 'Crear opinión',
        'edit_title' => 'Editar la opinión de :name',

        'actions' => [
            'create' => 'Crear opinión',
            'delete' => [
                'label' => 'Borrar opinión',
                'modal_heading' => 'Borrar esta opinión',
                'modal_description' => 'Esta acción es irreversible.',
                'submit' => 'Borrar',
                'success' => 'Opinión borrada.',
            ],
        ],
    ],

    'offers' => [
        'nav_label' => 'Ofertas',
        'model_label_singular' => 'oferta',
        'model_label_plural' => 'Ofertas',

        'col_image' => 'Imagen',
        'col_title' => 'Título',
        'col_active' => 'Activa',
        'active_yes' => 'Activa',
        'active_no' => 'Inactiva',

        'section_classification' => 'Clasificación',
        'field_position' => 'Orden',
        'field_position_hint' => 'Orden en el carrusel del widget (menor primero).',
        'field_is_active' => 'Activa',
        'field_is_active_hint' => 'Si se desactiva, la oferta no aparece. El widget «caja de regalo» solo se muestra si hay al menos una oferta activa.',
        'field_image' => 'Imagen',
        'field_image_hint' => 'Imagen de la oferta (webp, jpg o png; máx. 2 MB). Se muestra en el modal al abrir la caja.',

        'lang' => [
            'es' => 'Español',
            'en' => 'Inglés',
            'fr' => 'Francés',
        ],
        'field_title' => 'Título',

        'create_title' => 'Crear oferta',
        'edit_title' => 'Editar oferta: :name',

        'actions' => [
            'create' => 'Crear oferta',
            'delete' => [
                'label' => 'Borrar oferta',
                'modal_heading' => 'Borrar esta oferta',
                'modal_description' => 'Esta acción es irreversible. También se borrará su imagen.',
                'submit' => 'Borrar',
                'success' => 'Oferta borrada.',
            ],
        ],
    ],

    // ══ /bar · LAS IMÁGENES (`DECISIONES #536`) ═══════════════════════════════════════════════
    // `[DECIDIDO owner]`: la carta se publica como IMAGEN, no tecleando los platos. Este es el
    // sitio ÚNICO donde se suben las dos cosas que la página enseña.
    'bar_images' => [
        'nav_label' => 'El bar',
        'model_label_singular' => 'imagen del bar',
        'model_label_plural' => 'Imágenes del bar',

        'col_image' => 'Imagen',
        'col_kind' => 'Tipo',
        'col_alt' => 'Qué se ve',
        'col_active' => 'Activa',
        'active_yes' => 'Activa',
        'active_no' => 'Inactiva',

        'kind' => [
            'menu' => 'Carta',
            'venue' => 'Foto del local',
        ],

        'section_image' => 'La imagen',
        'section_image_hint' => 'La CARTA admite varias imágenes, una por cara: se publican en este orden. De la FOTO DEL LOCAL se publica la primera activa.',
        'field_kind' => 'Tipo',
        'field_kind_hint' => '«Carta» para las caras del menú; «Foto del local» para la foto de las mesas.',
        'field_position' => 'Orden',
        'field_position_hint' => 'Orden en que se publican (menor primero). También se puede arrastrar en el listado.',
        'field_is_active' => 'Activa',
        'field_is_active_hint' => 'Si se desactiva, deja de publicarse sin borrarla. Sin ninguna carta activa, la página no enseña carta.',
        'field_image' => 'Imagen',
        'field_image_hint' => 'webp, jpg o png; máx. 3 MB. La descarga el visitante desde el móvil: recorta el margen blanco del escáner antes de subirla.',

        'section_alt' => 'Qué se ve en la imagen',
        'section_alt_hint' => 'Obligatorio. Una carta en imagen no la puede leer una persona ciega ni un buscador: esta frase es lo único que van a encontrar.',
        'field_alt' => 'Qué se ve',
        'field_alt_hint' => 'Descríbelo en una frase. Por ejemplo: «Carta del bar: bocadillos, pizzas y bebidas, con sus precios».',

        'lang' => [
            'es' => 'Español',
            'en' => 'Inglés',
            'fr' => 'Francés',
        ],

        'create_title' => 'Añadir una imagen del bar',
        'edit_title' => 'Editar imagen: :name',

        'actions' => [
            'create' => 'Añadir imagen',
            'delete' => [
                'label' => 'Borrar imagen',
                'modal_heading' => 'Borrar esta imagen',
                'modal_description' => 'Esta acción es irreversible. También se borra el fichero subido.',
                'submit' => 'Borrar',
                'success' => 'Imagen borrada.',
            ],
        ],
    ],

    'park_rules' => [
        'nav_label' => 'Normas',
        'model_label_singular' => 'norma',
        'model_label_plural' => 'Normas',

        'col_name' => 'Norma',
        // El MOMENTO se ve en el listado (`#533`) porque es lo que agrupa la página: desde aquí se
        // nota de un vistazo cuál se ha quedado sin él, que son las que salen al final y sin grupo.
        'col_moment' => 'Momento',
        'col_active' => 'Activa',
        'active_yes' => 'Activa',
        'active_no' => 'Inactiva',

        'section_classification' => 'Clasificación',
        'field_position' => 'Orden',
        'field_position_hint' => 'Orden de aparición (menor primero).',
        'field_is_active' => 'Activa',
        'field_is_active_hint' => 'Si se desactiva, la norma no aparece en la landing ni en /normas.',

        /*
         * EL MOMENTO (`DECISIONES #533`): agrupa las normas en la página pública. Los tres rótulos
         * son los del artboard y describen CUÁNDO le toca al visitante, no de qué trata la norma.
         * ⚠️ «Sin agrupar» no es un momento: es el hueco vacío, y esas normas se publican al final.
         */
        'field_moment' => 'Momento',
        'field_moment_hint' => 'Cuándo le toca al visitante. Agrupa las normas en /normas; si lo dejas vacío, la norma sale al final y sin grupo.',
        'field_moment_none' => 'Sin agrupar',
        'moments' => [
            'before' => 'Antes de venir',
            'gate' => 'En la puerta',
            'inside' => 'Dentro',
        ],

        'lang' => [
            'es' => 'Español',
            'en' => 'Inglés',
            'fr' => 'Francés',
        ],
        'field_name' => 'Título',
        'field_description' => 'Descripción',
        'field_reason' => 'Por qué',
        'field_reason_hint' => 'El motivo de la norma, en una frase. Se publica debajo de ella. Opcional: si no lo pones, no se pinta nada.',

        'create_title' => 'Crear norma',
        'edit_title' => 'Editar norma: :name',

        'actions' => [
            'create' => 'Crear norma',
            'delete' => [
                'label' => 'Borrar norma',
                'modal_heading' => 'Borrar esta norma',
                'modal_description' => 'Esta acción es irreversible.',
                'submit' => 'Borrar',
                'success' => 'Norma borrada.',
            ],
        ],
    ],

    /**
     * PUBLICAR una versión de un documento legal (`#348`). Vive fuera de `waiver` porque la acción es
     * de DOS documentos: el descargo, que se FIRMA, y las condiciones, que se ACEPTAN al contratar.
     * ⚠️ Los textos no dicen «firmar» ni «aceptar»: dicen **publicar**, que es lo único que los dos
     * comparten. Nombrar aquí uno de los dos verbos volvería a atar la acción a un solo documento.
     */
    'legal' => [
        'publish' => [
            'label' => 'Publicar versión',
            'heading' => 'Publicar el texto guardado como versión :next',
            'description' => 'Se congela el texto tal y como está GUARDADO ahora (idiomas: :locales) como la versión :next, la que se le servirá a los clientes a partir de este momento. Una versión publicada NO se puede editar ni borrar: es la prueba de lo que cada persona aceptó. Si el texto todavía lleva un marcador [PENDIENTE], la publicación se rechaza.',
            'confirm' => 'Publicar versión :next',
            'done' => 'Versión :version publicada (:locales).',
            'refused_draft' => 'No se ha publicado: el texto sigue siendo un borrador',
            'nothing' => 'No hay texto que publicar: el cuerpo está vacío en todos los idiomas.',
            'draft_words' => '⚠️ El texto menciona «borrador» (:locales). Publicar es irreversible: si de verdad es un borrador, no lo publiques.',
        ],
    ],

    'pages' => [
        'nav_label' => 'Páginas legales',
        'model_label_singular' => 'página legal',
        'model_label_plural' => 'Páginas legales',

        'col_slug' => 'Identificador',
        'col_title' => 'Título',
        'col_active' => 'Estado',
        'active_yes' => 'Publicada',
        'active_no' => 'Oculta (404)',

        'section_settings' => 'Ajustes de la página',
        'tokens_hint' => 'En el texto puedes usar estos marcadores y se sustituyen al mostrar la página: :legal_name (razón social), :legal_nif (NIF), :legal_address (domicilio), :legal_email (email). Se rellenan desde Configuración › Ajustes.',
        'field_slug' => 'Identificador',
        'field_slug_hint' => 'Identificador de la página, ligado a su URL. No editable.',
        'field_is_active' => 'Publicada',
        'is_active_hint' => 'Si la ocultas, la página devuelve 404 y se rompen los enlaces del pie. Mantenla publicada salvo que la estés reescribiendo.',

        'lang' => [
            'es' => 'Español',
            'en' => 'Inglés',
            'fr' => 'Francés',
        ],
        'field_title' => 'Título',
        'field_body' => 'Secciones del texto',
        'field_h' => 'Encabezado (opcional)',
        'field_p' => 'Párrafo',
        'add_section' => 'Añadir sección',

        'edit_title' => 'Editar página: :name',

        'actions' => [
            'view' => 'Ver en la web',
        ],
    ],

    // ─── Fase 6 · waiver con valor probatorio (`specs/waiver-probatorio.md`) ───────────────────
    'waiver' => [
        'settings_mode' => 'Gestión del descargo de responsabilidad',
        'settings_mode_hint' => 'Externo: lo gestiona vuestro sistema y aquí solo se guarda el sello (como hasta ahora). Interno: el cliente lo firma en esta web y queda el registro probatorio; exige publicar una versión del texto desde «Páginas». Desactivado: la puerta no lo comprueba.',
        'modes' => [
            'externo' => 'Externo (sistema propio del parque)',
            'interno' => 'Interno (se firma en esta web)',
            'desactivado' => 'Desactivado (no se comprueba)',
        ],
        'settings_retention' => 'Conservación del registro firmado (meses)',
        'settings_retention_hint' => 'Cuántos meses se conserva cada firma del titular desde su fecha, también después de borrar la cuenta (conservación con tratamiento restringido). Vacío = no se purga nada hasta que se fije el plazo.',
        'settings_dependent_retention' => 'Conservación de la firma de un MENOR a cargo (meses tras cumplir 18)',
        'settings_dependent_retention_hint' => 'Cuántos meses se conserva la firma hecha en nombre de un menor DESPUÉS de que cumpla 18 años (un niño de 3 puede implicar conservarla 15 años). Vacío = no se purga ninguna firma de menor hasta que se fije el plazo.',
        'gate_outdated' => 'Su descargo es de una versión anterior del texto: puede pasar. Se le pedirá la firma nueva en su próxima compra o inicio de sesión, no en el mostrador.',
        // ⚠️ Los textos de PUBLICAR se mudaron a `admin.legal.publish` en `#348`: la acción dejó de ser
        // del descargo (se FIRMA) para servir también a las condiciones (se ACEPTAN), y un rótulo que
        // dijera «firmable» mentiría en la mitad de los casos.
        // El registro probatorio en la ficha del usuario (tanda 2): acción con permiso propio y auditada.
        'proof' => [
            'action' => 'Registro del descargo',
            'heading' => 'Registro probatorio del descargo · :name',
            'description' => 'Régimen restringido: esta consulta queda registrada en la auditoría. Cada firma enlaza con la anterior de la misma persona; el PDF se compone del texto exacto que aceptó, no del texto actual de la página.',
            'close' => 'Cerrar',
            'status_label' => 'Estado',
            'status_unsigned' => 'sin descargo firmado en este sistema',
            'status_current' => 'firmado, versión vigente (v:version)',
            'status_outdated' => 'firmado en una versión ANTERIOR (v:version): puede pasar; se le pedirá la firma nueva en su próxima compra o inicio de sesión',
            'empty' => 'Esta persona no tiene ninguna firma registrada en este sistema.',
            'channels' => [
                'web' => 'web',
                'api' => 'app',
                'panel' => 'mostrador',
            ],
            'subject_dependent' => 'en nombre del menor a su cargo :name',
            'declared_by' => 'declarada por :operator (alta presencial)',
            'integrity_ok' => 'integridad verificada',
            'integrity_ko' => 'INTEGRIDAD ROTA',
            'pdf' => 'PDF',
        ],
    ],

    // Fase 6 · menores a cargo (`specs/menores-a-cargo.md` §4.5): el ajuste del tope, en «Puerta».
    'dependents' => [
        'settings_max' => 'Menores a cargo por cuenta (máx.)',
        'settings_max_hint' => 'Cuántas personas a cargo puede declarar cada cuenta de cliente. Es un tope de servidor —no solo de pantalla— y no afecta a las que ya estén declaradas. Vacío = 20.',
    ],
];
