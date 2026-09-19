<?php

return [
    'eyebrow' => 'Formulario de reserva',
    'title' => 'Formulario de reserva',
    'subtitle' => 'Completa los datos de cada invitado de esta reserva. Puedes editarlos cuando quieras hasta el día del evento.',
    'fact_when' => 'Fecha y hora',
    'fact_guests' => 'Invitados',
    'fact_ref' => 'Reserva',
    'progress' => ':done de :total fichas completas',
    'progress_complete' => 'Todas las fichas completas (:total)',

    // Fiesta MIXTA (`docs/specs/cumple-mixto.md` §9·7). ⚠️ El texto NO promete un cobro: el
    // suplemento lo aplica el operador, así que dice dónde se paga y no da nada por hecho.
    // Una edad SIN PRODUCTO (`#284` D6, §22.4): tres casos, y el parque puede escribir los suyos en
    // Ajustes (`mixed_party.no_product.*`). `:phone` = teléfono de contacto de la instalación.
    'regime_no_product' => 'Sin producto para esta edad',
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
    'privacy' => 'Solo usamos estos datos para preparar tu evento. Los datos de los menores se tratan de forma confidencial y se eliminan según nuestra política de privacidad.',
    'readonly_notice' => 'Esta reserva ya se ha celebrado. El formulario es de solo lectura: puedes consultar los datos pero ya no editarlos.',
    'general_heading' => 'Datos generales',
    // `#570` (T1 de `specs/celebracion-e-invitacion.md`): la página ya se titula «Datos de los
    // invitados» y la palabra que usa el resto de la pantalla es «ficha».
    'children_heading' => 'Una ficha por invitado',
    // La receta del aviso sobre papel pide TÍTULO, y estos dos avisos eran solo su frase. El de error
    // sirve para los cinco rechazos: cuentan lo mismo y solo cambian de remedio.
    'count_warn_title' => 'Antes de guardar',
    'count_error_title' => 'Los invitados no se han cambiado',
    // Se marca lo OPCIONAL en vez de lo obligatorio: de cinco columnas, dos son obligatorias.
    'optional' => '(opcional)',
    // La política sale de su frase y es un control propio de 48 (F-08 del canvas).
    'privacy_link' => 'Leer la política de privacidad',
    'saved' => 'Formulario guardado. ¡Gracias! Puedes volver a editarlo cuando quieras.',
    'child' => 'Invitado/a :n',
    // Los EXTRAS de venta posterior (`specs/complementos-post-reserva.md`, `#413`): lo que se
    // puede añadir DESPUÉS de reservar y se paga en el parque.
    'extras_heading' => 'Extras',
    'extras_lead' => 'Puedes añadirlos hasta poco antes de la fiesta. Se pagan en el parque, junto con el resto.',
    'extras_qty_label' => 'Cuántos quieres de :name',
    'extras_total' => 'Extras',
    'extras_where' => 'Estos extras se pagan en el parque el día de la fiesta.',
    'extras_closed_cutoff' => 'Ya no se puede cambiar',
    'extras_closed_sold' => 'Lo elegiste al reservar — llámanos para cambiarlo',
    'extras_blocked' => 'Tus datos se han guardado, pero alguno de los extras no se ha podido cambiar: puede que ya haya pasado su plazo. Llámanos si lo necesitas.',
    'extras_stale' => 'Tus datos se han guardado, pero los extras no: la reserva ha cambiado mientras tenías esta página abierta. Vuelve a cargarla y revísalos.',

    // El cliente cambia sus invitados desde aquí (`specs/invitados-en-post-form.md`, `#444`).
    // ⚠️ Los textos de RECHAZO son cinco y distinguen el remedio: el techo se resuelve llamando, el
    // suelo del pack también, pero «alguien ya tiene esa plaza» se resuelve quitándolo de la lista.
    'count_label' => 'Número de invitados',
    'count_hint' => 'Puedes cambiarlo hasta el :when (máximo :max).',
    'count_closed_cutoff' => 'Ya no se puede cambiar el número de invitados: ha pasado el plazo.',
    'count_closed' => 'El número de invitados ya no se puede cambiar.',
    // ⚠️ La pinta el JS. Hasta la T2 era UNA sola forma porque `trans_choice` no existe en el navegador y una
    // cadena con `|` habría llegado entera a la pantalla («de 1 fichas»); desde `#571` la resuelve `choice()`
    // de `public/js/guest-form/logic.js`, que elige por las fichas que se pierden.
    'count_warn_discard' => 'Al bajar a :count invitados se perderán los datos ya rellenados de :discarded ficha.|Al bajar a :count invitados se perderán los datos ya rellenados de :discarded fichas.',
    'count_saved_up' => 'Tus datos se han guardado y tu reserva pasa a :count invitados. La diferencia se abona en el parque.',
    'count_saved_down' => 'Tus datos se han guardado y tu reserva pasa a :count invitados.',
    'count_error_above_max' => 'Tus datos se han guardado, pero el número de invitados no: es más de lo que admite este cumpleaños. Llámanos y lo vemos contigo.',
    'count_error_below_min' => 'Tus datos se han guardado, pero el número de invitados no: es menos del mínimo de este cumpleaños. Llámanos y lo vemos contigo.',
    'count_error_below_assigned' => 'Tus datos se han guardado, pero el número de invitados no: ya has asignado más plazas de las que quieres dejar. Quita a alguien de la lista y vuelve a intentarlo.',
    'count_error_sold_out' => 'Tus datos se han guardado, pero el número de invitados no: ya no queda sitio para tantos a esa hora. Llámanos y lo vemos contigo.',
    'count_error_cutoff' => 'Tus datos se han guardado, pero el número de invitados no: ha pasado el plazo para cambiarlo.',
    'count_error_closed' => 'Tus datos se han guardado, pero el número de invitados no se ha podido cambiar. Llámanos y lo vemos contigo.',
    'count_error_stale' => 'Tus datos se han guardado, pero el número de invitados no: la reserva ha cambiado mientras tenías esta página abierta. Vuelve a cargarla.',

    // ── EL BLOQUE DE LA INVITACIÓN (T6·1, `specs/celebracion-e-invitacion.md` §4.7) ───────────────
    // Va ARRIBA y antes de que el anfitrión empiece a teclear —después ya no sirve de nada— y no
    // promete rellenarlo todo: promete **repartir el trabajo**, dejando debajo la puerta de teclear
    // sin esconderla (canvas, turno 3a). ⚠️ Lo que el anfitrión escribe aquí lo lee un DESCONOCIDO
    // en la página pública, y por eso lo de allí vive en `invitation.php`: aquí solo está lo que lee
    // ÉL. Las dos mitades no comparten voz.
    'invite' => [
        'title' => 'La invitación',
        'lead' => 'Reparte el enlace y que cada familia te diga si viene. Lo que contesten aparecerá aquí y tú decides qué apuntas.',
        'share' => 'Compartir la invitación',
        // Lo que se manda con el enlace por Web Share. ⚠️ Sin el enlace dentro: lo pone el navegador
        // en su propio campo, y repetirlo lo pega dos veces en el chat.
        'share_text' => 'Estás invitado al cumple de :name.',
        'share_text_generic' => 'Estás invitado a nuestra fiesta.',
        'copy' => 'Copiar enlace',
        'copied' => 'Enlace copiado',
        // El enlace se ENSEÑA siempre, no solo detrás de un botón: sin JavaScript no hay ni Web Share
        // ni portapapeles, y sin verlo escrito no habría forma de repartirlo.
        'link_label' => 'Enlace de la invitación',
        // ⚠️ Sin punto final: `DisplayTime::dayLabel()` ya termina en uno («Mar. 22 sep.») y la frase
        // salía con dos. Lo vio la sonda, no una relectura. ▶ Y la fecha va ABREVIADA, como la pista
        // del número de invitados dos líneas más arriba: es el MISMO plazo, y darle dos formas en la
        // misma pantalla se lee como dos fechas distintas.
        'deadline' => 'Pueden contestar hasta el :when',
        'deadline_closed' => 'El plazo para contestar ya ha pasado. El enlace sigue abriendo: lo que dice la invitación hace falta el mismo día de la fiesta.',
        'needs_name_title' => 'Antes de repartirla',
        'needs_name' => 'Dinos de quién es la fiesta y ya puedes compartir el enlace: lo escribes aquí debajo, en «Personalizar».',
        // El resumen de §4.7. ⚠️ «Por repasar» son las respuestas de las DOS clases, también los «no»:
        // un «no» lleva a bajar el número de invitados, así que también hay que verlo.
        'tally_yes' => '{0} Nadie ha dicho que viene|{1} :count viene|[2,*] :count vienen',
        'tally_no' => '{0} Nadie ha dicho que no|{1} :count no puede|[2,*] :count no pueden',
        'tally_pending' => '{0} Nada por repasar|{1} :count por repasar|[2,*] :count por repasar',
        'customize' => 'Personalizar',
        'theme' => 'Tema',
        'theme_confeti' => 'Confeti',
        'theme_fiesta' => 'Fiesta',
        'theme_sereno' => 'Sereno',
        'honoree_name' => 'Quién cumple',
        'honoree_age' => 'Años que cumple',
        'host_line' => 'Te invita',
        'show_phone' => 'Enseñar mi teléfono en la invitación',
        'save' => 'Guardar la invitación',
        'saved' => 'Invitación guardada.',
        // ⚠️ Se DICE cuando un texto no se admite: el campo se queda como estaba, y callarlo dejaría
        // al anfitrión creyendo que lo suyo se publicó (§7.2·R9).
        'rejected_title' => 'Eso no lo hemos guardado',
        'rejected' => 'En «Quién cumple» y en «Te invita» no caben enlaces ni direcciones de correo: la invitación se publica en nuestra web y cualquiera podría pulsarlos. Quítalos y vuelve a guardar.',
    ],

    'submit' => 'Guardar',
    'hint' => 'Si aún no los sabes todos, guarda lo que tengas y vuelve más adelante.',
    'back' => 'Volver a mis reservas',

    // Rediseño de la página (#264): hoja enfocada con acordeón de fichas.
    'heading' => 'Datos de los invitados',
    'meter_label' => 'Fichas completas',
    'bulk_prompt' => '¿Aún no los sabes todos?',
    'bulk_action' => 'Abrir la primera pendiente',
    'status_pending' => 'Pendiente',
    'status_done' => 'Lista',

    // T2 de `specs/celebracion-e-invitacion.md` (`#571`): muchos invitados. ⚠️ Las que pinta el JS del
    // pegado usan el formato «uno|varios»: lo resuelve `choice()` de `public/js/guest-form/logic.js`,
    // porque `trans_choice` no existe en el navegador.
    'group_pending' => 'Falta algo · :count',
    'group_done' => '{1} :count ficha ya lista|[2,*] :count fichas ya listas',
    'status_missing' => 'Falta :field',
    'extras_chosen' => '{0} Ninguno elegido|{1} :count elegido|[2,*] :count elegidos',
    'paste_prompt' => '¿Tienes la lista escrita? Pégala y solo te quedan las edades.',
    'paste_open' => 'Pegar la lista de nombres',
    'paste_title' => 'Pega la lista de nombres',
    'paste_help' => 'Uno por línea. Los ponemos en orden y tú repasas las edades.',
    'paste_label' => 'Lista de nombres',
    'paste_count' => 'Hemos leído :count nombre|Hemos leído :count nombres',
    'paste_scope' => 'Irá a la ficha que está sin rellenar.|Irán a las :count fichas que están sin rellenar.',
    'paste_kept' => 'La que ya tiene datos no se toca.|Las :count que ya tienen datos no se tocan.',
    'paste_overflow' => 'No cabe :count nombre: no quedan fichas sin rellenar.|No caben :count nombres: no quedan fichas sin rellenar.',
    'paste_apply' => 'Poner el nombre|Poner los :count',
    'paste_cancel' => 'Cancelar',
    // ⚠️ Pegar NO guarda: los nombres viven en la página hasta que se pulsa «Guardar», y se dice.
    'paste_done' => ':count nombre puesto. Guarda para que no se pierda.|:count nombres puestos. Guarda para que no se pierdan.',
    'name_empty' => 'Sin completar',
    'nav_prev' => 'Anterior',
    'nav_next' => 'Siguiente',
    'nav_last' => 'Última ficha',
    'toast_saved' => 'Guardado',
    'footer_privacy' => 'Privacidad',
];
