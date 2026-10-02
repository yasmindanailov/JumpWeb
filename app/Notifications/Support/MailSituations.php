<?php

namespace App\Notifications\Support;

/**
 * **CUÁNDO SALE CADA TEXTO Y CÓMO VERLO** (R1·T2 de `specs/correos-rediseno.md` §4.2.3, `[DECIDIDO owner]` `#809`): la
 * ÚNICA declaración que leen la página «Textos de los correos» (el aviso «Solo sale si…» bajo cada campo y el desplegable
 * «Situación» de la vista previa), la vista previa (`MailPreviews`) y su guarda (`MailPreviewsTest`).
 *
 * ▶ **Las condiciones** salen del CÓDIGO de cada correo (sus `toMail()`), no de un caso: la lista que no pinta el último caso
 * cambia con el caso (medido el 02-10: 33 en la local frente a los 34 de la prueba, y no los mismos). Se cuentan las DOS ramas
 * de cada alternativa —«con invitación digital» y «sin ella» se condicionan las dos— y lo que la vista previa sí enseña pero
 * en la vida real sale a veces (el saldo en el parque, el carné adjunto): un aviso que solo estuviera en lo invisible dejaría
 * como normal lo excepcional.
 * ▶ **Las situaciones** de cada correo, en su orden: la PRIMERA es la vista previa sin elegir. Su construcción vive en
 * `MailPreviews::constructores()`, que recibe la clave de la situación.
 */
final class MailSituations
{
    /** La situación de siempre: el caso real más reciente, sin ningún cambio. */
    public const CASO = 'caso';

    /**
     * Cada texto que depende de la situación → su condición (`admin.mail_texts.solo_si.<condición>`).
     *
     * @var array<string, string>
     */
    public const CONDICIONES = [
        // La confirmación del pedido: con o sin un día único, la lista de invitados y el carné.
        'emails.order_confirmation.subject' => 'un_dia',
        'emails.order_confirmation.headline' => 'un_dia',
        'emails.order_confirmation.subject_no_date' => 'sin_un_dia',
        'emails.order_confirmation.headline_no_date' => 'sin_un_dia',
        'emails.order_confirmation.card_attached' => 'con_carne',
        'emails.order_confirmation.paid_confirmation' => 'sin_lista',
        'emails.order_confirmation.paid_confirmation_guest_form' => 'con_lista',
        // La víspera: lo que falta, quien cumple y el saldo.
        'emails.visit_eve.guests' => 'faltan_invitados',
        'emails.visit_eve.honoree' => 'cumple_con_nombre',
        'emails.visit_eve.honoree_unnamed' => 'cumple_sin_nombre',
        'emails.visit_eve.balance_title' => 'saldo_en_parque',
        'emails.visit_eve.balance' => 'saldo_en_parque',
        // El formulario de invitados: con o sin fecha, con o sin invitación digital, y los extras abiertos.
        'emails.guest_form.subject' => 'reserva_con_fecha',
        'emails.guest_form.subject_no_date' => 'reserva_sin_fecha',
        'emails.guest_form.intro' => 'sin_invitacion',
        'emails.guest_form.body' => 'sin_invitacion',
        'emails.guest_form.action' => 'sin_invitacion',
        'emails.guest_form.outro' => 'sin_invitacion',
        'emails.guest_form.intro_invite' => 'con_invitacion',
        'emails.guest_form.body_invite' => 'con_invitacion',
        'emails.guest_form.action_invite' => 'con_invitacion',
        'emails.guest_form.outro_invite' => 'con_invitacion',
        'emails.guest_form.notice_title' => 'con_extras',
        'emails.guest_form.extras' => 'con_extras',
        // La copia de una autorización.
        'emails.guardian_authorization.booking' => 'firma_de_reserva',
        // Los extras del formulario.
        'emails.postform_addons.added' => 'extra_anadido',
        'emails.postform_addons.updated' => 'extra_cambiado',
        'emails.postform_addons.removed' => 'extra_quitado',
        'emails.postform_addons.delta_up' => 'sube',
        'emails.postform_addons.delta_down' => 'baja',
        // El suplemento de la fiesta mixta.
        'emails.mixed_party_surcharge.intro' => 'por_cliente',
        'emails.mixed_party_surcharge.intro_by_park' => 'por_parque',
        'emails.mixed_party_surcharge.added' => 'suplemento_nace',
        'emails.mixed_party_surcharge.updated' => 'suplemento_cambia',
        'emails.mixed_party_surcharge.removed' => 'suplemento_se_quita',
        'emails.mixed_party_surcharge.credit_added' => 'descuento_nace',
        'emails.mixed_party_surcharge.credit_updated' => 'descuento_cambia',
        'emails.mixed_party_surcharge.credit_removed' => 'descuento_se_quita',
        'emails.mixed_party_surcharge.changed_direction' => 'cambia_de_signo',
        'emails.mixed_party_surcharge.where_to_pay' => 'hay_suplemento',
        'emails.mixed_party_surcharge.where_discounted' => 'hay_descuento',
        // El cumpleaños que viene.
        'fiesta.cumple_mail.desde' => 'con_desde',
        // La reserva modificada: una línea por cambio.
        'emails.order_item_modified.slot_change' => 'cambio_dia',
        'emails.order_item_modified.quantity_change' => 'cambio_cantidad',
        'emails.order_item_modified.product_change' => 'cambio_producto',
        'emails.order_item_modified.event_data_change' => 'cambio_datos',
        'emails.order_item_modified.addon_change' => 'cambio_complementos',
        // La reserva cancelada.
        'emails.order_item_cancelled.cascaded_addons' => 'caen_complementos',
        // Las devoluciones.
        'emails.order_item_refunded.also_cancelled' => 'tambien_cancelada',
        'emails.order_item_refunded.when' => 'devolucion_automatica',
        'emails.order_item_refunded.when_manual' => 'devolucion_a_mano',
        'emails.order_refunded.also_cancelled' => 'tambien_cancelado',
        'emails.order_refunded.when' => 'devolucion_automatica',
        'emails.order_refunded.when_manual' => 'devolucion_a_mano',
        // La cuenta enlazada con Google.
        'account.social_link_mail.promoted' => 'google_verifico',
        // La encuesta.
        'surveys.mail.line1' => 'encuesta_sin_entrada',
    ];

    /**
     * Las situaciones de cada correo (`admin.mail_texts.situacion.<situación>`), en su orden. ⚠️ En la reserva modificada la
     * primera ya es un cambio: sin ninguno, ese correo no existe (`#809`; el de verdad siempre lleva uno, `OrderItemEditor`).
     *
     * @var array<string, list<string>>
     */
    public const SITUACIONES = [
        'order_confirmation' => [self::CASO, 'entradas', 'cumpleanos', 'sin_un_dia'],
        'visit_eve_notice' => [self::CASO, 'cumple_con_nombre', 'cumple_sin_nombre'],
        'guest_form_request' => [self::CASO, 'con_invitacion', 'sin_invitacion', 'con_extras', 'reserva_sin_fecha'],
        'guardian_authorization_signed' => [self::CASO, 'firma_de_reserva'],
        'post_form_addons_changed' => ['extra_anadido', 'extra_cambiado', 'extra_quitado'],
        'mixed_party_surcharge_changed' => ['suplemento_nace', 'suplemento_cambia', 'suplemento_se_quita', 'descuento_nace', 'descuento_cambia', 'descuento_se_quita', 'cambia_de_signo', 'por_parque'],
        'birthday_coming_notice' => [self::CASO, 'con_desde'],
        'order_item_modified' => ['cambio_dia', 'cambio_cantidad', 'cambio_producto', 'cambio_datos', 'cambio_complementos'],
        'order_item_cancelled' => [self::CASO, 'caen_complementos'],
        'order_item_refunded' => [self::CASO, 'tambien_cancelada', 'devolucion_a_mano'],
        'order_refunded' => [self::CASO, 'tambien_cancelado', 'devolucion_a_mano'],
        'social_identity_linked' => [self::CASO, 'google_verifico'],
        'survey_invitation' => [self::CASO, 'encuesta_sin_entrada'],
    ];

    /** La condición de un texto, o `null` si sale siempre que sale su correo. */
    public static function condicion(string $clave): ?string
    {
        return self::CONDICIONES[$clave] ?? null;
    }

    /**
     * Las situaciones de un correo, en su orden (vacío: no tiene textos que dependan de la situación).
     *
     * @return list<string>
     */
    public static function de(string $correo): array
    {
        return self::SITUACIONES[$correo] ?? [];
    }

    /** La situación a pintar: la pedida si es de ese correo; si no, la primera (o ninguna). */
    public static function elegida(string $correo, ?string $situacion): ?string
    {
        $suyas = self::de($correo);

        return in_array($situacion, $suyas, true) ? $situacion : ($suyas[0] ?? null);
    }
}
