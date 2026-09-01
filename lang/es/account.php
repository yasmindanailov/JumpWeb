<?php

return [
    'close' => 'Cerrar',

    'nav' => [
        'hello' => 'Hola, :name',
        'login' => 'Iniciar sesión',
        'sign_out' => 'Cerrar sesión',
        'pending_form' => 'Tienes un formulario pendiente',
    ],

    'sidecart' => [
        // ⚠️⚠️ **Aquí vivían `next` y `no_upcoming`, y se RETIRARON el 2026-08-28**
        // (`specs/identidad-qr-puerta.md` §9.7 C·2, `DECISIONES #217`): eran la sub-línea de la
        // próxima reserva bajo el nombre, que se decía en DOS sitios —el bloque de cuenta y el índice
        // del área— y el hueco lo ocupa ahora el atajo «Mi QR». El índice sigue enseñándola.
        // ▶ Se van también del ARRANQUE, y eso vale más que el borrado: este grupo NO se poda por
        // sesión —el bloque cambia de cara sin recargar— así que viajaban en **todas** las páginas
        // públicas para no pintarse nunca. Con ellas se fue `panel.js::sublineOf()` y sus casos.
        'form_pending_one' => 'Tienes pendiente un formulario para :product',
        'form_pending_many' => 'Tienes :count formularios pendientes',
        'guest_hello' => 'Hola, saltador/a',
        'guest_sub' => 'Inicia sesión y guarda tus reservas.',
        'upcoming_count' => ':count reservas próximas',
        // Sidebar v2 — tag sutil que muestra la cuenta cuando se minimiza al entrar en el flujo.
    ],

    'account' => [
        'eyebrow' => 'Tu espacio',
        'title' => 'Mi cuenta',
        'subtitle' => 'Gestiona tus datos, tu acceso y tu privacidad.',
        'wrong_password' => 'La contraseña actual no es correcta.',
        'profile' => [
            'title' => 'Tus datos',
            'intro' => 'Actualiza tu nombre, teléfono e idioma. Si cambias el email, te enviaremos un enlace al nuevo buzón: el cambio se aplica al confirmarlo desde ahí.',
            'name' => 'Nombre y apellidos',
            'email' => 'Email',
            'phone' => 'Teléfono',
            'locale' => 'Idioma',
            'current_password' => 'Contraseña actual',
            'email_change_hint' => 'Solo necesaria si cambias el email. El cambio NO se aplica hasta que lo confirmes desde el nuevo buzón (te enviaremos un enlace).',
            'save' => 'Guardar cambios',
            'saving' => 'Guardando…',
            'pending_email_title' => 'Cambio de email pendiente',
            'pending_email_msg' => 'Te hemos enviado un enlace de confirmación a :email. Caduca en :minutes min. Mientras tanto, sigues usando :current.',
            'pending_email_resend' => 'Reenviar enlace',
            'pending_email_cancel' => 'Cancelar cambio',
        ],
        'password' => [
            'title' => 'Cambiar contraseña',
            'intro' => 'Usa una contraseña larga y única.',
            'current' => 'Contraseña actual',
            'new' => 'Nueva contraseña',
            'confirm' => 'Repite la nueva contraseña',
            'save' => 'Actualizar contraseña',
            'saving' => 'Guardando…',
            'show' => 'Mostrar contraseña',
            'hide' => 'Ocultar contraseña',
        ],
        'sessions' => [
            'title' => 'Sesiones',
            'intro' => 'Si crees que alguien más usa tu cuenta, cierra la sesión en el resto de dispositivos.',
            'current_password' => 'Contraseña actual',
            'logout_others' => 'Cerrar sesión en los demás dispositivos',
            'working' => 'Cerrando…',
        ],
        'privacy' => [
            'title' => 'Privacidad y datos (RGPD)',
            'intro' => 'Descarga una copia de tus datos o elimina tu cuenta.',
            'consents_title' => 'Tus consentimientos',
            'no_consents' => 'No hay consentimientos registrados.',
            'consent_types' => [
                'privacy' => 'Política de privacidad',
                'terms' => 'Términos y condiciones',
                'waiver' => 'Descargo de responsabilidad',
                'marketing' => 'Comunicaciones comerciales',
            ],
            'export_btn' => 'Descargar mis datos',
            'delete_title' => 'Eliminar mi cuenta',
            'delete_intro' => 'Borraremos tu nombre, email, teléfono y contraseña de forma permanente, y cerraremos tu sesión. Por ley, conservamos los datos mínimos de tus pedidos (sin tu identidad) para la facturación. Esta acción no se puede deshacer.',
            'delete_password' => 'Contraseña actual',
            'delete_confirm' => '¿Seguro que quieres eliminar tu cuenta? Esta acción es permanente.',
            'delete_btn' => 'Eliminar mi cuenta',
            // Fase 6 · waiver (`specs/waiver-probatorio.md` §4.5, §4.8): la tarjeta del waiver en esta
            // zona y el aviso del índice. Viajan solo con sesión (poda de `layout.blade.php`).
            'waiver' => [
                'title' => 'Descargo de responsabilidad',
                'status_external' => 'La gestiona el parque fuera de esta web.',
                'status_unsigned' => 'Todavía no la has firmado.',
                'status_current' => 'Firmada, versión vigente (v:version).',
                'status_outdated' => 'La firmaste en una versión anterior del texto: puedes entrar igual, pero te pedimos que aceptes la nueva.',
                'sign_btn' => 'Firmar',
                'signing' => 'Firmando…',
                'signed_ok' => 'Firma registrada ✓',
                'declared' => 'declarada en mostrador',
                'pdf' => 'PDF',
                'pending_notice' => 'Tienes pendiente el descargo de responsabilidad.',
                'pending_cta' => 'Firmarla',
                // `#329` — el estado «la aceptó al registrarse y falta verificar el correo». No dice
                // que no la haya firmado, porque sí la aceptó: dice qué falta y ofrece la salida.
                'status_awaiting_verification' => 'Tu descargo de responsabilidad quedará firmado en cuanto verifiques tu correo.',
            ],
            'deleting' => 'Eliminando…',
        ],
        // Fase 6 · menores a cargo (`specs/menores-a-cargo.md` §9.8): la zona y sus tarjetas. Viajan
        // solo con sesión, ENTEROS (la zona los pinta todos). La casilla, «leer el texto», «Firmar»,
        // «Firmando…», «Firma registrada» y «PDF» se REUTILIZAN de `register.*` y `privacy.waiver.*`.
        'dependents' => [
            'title' => 'Menores a cargo',
            'intro' => 'Declara a los menores de los que te haces responsable y firma el descargo en nombre de cada uno. En la puerta solo se ve su edad y si tiene el descargo al día, nunca su nombre.',
            'empty' => 'Todavía no has declarado ningún menor.',
            'add_title' => 'Añadir un menor',
            'name' => 'Nombre',
            // ⚠️ El texto cambió en `#236`: hasta entonces decía «solo lo verás tú», y desde
            // que la pantalla de puerta enseña el nombre del menor eso dejó de ser cierto.
            'name_hint' => 'Vale el nombre que uséis en casa: es el que verá el personal en la entrada.',
            'surname' => 'Apellidos',
            'relationship' => '¿Qué eres suyo?',
            'relationship_choose' => 'Elige una opción',
            'relationship_hint' => 'Nos hace falta para poder aceptar que firmes el descargo en su nombre.',
            'relationship_father' => 'Padre',
            'relationship_mother' => 'Madre',
            'relationship_legal_guardian' => 'Tutor o tutora legal',
            'relationship_grandparent' => 'Abuelo o abuela',
            'relationship_other' => 'Otra',
            'born_on' => 'Fecha de nacimiento',
            'add' => 'Añadir',
            'adding' => 'Añadiendo…',
            // ── El alta se DESPLIEGA desde un botón (encargo del owner, 2026-08-28) ───────────
            // `add_title` rotula el disparador Y la sección que abre: el botón y la pantalla a la
            // que lleva tienen que llamarse igual, o el cliente cree que va a otro sitio (la misma
            // regla que el atajo «Mi QR» del bloque de cuenta). `add_cancel` la pliega y tira lo
            // tecleado.
            'add_cancel' => 'Cancelar',
            'age' => ':age años',
            'adult' => 'Ya tiene 18 años: tu descargo ya no le cubre.',
            'remove' => 'Quitar',
            'removing' => 'Quitando…',
            'remove_confirm' => '¿Quitar a :name de tu cuenta? Si firmaste el descargo en su nombre, ese registro se conserva.',
            'waiver_unsigned' => 'Descargo sin firmar en su nombre.',
            'waiver_current' => 'Descargo firmado en su nombre, versión vigente (v:version).',
            'waiver_outdated' => 'versión anterior',
            // La asignación de ENTRADAS a menores en el embudo (tanda 4, `specs/menores-a-cargo.md`
            // §9.9.3 D9/D10): el selector de los pasos 3 y 4, el aviso de la puerta 2, la fila del
            // resumen y la tarjeta de «Mis reservas». Solo con sesión, como el resto del subgrupo.
            'for_label' => 'Para:',
            'assigned_none' => 'Sin asignar a ningún menor.',
            'assigned_show' => 'Ver para quién es',
            'assigned_hide' => 'Ocultar para quién es',
            // ── La lista se PAGINA en cliente, y solo cuando hace falta ──────────────────────
            // Llega entera de `GET /me/dependents`, así que paginar es cortar lo que ya está en
            // memoria. Con una sola página el paginador NO se pinta (`dependentsPager()` devuelve
            // `null`): una cuenta con dos menores no puede ver una barra de páginas para dos
            // tarjetas. Mismos rótulos y misma forma que `orders.pagination` y `purchases.pagination`
            // — las tres listas del cajón se paginan igual.
            'pagination' => [
                'label' => 'Paginación de menores',
                'prev' => 'Anteriores',
                'next' => 'Siguientes',
                'page' => 'Página :current de :last',
            ],
        ],
        // El QR de puerta (Fase 6 · A, `specs/identidad-qr-puerta.md` §9.6 B·2): verlo, dictarlo,
        // descargarlo y renovarlo. Solo con sesión: es una credencial de puerta.
        //
        // ⚠️ **La palabra de cara al cliente es «QR», no «carné»** (`[DECIDIDO owner, 2026-08-28]`,
        // §9.7 C·6, `DECISIONES #217`): es lo que el cliente ya dice en la cola —«enséñame el QR»— y
        // «carné» sugería una tarjeta física que el parque no emite. **Las CLAVES no cambian**
        // (`account.card.*`), ni los nombres técnicos (`CustomerCard`, `/me/card`, `carne-qr.png`):
        // renombrarlos habría tocado rutas, contrato y auditoría para arreglar un texto.
        'card' => [
            'title' => 'Mi QR',
            'intro' => 'Tu QR te identifica en la puerta: enséñalo desde el móvil o impreso. No sirve para entrar en tu cuenta.',
            'alt' => 'Tu QR',
            'token_label' => 'Si la cámara falla, dicta este código:',
            'download' => 'Descargar (PNG)',
            'hint' => 'Comparte este enlace con los padres. Cada uno rellena SUS datos.',
            'unavailable' => 'Este QR ya no se puede mostrar. Renuévalo y tendrás uno nuevo al instante.',
            'rotate' => 'Renovar mi QR',
            'rotating' => 'Renovando…',
            // ⚠️ El aviso va SIEMPRE visible bajo el botón, no dentro de la confirmación
            // (`specs/identidad-qr-puerta.md` §9.7 C·4): quien no pulsaba nunca llegaba a leer que el
            // QR anterior deja de valer, porque el texto solo existía dentro de `window.confirm`.
            // Y la confirmación vive ya DENTRO del cajón (`[DECIDIDO owner]`, `DECISIONES #217`): la
            // del navegador salía fuera, sin nuestros tres idiomas, y el owner no llegó a verla.
            'rotate_notice' => 'El QR anterior dejará de funcionar en el acto: el del correo y cualquier copia impresa.',
            'rotate_confirm_title' => '¿Seguro que quieres renovarlo?',
            'rotate_confirm_yes' => 'Sí, renovar',
            'rotate_confirm_no' => 'Cancelar',
            'rotated' => 'QR renovado. El anterior ya no vale.',
            'expired' => 'Tu sesión ha caducado: vuelve a entrar para ver tu QR.',
        ],
    ],

    'login' => [
        'cta' => 'Entrar',
        'eyebrow' => 'Bienvenido de nuevo',
        'title' => 'Inicia sesión',
        'email' => 'Email',
        'password' => 'Contraseña',
        'remember' => 'Mantener la sesión iniciada',
        'submit' => 'Entrar',
        'submitting' => 'Entrando…',
        'forgot' => '¿Olvidaste tu contraseña?',
    ],

    'forgot' => [
        'eyebrow' => 'Recuperar acceso',
        'title' => 'Recupera tu contraseña',
        'intro' => 'Escribe tu email y te enviaremos un enlace para crear una nueva contraseña.',
        'email' => 'Email',
        'submit' => 'Enviar enlace',
        'submitting' => 'Enviando…',
        'back_to_login' => 'Volver a iniciar sesión',
        'sent_title' => 'Revisa tu correo',
        'sent_msg' => 'Si existe una cuenta con ese email, te hemos enviado un enlace para restablecer la contraseña. Revisa también la carpeta de spam.',
    ],

    'reset' => [
        'eyebrow' => 'Nueva contraseña',
        'title' => 'Crea una nueva contraseña',
        'intro' => 'Elige una contraseña nueva para tu cuenta.',
        'email' => 'Email',
        'password' => 'Nueva contraseña',
        'password_confirmation' => 'Repite la contraseña',
        'submit' => 'Guardar contraseña',
        'submitting' => 'Guardando…',
    ],

    'register' => [
        'cta' => 'Crear cuenta',
        'eyebrow' => 'Únete',
        'title' => 'Crea tu cuenta',
        'subtitle' => 'Necesaria para reservar entradas y cumpleaños.',
        'name' => 'Nombre y apellidos',
        'email' => 'Email',
        'phone' => 'Teléfono',
        'password' => 'Contraseña',
        'password_hint' => 'Mínimo 8 caracteres. Evita contraseñas comunes o filtradas.',
        'must_accept' => 'Debes aceptar esta condición para continuar.',
        'accept_waiver' => 'He leído y acepto el descargo de responsabilidad.',
        'waiver_read' => 'Leer el texto completo',
        'accept_privacy' => 'He leído y acepto la <a href=":url" target="_blank" rel="noopener">política de privacidad</a>.',
        'accept_terms' => 'Acepto los <a href=":url" target="_blank" rel="noopener">términos y condiciones</a>.',
        'marketing' => 'Quiero recibir novedades y ofertas (opcional).',
        'submit' => 'Crear cuenta',
        'submitting' => 'Creando…',
        'fix_errors' => 'Revisa estos campos:',
        'bot_check_failed' => 'No hemos podido verificar que no eres un robot. Inténtalo de nuevo.',
        'already_exists' => 'Ya tienes una cuenta con este correo. Inicia sesión para continuar.',
        'exists_unverified' => 'Ya te registraste con este correo pero no lo verificaste. Te hemos reenviado el enlace de verificación.',
        'leave_blank' => 'Deja este campo en blanco',
    ],

    'status' => [
        'email-verified' => '¡Email confirmado! Tu cuenta ya está activa.',
        'email-already-verified' => 'Tu email ya estaba confirmado.',
        'verification-link-sent' => 'Te hemos reenviado el correo de verificación. Revisa tu bandeja (y la carpeta de spam).',
        'verification-resend-throttled' => 'Acabamos de enviarte el correo. Espera un minuto antes de pedir otro.',
        'password-reset' => 'Contraseña actualizada. Ya puedes iniciar sesión.',
        'profile-updated' => 'Datos actualizados.',
        'password-updated' => 'Contraseña actualizada. Hemos cerrado tus demás sesiones por seguridad.',
        'email-change-requested' => 'Hemos enviado un enlace al nuevo email para confirmar el cambio. Mientras tanto, tu cuenta sigue usando el email actual.',
        'email-change-confirmed' => 'Email confirmado. Ya puedes usarlo para iniciar sesión.',
        'email-change-expired' => 'El enlace para confirmar el cambio de email ha caducado. Vuelve a solicitarlo si todavía quieres cambiarlo.',
        'email-change-taken' => 'Ese email lo ha registrado otra cuenta mientras esperabas la confirmación. Hemos cancelado el cambio; prueba con otro email.',
        'email-change-cancelled' => 'Cambio de email cancelado.',
        'email-change-resent' => 'Te hemos reenviado el enlace al nuevo email.',
        'logged-out-others' => 'Has cerrado la sesión en los demás dispositivos.',
        'account-deleted' => 'Tu cuenta ha sido eliminada. Esperamos verte de nuevo.',
        'order-retry-unavailable' => 'Ya no podemos reintentar este pago: la reserva ha caducado y la plaza se ha liberado. Puedes hacer una nueva reserva cuando quieras.',
        'order-retry-failed' => 'No hemos podido iniciar el pago ahora. Inténtalo de nuevo en un momento; si el problema persiste, escríbenos.',
        // Frecuencia (Fase 3 · paso 2): el reintento comparte el limitador por titular con la
        // creación de reservas. La reserva NO ha caducado, así que no vale el mensaje de arriba.
        'order-retry-throttled' => 'Lo estás intentando demasiado seguido. Espera un minuto y vuelve a probar: tu reserva sigue guardada.',
        // Reservas en pausa (#218): el reintento de pago se bloquea; el cliente puede llamar.
        'order-retry-paused' => 'Las reservas online están pausadas temporalmente. Llámanos por teléfono y completamos tu reserva.',
        'guest-form-saved' => 'Formulario de reserva guardado. ¡Gracias! Puedes volver a editarlo cuando quieras.',
    ],

    'verify' => [
        'eyebrow' => 'Casi listo',
        'title' => 'Confirma tu email',
        'intro' => 'Te hemos enviado un enlace de confirmación a tu correo. Ábrelo para activar tu cuenta.',
        'sent_to' => 'Te hemos enviado un correo de confirmación a :email. Ábrelo para activar tu cuenta.',
        'spam_hint' => '¿No lo ves? Revisa la carpeta de spam o promociones.',
        'resend' => 'Reenviar correo',
        'pending_notice' => 'Debes verificar tu correo electrónico.',
        'resend_in' => 'Reenviar en',
        'resending' => 'Reenviando…',
        'resends_left' => 'Te quedan :n reenvíos.',
        'resend_limit' => 'Has alcanzado el límite de reenvíos. Revisa tu carpeta de spam o inténtalo más tarde.',
        'notice_resend_hint' => '¿No te ha llegado? Reenvíate el correo de verificación:',
        'notice_resend_button' => 'Reenviar correo de verificación',
        'notice_resend_hint_guest' => '¿No te ha llegado? Inicia sesión y podrás reenviarte el correo de verificación.',
        'already_have_account' => '¿Ya tienes cuenta?',
    ],

    'exists_mail' => [
        'subject' => 'Ya tienes una cuenta en :park',
        'greeting' => '¡Hola!',
        'line1' => 'Alguien ha intentado registrarse con tu email. Si fuiste tú, ya tienes una cuenta: inicia sesión o recupera tu contraseña.',
        'action' => 'Iniciar sesión',
        'line2' => 'Si no has sido tú, puedes ignorar este mensaje con tranquilidad.',
    ],

    'orders' => [
        // Las respuestas del pack, BAJO DEMANDA (tanda 3): son datos de un menor
        // (art. 9) y por eso no se pintan solas ni viajan en la lista de pedidos.
        'event_data_show' => 'Ver los datos del evento',
        'event_data_hide' => 'Ocultar los datos del evento',
        'eyebrow' => 'Tus reservas',
        'title' => 'Mis reservas',
        'intro' => 'Consulta tus reservas, su código y su estado.',
        'view' => 'Ver mis reservas',
        'empty' => 'Todavía no tienes reservas.',
        // #179: paginación de la lista de pedidos.
        'pagination' => [
            'label' => 'Paginación de reservas',
            'prev' => 'Anteriores',
            'next' => 'Siguientes',
            'page' => 'Página :current de :last',
        ],
        // Fase 7.1b (#127): badge cuando la fecha+hora del producto ya pasó.
        'item_finished' => 'Finalizado',
        'item_cancelled' => 'Cancelado',
        // ── El HISTORIAL, en su propia pantalla (`specs/mis-reservas-por-reserva.md`) ─────────
        // Lo pasado no se mezcla con lo vivo: la lista principal responde a «¿qué tengo?» y el
        // historial de un cliente veterano la empujaría fuera de la primera página.
        'history' => [
            'title' => 'Historial de reservas',
            'cta' => 'Ver historial de reservas',
            'empty' => 'Aquí aparecerán tus reservas pasadas y las canceladas.',
        ],
        // El desglose del PEDIDO, bajo demanda: es del pedido y no de la reserva, así que se pide
        // al desplegarlo en vez de repetirlo en cada tarjeta.
        'order_ref' => 'Pedido :code',
        'order_show' => 'Ver pedido',
        'retry_payment' => 'Reintentar el pago',
        'retry_hint' => 'Mantenemos tu reserva unos minutos más por si quieres completar el pago.',
        'guest_form_pending' => 'Completa el formulario de :product',
        'guest_form_done' => 'Ver o editar el formulario de :product',
        'guest_form_past' => 'Ver el formulario de :product',
        // Reserva CANCELADA: el botón sigue visible pero desactivado (no clicable) — el post-form ya no aplica.
        'guest_form_cancelled' => 'Formulario de :product · reserva cancelada',
        'manage' => 'Gestionar',
        'manage_eyebrow' => 'Tu reserva',
        'manage_title' => 'Gestionar tu reserva',
        'manage_intro' => 'Cualquier cambio en tu reserva (modificar fecha u hora, cancelar, ajustar invitados o cualquier otra incidencia) lo gestionamos directamente con nuestro equipo para poder valorar tu caso y darte la mejor solución posible.',
        'manage_note' => 'Anota o copia este número y proporciónalo al contactar con nosotros para que podamos ayudarte rápidamente.',
        'manage_cta' => 'Ir a contacto',
    ],

    /**
     * **«Mis pedidos»** — el DINERO, por pedido (`specs/desglose-dinero-cliente.md` §19,
     * `DECISIONES #129`). Pantalla propia porque el desglose es del PEDIDO y una reserva no es su
     * unidad: un pedido puede llevar tres reservas de tres fechas, y repetir el mismo total en las
     * tres decía algo falso en dos de ellas.
     *
     * ⚠️ Ojo al nombre: el grupo `orders` de arriba es «MIS RESERVAS» —su ruta web es
     * `/mi-cuenta/pedidos` y por ahí entran 8 correos ya enviados—, así que el grupo de esta pantalla
     * tiene que llamarse de otra forma. Lo mismo pasa con la zona del cajón (`ZONES.PURCHASES`).
     */
    'purchases' => [
        'guest_minors' => [
            'count' => ':count firmados',
            'waiver_outdated' => 'versión anterior',
            'waiver_missing' => 'falta la firma',
            'hint' => 'Comparte este enlace con los padres o tutores. Cada uno rellena SUS datos y no ve los de los demás.',
        ],
        'title' => 'Mis pedidos',
        'empty' => 'Todavía no tienes ningún pedido.',
        'ref' => 'Pedido :code',
        'show' => 'Ver el desglose',
        'hide' => 'Ocultar el desglose',
        'reservations' => 'Reservas de este pedido',
        'pagination' => [
            'label' => 'Paginación de pedidos',
            'prev' => 'Anteriores',
            'next' => 'Siguientes',
            'page' => 'Página :current de :last',
        ],
    ],
];
