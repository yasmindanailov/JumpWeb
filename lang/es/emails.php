<?php

return [
    'customer_account_created' => [
        'subject' => 'Tu cuenta en :park ya está lista',
        'greeting' => '¡Hola!',
        'intro' => 'El equipo de :park ha creado una cuenta para ti para que puedas consultar tus reservas.',
        'email_label' => 'Email',
        'password_label' => 'Contraseña temporal',
        'action' => 'Iniciar sesión',
        'recommend_change' => 'Por seguridad, te recomendamos cambiar la contraseña en cuanto entres, desde «Mi cuenta».',
        'ignore' => 'Si no esperabas este correo, puedes ignorarlo.',
    ],
    'verify_purchase' => [
        'subject' => 'Confirma tu email para continuar con tu reserva',
        'greeting' => '¡Hola!',
        'intro' => 'Casi listo. Tu reserva (nº :code) está apartada. Confirma tu email para continuar con el pago.',
        'action' => 'Confirmar mi email',
        'hold_note' => 'Tu plaza está reservada provisionalmente. Si no confirmas a tiempo, podría liberarse.',
        'outro' => 'Si no has hecho esta reserva, puedes ignorar este correo.',
    ],
    'verify_pending_email' => [
        'subject' => 'Confirma tu nuevo email en :park',
        'greeting' => '¡Hola!',
        'intro' => 'Has pedido cambiar el email de tu cuenta a este. Para confirmarlo, pulsa el botón.',
        'action' => 'Confirmar mi nuevo email',
        'expires' => 'Este enlace caduca en 60 minutos.',
        'ignore' => 'Si no has pedido este cambio, puedes ignorar este correo: tu cuenta seguirá usando el email anterior.',
    ],
    'email_change_requested' => [
        'subject' => 'Solicitud de cambio de email en tu cuenta',
        'greeting' => '¡Hola!',
        'intro' => 'Alguien ha solicitado cambiar el email de tu cuenta a :new.',
        'it_was_me' => 'Si has sido tú, confirma desde el enlace que hemos enviado al nuevo email.',
        'it_was_not_me' => 'Si NO has sido tú, ignora ese correo: tu cuenta seguirá usando este email. Te recomendamos cambiar la contraseña.',
    ],
    'email_change_completed' => [
        'subject' => 'El email de tu cuenta ha cambiado',
        'greeting' => '¡Hola!',
        'intro' => 'Confirmamos que el email de tu cuenta es ahora :new. Este buzón (el anterior) ya no recibirá comunicaciones de :park.',
        'what_means' => 'A partir de ahora, debes iniciar sesión con el nuevo email.',
        'it_was_not_me' => 'Si NO has hecho este cambio, contacta con nosotros INMEDIATAMENTE: tu cuenta puede haber sido comprometida.',
    ],
    'order_confirmation' => [
        'subject' => 'Tu reserva confirmada en :park (nº :code)',
        'greeting' => '¡Hola!',
        'intro' => '¡Pago recibido y reserva confirmada! Ya está todo listo para tu visita. Tu nº de pedido es :code — guárdalo, te lo pedirán en el parque.',
        'total' => 'Total pagado: :amount €',
        // #225 (señal/depósito): cuando online se cobra solo la señal del cumpleaños.
        'deposit_paid' => 'Señal pagada online: :amount €',
        'pending_at_park' => 'Pendiente de pago en el parque el día de tu reserva: :amount € (puedes pagarlo en efectivo o con tarjeta).',
        'paid_at' => 'Fecha del cobro: :when',
        'action' => 'Ver mis reservas',
        'paid_confirmation' => 'Te esperamos en la fecha y hora que elegiste. Puedes ver todos los detalles desde «Mis reservas», en tu cuenta.',
        'paid_confirmation_guest_form' => 'Te esperamos en la fecha y hora que elegiste. En breve te pediremos por email los datos de los invitados; también puedes completarlos cuando quieras desde «Mis reservas», en tu cuenta.',
        'outro' => '¿Alguna duda antes de tu visita? Escríbenos, estamos encantados de ayudarte.',
    ],
    'guest_form' => [
        'subject' => 'Completa los datos de tu reserva «:product» · :code',
        'greeting' => '¡Hola!',
        'intro' => '¡Tu reserva «:product» (nº :code) está confirmada! Para dejarlo todo listo y que el día sea perfecto, cuéntanos quién viene: completa los datos de cada invitado.',
        'body' => 'Puedes rellenarlo ahora o más adelante, y editarlo cuando quieras hasta el día del evento.',
        'action' => 'Rellenar el formulario de reserva',
        'outro' => 'También puedes hacerlo desde «Mis reservas». ¡Gracias!',
    ],
    'order_after_expiration' => [
        'subject' => 'Tu pago llegó bien, estamos revisando tu reserva (nº :code)',
        'greeting' => 'Hola,',
        'intro' => 'Buenas noticias: tu pago de la reserva :code ha llegado correctamente. Nos entró con algo de retraso, así que no quedó registrado a tiempo de forma automática y lo estamos gestionando a mano.',
        'amount' => 'Importe cobrado: :amount €',
        'next_steps' => 'Nuestro equipo lo está revisando ahora mismo: nos pondremos en contacto contigo en las próximas 24 horas para reagendar tu visita o, si lo prefieres, devolverte el importe.',
        'contact' => 'Si necesitas hablar antes con nosotros, responde a este correo o llámanos. Lamentamos las molestias.',
    ],
    'order_declined' => [
        'subject' => 'No hemos podido procesar el pago de tu reserva (nº :code)',
        'greeting' => 'Hola,',
        'intro' => 'No hemos podido completar el pago de tu reserva :code.',
        'no_charge' => 'Tranqui: no se ha cobrado nada en tu tarjeta. Mantenemos tu reserva unos minutos más por si quieres volver a intentarlo; si no, la plaza se liberará para otras personas.',
        'reason_prefix' => 'Motivo:',
        'action' => 'Reintentar el pago',
        'contact' => 'Si crees que se trata de un error, contáctanos con el número de pedido y te ayudamos.',
    ],
    'order_expired_without_payment' => [
        'subject' => 'Tu reserva ha caducado (nº :code)',
        'greeting' => 'Hola,',
        'intro' => 'Tu reserva :code ha caducado porque no se completó el pago en el tiempo previsto.',
        'no_charge' => 'No se ha cobrado nada en tu tarjeta. La plaza se ha liberado para otras personas.',
        'retry' => '¡Nos encantaría verte por aquí! Vuelve cuando quieras y reserva tu sitio sin prisa.',
        'action' => 'Hacer una nueva reserva',
        'contact' => 'Si crees que sí pagaste y no recibiste confirmación, contáctanos con el número de pedido — lo revisaremos en el banco.',
    ],
    // Cancelación. Texto NEUTRO sobre el reembolso (#139): cancelar y devolver son
    // operaciones independientes. Si procede devolución, el cliente recibe el
    // email `order_refunded` por separado (con importe y plazo); si no procede
    // (canje en parque por entradas físicas, acuerdo en persona, etc.), ese email
    // no llega. Aquí solo se confirma la cancelación.
    'order_cancelled' => [
        'subject' => 'Tu reserva ha sido cancelada (nº :code)',
        'greeting' => 'Hola,',
        'intro' => 'Hemos cancelado tu reserva :code.',
        'next_steps' => 'Si la cancelación lleva asociado un reembolso, recibirás un correo aparte con el importe devuelto y el plazo bancario. Cualquier acuerdo en persona con nuestro equipo (canje por entradas, reagendar, etc.) queda registrado en tu reserva.',
        'action' => 'Ver mis reservas',
        'contact' => 'Si tienes cualquier duda, escríbenos con el número de pedido.',
    ],
    // Reembolso. Mensaje firme: importe + plazo bancario + tarjeta cargada.
    // Cuando la acción del panel también cancela el pedido (`alsoCancelled=true`),
    // se inserta la línea `also_cancelled` sin reenviar `order_cancelled` aparte
    // (un único correo es más claro para el cliente).
    'order_refunded' => [
        'subject' => 'Te hemos devuelto el importe de tu reserva (nº :code)',
        'greeting' => 'Hola,',
        'intro' => 'Hemos procesado la devolución de la reserva :code.',
        'amount' => 'Importe devuelto: :amount €',
        'also_cancelled' => 'Tu reserva también queda cancelada.',
        'when' => 'Verás el reintegro en la tarjeta con la que pagaste en los próximos 3-5 días laborables (depende de tu banco).',
        'action' => 'Ver mis reservas',
        'contact' => 'Si en una semana no ves el reintegro, escríbenos con el número de pedido.',
    ],
    // Cancelación de un producto suelto del pedido (sub-fase 7.2e.1bis,
    // decisión #154). Texto NEUTRO sobre el reembolso — la acción de cancelar
    // un item es ortogonal a la devolución (alineación con el patrón del
    // Order completo de 7.2b/#139). Si procede refund, llega `order_item_refunded`.
    'order_item_cancelled' => [
        'subject' => 'Hemos cancelado «:product» de tu reserva (nº :code)',
        'greeting' => 'Hola,',
        'intro' => 'Hemos cancelado «:product» de tu reserva :code. El resto de la reserva sigue activo.',
        'cascaded_addons' => 'Sus :count complemento(s) también quedan cancelados (van con el producto principal).',
        'next_steps' => 'Si la cancelación lleva asociado un reembolso, recibirás un correo aparte con el importe devuelto y el plazo bancario. Cualquier acuerdo en persona con nuestro equipo (canje, reagendar, etc.) queda registrado en tu reserva.',
        'action' => 'Ver mis reservas',
        'contact' => 'Si tienes cualquier duda, escríbenos con el número de pedido.',
        'product_fallback' => 'producto :id',
    ],

    // Reembolso parcial ligado a un producto concreto del pedido (sub-fase 7.2e).
    // Cita explícitamente el producto y el importe para evitar dudas sobre qué
    // se devolvió. Si la acción también canceló el item se añade la línea.
    'order_item_refunded' => [
        'subject' => 'Te hemos devuelto un importe de tu reserva (nº :code)',
        'greeting' => 'Hola,',
        'intro' => 'Hemos procesado una devolución parcial de tu reserva :code.',
        'amount' => 'Importe devuelto de «:product»: :amount €',
        'also_cancelled' => '«:product» queda cancelado.',
        'when' => 'Verás el reintegro en la tarjeta con la que pagaste en los próximos 3-5 días laborables (depende de tu banco).',
        'action' => 'Ver mis reservas',
        'contact' => 'Si en una semana no ves el reintegro, escríbenos con el número de pedido.',
        'product_fallback' => 'producto :id',
    ],
    // Modificación de un producto del pedido (sub-fase 7.2e). Polivalente: el
    // caller pasa solo las líneas relevantes al cambio efectuado.
    'order_item_modified' => [
        'subject' => 'Cambios en «:product» de tu reserva (nº :code)',
        'greeting' => 'Hola,',
        'intro' => 'Hemos actualizado tu reserva :code. Esto es lo que cambia en «:product»:',
        'slot_change' => 'Nueva fecha y hora: :old → :new',
        'quantity_change' => 'Cantidad: :old → :new',
        'product_change' => 'Producto: :old → :new',
        'event_data_change' => 'Datos del evento actualizados.',
        'addon_change' => 'Complementos actualizados.',
        'extra_due' => 'Pendiente de pago al llegar al parque: :amount €',
        'refunded' => 'Importe devuelto a tu tarjeta: :amount € (3-5 días laborables).',
        // `#155`: la BAJADA también es dinero — mismo vocabulario que la pantalla («pendiente de
        // devolverte») para que el email y «Mis reservas» digan lo mismo.
        'reduction_pending_refund' => 'Este cambio deja :amount € pendientes de devolverte. Lo verás en «Mis reservas» y te avisaremos por email cuando procesemos la devolución.',
        'reduction_gate_credit' => 'Con el nuevo precio pagarás :amount € menos al llegar al parque.',
        'action' => 'Ver mis reservas',
        'contact' => 'Si tienes cualquier duda, escríbenos con el número de pedido.',
        'product_fallback' => 'producto :id',
    ],
];
