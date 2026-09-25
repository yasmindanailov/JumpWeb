<?php

/*
 * Las ENCUESTAS que llegan al cliente por correo (`docs/specs/encuestas.md` §4.3, T3; `DECISIONES #740`): el
 * correo del día siguiente y la página que abre su botón. Lo lee un cliente en SU idioma —por eso vive en un
 * espacio con los tres (es/en/fr) y nunca en `admin.*`— y sin haber entrado en su cuenta.
 *
 * ⚠️ El correo es de SERVICIO (`[DECIDIDO owner]` §7·4): ni una línea comercial aquí. La `preheader` va sin
 * dato variable y no repite el asunto (`MailInboxLineTest`).
 */
return [
    'mail' => [
        'badge' => 'Tu opinión',
        'headline' => '¿Qué tal ayer en el parque?',
        // ⚠️ Sin el nombre del parque: ya va en el remitente (`MailInboxLineTest`).
        'subject' => '¿Qué tal ayer en el parque?',
        'preheader' => 'Dos minutos y nos ayudas a mejorar. Sin cuenta ni contraseña.',
        'line1' => 'Ayer estuviste en :park y nos gustaría saber qué tal fue. Son unas pocas preguntas.',
        // ⚠️ Ni siquiera para negarlo: la palabra «oferta» no entra en este correo (`SurveySendTest` lo mide).
        'line2' => 'Se contesta en dos minutos y sin entrar en tu cuenta. Es solo para mejorar el parque, nada más.',
        'action' => 'Contestar',
        'optout' => 'No quiero recibir más encuestas',
    ],
    'page' => [
        'title' => 'Tu opinión',
        'badge' => 'Encuesta',
        'intro_default' => 'Unas pocas preguntas sobre tu visita. Gracias por tu tiempo.',
        'required' => 'obligatoria',
        'yes' => 'Sí',
        'no' => 'No',
        'text_placeholder' => 'Escribe aquí (opcional)',
        'submit' => 'Enviar respuestas',
        'error_required' => 'Contesta esta pregunta, por favor.',
        'error_invalid' => 'Esa respuesta no vale para esta pregunta.',
        'thanks_title' => '¡Gracias!',
        'thanks' => 'Tus respuestas nos ayudan a mejorar :park.',
        'optout_title' => 'No recibir más encuestas',
        'optout_text' => 'Si pulsas el botón, no te mandaremos más correos de encuestas. Podrás volver a activarlos desde «Mi cuenta → Privacidad».',
        'optout_button' => 'No quiero más encuestas',
        'optout_done_title' => 'Hecho',
        'optout_done' => 'No te mandaremos más encuestas por correo.',
    ],
];
