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
    'regime_unknown' => 'Edad fuera de tramo',
    'mixed_title' => 'Fiesta mixta',
    'mixed_line' => 'A :count invitado(s) les corresponde «:target» (:target_price por invitado) en vez de «:booked» (:booked_price).',
    'mixed_surcharge' => 'Por eso se abona un suplemento de :amount en el parque, el día de la fiesta.',
    'mixed_savings' => 'Por eso tu fiesta saldría :amount más barata. No se descuenta automáticamente: coméntalo en recepción el día de la fiesta.',
    'mixed_no_difference' => 'No hay diferencia de precio entre los dos: no tienes nada que abonar por este motivo.',
    'privacy' => 'Solo usamos estos datos para preparar tu evento. Los datos de los menores se tratan de forma confidencial y se eliminan según nuestra política de privacidad.',
    'readonly_notice' => 'Esta reserva ya se ha celebrado. El formulario es de solo lectura: puedes consultar los datos pero ya no editarlos.',
    'general_heading' => 'Datos generales',
    'children_heading' => 'Datos de cada invitado',
    'saved' => 'Formulario guardado. ¡Gracias! Puedes volver a editarlo cuando quieras.',
    'child' => 'Invitado/a :n',
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
