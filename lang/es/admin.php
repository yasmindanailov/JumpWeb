<?php

return [
    // Subtítulo bajo el wordmark del panel (#215).
    'panel_subtitle' => 'Panel de Control',

    'clusters' => [
        'configuracion' => 'Configuración',
    ],

    // Grupos del sidebar (Plan B · L1): reorganizan el panel para el empleado no técnico.
    // «Operativa» = el día a día; «Sistema» aísla lo delicado (usuarios/roles/mantenimiento).
    'nav_groups' => [
        'operativa' => 'Operativa',
        'programacion' => 'Programación',
        'catalogo' => 'Catálogo y precios',
        'contenido' => 'Contenido web',
        'sistema' => 'Sistema',
    ],

    'puerta' => [
        'validar' => [
            'nav_group' => 'Puerta',
            'title' => 'Validar registro',
            'intro' => 'Introduce el email o el teléfono del cliente para comprobar si está registrado y si ha firmado el waiver.',
            'back_to_panel' => 'Volver al panel',
            'input_placeholder' => 'Email o teléfono',
            'button' => 'Verificar',
            'button_loading' => 'Verificando…',
            'new_search' => 'Nueva búsqueda',
            'searched_for' => 'Resultado para',

            // Resultados (3 estados, decisión #126). El estado 2-en-1 `registered` se usa cuando la
            // comprobación de waiver está DESACTIVADA (#216): solo importa si tiene cuenta o no.
            'registered_with_waiver' => 'Registrado',
            'waiver_date' => 'Waiver aceptado el :date.',
            'registered_no_waiver' => 'Registrado, falta firmar waiver',
            'registered_no_waiver_cta' => 'Pásale la tablet al cliente para que firme el waiver antes de saltar.',
            'registered' => 'Cliente registrado',
            'registered_sub' => 'Tiene cuenta en el sistema.',
            'not_registered' => 'No registrado',

            // Edge cases.
            'invalid_input' => 'Introduce un email o un teléfono válido.',
            'rate_limited' => 'Demasiadas búsquedas seguidas. Espera un minuto e inténtalo de nuevo.',
        ],
    ],

    // Calendario unificado (Fase 7.4, decisión #14).
    'calendar' => [
        'nav_label' => 'Calendario',
        'nav_group' => 'Operativa',
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
        'nav_group' => 'Operativa',
        'model_label_singular' => 'pedido',
        'model_label_plural' => 'Pedidos',

        // Pedido manual desde back-office (Fase 7.3, #120).
        'create_manual' => [
            'nav_label' => 'Crear pedido',
            'title' => 'Crear pedido manual',
            'step_customer' => 'Cliente',
            'step_products' => 'Productos',
            'step_payment' => 'Pago',
            'customer' => 'Cliente',
            'customer_search_placeholder' => 'Busca por email, teléfono o nombre',
            'customer_help' => 'Selecciona al cliente para el que creas el pedido. Si tiene email, recibirá la confirmación por correo.',
            'customer_no_email' => '(sin email)',
            'register_cta' => '¿No tiene cuenta? Registrar al cliente',
            'register_heading' => 'Registrar cliente nuevo',
            'register_description' => 'Crea la cuenta del cliente al momento. El email es opcional: si lo indicas, le llegará un correo con una contraseña temporal para ver sus pedidos. Sin email, la reserva queda guardada en el panel con su teléfono (no recibe correos).',
            'register_name' => 'Nombre del cliente',
            'register_email' => 'Email del cliente',
            'register_email_optional' => 'Opcional. Si no lo tienes, déjalo vacío: la reserva quedará en el sistema con su teléfono (no recibirá correos y el enlace del formulario se copia desde el icono del producto).',
            'register_phone' => 'Teléfono del cliente',
            'register_privacy' => 'He informado al cliente de la política de privacidad y crea su cuenta con su consentimiento.',
            'register_privacy_required' => 'Debes confirmar que has informado al cliente de la política de privacidad.',
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
            'no_times_for_date' => 'No hay franjas disponibles para esta fecha. Regenera o abre franjas, o elige otro día.',
            'quantity' => 'Cantidad',
            'guests' => 'Invitados',
            'qty_out_of_range' => 'La cantidad debe estar entre :min y :max.',
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
            'confirm' => '¿Cobrar y crear el pedido?',
            'confirm_description' => 'Se registrará el cobro y se creará la reserva del cliente.',
            'submit' => 'Cobrar y crear pedido',
            'no_customer' => 'Selecciona un cliente.',
            'cart_empty' => 'Añade al menos un producto al pedido.',
            'invalid_method' => 'Método de pago no válido.',
            'reservation_failed' => 'No se pudo crear el pedido.',
            'created' => 'Pedido :code creado y cobrado.',
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
            'paid' => 'Completado',
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
            'guardian_label' => 'Padre/madre o tutor legal',
            'client_label' => 'Nombre del cliente',
            'prepared_check' => 'Preparado',
            'pending_at_gate' => 'A cobrar en puerta',
            // #225: el resto de la señal (no cobrado online) en el breakdown de la caja.
            'deposit_remainder_line' => 'Resto de la señal',
            // Caja prominente de devolución pendiente (#200), simétrica a la de
            // "A cobrar en puerta" — solo si queda algo por devolver.
            'pending_refund' => 'Pendiente de devolución',
            'pending_refund_caption' => 'Importe pagado de más (por una reducción o cancelación), pendiente de devolver al cliente.',
            'cancelled_notice' => 'Reserva cancelada',
        ],

        // Datos del evento por item (#86, sub-fase 7.2a).
        'event_data_section' => 'Datos del evento',
        'party_data_section' => 'Formulario de reserva',
        'show_more' => 'Ver más',
        'show_less' => 'Ver menos',
        // #225 F3: toggle del desglose ↳ de «A cobrar en el parque» (oculto por defecto).
        'show_breakdown' => 'Ver desglose',
        'hide_breakdown' => 'Ocultar desglose',
        // #225 (feedback clienta): conector que nombra el producto de un «Resto de la señal».
        'deposit_for_product' => 'de :product',
        // Badge corto junto al título «Formulario de reserva» (sin duplicarlo); icono ✓/! coherente.
        'guest_badge_ok' => 'Completado',
        'guest_badge_pending' => 'Pendiente',
        'guests_empty' => 'El cliente aún no ha rellenado el formulario de reserva.',

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
        'customer_waiver' => 'Waiver',
        'customer_waiver_missing' => 'No firmado',

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
                'modal_description' => 'Devolvemos el importe al cliente y le mandamos un aviso por email cuando se confirme.',
                'modal_description_finished_service' => 'El cliente ya ha disfrutado del servicio (todos los productos finalizaron). Esta acción le devuelve el importe; el pedido se queda como pagado con el reembolso anotado.',
                'submit' => 'Reembolsar',

                // Modo (#142, simplificado #143).
                'mode_label' => '¿Cómo lo procesamos?',
                'mode_rest' => 'Devolver ahora (recomendado)',
                'mode_rest_desc' => 'Devolvemos el dinero en el banco del cliente. Si el banco confirma, actualizamos el pedido y le mandamos el email. Si hubiera algún problema, el pedido se queda como estaba y te decimos qué ha pasado.',
                'mode_manual' => 'Solo registrar (ya devuelto fuera)',
                'mode_manual_desc' => 'Usa esta opción solo si ya devolviste el dinero por otro sitio (por ejemplo desde el portal del banco). Aquí solo lo dejamos apuntado y mandamos el email al cliente.',
                'intent_label' => '¿Por qué se le devuelve el dinero?',
                'intent_compensation' => 'Es una compensación: no nos debe nada',
                'intent_compensation_desc' => 'Le devolvemos el dinero y conserva su reserva sin tener que pagar nada más. Es lo que verá en su desglose.',
                'intent_paid_in_person' => 'Lo pagará en persona, en recepción',
                'intent_paid_in_person_desc' => 'Le devolvemos lo que pagó por la web porque abonará el importe al llegar. En su desglose aparecerá como pendiente de pagar en el parque.',

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
                ],
            ],
            'reasons' => [
                'already_cancelled' => 'el pedido ya está cancelado',
                'already_refunded' => 'el pedido ya está reembolsado',
                'expired' => 'el pedido caducó sin pago',
                'not_paid' => 'el pedido no está pagado',
                'already_finished' => 'todos los productos del pedido ya finalizaron (servicio prestado)',
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
            // eventFields configurados Y el operador tiene permiso (decisión #148).
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
            // Formato humanizado de duración (decisión #148 iter — `App\Domain\Platform\Services\Duration::formatHumane`):
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
            'unit_price_move' => 'Precio unitario: :from → :to',
            'price_diff' => 'Diferencia: :amount',
            'adjustment_amount' => 'Importe: :amount',
            'changed_what' => 'Cambió: :what',
            'change_kinds' => [
                'slot_change' => 'la fecha y la hora',
                'product_change' => 'el producto',
                'quantity_change' => 'la cantidad',
                'addon_change' => 'los complementos',
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
                    'guest_form_submitted' => 'Formulario de invitados enviado',
                    'payment_init_failed' => 'No se pudo iniciar el cobro',
                    'slip_printed' => 'Hoja de reserva impresa',

                    // Dinero de puerta. Los tres mueven «A cobrar en el parque» y son los que el
                    // operador tiene que poder explicar con el cliente delante.
                    'extra_due_applied' => 'Cargo añadido a cobrar en el parque',
                    'gate_credit_applied' => 'Abono sobre lo pendiente en el parque',
                    'deposit_remainder_credit_applied' => 'Abono sobre el resto de la señal',

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
                'deposit_remainder' => 'resto de la señal, a pagar en el parque',
            ],
        ],

        // #263 — Modal «Enlace del formulario de invitados»: lo abre el icono de enlace de cada
        // reserva con post-form (lista de productos). Muestra el enlace firmado para copiarlo y
        // enviarlo por WhatsApp/SMS (útil sobre todo si el cliente no tiene email).
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
            'modal_description' => 'Marca qué productos quieres devolver al cliente. Cada uno se marcará como cancelado y se devolverá su importe completo. Si marcas el producto principal, sus complementos quedarán también marcados (no tiene sentido devolver el producto pero entregar sus extras).',
            'submit' => 'Reembolsar lo seleccionado',

            'mode_label' => '¿Cómo procesamos la devolución?',
            'mode_rest' => 'Devolver ahora (recomendado)',
            'mode_rest_desc' => 'Devolvemos el dinero al banco del cliente. Si algo falla, te avisamos y no se modifica nada del resto.',
            'mode_manual' => 'Solo registrar (ya devuelto fuera)',
            'mode_manual_desc' => 'Solo lo dejamos apuntado (úsalo si ya devolviste el dinero por otro sitio: portal del banco, efectivo, etc.).',
            'intent_label' => '¿Por qué se le devuelve el dinero?',
            'intent_compensation' => 'Es una compensación: no nos debe nada',
            'intent_compensation_desc' => 'Le devolvemos el dinero y conserva su reserva sin tener que pagar nada más. Es lo que verá en su desglose.',
            'intent_paid_in_person' => 'Lo pagará en persona, en recepción',
            'intent_paid_in_person_desc' => 'Le devolvemos lo que pagó por la web porque abonará el importe al llegar. En su desglose aparecerá como pendiente de pagar en el parque.',

            'items_label' => 'Productos a reembolsar',
            'items_help' => 'Solo aparecen los productos que aún tienen importe pendiente de devolver. Si un producto ya se ha reembolsado completamente, no se lista.',

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
            // Sub-fase 7.2e.1bis5 (decisión #158, punto 4 feedback): título
            // explícito del bloque para diferenciar de "Totales del pedido".
            'heading' => 'Totales del producto',
            'principal' => 'Producto principal',
            'addons' => 'Complementos',
            'total' => 'Total del producto',
            // Robustez del desglose (#196): desglose detallado y consistente del
            // "Total del producto" → pagado online + lo de puerta (pendiente o cobrado).
            'paid_online' => 'Pagado online',
            'at_gate' => 'Falta por cobrar',
            // #225 F2: línea ↳ del resto de la señal dentro de "A cobrar en el parque"
            // (la card del producto, espejo del bloque del pedido order_financial).
            'deposit_remainder_line' => 'Resto de la señal',
            // Unificado con el bloque del pedido (order_financial.pagado_puerta) y
            // con el wording de la clienta: "Pagado en el parque" cuando finalizó.
            'collected_at_gate' => 'Pagado en el parque',
            'refunded_label' => 'Devuelto',
            'pending_refund_label' => 'Pendiente de devolución',
            // Robustez del desglose (#198): explica el PORQUÉ del pendiente en la card.
            'pending_refund_caption' => 'El cliente pagó de más por un cambio en este producto (una reducción de cantidad o una cancelación) y está pendiente de devolvérselo.',

            // Claves legacy de 7.2e.1bis (badges sueltos arriba); se conservan
            // por compat retro si algún partial las usa todavía.
            'refunded' => '↩ Devuelto: −:amount €',
            'pending_refund' => '⚠ Pendiente reembolso: :amount €',
        ],

        // Sub-fase 7.2e.1bis5 (decisión #158, punto 4 feedback): bloque
        // compacto de totales DEL PEDIDO al final de la card Resumen,
        // diferenciado del "item_financial" del sub-card de cada producto.
        'order_financial' => [
            'heading' => 'Totales del pedido',
            // ⚠️⚠️ El desglose NO CIERRA (`DECISIONES #132`). Al operador se le ENSEÑA —es quien
            // puede arreglarlo—; al cliente se le oculta la descomposición y se le da una frase
            // honesta. Hasta esa decisión no se enteraba ninguno de los dos.
            'no_cuadra_title' => 'Este desglose no cuadra.',
            'no_cuadra_body' => 'Las cifras de abajo no cierran entre sí, así que alguna es falsa: revisa los pagos, los reembolsos y los ajustes de este pedido antes de fiarte de ellas. El cliente NO ve este desglose: ve el importe que se le cobró y un aviso de que lo estamos revisando.',
            // 7.2e.3 (pulido #168): diferencia pendiente de cobrar en el parque
            // por una edición del producto que subió el importe.
            'pending_at_gate' => 'Falta por cobrar',
            'pending_at_gate_caption' => 'Importe a cobrar en recepción al llegar (resto de la señal y/o diferencias por cambios en los productos).',
            // #225: el resto de la señal (lo no cobrado online) como línea ↳ del «A cobrar en el parque».
            'deposit_remainder_line' => 'Resto de la señal',
            // Robustez del desglose (#196): devolución debida aún no procesada
            // (reducción/cancelación con reembolso fallido o pendiente) + total
            // final neto = lo que el cliente acaba pagando (= valor de productos).
            'pendiente_devolucion' => 'Pendiente de devolución',
            'pendiente_devolucion_caption' => 'El cliente pagó de más por un cambio en el pedido (una reducción de cantidad o una cancelación) y está pendiente de devolvérselo.',
            // Rediseño valor-primero (sesión 2026-06-06): el bloque del pedido pasa a
            // ser la SUMA de las cards de producto, con el MISMO vocabulario →
            // Valor final = Pagado online + A cobrar en el parque + Pagado en el parque.
            'valor_final' => 'Valor final del pedido',
            'pagado_online' => 'Pagado online',
            // P1/P10: si el cobro fue manual (efectivo/datáfono) y no por la web, no se dice «online».
            'cobrado_manual' => 'Cobrado (efectivo/datáfono)',
            // ⚠️ Los tres conceptos que la tanda B añade (`DECISIONES #127`). MISMOS conceptos que
            // ve el cliente, en tercera persona — que es la voz del operador y no se toca.
            'pendiente_online' => 'Pendiente de cobro online',
            'compensado' => 'Compensación devuelta',
            'cobrado_web' => 'Cobrado por web (extracto)',
            'pagado_puerta' => 'Pagado en el parque',
            // Ancla del importe bruto pagado por web (conciliación con el banco),
            // en el detalle de "Pendiente de devolución".
            'pendiente_devolucion_caption_web' => 'El cliente pagó :total por web; tras una reducción o cancelación se le devuelven :pendiente.',
            // #171: etiqueta compacta de las sub-líneas del desglose.
            'breakdown' => [
                'product_change' => 'Cambio a :name',
                // `DECISIONES #145`. Antes de esto, un ajuste nacido de mover la fecha llegaba con
                // el contexto vacío y caía al texto de respaldo, así que tres líneas seguidas
                // repetían la misma frase muda sin decir qué había cambiado.
                'slot_change' => 'Cambio de fecha a :when',
            ],
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
                'order_cancelled' => 'Este pedido está cancelado. Las acciones individuales de cancelar o reembolsar productos ya no aplican — el pedido entero se canceló como bloque.',
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
                'beyond_horizon' => 'la fecha está más allá del horizonte de reservas permitido',

                // Sub-fase 7.2e.3 (decisión #167): razones del cambio de
                // cantidad + producto (executeItemEdit).
                'cross_type_change_forbidden' => 'no puedes cambiar entre entrada y pack desde aquí; usa un pedido manual',
                'cross_zone_change_forbidden_product' => 'solo puedes cambiar a un producto de la misma zona; para cambiar de zona usa un pedido manual',
                'invalid_product' => 'el producto seleccionado no es válido o no está a la venta',
                'invalid_quantity' => 'la cantidad indicada no es válida',
                'pack_quantity_range' => 'el número de invitados está fuera del rango permitido para este pack',
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
        'nav_label' => 'Usuarios',
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
        'section_orders' => 'Pedidos',

        'orders_summary' => [
            'empty' => 'Este cliente todavía no tiene pedidos.',
        ],

        'consents' => [
            'empty' => 'Esta cuenta no tiene consentimientos registrados.',
            'types' => [
                'privacy' => 'Política de privacidad',
                'terms' => 'Términos y condiciones',
                'waiver' => 'Descargo de responsabilidad (waiver)',
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
            'anonymize' => [
                'label' => 'Anonimizar',
                'modal_heading' => 'Anonimizar usuario (RGPD)',
                'modal_description' => 'Esta acción es IRREVERSIBLE. Se borran los datos personales (nombre, email, teléfono), se eliminan los consentimientos y se desvinculan los roles. Los pedidos se conservan por obligación fiscal. La cuenta no podrá iniciar sesión y su email quedará libre para reuso.',
                'reason' => 'Motivo (queda en el registro de auditoría)',
                'submit' => 'Anonimizar definitivamente',
                'success' => 'Usuario anonimizado correctamente.',
                'blocked' => 'No se puede anonimizar esta cuenta.',
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
            'registrations_validate' => 'Validar registro/waiver en puerta',
            'orders_view' => 'Ver pedidos',
            'orders_create_manual' => 'Crear pedido manual (back-office)',
            'orders_cancel' => 'Cancelar pedido',
            'orders_refund' => 'Reembolsar pedido',
            'orders_edit_event_data' => 'Editar datos del evento del pedido (homenajeado, edad, notas)',
            'orders_edit_item' => 'Editar producto del pedido (fecha, cantidad, producto, datos, complementos)',
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

        'field_is_active' => 'Visible en la web',
        'is_active_hint' => 'Si se desactiva, deja de mostrarse en la web pública.',
        'field_is_sellable' => 'En venta online',
        'is_sellable_hint' => 'Debe tener precio para poder venderse.',
        'field_featured' => 'Destacado',
        'featured_hint' => 'Resalta el producto en la web.',
        'field_icon' => 'Icono del producto',
        'icon_placeholder' => 'El que le toca por su tipo',
        'icon_hint' => 'Marca el producto en la cesta, en el resumen y en «Mis pedidos». Si lo dejas vacío se usa el de su tipo: tarta para los packs y entrada para el resto.',
        'icon_option' => [
            'ic-b1' => 'Tarta de cumpleaños',
            'ic-b7' => 'Cañón de confeti',
            'ic-e2' => 'Par de entradas',
            'ic-e5' => 'Taco de entradas',
            'ticket-tear-off' => 'Entrada troquelada',
            'socks' => 'Calcetines',
        ],

        'zone_hint' => 'Zona a la que da acceso.',
        'zone_locked_sold' => 'No se puede cambiar la zona: el producto ya tiene ventas (movería su aforo).',

        'field_duration_min' => 'Duración (minutos)',
        'duration_min_hint' => 'Vacío = ilimitada (todo el día).',
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
        'landing_tagline' => 'Eslogan del pie',
        'landing_tagline_hint' => 'Frase bajo el nombre en el pie de página. Ej.: «Parque de saltos para toda la familia · Murcia».',
        'landing_footer_rights' => 'Coletilla del copyright',
        'landing_footer_rights_hint' => 'Texto tras «© AÑO NOMBRE —» en el pie. Ej.: «Hecho para reír.».',

        'section_registration' => 'Registro (sistema externo)',
        'section_registration_hint' => 'El botón «Registro» del header de la web lleva a vuestro sistema externo de registro/waiver. Etiqueta y subtítulo editables por idioma; si la URL queda vacía, el botón abre el registro interno de reservas.',
        'registration_url' => 'URL del registro externo',
        'registration_url_hint' => 'Dirección completa (https://…) del sistema de registro. Se abre en una pestaña nueva. Vacío = se usa el registro interno.',
        'registration_label' => 'Etiqueta del botón',
        'registration_label_hint' => 'Texto principal del botón. Ej.: «Registro».',
        'registration_subtitle' => 'Subtítulo del botón',
        'registration_description' => 'Texto informativo (confirmación)',
        'registration_description_hint' => 'Párrafo que se muestra en la pantalla de «Reserva confirmada», junto al botón de registro. Si lo dejas vacío, se usa un texto por defecto.',

        'theme_brand' => 'Color de marca',
        'theme_brand_hint' => 'Color principal de la marca (formato #RRGGBB). No afecta a los colores de zona, que se configuran por zona.',
        'theme_brand_secondary' => 'Color de marca secundario',
        'theme_brand_secondary_hint' => 'Acento que acompaña al principal en detalles y decoraciones (formato #RRGGBB). Si lo dejas vacío se usa el del diseño por defecto.',

        'cookies_banner_enabled' => 'Mostrar el banner de cookies',
        'cookies_banner_enabled_hint' => 'Si lo desactivas, NO se oculta el bloqueo previo: el mapa y el feed social siguen sin cargarse hasta que el visitante consienta; solo se oculta el aviso. Déjalo activado salvo que gestiones el consentimiento por otra vía.',

        'catalog_search_min_items' => 'Mostrar el buscador a partir de N productos',
        'catalog_search_min_items_hint' => 'El buscador del catálogo aparece solo cuando el nº total de productos vendibles SUPERA este número. Déjalo vacío para el valor por defecto (12). 0 = el buscador se muestra siempre.',

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
        'puerta_waiver_check' => 'Comprobar el waiver en la puerta',
        'puerta_waiver_check_hint' => 'Activado: la puerta muestra si el cliente firmó el waiver (3 estados). Desactivado: solo muestra si está registrado (2 estados), útil si el waiver lo gestiona vuestro sistema externo.',
        'tax_rate' => 'IVA por defecto (%)',
        'tax_rate_hint' => 'Porcentaje de IVA por defecto (informativo).',
        'packs_max_per_slot' => 'Cumpleaños por franja (máx.)',
        'packs_cap_hint' => '0 = sin tope.',
        'packs_max_guests_per_slot' => 'Invitados totales por franja (máx.)',
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

        'section_cupo' => 'Aforo de packs (cupo) por zona',
        'section_cupo_hint' => 'Solo aplica si la zona aloja packs (cumpleaños). Vacío = usa el valor global de Configuración; un valor manda sobre el global. La ocupación se cuenta por zona.',
        'field_max_per_slot' => 'Fiestas por franja',
        'field_max_guests_per_slot' => 'Niños por franja',
        'field_cupo_hint' => 'Vacío = usa el valor global. 0 = sin tope.',
        'field_prep_blocks_cupo' => 'El montaje/limpieza bloquea cupo',
        'field_prep_blocks_cupo_hint' => 'Si el montaje y la limpieza ocupan también las franjas vecinas.',
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
        'col_nav' => 'En el menú',
        'nav_yes' => 'Sí',
        'nav_no' => 'No',

        'section_identity' => 'Identidad',
        'field_slug' => 'Anchor (slug)',
        'field_slug_hint' => 'Identificador estable de la sección: el menú enlaza a /servicios#anchor. Se fija al crear y no se cambia (rompería enlaces).',
        'field_position' => 'Orden',
        'field_position_hint' => 'Orden de aparición en /servicios y en el menú (menor primero).',
        'field_image' => 'Imagen (ruta)',
        'field_image_hint' => 'Ruta relativa a public/ (p. ej. images/attractions/park_jump.webp). La subida de ficheros llegará con la galería.',
        'field_pack' => 'Pack vinculado (opcional)',
        'field_pack_hint' => 'Si vinculas un pack vendible, la sección muestra su precio y un botón «Reservar». Sin pack, muestra «Pedir información». Vincular un pack lo SACA de la sección Cumpleaños.',
        'pack_not_purchasable_warning' => 'Aviso: este pack no se podrá comprar en la web (la sección mostrará «Pedir información»). Revisa en Catálogo que esté en venta online, activo, con precio y en una zona operativa.',
        'field_is_active' => 'Visible en /servicios',
        'field_is_active_hint' => 'Si se desactiva, la sección no aparece en la página /servicios.',
        'field_show_in_nav' => 'Mostrar en el menú «Servicios»',
        'field_show_in_nav_hint' => 'Si se activa, el servicio aparece en el desplegable «Servicios» del menú de navegación.',

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
        'field_nav_subtitle' => 'Subtítulo en el menú',
        'field_nav_subtitle_hint' => 'Texto pequeño bajo el título en el desplegable «Servicios» (p. ej. «Sesión de 30 min»).',
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

    'park_rules' => [
        'nav_label' => 'Normas',
        'model_label_singular' => 'norma',
        'model_label_plural' => 'Normas',

        'col_name' => 'Norma',
        'col_active' => 'Activa',
        'active_yes' => 'Activa',
        'active_no' => 'Inactiva',

        'section_classification' => 'Clasificación',
        'field_position' => 'Orden',
        'field_position_hint' => 'Orden de aparición (menor primero).',
        'field_is_active' => 'Activa',
        'field_is_active_hint' => 'Si se desactiva, la norma no aparece en la landing ni en /normas.',

        'lang' => [
            'es' => 'Español',
            'en' => 'Inglés',
            'fr' => 'Francés',
        ],
        'field_name' => 'Título',
        'field_description' => 'Descripción',

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
];
