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
    'children_heading' => 'Datos de cada invitado',
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
    // ⚠️ UNA sola forma, sin plural: la pinta el JS sustituyendo dos marcadores, y `trans_choice`
    // no existe en el navegador — una cadena con `|` habría llegado entera a la pantalla.
    'count_warn_discard' => 'Al bajar a :count invitados se perderán los datos ya rellenados de :discarded fichas.',
    'count_saved_up' => 'Tus datos se han guardado y tu reserva pasa a :count invitados. La diferencia se abona en el parque.',
    'count_saved_down' => 'Tus datos se han guardado y tu reserva pasa a :count invitados.',
    'count_error_above_max' => 'Tus datos se han guardado, pero el número de invitados no: es más de lo que admite este cumpleaños. Llámanos y lo vemos contigo.',
    'count_error_below_min' => 'Tus datos se han guardado, pero el número de invitados no: es menos del mínimo de este cumpleaños. Llámanos y lo vemos contigo.',
    'count_error_below_assigned' => 'Tus datos se han guardado, pero el número de invitados no: ya has asignado más plazas de las que quieres dejar. Quita a alguien de la lista y vuelve a intentarlo.',
    'count_error_sold_out' => 'Tus datos se han guardado, pero el número de invitados no: ya no queda sitio para tantos a esa hora. Llámanos y lo vemos contigo.',
    'count_error_cutoff' => 'Tus datos se han guardado, pero el número de invitados no: ha pasado el plazo para cambiarlo.',
    'count_error_closed' => 'Tus datos se han guardado, pero el número de invitados no se ha podido cambiar. Llámanos y lo vemos contigo.',
    'count_error_stale' => 'Tus datos se han guardado, pero el número de invitados no: la reserva ha cambiado mientras tenías esta página abierta. Vuelve a cargarla.',

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
    'name_empty' => 'Sin completar',
    'nav_prev' => 'Anterior',
    'nav_next' => 'Siguiente',
    'nav_last' => 'Última ficha',
    'toast_saved' => 'Guardado',
    'footer_privacy' => 'Privacidad',
];
