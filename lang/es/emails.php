<?php

return [
    /*
     * EL RESGUARDO — las cuatro cosas que se buscan al abrir un correo (`#503`; artboard
     * `Correos PJP` 1a). Compartido: es el mismo resguardo del formulario de invitados y del
     * justificante, así que sus rótulos viven una sola vez.
     */
    'slip' => [
        'when' => 'Cuándo',
        'what' => 'Qué',
        'where' => 'Dónde',
        'order' => 'Pedido',
    ],

    'customer_account_created' => [
        'subject' => 'Tu cuenta ya está lista',
        'preheader' => 'Dentro tienes tu contraseña temporal: cámbiala la primera vez que entres.',
        'greeting' => '¡Hola!',
        'intro' => 'El equipo de :park ha creado una cuenta para ti para que puedas consultar tus reservas.',
        'email_label' => 'Email',
        'password_label' => 'Contraseña temporal',
        'action' => 'Iniciar sesión',
        'recommend_change' => 'Por seguridad, te recomendamos cambiar la contraseña en cuanto entres, desde «Mi cuenta».',
        'ignore' => 'Si no esperabas este correo, puedes ignorarlo.',
        'badge' => 'Cuenta creada',
        'headline' => 'Tu cuenta ya está lista',
    ],
    /*
     * LOS DOS CORREOS DEL FRAMEWORK (`#508`) — el enlace de restablecer contraseña y el de verificar
     * el correo de una cuenta nueva. No estaban en el inventario de 23 porque el artboard contó
     * carpetas y éstos salían de `Illuminate\Auth\Notifications`.
     *
     * ⚠️ Aquí SOLO viven las tres piezas del molde —chapa, titular y línea de adelanto— más el
     * asunto. El CUERPO sigue saliendo de las cadenas del framework, ya traducidas en `lang/es.json`
     * y `lang/fr.json`: esta tanda no cambia ni una palabra de lo que el cliente venía leyendo.
     */
    'password_reset' => [
        'subject' => 'Restablece tu contraseña',
        'preheader' => 'Si no lo has pedido tú no tienes que hacer nada: el enlace caduca solo.',
        'badge' => 'Falta un paso',
        'headline' => 'Restablece tu contraseña',
    ],
    'verify_email' => [
        'subject' => 'Verifica tu email',
        'preheader' => 'Un clic y tu cuenta queda lista. Si no la has creado tú, ignora este correo.',
        'badge' => 'Falta confirmar',
        'headline' => 'Verifica tu email',
    ],
    'verify_purchase' => [
        'subject' => 'Confirma tu email · :code',
        'preheader' => 'Tu plaza está apartada mientras confirmas. Un clic y sigues con el pago.',
        'greeting' => '¡Hola!',
        'intro' => 'Casi listo. Tu reserva (nº :code) está apartada. Confirma tu email para continuar con el pago.',
        'action' => 'Confirmar mi email',
        'hold_note' => 'Tu plaza está reservada provisionalmente. Si no confirmas a tiempo, podría liberarse.',
        'outro' => 'Si no has hecho esta reserva, puedes ignorar este correo.',
        'badge' => 'Falta confirmar',
        'headline' => 'Confirma tu email para seguir',
    ],
    'verify_pending_email' => [
        'subject' => 'Confirma tu nuevo email',
        'preheader' => 'El enlace caduca en 60 minutos. Si no has pedido el cambio, ignora este correo.',
        'greeting' => '¡Hola!',
        'intro' => 'Has pedido cambiar el email de tu cuenta a este. Para confirmarlo, pulsa el botón.',
        'action' => 'Confirmar mi nuevo email',
        'expires' => 'Este enlace caduca en 60 minutos.',
        'ignore' => 'Si no has pedido este cambio, puedes ignorar este correo: tu cuenta seguirá usando el email anterior.',
        'badge' => 'Falta confirmar',
        'headline' => 'Confirma tu nuevo email',
    ],
    'email_change_requested' => [
        'subject' => 'Se ha pedido cambiar tu email',
        'preheader' => 'Si no has sido tú, tu cuenta sigue con este correo. Dentro te decimos qué hacer.',
        'greeting' => '¡Hola!',
        'intro' => 'Alguien ha solicitado cambiar el email de tu cuenta a :new.',
        'it_was_me' => 'Si has sido tú, confirma desde el enlace que hemos enviado al nuevo email.',
        'it_was_not_me' => 'Si NO has sido tú, ignora ese correo: tu cuenta seguirá usando este email. Te recomendamos cambiar la contraseña.',
        'badge' => 'Revisa esto',
        'headline' => 'Se ha pedido cambiar tu email',
    ],
    'email_change_completed' => [
        'subject' => 'El email de tu cuenta ha cambiado',
        'preheader' => 'A partir de ahora entras con el correo nuevo. Este buzón ya no recibe avisos.',
        'greeting' => '¡Hola!',
        'intro' => 'Confirmamos que el email de tu cuenta es ahora :new. Este buzón (el anterior) ya no recibirá comunicaciones de :park.',
        'what_means' => 'A partir de ahora, debes iniciar sesión con el nuevo email.',
        'it_was_not_me' => 'Si NO has hecho este cambio, contacta con nosotros INMEDIATAMENTE: tu cuenta puede haber sido comprometida.',
        'badge' => 'Email cambiado',
        'headline' => 'El email de tu cuenta ha cambiado',
    ],
    'order_confirmation' => [
        'subject' => 'Reserva confirmada · :day · :code',
        'subject_no_date' => 'Reserva confirmada · :code',
        'preheader' => 'Dentro está tu resguardo y tu QR. Calcetines antideslizantes y llegar 10 min antes.',
        'badge' => 'Reserva confirmada',
        'headline' => 'Nos vemos el :day',
        'headline_no_date' => 'Reserva confirmada',
        'greeting' => '¡Hola!',
        'intro' => '¡Pago recibido y reserva confirmada! Ya está todo listo para tu visita. Tu nº de pedido es :code — guárdalo, te lo pedirán en el parque.',
        'paid_at' => 'Fecha del cobro: :when',
        'notice_title' => 'Antes de venir',
        'notice_body' => 'Calcetines antideslizantes obligatorios (se compran allí), y el descargo de responsabilidad se firma una vez, desde el móvil. Ven 10 minutos antes.',
        // Fase 6 · subsistema A: el QR del cliente va adjunto (PNG).
        'card_attached' => 'Adjuntamos tu QR (carne-qr.png): enséñalo en la entrada y te atenderemos al momento. Es personal y no caduca; si lo pierdes, puedes renovarlo desde tu cuenta.',
        'action' => 'Ver mis reservas',
        'paid_confirmation' => 'Te esperamos en la fecha y hora que elegiste. Puedes ver todos los detalles desde «Mis reservas», en tu cuenta.',
        'paid_confirmation_guest_form' => 'Te esperamos en la fecha y hora que elegiste. En breve te pediremos por email los datos de los invitados; también puedes completarlos cuando quieras desde «Mis reservas», en tu cuenta.',
        'outro' => '¿Alguna duda antes de tu visita? Escríbenos, estamos encantados de ayudarte.',
    ],
    'guardian_authorization' => [
        'subject' => 'Justificante firmado',
        'preheader' => 'Adjuntamos tu copia en PDF, con el texto completo y la hora de la firma.',
        'greeting' => 'Hola,',
        'intro' => 'Acabas de firmar la autorización de :name. Aquí tienes tu copia.',
        'booking' => 'Reserva: :code.',
        'attached' => 'El documento adjunto es el registro completo de lo que aceptaste, con el texto íntegro, la fecha y la hora.',
        'not_verified' => 'Los datos que escribiste los declaraste tú y no los hemos comprobado con ningún documento. Si ves algún error, avisa a la persona que hizo la reserva.',
        'salutation' => 'Gracias.',
        'badge' => 'Justificante firmado',
        'headline' => 'Tu justificante está firmado',
    ],
    'guest_form' => [
        'subject' => 'Datos de los invitados · :day · :code',
        'subject_no_date' => 'Datos de los invitados · :code',
        'preheader' => 'Necesitamos nombre y edad de cada invitado. Puedes rellenarlo ahora o más tarde.',
        'greeting' => '¡Hola!',
        'intro' => '¡Tu reserva «:product» (nº :code) está confirmada! Para dejarlo todo listo y que el día sea perfecto, cuéntanos quién viene: completa los datos de cada invitado.',
        'body' => 'Puedes rellenarlo ahora o más adelante, y editarlo cuando quieras hasta el día del evento.',
        'extras' => 'Y si quieres, ahí mismo puedes añadir extras para la fiesta: bebidas, algo de picar… Se pagan en el parque el día del evento.',
        'action' => 'Rellenar el formulario de reserva',
        'outro' => 'También puedes hacerlo desde «Mis reservas». ¡Gracias!',
        'badge' => 'Nos falta un dato',
        'headline' => '¿Quién viene a la fiesta?',
        'notice_title' => 'Y si quieres, extras',
        // ── CON INVITACIÓN DIGITAL (T7·1, `specs/celebracion-e-invitacion.md` §4.9) ───────────────
        // ⚠️ El asunto, la línea de adelanto, la chapa y el titular NO tienen versión: el censo de la
        // bandeja lee el grupo de una cadena literal, y uno elegido por variable se quedaría fuera.
        // Lo que cambia es el cuerpo y la llamada — repartir el enlace, no teclear veinte nombres.
        'intro_invite' => '¡Tu reserva «:product» (nº :code) está confirmada! Y no tienes que rellenarlo todo tú: reparte la invitación y que cada familia te diga si viene y cómo se llama su hijo.',
        'body_invite' => 'Lo que contesten aparece en tu formulario y tú decides qué apuntas. Puedes seguir editándolo cuando quieras hasta el día de la fiesta.',
        'action_invite' => 'Compartir la invitación',
        // ⚠️ «Rellenarlo yo» es una FRASE y no otro botón: el destino es la misma página — el bloque
        // de la invitación arriba y el formulario justo debajo.
        'outro_invite' => 'Si prefieres rellenarlo a mano, es la misma página: el formulario está justo debajo de la invitación. También puedes entrar desde «Mis reservas». ¡Gracias!',
    ],
    'order_after_expiration' => [
        'subject' => 'Tu pago llegó bien · :code',
        'preheader' => 'Te llamamos en 24 h para reagendar tu visita o devolverte el importe.',
        'greeting' => 'Hola,',
        'intro' => 'Buenas noticias: tu pago de la reserva :code ha llegado correctamente. Nos entró con algo de retraso, así que no quedó registrado a tiempo de forma automática y lo estamos gestionando a mano.',
        'amount' => 'Importe cobrado: :amount €',
        'next_steps' => 'Nuestro equipo lo está revisando ahora mismo: nos pondremos en contacto contigo en las próximas 24 horas para reagendar tu visita o, si lo prefieres, devolverte el importe.',
        'contact' => 'Si necesitas hablar antes con nosotros, responde a este correo o llámanos. Lamentamos las molestias.',
        'badge' => 'Lo estamos revisando',
        'headline' => 'Tu pago llegó bien',
    ],
    'order_declined' => [
        'subject' => 'El pago no ha salido · :code',
        'preheader' => 'No se ha cobrado nada. La plaza sigue apartada unos minutos por si lo reintentas.',
        'greeting' => 'Hola,',
        'intro' => 'No hemos podido completar el pago de tu reserva :code.',
        'no_charge' => 'Tranqui: no se ha cobrado nada en tu tarjeta. Mantenemos tu reserva unos minutos más por si quieres volver a intentarlo; si no, la plaza se liberará para otras personas.',
        'reason_prefix' => 'Motivo:',
        'action' => 'Reintentar el pago',
        'contact' => 'Si crees que se trata de un error, contáctanos con el número de pedido y te ayudamos.',
        'badge' => 'No se ha cobrado nada',
        'headline' => 'El pago no ha salido',
        'notice_title' => 'Motivo',
    ],
    'order_expired_without_payment' => [
        'subject' => 'Reserva caducada · :code',
        'preheader' => 'No se ha cobrado nada y la plaza ha quedado libre. Puedes reservar cuando quieras.',
        'greeting' => 'Hola,',
        'intro' => 'Tu reserva :code ha caducado porque no se completó el pago en el tiempo previsto.',
        'no_charge' => 'No se ha cobrado nada en tu tarjeta. La plaza se ha liberado para otras personas.',
        'retry' => '¡Nos encantaría verte por aquí! Vuelve cuando quieras y reserva tu sitio sin prisa.',
        'action' => 'Hacer una nueva reserva',
        'contact' => 'Si crees que sí pagaste y no recibiste confirmación, contáctanos con el número de pedido — lo revisaremos en el banco.',
        'badge' => 'Reserva caducada',
        'headline' => 'La reserva ha caducado',
    ],
    // Cancelación. Texto NEUTRO sobre el reembolso (#139): cancelar y devolver son
    // operaciones independientes. Si procede devolución, el cliente recibe el
    // email `order_refunded` por separado (con importe y plazo); si no procede
    // (canje en parque por entradas físicas, acuerdo en persona, etc.), ese email
    // no llega. Aquí solo se confirma la cancelación.
    'order_cancelled' => [
        'subject' => 'Reserva cancelada · :code',
        'preheader' => 'Si hay dinero que devolver, te llega en un correo aparte con importe y plazo.',
        'greeting' => 'Hola,',
        'intro' => 'Hemos cancelado tu reserva :code.',
        'next_steps' => 'Si la cancelación lleva asociado un reembolso, recibirás un correo aparte con el importe devuelto y el plazo bancario. Cualquier acuerdo en persona con nuestro equipo (canje por entradas, reagendar, etc.) queda registrado en tu reserva.',
        'action' => 'Ver mis reservas',
        'contact' => 'Si tienes cualquier duda, escríbenos con el número de pedido.',
        'badge' => 'Reserva cancelada',
        'headline' => 'Tu reserva ha sido cancelada',
    ],
    // Reembolso. Mensaje firme: importe + plazo bancario + tarjeta cargada.
    // Cuando la acción del panel también cancela el pedido (`alsoCancelled=true`),
    // se inserta la línea `also_cancelled` sin reenviar `order_cancelled` aparte
    // (un único correo es más claro para el cliente).
    'order_refunded' => [
        'subject' => 'Devolución hecha · :code',
        'preheader' => 'Dentro tienes el importe y por dónde te llega. Este correo te sirve de justificante.',
        'greeting' => 'Hola,',
        'intro' => 'Hemos procesado la devolución de la reserva :code.',
        'amount' => 'Importe devuelto: :amount €',
        'also_cancelled' => 'Tu reserva también queda cancelada.',
        'when' => 'Verás el reintegro en la tarjeta con la que pagaste en los próximos 3-5 días laborables (depende de tu banco).',
        // T5 (`cumple-mixto.md` §25.5, Q3): reembolso registrado como MANUAL — devuelto fuera de
        // la pasarela (el circuito de parque de §20.5); la promesa de tarjeta aquí sería falsa.
        'when_manual' => 'Este importe se te ha devuelto en el parque. Este correo te sirve de justificante.',
        'action' => 'Ver mis reservas',
        'contact' => 'Si en una semana no ves el reintegro, escríbenos con el número de pedido.',
        'badge' => 'Devolución hecha',
        'headline' => 'Te hemos devuelto el importe',
    ],
    // Cancelación de un producto suelto del pedido (sub-fase 7.2e.1bis,
    // decisión #154). Texto NEUTRO sobre el reembolso — la acción de cancelar
    // un item es ortogonal a la devolución (alineación con el patrón del
    // Order completo de 7.2b/#139). Si procede refund, llega `order_item_refunded`.
    'order_item_cancelled' => [
        'subject' => 'Producto cancelado · :product · :code',
        'preheader' => 'El resto de tu reserva sigue en pie. Dentro ves qué queda y para qué día.',
        'greeting' => 'Hola,',
        'intro' => 'Hemos cancelado «:product» de tu reserva :code. El resto de la reserva sigue activo.',
        'cascaded_addons' => 'Sus :count complemento(s) también quedan cancelados (van con el producto principal).',
        'next_steps' => 'Si la cancelación lleva asociado un reembolso, recibirás un correo aparte con el importe devuelto y el plazo bancario. Cualquier acuerdo en persona con nuestro equipo (canje, reagendar, etc.) queda registrado en tu reserva.',
        'action' => 'Ver mis reservas',
        'contact' => 'Si tienes cualquier duda, escríbenos con el número de pedido.',
        'product_fallback' => 'producto :id',
        'badge' => 'Producto cancelado',
        'headline' => 'Hemos cancelado una parte de tu reserva',
    ],

    // Reembolso parcial ligado a un producto concreto del pedido (sub-fase 7.2e).
    // Cita explícitamente el producto y el importe para evitar dudas sobre qué
    // se devolvió. Si la acción también canceló el item se añade la línea.
    'order_item_refunded' => [
        'subject' => 'Devolución hecha · :product · :code',
        'preheader' => 'Dentro tienes qué se ha devuelto, cuánto y por dónde te llega.',
        'greeting' => 'Hola,',
        'intro' => 'Hemos procesado una devolución parcial de tu reserva :code.',
        'amount' => 'Importe devuelto de «:product»: :amount €',
        'also_cancelled' => '«:product» queda cancelado.',
        'when' => 'Verás el reintegro en la tarjeta con la que pagaste en los próximos 3-5 días laborables (depende de tu banco).',
        // T5 (`cumple-mixto.md` §25.5, Q3): reembolso registrado como MANUAL — devuelto fuera de
        // la pasarela (el circuito de parque de §20.5); la promesa de tarjeta aquí sería falsa.
        'when_manual' => 'Este importe se te ha devuelto en el parque. Este correo te sirve de justificante.',
        'action' => 'Ver mis reservas',
        'contact' => 'Si en una semana no ves el reintegro, escríbenos con el número de pedido.',
        'product_fallback' => 'producto :id',
        'badge' => 'Devolución hecha',
        'headline' => 'Te hemos devuelto un importe',
    ],
    // Modificación de un producto del pedido (sub-fase 7.2e). Polivalente: el
    // caller pasa solo las líneas relevantes al cambio efectuado.
    // Suplemento de fiesta MIXTA (`docs/specs/cumple-mixto.md` §12). Se manda cuando el IMPORTE
    // cambia, en las dos direcciones: la bajada también es dinero (`#155`).
    'mixed_party_surcharge' => [
        'subject' => 'Cambia tu importe en el parque · :code',
        'preheader' => 'No hay que pagar nada ahora: se ajusta en el parque el día de la fiesta.',
        'greeting' => 'Hola,',
        'intro' => 'Has actualizado las edades de los invitados, y algunos entran en un tramo de edad distinto del que reservaste.',
        'intro_by_park' => 'Hemos actualizado tu reserva, y algunos invitados entran en un tramo de edad distinto del que reservaste.',
        'added' => 'Suplemento por invitados de otro tramo de edad: :amount €.',
        'updated' => 'El suplemento por invitados de otro tramo de edad pasa de :old € a :new €.',
        'removed' => 'Ya no hay suplemento que abonar: todos los invitados entran en el pack reservado.',
        // T4 (`specs/cumple-mixto.md` §24.5): la voz del DESCUENTO — el espejo del suplemento.
        'credit_added' => 'Descuento por invitados de un tramo más económico: :amount €. Se descuenta de lo que pagarás en el parque.',
        'credit_updated' => 'El descuento por invitados de un tramo más económico pasa de :old € a :new €.',
        'credit_removed' => 'El descuento anterior ya no corresponde con las edades actuales y se ha retirado.',
        'changed_direction' => 'Tu importe por edades pasa de :old a :new.',
        'amount_surcharge' => ':amount € de suplemento',
        'amount_discount' => ':amount € de descuento',
        'where_to_pay' => 'Se abona en el parque el día de la fiesta, junto con el resto pendiente.',
        'where_discounted' => 'Se descuenta de lo que pagarás en el parque el día de la fiesta; si ya lo tenías todo pagado, se te devuelve allí ese día.',
        'editable' => 'Puedes seguir editando los datos de los invitados hasta el día del evento; si cambian las edades, este importe se ajusta solo.',
        'notice_title' => 'Lo que cambia',
        'badge' => 'Cambia lo que se abona',
        'headline' => 'Tu importe en el parque ha cambiado',
    ],

    'postform_addons' => [
        'subject' => 'Extras actualizados · :code',
        'preheader' => 'Dentro tienes qué has añadido o quitado y lo que se paga en el parque.',
        'greeting' => 'Hola,',
        'intro' => 'Hemos anotado los extras de tu reserva :code. Esto es lo que cambia:',
        'added' => 'Añadido: :name × :qty.',
        'updated' => ':name: de :old a :new.',
        'removed' => 'Retirado: :name.',
        'delta_up' => 'Se suman :amount a lo que abonarás en el parque.',
        'delta_down' => 'Se restan :amount de lo que abonarás en el parque.',
        'where_to_pay' => 'Los extras se pagan en el parque el día de la fiesta, junto con el resto pendiente.',
        'editable' => 'Puedes cambiarlos desde el mismo formulario hasta poco antes de la fiesta. Si no has sido tú, llámanos.',
        'badge' => 'Extras actualizados',
        'headline' => 'Tus extras para la fiesta',
    ],

    'order_item_modified' => [
        'subject' => 'Reserva modificada · :product · :code',
        'preheader' => 'Dentro tienes qué ha cambiado exactamente y cómo queda tu reserva.',
        'greeting' => 'Hola,',
        'intro' => 'Hemos actualizado tu reserva :code. Esto es lo que cambia en «:product»:',
        'slot_change' => 'Nueva fecha y hora: :old → :new',
        'quantity_change' => 'Cantidad: :old → :new',
        'product_change' => 'Producto: :old → :new',
        'event_data_change' => 'Datos del evento actualizados.',
        'addon_change' => 'Complementos actualizados.',
        // `#155`: la BAJADA también es dinero — mismo vocabulario que la pantalla («pendiente de
        // devolverte»). T5 (`cumple-mixto.md` §25.5): sin prometer canal ni correo — el importe
        // puede volver por banco o liquidarse en el parque (§20.5), y el registro del manual es
        // opcional. El puntero es «Mis pedidos»: «Mis reservas» no enseña importes desde `#130`.
        'action' => 'Ver mis reservas',
        'contact' => 'Si tienes cualquier duda, escríbenos con el número de pedido.',
        'product_fallback' => 'producto :id',
        'badge' => 'Reserva modificada',
        'headline' => 'Hay cambios en tu reserva',
    ],
    /*
     * El enlace del JUSTIFICANTE de un menor invitado, al que RESERVÓ
     * (`specs/waiver-por-reserva.md` §12.3, T7).
     *
     * ⚠️ El texto tiene que decir **que el enlace es para REPARTIR** y que quien lo abre no ve lo que
     * han escrito los demás. Sin esa frase, un responsable prudente no lo reenvía —parece su enlace
     * privado— y la feature se queda parada en su bandeja.
     */
    'guardian_request' => [
        'subject' => 'Autorización de los menores · :code',
        'preheader' => 'Reenvíaselo a los padres: cada uno rellena lo suyo sin ver los datos de los demás.',
        'greeting' => '¡Hola!',
        'intro' => 'En tu reserva «:product» (nº :code) viene algún menor que no está a tu cargo. Para que pueda entrar, su padre, madre o tutor tiene que firmar una autorización.',
        'body' => 'Es un momento: rellena sus datos, los del menor, acepta el descargo de responsabilidad y listo. No hace falta tener cuenta.',
        'action' => 'Abrir la autorización',
        'share' => 'Pásales este enlace a los padres o tutores. Vale para todos: cada uno rellena SUS datos y no ve los de los demás.',
        'outro' => 'El enlace caduca poco después de la visita. Si necesitas otro, dínoslo.',
        'badge' => 'Para pasárselo a los padres',
        'headline' => 'Un enlace para todos los padres',
        'notice_title' => 'Pásales este enlace',
    ],
];
