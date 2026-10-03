<?php

namespace App\Domain\Platform\Services\Analytics;

/**
 * **QUÉ CORREOS DICEN A QUÉ HORA LEE LA GENTE** (`specs/correos-salientes.md` §4.14, `#796`, la C4).
 *
 * ⚠️⚠️ Un correo que el cliente PROVOCA en ese momento —su cuenta, su contraseña, su compra— lo abre al instante porque lo
 * está esperando: su hora dice cuándo compró, no cuándo lee. Mezclado, el mapa de «cuándo abren y pulsan» daría una hora
 * falsa. Solo los que le LLEGAN sin esperarlos (lo que hace el parque, lo que se manda a una hora) enseñan sus horarios.
 *
 * Los dos juntos son TODOS los correos al cliente, sin huecos ni solapes: `EmailTimingTest` pone la suite en rojo si llega
 * uno nuevo sin clasificar.
 */
final class EmailTiming
{
    /**
     * Los que provoca el propio cliente y abre al instante.
     *
     * @var list<string>
     */
    public const PROVOKED = [
        // Su cuenta.
        'account_already_exists',
        'customer_account_created',
        'email_change_completed',
        'email_change_requested',
        // El código para entrar (`#853`) y el de confirmar (`#855`): los pide y los abre en segundos, con la pantalla esperando.
        'login_code',
        'confirmation_code',
        'social_identity_linked',
        'verify_email_address',
        'verify_email_for_purchase',
        'verify_pending_email',
        // Su compra, en el momento de pagar (o de no pagar).
        'order_confirmation',
        'order_payment_declined',
        'order_expired_without_payment',
        'order_processed_after_expiration',
        'guest_form_request',
        'guardian_authorization_request',
        // Lo que él mismo acaba de firmar o de guardar en el formulario de invitados.
        'guardian_authorization_signed',
        'mixed_party_surcharge_changed',
        'post_form_addons_changed',
    ];

    /**
     * Los que le llegan sin esperarlos: lo que hace el parque y lo que se manda a una hora.
     *
     * @var list<string>
     */
    public const RECEIVED = [
        'order_cancelled',
        'order_item_cancelled',
        'order_item_modified',
        'order_item_refunded',
        'order_refunded',
        'visit_eve_notice',
        // La R2d de los correos: el 3, «Mañana os esperamos», a una hora (la víspera o dos horas antes).
        'visit_reminder_notice',
        // P4 (`#914`): «Falta elegir…», a una hora, el día antes de que se cierre la lista.
        'choice_reminder_notice',
        'analytics_link_notice',
        // Sin marca ni píxel (sin cuenta; la encuesta es anónima): clasificados para que el censo cierre, nunca darán datos.
        'birthday_coming_notice',
        'survey_invitation',
    ];

    /** ¿Dice este correo a qué hora lee la gente? */
    public static function isReceived(string $key): bool
    {
        return in_array($key, self::RECEIVED, true);
    }
}
