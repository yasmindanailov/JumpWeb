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
    // Fiesta MIXTA (`docs/specs/cumple-mixto.md` §9·7): un dato PROPIO de la reserva, no un
    // trozo del nombre del producto. Vive en `tickets.*` y no en `admin.*` porque lo leen los
    // dos, y `admin.*` solo existe en español y chino.
    'mixed_party_badge' => 'MIXTA',
    // La etiqueta PEGADA al nombre, para las superficies de texto plano (PDF, correos, puerta,
    // calendario). Es una clave y no una concatenación en PHP para que una instalación pueda
    // cambiar el separador sin tocar código.
    'mixed_party_product_name' => ':name · :badge',
    'gate_mixed_party_line' => 'Suplemento por :count invitado de otro tramo de edad|Suplemento por :count invitados de otro tramo de edad',
    'gate_mixed_party_line_named' => 'Suplemento por :count invitado que corresponde a :target|Suplemento por :count invitados que corresponden a :target',
    // T4 (`specs/cumple-mixto.md` §24.5): la línea espejo — el DESCUENTO, en negativo en el desglose.
    'gate_mixed_party_credit_line' => 'Descuento por :count invitado de un tramo más económico|Descuento por :count invitados de un tramo más económico',
    'gate_mixed_party_credit_line_named' => 'Descuento por :count invitado que corresponde a :target|Descuento por :count invitados que corresponden a :target',
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
    // La HORA EXTRA (`specs/hora-extra.md` §8.6): la cantidad son ENTRADAS que se quedan.
    'addon_stay_price' => ':price por entrada que se queda',
    'addon_stay_selected' => 'Para 1 entrada que se queda · :price|Para :count entradas que se quedan · :price',
    'addon_per_guest_qty' => ':count (uno por invitado)',
    'addon_per_guest_add' => 'Añadir · uno por invitado',
    'addon_requires' => 'Requiere: :name',
    'from' => 'desde',
    'qty_less' => 'Quitar uno',
    'qty_more' => 'Añadir uno',
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
    // ── Lo que falta antes de pagar (`#349`) ───────────────────────────────────────────────────
    // ⚠️ El teléfono se pide con su PORQUÉ y en tono de favor, no de trámite: es un dato que el
    // cliente no esperaba dar aquí, y decirle para qué sirve es la diferencia entre un formulario y
    // un peaje. `[owner]`: «algo amable y sutil, solo si falta su número de teléfono».
    'due_phone_label' => 'Tu teléfono',
    'due_phone_hint' => 'Nos falta para poder avisarte si algo cambia en tu reserva. No lo usamos para nada más.',
    'due_terms' => 'He leído y acepto las <a href=":url" target="_blank" rel="noopener">condiciones de reserva</a>.',
    // ⚠️⚠️ Este enlace se pinta SIEMPRE, se pida la casilla o no: antes de `#349` el embudo no
    // enseñaba las condiciones en ningún sitio, y la LCGC (art. 5) pide que el consumidor haya
    // podido conocerlas para que se incorporen al contrato.
    'terms_link' => 'Al reservar aceptas las <a href=":url" target="_blank" rel="noopener">condiciones de reserva</a>.',
    'due_terms_updated' => 'Hemos actualizado las condiciones de reserva. Léelas y acéptalas para seguir.',
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
    // ▶ Hasta la T3·4 del libro (`DECISIONES #315`) aquí vivían los rótulos del modelo de DOS EJES
    // (`ledger.*`, «Resto de la señal», «Pendiente de devolución», «Total final», las etiquetas del
    // cargo de puerta…). El libro los sustituye por `journal.*` (abajo) y por su saldo con clase.
    // ⚠️⚠️ LA FRASE que explica el estado del pedido (`DECISIONES #127`). Un número no explica:
    // el encargo era que el cliente entienda su situación ante CUALQUIER situación. La compone el
    // DOMINIO (`Booking\Services\OrderBook`), que es quien sabe qué caso es.
    'ledger_note' => [
        // ⚠️⚠️ La clase `under_review` MANDA sobre las demás: si el libro no cuadra, ninguna otra
        // frase puede ser cierta (`DECISIONES #132`). Las otras clases del saldo no llevan frase:
        // el libro las dice con su línea (`journal.balance_*`).
        'under_review' => 'Estamos revisando el detalle de este pedido. El importe que te hemos cobrado es el que ves abajo; si tienes cualquier duda, escríbenos y lo miramos contigo.',
        'expired' => 'Esta reserva caducó sin completarse el pago. No se te ha cobrado nada.',
        'pending_payment' => 'Todavía no se ha completado el pago de :amount. Tu plaza sigue reservada hasta que caduque.',
    ],
    // ⚠️⚠️ EL LIBRO del pedido (`specs/desglose-libro.md` §4.3, `DECISIONES #305` `[DECIDIDO owner]`):
    // cada gestión es una línea con su signo y su fecha, el total es la suma y el saldo se liquida
    // en el parque. Las compone el DOMINIO (`Booking\Services\MovementLabel`) y son NEUTRAS DE VOZ
    // —sin «tu» ni «el cliente»— porque el mismo diccionario sirve al cliente y al panel (D1).
    // ⚠️ Cada etiqueta dice QUÉ pasó y no en qué dirección: la dirección la pone el signo del importe.
    // ⚠️ `addon_quantity` y `with_reservation` son plantillas sin palabras, iguales en los idiomas.
    'journal' => [
        'booking' => 'Reserva realizada',
        'quantity' => 'Cantidad: :old → :new',
        'addon_quantity' => ':name: :old → :new',
        'product_change' => 'Cambio a :name',
        'slot_change' => 'Cambio de fecha a :when',
        'price_change' => 'Precio del día: :old → :new',
        'edit_fallback' => 'Cambios en :product',
        'cancel' => 'Cancelado: :name · :quantity',
        // T4 del libro (`DECISIONES #316`, decisión 5): «Compensación» se leía como un reembolso.
        'courtesy' => 'Descuento por cortesía',
        'paid_online' => 'Pagado online',
        'paid_desk' => 'Pagado en recepción',
        'refund_card' => 'Devuelto a la tarjeta',
        'refund_manual' => 'Devuelto en el parque (registrado)',
        'refund_pending' => 'Devolución en curso',
        'refund_failed' => 'Devolución fallida',
        // D9 de la T5 de mixtos: nadie registra el cobro en puerta, así que «Liquidado» y no «Pagado».
        'gate' => 'Liquidado en el parque',
        // D9 bis (T4): con la visita pasada, lo que quedaba por devolver se da por entregado en recepción.
        'gate_refund' => 'Devuelto en el parque',
        'with_reservation' => ':reservation · :label',
        // T3·1 (`specs/desglose-libro.md` §6.3): los TÍTULOS del libro y los rótulos del SALDO (§4.4),
        // uno por clase — la clase la decide el servidor (`balance.kind`), el rótulo lo pone quien
        // pinta desde aquí. `settled` no lleva línea. `balance_rest_at_park` acompaña a `pay_online`
        // cuando hay señal: lo que además se pagará en el parque.
        'movements_title' => 'Movimientos',
        'settlements_title' => 'Pagos y devoluciones',
        'total' => 'Total',
        'paid' => 'Pagado',
        'balance_pay_at_park' => 'A pagar en el parque',
        'balance_refund_at_park' => 'A devolver en el parque',
        'balance_refund_pending' => 'Pendiente de devolución',
        'balance_pay_online' => 'Pendiente de pagar por web',
        'balance_rest_at_park' => 'y :amount en el parque',
        // El bloque del libro en los CORREOS (T3·3, D-T3·5): título y las tres clases sin línea en el cajón.
        'email_title' => 'Tu pedido, a día de hoy',
        'balance_settled' => 'Nada pendiente',
        'balance_expired' => 'Caducado sin cobro',
        'balance_under_review' => 'Importe en revisión',
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
        // La HORA EXTRA (`specs/hora-extra.md`): un complemento que OCUPA la franja siguiente.
        'addon_occupancy_line' => '«:addon» de «:product» del :when ya no cabe: la franja siguiente está completa o cerrada. Quítalo o elige otra hora.',
        'addon_over_line' => 'No pueden quedarse más personas (:staying) de las que entran (:entering). Revisa las horas extra de tu selección.',
        'pack_sold_out_line' => 'El pack «:product» del :when ya no tiene cupo. Quítalo del carrito y prueba con otra franja.',
        // La hora extra de un PACK (`specs/hora-extra.md` §10): la fiesta cabe, pero ALARGADA no.
        // Dice qué quitar —la hora extra, no la fiesta— porque las dos son quitables y sólo una
        // salva la reserva.
        'stay_extension_line' => 'El pack «:product» del :when no cabe con la hora extra: la sala está ocupada después. Quita la hora extra o elige otra franja.',
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
        'adult' => 'ya tiene 18 años',
        // `#242`, `[OWNER]`: al menor ya asignado, de forma sutil. Es «1» siempre —una entrada por
        // menor—, así que el rótulo es literal y no lleva contador.
        'assigned' => '1 entrada asignada',
        'unsigned' => 'descargo sin firmar: fírmalo en «Menores a cargo»',
        'outdated' => 'firmaste una versión anterior: acepta la nueva en «Menores a cargo»',
        'notice' => 'Tienes menores a cargo: indica para quién es cada entrada antes de pagar (o déjalas como adultos).',
        'for' => 'Para:',
        'age' => ':age años',
    ],

    /*
     * El JUSTIFICANTE de un menor invitado (`specs/waiver-por-reserva.md` §12.2).
     *
     * ⚠️ El texto NO dice «waiver» ni «justificante offshore»: dice el CASO. Quien compra no conoce
     * nuestro vocabulario, y «un menor que no está a tu cargo» es lo que reconoce en su vida.
     *
     * ⚠️ Y no promete que se firme AHORA: se dice cuándo llega el enlace, porque el correo sale al
     * quedar pagado y prometerlo antes deja al cliente esperando algo que no ha pasado.
     */
    /*
     * El bloque PLEGADO de «¿quiénes vienen?» (`[DECIDIDO owner, 2026-09-02]`: más sutil, menos
     * centrado en el proceso). El rótulo dice lo que hay dentro SIN obligar a abrirlo.
     */
    'who_block' => [
        'title' => '¿Quiénes vienen?',
        'none' => 'Menores a cargo y justificantes',
        'some' => 'Menores a tu cargo: :count',
        'guardian' => 'Con justificante de un menor invitado',
        'both' => 'Menores a tu cargo: :count · y un justificante',
    ],
    'guardian_no_places' => 'No quedan plazas libres en esta reserva: ya has asignado todas a menores a tu cargo. Añade una entrada más o quita una asignación.',
    'guardian_optional' => 'Viene un menor que no está a mi cargo',
    'guardian_optional_help' => 'Su padre, madre o tutor tendrá que firmar una autorización. Al terminar la compra te enviamos el enlace para pasárselo.',
    'guardian_required' => 'Cada menor de esta reserva necesita la autorización firmada de su padre, madre o tutor. Al terminar la compra te enviamos el enlace para repartirlo.',
];
