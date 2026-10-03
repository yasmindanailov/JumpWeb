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

    /*
     * EL PIE de todos los correos (la R1a, `correos-rediseno.md` §4.1.1; brief: «dirección, horario de hoy, teléfono,
     * WhatsApp y correo»). El horario lleva su DÍA: el correo se puede leer mañana, y «hoy abrimos» sin fecha dejaría de
     * ser verdad.
     */
    'pie' => [
        'hoy_abierto' => 'Hoy, :dia, abrimos de :desde a :hasta.',
        'hoy_cerrado' => 'Hoy, :dia, no abrimos.',
    ],

    // El pie de los COMERCIALES (la C1, `#920`): por qué lo recibe y la baja. Lo legal no se edita desde el panel (`porque`).
    'comercial' => [
        'porque' => 'Recibes este correo porque marcaste la casilla de novedades. Si no quieres más correos como este, [toca aquí](baja).',
    ],

    'customer_account_created' => [
        'subject' => 'Tu cuenta ya está lista',
        'preheader' => 'Para entrar solo necesitas tu email: te enviaremos un código cada vez.',
        'greeting' => '¡Hola!',
        'intro' => 'El equipo de :park ha creado una cuenta para ti para que puedas consultar tus reservas.',
        'email_label' => 'Email',
        // Sin contraseña desde la A5 (`#869`): se entra con el correo y un código, como todos.
        'how_to_enter' => 'Para entrar, escribe tu email y te enviaremos un código de un solo uso. No necesitas contraseña.',
        'action' => 'Iniciar sesión',
        'ignore' => 'Si no esperabas este correo, puedes ignorarlo.',
        'badge' => 'Cuenta creada',
        'headline' => 'Tu cuenta ya está lista',
    ],
    // La contraseña del PANEL (A5a de `specs/acceso-con-codigo.md` §4.12, `#870`): la envía un administrador desde la ficha
    // de una cuenta del panel. Va al EQUIPO, no a un cliente: sin UTM y fuera de lo editable.
    'panel_password_link' => [
        'subject' => 'Tu contraseña del panel',
        'preheader' => 'Si no lo esperabas, no tienes que hacer nada: el enlace caduca solo.',
        'badge' => 'Acceso al panel',
        'headline' => 'Crea tu contraseña del panel',
        'intro' => 'Te enviamos este enlace para que crees la contraseña con la que entras en el panel de :park.',
        'action' => 'Crear la contraseña',
        'expires' => 'El enlace vale :minutes minutos y una sola vez.',
        'ignore' => 'Si no lo esperabas, ignora este correo: tu contraseña no cambia.',
    ],
    /*
     * EL CORREO DEL FRAMEWORK (`#508`): el de verificar el correo de una cuenta nueva (eran dos; el de restablecer la
     * contraseña se retiró en la A5, `#869`). No estaba en el inventario de 23 porque el artboard contó carpetas y éste
     * salía de `Illuminate\Auth\Notifications`.
     *
     * ⚠️ Aquí SOLO viven las tres piezas del molde —chapa, titular y línea de adelanto— más el asunto. El CUERPO sigue
     * saliendo de las cadenas del framework, ya traducidas en `lang/es.json` y `lang/fr.json`.
     */
    'verify_email' => [
        'subject' => 'Verifica tu email',
        'preheader' => 'Un clic y tu cuenta queda lista. Si no la has creado tú, ignora este correo.',
        'badge' => 'Falta confirmar',
        'headline' => 'Verifica tu email',
    ],
    /*
     * EL CÓDIGO PARA ENTRAR (A1 de `specs/acceso-con-codigo.md`, `#848`): solo el código, sin enlace. Va en el asunto
     * —se lee en el aviso del móvil sin abrir el correo— y en su BLOQUE, bajo el titular, sin chapa (la R1c, el 8 del zip
     * (6)): `code_label` es su etiqueta y `validity`, su nota. La línea de adelanto no lleva la cifra de los minutos: el
     * molde no le pasa reemplazos (la dice el cuerpo, desde `LoginCodes::TTL_MINUTES`).
     */
    'login_code' => [
        'subject' => ':code es tu código para entrar',
        'preheader' => 'Escríbelo donde lo pediste. Si no lo has pedido tú, ignora este correo.',
        'headline' => 'Tu código para entrar',
        'code_label' => 'Código de :digits cifras',
        'validity' => 'Escríbelo en la pantalla donde lo pediste. Vale :minutes minutos y una sola vez.',
        'ignore' => 'Si no lo has pedido tú, ignora este correo: sin este código nadie puede entrar en tu cuenta.',
    ],
    /*
     * EL CÓDIGO PARA CONFIRMAR una acción sensible (A2a, `#855`): dice PARA QUÉ es, porque solo se pide con la sesión
     * abierta —quien lo recibe sin haberlo pedido sabe que alguien la tiene—.
     */
    'confirmation_code' => [
        'subject' => ':code es tu código para confirmar',
        'preheader' => 'Escríbelo donde lo pediste. Si no lo has pedido tú, no se lo des a nadie.',
        'headline' => 'Tu código para confirmar',
        'code_label' => 'Código de :digits cifras',
        'for' => 'Es para :action.',
        'actions' => [
            'delete_account' => 'borrar tu cuenta',
            'change_email' => 'cambiar el correo de tu cuenta',
            'unlink_google' => 'desvincular tu cuenta de Google',
            'close_sessions' => 'cerrar la sesión en tus otros dispositivos',
        ],
        'validity' => 'Vale :minutes minutos y una sola vez.',
        'ignore' => 'Si no lo has pedido tú, no se lo des a nadie: lo ha pedido alguien con tu cuenta abierta en otro dispositivo.',
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
    // De la versión con ENLACE (retirada en la A5, `#869`) queda solo «si no fuiste tú», que el correo del código reutiliza.
    'verify_pending_email' => [
        'ignore' => 'Si no has pedido este cambio, puedes ignorar este correo: tu cuenta seguirá usando el email anterior.',
    ],
    // El mismo correo, con el CÓDIGO (A2b, `#856`), en su bloque (la R1c). Sin botón desde la A5.
    'verify_pending_email_code' => [
        'subject' => ':code es el código de tu nuevo email',
        'preheader' => 'Escríbelo donde pediste el cambio. Si no lo has pedido tú, ignora este correo.',
        'headline' => 'Confirma tu nuevo email',
        'code_label' => 'Código de :digits cifras',
        'intro' => 'Has pedido cambiar el email de tu cuenta a este. Escribe el código en la pantalla donde lo pediste.',
        'validity' => 'El código vale :minutes minutos y una sola vez.',
    ],
    'email_change_requested' => [
        'subject' => 'Se ha pedido cambiar tu email',
        'preheader' => 'Si no has sido tú, tu cuenta sigue con este correo. Dentro te decimos qué hacer.',
        'greeting' => '¡Hola!',
        'intro' => 'Alguien ha solicitado cambiar el email de tu cuenta a :new.',
        'it_was_me' => 'Si has sido tú, confirma con el código que hemos enviado al nuevo email.',
        'it_was_not_me' => 'Si NO has sido tú, ignora ese correo: tu cuenta seguirá usando este email. Por si acaso, cierra la sesión en tus otros dispositivos desde «Mi cuenta».',
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
    /*
     * LA RESERVA HECHA (la R2b, `specs/correos-rediseno.md` §4.3; el 1, el 1b y el 2 del diseño): un correo con TRES caras
     * (`MailReservation::cara()`). Su cabecera, en dos grupos —el de unas entradas o un grupo, y el de una fiesta, que no lleva
     * chapa—; su cuerpo, en `reserva` (abajo), que comparten y que usará la víspera.
     */
    'reservado' => [
        'subject' => 'Reservado: :day a las :time · :product',
        // Con varias reservas, el número: un día y una hora solos esconderían las otras (`#506`).
        'subject_varias' => 'Reservado: :count reservas · nº :code',
        // ⚠️ Sin datos variables y sin repetir el asunto (`#506`).
        'preheader' => 'Tu QR va dentro, con lo que hay que saber antes de venir.',
        'badge' => 'Reservado',
        'headline' => '¡Nos vemos el :day!',
        'headline_grupo' => '¡Os esperamos el :day!',
        'headline_varias' => '¡Reservado!',
    ],
    'fiesta_reservada' => [
        'subject' => 'Fiesta reservada: :day a las :time · :product',
        'subject_nombre' => 'Fiesta reservada: :day a las :time · el cumple de :name',
        'preheader' => 'Lo que queda antes de la fiesta, con sus fechas.',
        'headline' => '¡Fiesta reservada!',
    ],
    'reserva' => [
        // El resguardo: el número, el precio, las filas de dinero (las del libro de la reserva) y sus dos enlaces.
        'number_label' => 'Nº :code',
        'paid_label' => ':amount pagados',
        'per_person_label' => ':amount por persona',
        'deposit_label' => 'Señal pagada',
        'rest_label' => 'El día de la visita',
        'rest_party_label' => 'El día de la fiesta',
        'directions_label' => 'Cómo llegar',
        'calendar_label' => 'Añadir al calendario',
        // El QR, dentro (y adjunto): los textos de Mi cuenta.
        'qr_title' => 'Enséñalo en la puerta: ahí está todo.',
        'qr_dictate_label' => 'Si la cámara falla, dicta este código:',
        'action_qr' => 'Abrir Mi QR',
        // «Antes de venir»: quién firma (`#875`), con sus enlaces por nombre; y la hora.
        'before_title' => 'Antes de venir',
        'minors' => '**Menores a tu cargo:** [añádelos y firma por ellos](menores), si aún no están en tu cuenta. Un minuto.',
        'adults' => '**Otros adultos:** cada uno firma el suyo, desde casa o en el mostrador.',
        'arrival' => 'Tu tiempo empieza a las :time: llegad unos minutos antes.',
        'arrival_group' => 'Llegad unos minutos antes de las :time.',
        // Los pasos de una fiesta: el formulario de invitados y la invitación.
        'steps_title' => 'Ahora, dos cosas',
        'steps_one_title' => 'Ahora, una cosa',
        'step_form' => 'Rellena el formulario de invitados, hasta el :day: quién viene, edades y alergias.',
        'step_form_open' => 'Rellena el formulario de invitados: quién viene, edades y alergias.',
        'action_form' => 'Rellenar el formulario',
        'step_invite' => 'Comparte la invitación por WhatsApp: los padres confirman y firman ellos.',
        'action_invite' => 'Compartir la invitación',
        'extras' => 'Y si quieres, en el mismo formulario puedes pedir extras para la fiesta (:extras). Se pagan en el parque el día de la fiesta.',
        // «Si cambian los planes»: el plazo de la reserva (el de Mi cuenta), con el WhatsApp y el teléfono del parque.
        'changes_title' => 'Si cambian los planes',
        'changes' => 'Puedes cambiar o cancelar hasta el :day a las :time: [escríbenos por WhatsApp](whatsapp) o llámanos al [:phone](tel).',
        'changes_refund' => 'Puedes cambiar o cancelar hasta el :day a las :time, y te devolvemos la señal: [escríbenos por WhatsApp](whatsapp) o llámanos al [:phone](tel).',
        'changes_open' => 'Si necesitas cambiar o cancelar, [escríbenos por WhatsApp](whatsapp) o llámanos al [:phone](tel).',
        'changes_late' => 'Ya no se puede cambiar ni cancelar; si ha surgido algo, [escríbenos por WhatsApp](whatsapp) o llámanos al [:phone](tel).',
        // El pie la pinta en negrita, tal cual: sin negrita propia (`_label`).
        'replies_label' => 'Responde a este correo si tienes cualquier duda.',
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
    // ── EL AVISO DE LA VÍSPERA (T7·2b, `specs/celebracion-e-invitacion.md` §4.9) ──────────────────
    // ❗❗ Sale la tarde ANTES y SOLO si queda algo por hacer. Por eso puede permitirse ser concreto:
    // quien lo recibe tiene algo pendiente de verdad, y aún le da tiempo.
    // La ficha de Google del parque ha cambiado (`specs/google-business-profile.md` §4.2·4, `#725`).
    // ⚠️ Lo que está en juego no es un ajuste: es de qué negocio son las reseñas de la portada.
    'google_business_location' => [
        // El dato delante: en el corte de una lista de móvil tiene que entrar el rótulo.
        'subject' => 'Ficha de Google: ahora es «:name»',
        // Sin datos variables y sin repetir el asunto (`#506`).
        'preheader' => 'Las reseñas de la portada pasan a ser las de otra ficha.',
        'badge' => 'Cambio en la ficha',
        'headline' => 'Han cambiado la ficha de Google',
        'intro' => ':actor ha conectado la ficha «:name» desde el panel. Las reseñas que se publican en la portada pasan a ser las de esa ficha.',
        'previous' => 'Antes se usaba «:name».',
        'what_to_do' => 'Si no ha sido a propósito, entra en Ajustes → Contenido web → Ficha de Google y vuelve a elegir la correcta. Hasta entonces la portada enseñará las opiniones de la ficha nueva.',
    ],

    // EL 4, «Mañana es la fiesta» (la R2d, `correos-rediseno.md` §4.3; el aviso de T7·2b): sin chapa, como el diseño.
    'visit_eve' => [
        'subject' => 'Mañana a las :time · :product',
        'subject_nombre' => 'Mañana a las :time · el cumple de :name',
        // ⚠️ Sin datos variables y sin repetir el asunto (`#506`): lo que se lee en la lista.
        'preheader' => 'Lo que queda, y lo que se paga en el parque.',
        'headline' => 'Un repaso antes de mañana',
        'list_title' => 'Lo que queda',
        // Las cifras, cada una en su línea y solo si falta.
        'guests' => '**Invitados:** :done de :total con ficha.',
        'replies' => '{1} Una familia te ha contestado y está por repasar.|[2,*] :count familias te han contestado y están por repasar.',
        'guardians' => '**Autorizaciones:** :done de :total firmadas; las que falten se firman en la puerta.',
        // Quien cumple (F7, `#752`): su descargo, por su nombre y con dónde se firma.
        'honoree' => 'Falta el descargo de :name: puedes firmarlo en su fila de la lista.',
        'honoree_unnamed' => 'Falta el descargo de quien cumple: puedes firmarlo en su fila de la lista.',
        'balance' => 'En el parque se pagan **:amount**.',
        // ❗❗ La frase que quita el susto, y no es cortesía: la cifra sola se lee como un reproche a
        // las nueve de la noche del día antes de la fiesta de tu hijo.
        'not_serious' => 'Nada de esto impide la fiesta: lo que falte lo resolvemos en el mostrador, y un niño que venga con un adulto entra igual. Si puedes, déjalo hecho esta tarde y mañana solo tenéis que llegar.',
        'action' => 'Repasar la fiesta',
        'qr_title' => 'Enséñalo en la puerta: ahí está todo.',
        'qr_dictate_label' => 'Si la cámara falla, dicta este código:',
        'action_qr' => 'Abrir Mi QR',
    ],

    // EL 3, «Mañana os esperamos» (la R2d): a toda reserva que no es una fiesta, siempre (`#915`, a); «Hoy», si es del mismo
    // día. Sin chapa, como el diseño.
    'manana' => [
        'subject' => 'Mañana a las :time · :product',
        'subject_hoy' => 'Hoy a las :time · :product',
        'preheader' => 'Tu QR, la hora y cómo llegar.',
        'headline' => 'Mañana os esperamos',
        'headline_hoy' => 'Hoy os esperamos',
        'qr_title' => 'Enséñalo en la puerta: ahí está todo.',
        'qr_dictate_label' => 'Si la cámara falla, dicta este código:',
        'action_qr' => 'Abrir Mi QR',
        'arrival' => 'Llegad unos minutos antes de las :time.',
        'address' => ':address: [Cómo llegar](mapa).',
        'balance' => 'En el parque se pagan **:amount**.',
        'guardians' => '**Autorizaciones:** :done de :total firmadas; [pasa el enlace a las familias](autorizacion) o se firman en la puerta.',
        'minors' => '[Aún no has añadido a los menores a tu cargo](menores): un minuto, y en la puerta solo enseñas el QR. Si no, lo hacéis allí.',
    ],

    // P4 de `fiesta-sistema-nuevo.md` §4.20 (`#913`, `#914`): «Falta elegir…», el día antes de que se cierre la lista. Las
    // líneas de en medio son los TÍTULOS de los grupos que faltan (los pone el parque en el panel).
    'choice_reminder' => [
        'subject' => 'Falta elegir en tu lista · :code',
        // ⚠️ Sin datos variables y sin repetir el asunto (`#506`).
        'preheader' => 'Mañana se cierra la lista de invitados: con un toque lo dejas elegido.',
        'badge' => 'Falta elegir',
        'headline' => 'Te falta elegir en tu lista',
        'intro' => 'La lista de invitados de tu fiesta se cierra el :day a las :hora, y todavía falta por contestar:',
        'park_decides' => 'Si no se elige antes, lo decide el parque el día de la fiesta.',
        'action' => 'Elegir en mi lista',
        'outro' => '¡Nos vemos muy pronto!',
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
    // EL 5, «El pago no ha salido» (la R2e, `correos-rediseno.md` §4.3): sin chapa, como el diseño; Bizum solo si el parque
    // lo tiene (`#915`, c).
    'order_declined' => [
        'subject' => 'El pago no ha salido · :day a las :time',
        'subject_varias' => 'El pago no ha salido · nº :code',
        // ⚠️ Sin datos variables (`#506`): la hora hasta la que sigue guardada va en el cuerpo.
        'preheader' => 'Tu hora sigue guardada unos minutos y no se ha cobrado nada.',
        'headline' => 'El pago no ha salido',
        'body' => 'Tu banco no ha autorizado el cobro y no se ha cargado nada. Tu hora sigue guardada hasta las **:hora**.',
        'body_sin_hora' => 'Tu banco no ha autorizado el cobro y no se ha cargado nada.',
        'action_bizum' => 'Pagar con Bizum',
        'action_card' => 'Volver a intentar con tarjeta',
        'action' => 'Volver a intentar el pago',
        'reason_label' => 'Motivo',
        'help_bizum' => 'Si prefieres, [escríbenos por WhatsApp](whatsapp) y lo reservamos nosotros; pagas la señal o la entrada por Bizum.',
        'help' => 'Si prefieres, [escríbenos por WhatsApp](whatsapp) y lo reservamos nosotros.',
    ],
    // EL 6, «Tu hora se ha liberado» (la R2e): sin chapa, como el diseño.
    'order_expired_without_payment' => [
        'subject' => 'Tu hora se ha liberado · :day a las :time',
        'subject_varias' => 'Tu reserva ha caducado · nº :code',
        'preheader' => 'No se ha cobrado nada.',
        'headline' => 'Tu hora se ha liberado',
        'headline_varias' => 'Tu reserva ha caducado',
        'body' => 'No se completó el pago a tiempo y la hora ha vuelto a estar libre para otros. No se ha cobrado nada.',
        'action' => 'Volver a reservar',
        'contact' => 'Si pagaste y no ves la confirmación, [escríbenos](contacto) con el número **:code** y lo miramos en el banco.',
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
    /*
     * LOS DOS AVISOS AL EQUIPO (la R1c, `specs/correos-rediseno.md` §4.1.4): con la plantilla, en el idioma del PARQUE y NO
     * editables desde el panel (la R1·T es de los correos al cliente). El de contacto lleva el `replyTo` de quien escribe.
     */
    'contact_message' => [
        'subject_no_topic' => 'Nuevo mensaje de contacto',
        'preheader' => 'Si respondes a este correo, le contestas directamente a quien escribe.',
        'badge' => 'Mensaje de la web',
        'headline' => 'Te escribe :name',
        'name' => 'Nombre',
        'email' => 'Correo',
        'phone' => 'Teléfono',
        'topic' => 'Tema',
        'languages' => ['es' => 'español', 'en' => 'inglés', 'fr' => 'francés'],
        'sent_from' => 'Enviado desde el formulario de contacto · idioma: :language · :when',
    ],
    'payment_incident' => [
        'subject' => '⚠️ Incidencia de cobro (:label) — pedido :code',
        'preheader' => 'Un cobro capturado en el banco que no casa con su reserva: hay que revisarlo.',
        'badge' => 'Incidencia de cobro',
        'headline' => ':title',
        'labels' => ['duplicate' => 'cobro duplicado/huérfano', 'overbooked' => 'cobro tras caducar'],
        'titles' => ['duplicate' => 'Cobro duplicado o huérfano', 'overbooked' => 'Cobro llegado tras caducar la reserva'],
        'order' => 'Pedido',
        'order_status' => 'Estado del pedido',
        'gateway_order' => 'Nº de operación (pasarela)',
        'payment_id' => 'ID de pago',
        'source' => 'Origen',
        'duplicate' => [
            'title' => 'Procede una devolución manual',
            'body' => 'El banco **capturó un cobro** que no casa con una reserva cumplible (el pedido ya estaba pagado por otro pago, cancelado o reembolsado). Es un **cargo duplicado o huérfano**: el cliente ha sido cobrado y procede una **devolución manual** desde el portal de Redsys.',
        ],
        'overbooked' => [
            'title' => 'Hay que contactar al cliente',
            'body' => 'Una notificación de pago **autorizada** llegó **después de que la reserva caducara**. El cobro se capturó en el banco, pero la plaza pudo cederse a otro cliente. Hay que **contactar al cliente** para reagendar o devolver el cobro.',
        ],
        'footer' => 'Aviso automático del sistema · queda registrado en el panel (Sistema → Incidencias) · :when',
    ],
    // «Añadir al calendario» de una reserva (la R2, `ReservationCalendarController`): el título del evento en el calendario.
    'calendar' => [
        'summary' => ':product · :park',
    ],
];
