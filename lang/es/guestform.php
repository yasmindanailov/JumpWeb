<?php

/*
 * El formulario de invitados de un cumpleaños: lo que el DOMINIO y el controlador siguen diciendo con estas claves
 * (los avisos de la fiesta mixta, los rechazos del número y de los extras, el guardado, la solo lectura, las
 * chapas de la invitación, el recordatorio). La página se viste con el sistema nuevo desde la T1a de
 * `specs/fiesta-sistema-nuevo.md` y sus textos de pantalla viven en `fiesta.php` (`lista`); lo que pintaba la piel
 * vieja se retiró con ella en la T4 (26-09). `ClavesDeIdiomaTest` vigila que no vuelva a quedar texto muerto.
 */
return [
    // El título del formulario lo nombra la landing de cumpleaños («después de pagar te llega el :form»).
    'title' => 'Formulario de reserva',
    'progress' => ':done de :total fichas completas',
    'progress_complete' => 'Todas las fichas completas (:total)',

    // Fiesta MIXTA (`docs/specs/cumple-mixto.md` §9·7). ⚠️ El texto NO promete un cobro: el
    // suplemento lo aplica el operador, así que dice dónde se paga y no da nada por hecho.
    // Una edad SIN PRODUCTO (`#284` D6, §22.4): tres casos, y el parque puede escribir los suyos en
    // Ajustes (`mixed_party.no_product.*`). `:phone` = teléfono de contacto de la instalación. La chapa de la ficha
    // («Sin producto para esta edad») es `fiesta.lista.la_lista.sin_producto`.
    'no_product_title' => 'Una edad sin producto',
    'no_product_below' => 'Alguno de los invitados tiene una edad por debajo del tramo más bajo de este cumpleaños, y para esa edad no hay producto en las condiciones de tu reserva. Llámanos al :phone y lo vemos contigo; hasta entonces esa ficha no se da por completa.',
    'no_product_above' => 'Alguno de los invitados tiene una edad por encima del tramo más alto de este cumpleaños, y para esa edad no hay producto en las condiciones de tu reserva. Llámanos al :phone y lo vemos contigo; hasta entonces esa ficha no se da por completa.',
    'no_product_gap' => 'Alguno de los invitados tiene una edad que queda entre dos tramos de este cumpleaños, y para esa edad no hay producto en las condiciones de tu reserva. Llámanos al :phone y lo vemos contigo; hasta entonces esa ficha no se da por completa.',
    'no_product_phone_fallback' => 'parque',
    // El dinero solo se mueve al guardar con TODAS las edades (`#285` §20.6).
    'frozen_missing_ages' => '{1} Falta :count edad por declarar: el suplemento no se recalculará —ni arriba ni abajo— hasta que estén todas.|[2,*] Faltan :count edades por declarar: el suplemento no se recalculará —ni arriba ni abajo— hasta que estén todas.',
    'mixed_title' => 'Fiesta mixta',
    'mixed_line' => 'A :count invitado(s) les corresponde «:target» (:target_price por invitado) en vez de «:booked» (:booked_price).',
    'mixed_line_written' => 'A :count invitado(s) les corresponde «:target»: :unit más por invitado.',
    'mixed_surcharge' => 'Por eso se abona un suplemento de :amount en el parque, el día de la fiesta.',
    // ⚠️ `mixed_savings` CADUCÓ con la T4 (`specs/cumple-mixto.md` §24.5): el descuento SE APLICA
    // solo (`[DECIDIDO owner]` D5). Quedan las frases nuevas del descuento y del «a tu favor».
    'mixed_discount_total' => 'Por eso se te descuentan :amount. Si te queda algo por pagar en el parque, se descuenta de ahí; si ya lo tenías todo pagado, se te devuelven en el parque el día de la fiesta.',
    'mixed_net_zero' => 'Entre el suplemento y el descuento, tu importe en el parque no cambia por este motivo.',
    'mixed_savings_pending' => 'Por eso tu fiesta sale :amount más barata: el descuento se aplicará al completar todas las edades.',
    'mixed_no_difference' => 'No hay diferencia de precio entre los dos: no tienes nada que abonar por este motivo.',
    'readonly_notice' => 'Esta reserva ya se ha celebrado. El formulario es de solo lectura: puedes consultar los datos pero ya no editarlos.',
    // La receta del aviso pide TÍTULO. El de error sirve para los cinco rechazos: cuentan lo mismo y solo cambian de remedio.
    'count_warn_title' => 'Antes de guardar',
    'count_error_title' => 'Los invitados no se han cambiado',
    'saved' => 'Formulario guardado. ¡Gracias! Puedes volver a editarlo cuando quieras.',
    // Los EXTRAS de venta posterior (`specs/complementos-post-reserva.md`, `#413`): lo que se
    // puede añadir DESPUÉS de reservar y se paga en el parque.
    'extras_closed_cutoff' => 'Ya no se puede cambiar',
    'extras_closed_sold' => 'Lo elegiste al reservar — llámanos para cambiarlo',
    'extras_blocked' => 'Tus datos se han guardado, pero alguno de los extras no se ha podido cambiar: puede que ya haya pasado su plazo. Llámanos si lo necesitas.',
    'extras_stale' => 'Tus datos se han guardado, pero los extras no: la reserva ha cambiado mientras tenías esta página abierta. Vuelve a cargarla y revísalos.',

    // El cliente cambia sus invitados desde aquí (`specs/invitados-en-post-form.md`, `#444`).
    // ⚠️ Los textos de RECHAZO son cinco y distinguen el remedio: el techo se resuelve llamando, el
    // suelo del pack también, pero «alguien ya tiene esa plaza» se resuelve quitándolo de la lista.
    'count_hint' => 'Puedes cambiarlo hasta el :when (máximo :max).',
    'count_closed_cutoff' => 'Ya no se puede cambiar el número de invitados: ha pasado el plazo.',
    'count_closed' => 'El número de invitados ya no se puede cambiar.',
    // ⚠️ La pinta el JS de la lista (`resources/js/fiesta/lista.js`, con `choice()` de `logica.js`): `trans_choice` no
    // existe en el navegador, y la forma «uno|varios» la resuelve él por las fichas que se pierden.
    'count_warn_discard' => 'Al bajar a :count invitados se perderán los datos ya rellenados de :discarded ficha.|Al bajar a :count invitados se perderán los datos ya rellenados de :discarded fichas.',
    'count_error_above_max' => 'Tus datos se han guardado, pero el número de invitados no: es más de lo que admite este cumpleaños. Llámanos y lo vemos contigo.',
    'count_error_below_min' => 'Tus datos se han guardado, pero el número de invitados no: es menos del mínimo de este cumpleaños. Llámanos y lo vemos contigo.',
    'count_error_below_assigned' => 'Tus datos se han guardado, pero el número de invitados no: ya has asignado más plazas de las que quieres dejar. Quita a alguien de la lista y vuelve a intentarlo.',
    'count_error_sold_out' => 'Tus datos se han guardado, pero el número de invitados no: ya no queda sitio para tantos a esa hora. Llámanos y lo vemos contigo.',
    'count_error_cutoff' => 'Tus datos se han guardado, pero el número de invitados no: ha pasado el plazo para cambiarlo.',
    'count_error_closed' => 'Tus datos se han guardado, pero el número de invitados no se ha podido cambiar. Llámanos y lo vemos contigo.',
    'count_error_stale' => 'Tus datos se han guardado, pero el número de invitados no: la reserva ha cambiado mientras tenías esta página abierta. Vuelve a cargarla.',

    // ── LA INVITACIÓN, vista desde la lista del anfitrión (T6, `specs/celebracion-e-invitacion.md` §4.7) ──
    // ⚠️ Lo que lee un DESCONOCIDO en la página pública vive en `fiesta.php` (`invitacion_pagina`); aquí solo
    // está lo que lee ÉL. Las dos mitades no comparten voz.
    'invite' => [
        // ⚠️ No dice cuántas veces ni desde cuándo: solo que hay más de una y cuál se enseña (V6).
        'repeated' => 'Esta familia ha contestado más de una vez. Te enseñamos lo último que nos dijo.',
        // Un «no» no se apunta en ninguna parte: lleva a BAJAR el número de invitados (D3); la fila y la frase del
        // plazo son de `fiesta.php` (`fila.no`, `lista.la_lista.no_vienen_baja`).
        // El mismo gesto sirve para un «no» y para un «sí» que no quiere apuntar: «quítalo de mi lista»; la respuesta se conserva.
        'dismissed' => 'Hecho: eso ya no está en tu lista.',
        // ⚠️⚠️ La CARRERA, dicha (§7.1·3): entre pintar y guardar entraron más «sí». No es una lista de
        // espera y no se rechaza a nadie; la decisión es suya, que es quien sabe quién va.
        'overflow_title' => 'Hay respuestas que ya no caben',
        'overflow' => '{1} Una familia ha dicho que viene y ya no queda ficha para su hijo: sube el número de invitados o avísale.|[2,*] Hay :count respuestas que ya no caben: sube el número de invitados o avisa a esas familias.',
        'theme_confeti' => 'Confeti',
        'theme_fiesta' => 'Fiesta',
        'theme_sereno' => 'Sereno',
        // ⚠️ Se DICE cuando un texto no se admite: el campo se queda como estaba, y callarlo dejaría
        // al anfitrión creyendo que lo suyo se publicó (§7.2·R9).
        'rejected_title' => 'Eso no lo hemos guardado',
        'rejected' => 'En «Quién cumple» y en «Te invita» no caben enlaces ni direcciones de correo: la invitación se publica en nuestra web y cualquiera podría pulsarlos. Quítalos y vuelve a guardar.',

        // ── EL RECORDATORIO (T6·6, §4.7) ──────────────────────────────────────────────────────────
        // ❗❗ No se envía NADA: del padre no tenemos correo. Esto escribe el mensaje y el anfitrión lo
        // pega donde ya repartió el enlace, que es el único sitio por donde se puede llegar a ellos.
        // ⚠️ La casilla nace SIN marcar: una lista de «éstos no han contestado» en el chat de la clase
        // señala a unas familias delante de las demás, y si eso se puede hacer lo sabe él, no nosotros.
        'remind_names' => '{1} Nombrar a la familia que falta|[2,*] Nombrar a las :count familias que faltan',
        // La marca de que ya avisó. ⚠️ Sin punto: `DisplayTime::dayLabel()` ya termina en uno.
        'remind_last' => '{1} Lo escribiste una vez, la última el :when|[2,*] Lo escribiste :count veces, la última el :when',
        // ── Y el texto que se copia. Va DENTRO el enlace: esto se pega de una pieza en un chat.
        'reminder_text' => 'Nos faltan respuestas para el cumple de :name. Si todavía no nos habéis dicho si venís, se contesta aquí en un momento:',
        'reminder_text_generic' => 'Nos faltan respuestas para nuestra fiesta. Si todavía no nos habéis dicho si venís, se contesta aquí en un momento:',
        'reminder_names' => 'Nos faltan: :names.',
        'reminder_deadline' => 'Se puede contestar hasta el :when',
    ],
];
