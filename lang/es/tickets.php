<?php

return [
    // Ventana horaria de un producto ilimitado (sin hora de fin): "10:00 – sin límite".
    'time_no_limit' => 'sin límite',
    'eyebrow' => 'Entradas',
    'title' => 'Reservas',
    'my_reservations' => 'Ver mis reservas',
    'intro' => 'Elige tu entrada o pack, el día y la hora. Añade lo que quieras al carrito; el precio se ajusta según el día.',
    'step_date' => 'Elige el día',
    'step_time' => 'Elige la hora de entrada',
    'step_tickets' => 'Elige tus entradas',
    // Sidebar v2 — stepper detallado y footer dinámico. La 3.ª fase es «Extras» (entrada) o
    // «Datos» (pack: datos de la reserva + extras), según el tipo de producto.
    'step_count' => 'Paso :n de :total',
    'phase_date' => 'Fecha',
    'phase_time' => 'Hora',
    'phase_extras' => 'Extras',
    'phase_details' => 'Datos',
    'go_to_cart' => 'Ir al carrito',
    'go_to_pay' => 'Ir a pagar',
    'cart_items' => ':count artículo|:count artículos',
    // Desglose de la señal en el sticky footer, bajo el Total. «Pagas ahora» es neutro en el carrito
    // (en cestas mixtas no es solo señal); en el paso de producto único se aclara «(señal)». El resto
    // («En el parque») reutiliza tickets.pay_at_park.
    'footer_pay_now' => 'Pagas ahora',
    'footer_pay_now_deposit' => 'Pagas ahora (señal)',
    'deposit_info' => 'Ver desglose de la señal',
    'back_to_cart' => 'Volver al carrito',
    'section_entries' => 'Entradas',
    'section_services' => 'Servicios',
    'catalog_search' => 'Buscar en el catálogo…',
    'catalog_search_none' => 'Sin resultados.',
    'guests' => 'Invitados',
    'guests_left' => 'quedan :count plazas',
    'guests_count' => ':count invitados',
    // La cantidad CON su sustantivo, que es lo que la desambigua del importe (`DECISIONES #128`):
    // «8×216,00 €» se lee como 8 × 216 = 1.728 €, y «8 invitados · 216,00 €» no. Las compone
    // `OrderItem::displayQuantityLabel()`, en la voz del cliente — el panel tiene la suya.
    // ⚠️ Forma `singular|plural` y NO la de rangos (`{1}…|[2,*]…`): el grupo `tickets` viaja entero
    // al cajón y su `i18n.js` solo resuelve la primera — pintaría las llaves. Lo dijo
    // `SidebarTextParityTest` en cuanto se intentó, que es exactamente para lo que existe.
    'entries_count' => ':count entrada|:count entradas',
    'units_count' => ':count unidad|:count unidades',
    'per_child' => 'por niño',
    'step_complements' => 'Complementos',
    'complements_intro' => 'Añade extras a tu reserva (opcional).',
    'addon_choose_one' => 'Elige una opción:',
    'addon_badge_included' => 'Incluido',
    'addon_badge_free' => 'Gratis',
    'addon_more_info' => 'Más info',
    'addon_included' => 'Incluido',
    'addon_included_partial' => ':count incluido(s) gratis',
    'addon_included_extra' => 'Incluido · extras :price/u',
    'addon_extra_each' => 'extras :price/u',
    'addon_per_unit' => ':price/invitado',
    'addon_per_guest_qty' => ':count (uno por invitado)',
    'addon_per_guest_add' => 'Añadir · uno por invitado',
    'addon_requires' => 'Requiere: :name',
    'from' => 'desde',
    'quantity' => 'Cantidad',
    'no_dates' => 'No hay días disponibles ahora mismo. Vuelve a intentarlo más tarde.',
    'zone' => 'Zona',
    'seats_left' => ':count libres',
    'sold_out' => 'Sin plazas',
    'summary' => 'Resumen',
    'summary_empty' => 'Elige día, hora y entradas para ver el total.',
    'total' => 'Total',
    'continue' => 'Continuar',
    'confirm_next' => 'Carrito listo. Continúa para identificarte y pagar.',
    'iva_note' => 'Precios con IVA incluido. El pago se realiza de forma segura con Redsys.',
    'prev_month' => 'Mes anterior',
    'next_month' => 'Mes siguiente',
    'legend_normal' => 'Día normal',
    'legend_special' => 'Especial (festivo, finde o víspera)',
    // Tira de días + calendario plegable (`DECISIONES #239`). La tira es la vía normal; el
    // calendario, el atajo para el salto largo.
    'calendar_show' => 'Ver más fechas',
    'calendar_hide' => 'Ocultar el calendario',
    // Aviso de ocupación del chip de hora. `[DECIDIDO owner]`: SIN número — dice que queda poco, no
    // cuánto. El umbral lo pone el operador en Ajustes (`booking.low_availability_max`).
    'almost_full' => 'Casi llena',
    // Flechas de las tiras (`#241`): solo se ven con ratón, pero su nombre accesible viaja siempre.
    'strip_prev' => 'Ver anteriores',
    'strip_next' => 'Ver siguientes',
    'back' => 'Volver',
    'add_to_cart' => 'Añadir al carrito',
    'cart_title' => 'Tu carrito',
    'cart_empty' => 'Tu carrito está vacío.',
    'add_another' => 'Añadir otra reserva',
    'remove' => 'Quitar',
    'identify_title' => 'Identifícate',
    'identify_intro' => 'Inicia sesión o crea tu cuenta para completar tu reserva.',
    'pay_title' => 'Pago',
    'pay_intro' => 'Revisa tu reserva antes de pagar.',
    'pay_notice' => 'Pago seguro con tarjeta a través de Redsys. Tu tarjeta no se guarda en este sitio.',
    'pay_confirm' => 'Pagar con tarjeta',
    'pay_redirecting' => 'Te llevamos a la pasarela de pago segura. Si no se redirige en unos segundos, pulsa el botón.',
    'pay_redirecting_title' => 'Redirigiendo al pago',
    'pay_proceed_manual' => 'Continuar al pago',
    'redsys_product_description' => 'Reserva :name · pedido :code',
    'payment_failed_title' => 'El pago no se ha completado',
    'payment_failed_intro' => 'Tu banco no autorizó el cobro. No se ha cargado nada en tu tarjeta.',
    'payment_failed_retry' => 'Mantenemos tu reserva unos minutos más por si quieres reintentar el pago. Si no se completa, la plaza volverá a estar disponible.',
    'payment_failed_contact' => 'Escribirnos',
    'payment_failed_retry_cta' => 'Reintentar el pago',
    'payment_failed_reason_label' => 'Motivo',
    'payment_failed' => [
        // Mensajes al cliente cuando un pago Redsys es denegado (#114, mapeo `Ds_Response`
        // en `App\Domain\Payments\Services\RedsysResponseCode`). Tono: claro, sin tecnicismos, accionable.
        'reasons' => [
            'card_expired' => 'Tu tarjeta está caducada (o la fecha introducida no es correcta).',
            'card_invalid' => 'La tarjeta no es válida o no se puede usar para este pago.',
            'cvv_wrong' => 'El código CVV (3 dígitos del reverso) no es correcto.',
            'card_unsupported' => 'Tu tarjeta no admite pagos en este comercio. Prueba con otra.',
            'auth_failed' => 'No hemos podido verificar tu identidad con el banco. Inténtalo de nuevo o usa otra tarjeta.',
            'bank_denied' => 'Tu banco ha denegado el pago. Contacta con ellos para conocer el motivo.',
            'fraud_suspicion' => 'Tu banco detectó actividad inusual y bloqueó el pago. Contacta con ellos para autorizarlo.',
            'pin_attempts_exceeded' => 'Has superado el número de intentos permitidos por tu banco. Espera unas horas o contacta con tu banco.',
            'user_cancelled' => 'El pago se canceló en la pasarela. Puedes intentarlo de nuevo cuando quieras.',
            'system_error' => 'La pasarela de pago no pudo procesar la operación. Inténtalo de nuevo en unos minutos.',
            'default' => 'El pago no se autorizó. Si el problema continúa, contacta con tu banco o escríbenos.',
        ],
    ],
    'payment_rejected_generic' => 'No hemos podido verificar el resultado del pago. Si tienes dudas, contáctanos.',
    'payment_verifying_title' => 'Verificando tu pago',
    'payment_verifying_intro' => 'Tu banco ha procesado el pago. Estamos confirmando la operación con la pasarela; suele tardar unos segundos.',
    'payment_verifying_email_note' => 'Te enviaremos un email cuando esté confirmado. También puedes ver el estado en "Mis reservas".',
    'verify_title' => 'Verifica tu correo',
    'verify_intro' => 'Para completar tu reserva, verifica tu cuenta desde el correo que te hemos enviado.',
    'verify_hold' => 'Tu plaza está reservada mientras confirmas.',
    'reservation_created' => '¡Reserva creada!',
    'reservation_thanks' => '¡Gracias! Tu plaza está reservada. Aquí tienes el resumen:',
    'order_code' => 'Nº de pedido',
    'email_sent_note' => 'Te hemos enviado un correo con el resumen y este número.',
    'pending_payment' => 'Reserva pendiente de pago.',
    'payment_confirmed_note' => 'Pago confirmado.',
    'new_purchase' => 'Hacer otra reserva',
    'see_my_orders' => 'Ver mis reservas',
    'guest_form_notice' => 'Te pediremos completar el formulario de tu reserva: te enviaremos el enlace por email (también en «Mis reservas»).',
    // #146: el Order status `paid` lee "Completado" (no "Pagado"). Un Order
    // completado puede llevar reembolso anotado — el reembolso es una dimensión
    // independiente con su propio badge (`tickets.refunded_badge`). El estado
    // del COBRO (Payment) sigue siendo "Pagado", pero la card de pagos donde
    // se ve no aparece en mi-cuenta, solo en el panel admin.
    'statuses' => [
        'pending' => 'Pendiente de pago',
        'paid' => 'Completado',
        'cancelled' => 'Cancelado',
        'refunded' => 'Reembolsado',
        'expired' => 'Caducado',
    ],
    // Badge secundario aditivo al status y resumen financiero del reembolso en
    // mi-cuenta (#146). `refunded_on` admite tanto el modo REST (Redsys 0900) como
    // el modo manual (registrado por el operador tras devolver en el portal banco):
    // ambos son devolución efectiva desde el punto de vista del cliente.
    'refunded_badge' => 'Reembolsado',
    'refunded_on' => 'Reembolsado el :date',
    'net' => 'Neto',
    // #225 (señal/depósito): split del sidecart (paso 8) y de la pantalla de confirmación (paso 6).
    // #225 F2: la señal se anuncia en TODO el flujo de compra (catálogo · cantidad · cesta · pago)
    // con texto coherente. `total_pay_now` = importe PROMINENTE que se cobra ahora; el resto va al
    // parque. La señal POR PRODUCTO se detalla en su card (`deposit_catalog`/`deposit_card_note`).
    'total_pay_now' => 'Total a pagar ahora',
    'deposit_catalog' => 'Señal :amount',
    'deposit_card_note' => 'Señal :deposit · :rest en el parque',
    'pay_at_park' => 'En el parque',
    'paid_online_confirmed' => 'Pagado online',
    'pending_at_park' => 'Pendiente en el parque',
    // Robustez del desglose (#196/#198): desglose detallado en "Mis pedidos".
    'subtotal' => 'Subtotal',
    'at_gate' => 'A cobrar en el parque',
    // #225 F3: toggle del desglose ↳ (oculto por defecto) en «Mis pedidos».
    'show_breakdown' => 'Ver desglose',
    'hide_breakdown' => 'Ocultar desglose',
    // #225 (feedback clienta): nombra el producto de cada «Resto de la señal» (desglose por producto).
    'deposit_for_product' => 'de :product',
    'at_gate_caption' => 'Diferencia por cambios en el pedido. Se cobra en recepción al llegar.',
    // #225 (señal/depósito): el cliente pagó solo la señal online; el resto se cobra en el parque.
    // #225 F3: etiqueta NEUTRA del agregado online (señal(es) + productos de pago completo); la
    // señal por-producto se nombra en la card de cada producto, no aquí (engañaba en cestas mixtas).
    'deposit_paid_online' => 'Pagado online',
    // #225 F2: línea ↳ del resto de la señal dentro de "A cobrar en el parque".
    'deposit_remainder_line' => 'Resto de la señal',
    // ⚠️ El cargo de puerta por una EDICIÓN, cuando no se puede decir «+4 X» ni «Cambio a X»
    // (`DECISIONES #131`). Antes salía el nombre pelado del producto y no decía por qué se cobra,
    // mientras su línea hermana —el resto de la señal— sí se explicaba sola.
    'gate_change_line' => 'Diferencia por cambios en :product',
    // `#154`: las etiquetas ESPECÍFICAS del cargo de puerta vivían en `admin.*` (solo ES) y el
    // CLIENTE las consume — medido por HTTP: un cliente en inglés recibía la clave literal
    // `admin.orders.order_financial.breakdown.slot_change` en su desglose de dinero. Etiqueta que
    // lee el cliente ⇒ espacio del cliente, en sus tres idiomas.
    'gate_change_line_slot' => 'Cambio de fecha a :when',
    'gate_change_line_product' => 'Cambio a :name',
    'at_gate_caption_deposit' => 'Resto a pagar en recepción al llegar. La señal ya quedó pagada online.',
    'pendiente_devolucion' => 'Pendiente de devolución',
    'pendiente_devolucion_caption' => 'Pagaste de más por un cambio en el pedido (se redujo o quitó un producto) y está pendiente de devolvértelo.',
    'total_final' => 'Total final',
    // ⚠️⚠️ LA FRASE que explica el estado del pedido (`DECISIONES #127`). Un número no explica:
    // el encargo era que el cliente entienda su situación ante CUALQUIER situación. La compone el
    // DOMINIO (`Booking\Services\OrderLedger`), que es quien sabe qué caso es.
    // ⚠️⚠️ Los rótulos del DESGLOSE, en DOS BLOQUES que no se mezclan (`DECISIONES #127`):
    // arriba lo que vale y por qué canal se paga, abajo qué ha pasado con su dinero. Hasta la
    // tanda B, «Devuelto» y «Pendiente de devolución» se pintaban como restas dentro de la
    // columna del valor —de la que NO restan— y por eso la columna dejaba de leerse.
    'ledger' => [
        'value_title' => 'Qué vale este pedido',
        'value_total' => 'Valor del pedido',
        'paid_online' => 'Pagado por web',
        'pending_online' => 'Pendiente de pagar por web',
        'paid_at_gate' => 'Pagado en el parque',
        'pending_at_gate' => 'Pendiente de pagar en el parque',
        'compensated' => 'Compensación devuelta',
        // ⚠️ El MÉTODO manda en el rótulo (`DECISIONES #128`): el eje de caja suma todos los pagos
        // cobrados, y en un pedido de taquilla «por web» sería falso. El panel ya lo distinguía.
        'paid_desk' => 'Pagado en recepción',
        'cash_title' => 'Tu dinero',
        'cash_caption' => 'Es el dinero que ya te hemos cobrado por este pedido. Puedes cotejarlo con tu extracto bancario.',
        'charged_online' => 'Cobrado por web',
        'charged_desk' => 'Cobrado en recepción',
        'refunded' => 'Ya devuelto',
        'pending_refund' => 'Pendiente de devolverte',
        'invoiced' => 'Importe al reservar',
        // ⚠️⚠️ DIRECCIÓN E IMPORTE, no un número mudo (`L6`, `DECISIONES #133`). La frase anterior
        // era una sola y fija —«…es porque el pedido cambió después»—: decía QUE el pedido cambió y
        // no en qué sentido ni cuánto, que es justo lo que quiere saber quien ve 180,00 € donde
        // espera 120,00 €. Una BAJADA no dejaba más rastro que ese número.
        // ⚠️ Las elige el DOMINIO (`Booking\Services\OrderLedger`), como la frase de estado.
        // ⚠️ `:difference` es la DIFERENCIA, no el valor: el valor ya está dos líneas más arriba.
        'invoiced_hint_more' => 'Al reservar se facturaron :invoiced. El pedido cambió después y ahora vale :difference más.',
        'invoiced_hint_less' => 'Al reservar se facturaron :invoiced. El pedido cambió después y ahora vale :difference menos.',
    ],
    'ledger_note' => [
        // ⚠️⚠️ Va PRIMERO en `noteFor()`: si el desglose no cuadra, ninguna otra frase puede ser
        // cierta (`DECISIONES #132`).
        'under_review' => 'Estamos revisando el detalle de este pedido. El importe que te hemos cobrado es el que ves abajo; si tienes cualquier duda, escríbenos y lo miramos contigo.',
        'owing' => 'Tenemos pendiente devolverte :amount.',
        'cancelled_owing' => 'Tu reserva se canceló el :date. Tenemos pendiente devolverte :amount.',
        'cancelled_refunded' => 'Tu reserva se canceló y te devolvimos :amount el :date.',
        'cancelled' => 'Tu reserva se canceló el :date.',
        'expired' => 'Esta reserva caducó sin completarse el pago. No se te ha cobrado nada.',
        'pending_payment' => 'Todavía no se ha completado el pago de :amount. Tu plaza sigue reservada hasta que caduque.',
        'compensated' => 'Te devolvimos :amount y conservas tu reserva: no tienes que pagar nada más.',
        'refunded_pay_in_person' => 'Te devolvimos :amount porque abonarás el importe en recepción al llegar.',
        'refunded_still_booked' => 'Te devolvimos :amount y tu reserva sigue en pie.',
        'pending_at_gate' => 'Te quedan :amount por pagar en recepción al llegar.',
    ],
    'errors' => [
        'choose_one' => 'Elige al menos una entrada para continuar.',
        'cart_empty' => 'Añade al menos una visita para continuar.',
        'cart_too_large' => 'Has alcanzado el máximo de líneas en el carrito. Termina esta reserva antes de añadir más.',
        'login_required' => 'Inicia sesión para completar tu reserva.',
        // Mensajes genéricos (fallback, sin contexto de línea).
        'sold_out' => 'Lo sentimos, esa franja se acaba de agotar. Prueba con otra hora.',
        'unavailable' => 'Esa franja ya no está disponible. Revisa tu carrito.',
        'pack_sold_out' => 'Lo sentimos, ese día y hora para cumpleaños se acaba de completar. Prueba con otra franja.',
        'pack_guests_range' => 'El número de invitados no es válido para este pack.',
        'event_required' => 'Completa los datos obligatorios del cumpleaños.',
        // Validar-al-pulsar (#UX): feedback específico — resumen que NOMBRA los campos que faltan
        // y mensaje por-campo bajo cada input resaltado.
        'fields_missing' => 'Falta rellenar: :fields.',
        'field_required' => 'Campo obligatorio.',
        // Mensajes específicos por línea (auditoría 2026-05-26 2ª ronda, P-13/P-14): incluyen
        // qué producto y franja tienen el problema, para que el cliente pueda corregir sin adivinar.
        'sold_out_line' => '«:product» del :when se ha agotado. Quítalo del carrito y elige otra franja.',
        'unavailable_line' => '«:product» del :when ya no está disponible. Revisa tu carrito.',
        'past_date_line' => '«:product» del :when es de una fecha pasada. Quítalo y elige una nueva.',
        'outside_window_line' => 'La hora del :when no está disponible para «:product». Elige otra.',
        'too_soon_line' => '«:product» del :when requiere reservar con más antelación. Elige una fecha más adelante.',
        'too_late_line' => 'La hora de «:product» del :when ya ha pasado. Elige una franja más tarde.',
        'pack_sold_out_line' => 'El pack «:product» del :when ya no tiene cupo. Quítalo del carrito y prueba con otra franja.',
        'pack_guests_range_line' => 'El nº de invitados de «:product» debe estar entre :min y :max.',
        'event_required_line' => 'Faltan datos del cumpleaños para «:product». Vuelve atrás y rellénalos.',
        'too_many_pending' => 'Tienes :max reservas pendientes (el máximo). Si necesitas cancelar alguna, escríbenos desde Contacto y te ayudamos.',
        'try_later' => 'Demasiados intentos seguidos. Espera un minuto antes de volver a intentarlo.',
        'payment_unavailable' => 'No hemos podido iniciar el pago. Vuelve a intentarlo en un momento; si el problema persiste, escríbenos desde Contacto.',
        'retry_expired' => 'Tu reserva caducó mientras esperábamos el pago. La plaza ha vuelto a estar disponible; tendrás que elegirla de nuevo.',
        // Reservas en pausa (#218): guard de servidor del flujo de compra.
        'reservations_paused' => 'Las reservas online están pausadas temporalmente. Llámanos al :phone para reservar.',
        'reservations_paused_no_phone' => 'Las reservas online están pausadas temporalmente. Escríbenos desde Contacto para reservar.',
    ],

    // Aviso de mantenimiento DENTRO del sidecart (#218, item 3): al abrir la compra con las reservas
    // en pausa, el sidecart muestra esto + los canales de contacto (teléfono / WhatsApp).
    'paused' => [
        'title' => 'Estamos en mantenimiento',
        'body' => 'Disculpa las molestias. Ahora mismo no se puede reservar online, pero puedes hacerlo llamándonos o escribiéndonos por WhatsApp.',
        'call' => 'Llamar al :phone',
        'whatsapp' => 'Escríbenos por WhatsApp',
        'contact' => 'Ir a contacto',
    ],

    // Los menores a cargo EN EL EMBUDO (Fase 6 · tanda 4, `menores-a-cargo.md` §9.9): el selector de
    // los pasos 3 y 4, el aviso de la puerta 2 y el «Para:» del resumen. ⚠️ Viven AQUÍ y no en
    // `account.dependents` porque `account` viaja SOLO con sesión y quien entra anónimo y se identifica
    // en el paso 5 los necesita sin recargar — lo cazó el guion headless (§5.undecies).
    'dependents' => [
        'title' => '¿Para quién son estas entradas?',
        'hint' => 'Marca a los menores que vienen con estas entradas; el resto son adultos.',
        'full' => 'No caben más: una entrada por menor.',
        'adult' => 'ya tiene 18 años',
        // El estado POSITIVO de la exención, que solo existe en modo interno (§9.11 D·2,
        // `DECISIONES #217`): fuera de él no hay firma que comprobar y anunciar una sería mentir.
        // Va en la fila del menor, al lado de la edad; los tres de arriba son su reverso, el porqué.
        'signed' => 'exención firmada',
        'unsigned' => 'exención sin firmar: fírmala en «Menores a cargo»',
        'outdated' => 'firmaste una versión anterior: acepta la nueva en «Menores a cargo»',
        'notice' => 'Tienes menores a cargo: indica para quién es cada entrada antes de pagar (o déjalas como adultos).',
        'for' => 'Para:',
        'age' => ':age años',
    ],
];
