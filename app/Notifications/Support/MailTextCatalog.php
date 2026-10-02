<?php

namespace App\Notifications\Support;

use App\Domain\Content\Services\MailTextLoader;
use App\Notifications as N;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;

/**
 * QUÉ SE PUEDE EDITAR DE CADA CORREO (R1·T de `specs/correos-rediseno.md` §4.2.1, `[DECIDIDO owner]` `#802`): los correos
 * al cliente, cada uno con su clave —la de «Correos enviados», `EmailUtm::keyOf()`—, su tipo y los TRAMOS de idioma de donde
 * salen sus textos. La ESTRUCTURA es fija (la del molde y del diseño): lo que se edita son los textos de sus bloques.
 *
 * Lo que NUNCA se edita, por regla y no a mano (cada uno con su porqué):
 *  - el SALUDO (`greeting`): la cabecera lo sustituyó (`#506`) y ya no se pinta: un bloque que no sale confundiría;
 *  - lo LEGAL de un comercial: por qué lo recibes y la baja (`porque`, `baja`, `optout`; LSSI 22.1, `#750`, `#754`);
 *  - los DATOS y los FRAGMENTOS que otra frase inserta (`providers.*`, `actions.*`, y los sueltos: el nombre de reserva
 *    de un producto borrado, `product_fallback`, que va también al ASUNTO, y los importes rotulados del suplemento,
 *    `amount_discount`/`amount_surcharge`): no son un bloque del correo, y su formato lo pone la frase que los recibe;
 *  - lo que no usa nadie (`reason_prefix`) y los PLURALES (`{1} …|[2,*] …`), que un editor de texto no sabe guardar.
 * Una guarda comprueba que cada clave editable la pinta SU correo y ningún otro sitio (la web no cambia desde aquí).
 * Fuera del catálogo, los del EQUIPO (la ficha de Google, la incidencia de un cobro, el mensaje de contacto).
 */
final class MailTextCatalog
{
    /** @var list<string> Los tipos, en el orden de la pantalla. */
    public const TIPOS = ['reserva', 'fiesta', 'cambios', 'cuenta', 'encuestas'];

    /** El idioma en que el producto ESCRIBE sus correos: de su fichero salen los bloques (no del `fallback_locale`). */
    public const IDIOMA_BASE = 'es';

    /**
     * clave del correo → [clase, tipo, tramos de idioma]. El orden, el de la pantalla dentro de cada tipo. Un tramo puede ser
     * UN texto (`emails.verify_pending_email.ignore`): el correo usa ese y no sus hermanos.
     *
     * @var array<string, array{0: class-string, 1: string, 2: list<string>}>
     */
    public const CORREOS = [
        // ── La reserva ──
        'order_confirmation' => [N\OrderConfirmation::class, 'reserva', ['emails.order_confirmation']],
        'visit_eve_notice' => [N\VisitEveNotice::class, 'reserva', ['emails.visit_eve']],
        'order_payment_declined' => [N\OrderPaymentDeclined::class, 'reserva', ['emails.order_declined']],
        'order_expired_without_payment' => [N\OrderExpiredWithoutPayment::class, 'reserva', ['emails.order_expired_without_payment']],
        'order_processed_after_expiration' => [N\OrderProcessedAfterExpiration::class, 'reserva', ['emails.order_after_expiration']],
        // ── La fiesta ──
        'guest_form_request' => [N\GuestFormRequest::class, 'fiesta', ['emails.guest_form']],
        'guardian_authorization_request' => [N\GuardianAuthorizationRequest::class, 'fiesta', ['emails.guardian_request']],
        'guardian_authorization_signed' => [N\GuardianAuthorizationSigned::class, 'fiesta', ['emails.guardian_authorization']],
        'post_form_addons_changed' => [N\PostFormAddonsChanged::class, 'fiesta', ['emails.postform_addons']],
        'mixed_party_surcharge_changed' => [N\MixedPartySurchargeChanged::class, 'fiesta', ['emails.mixed_party_surcharge']],
        'birthday_coming_notice' => [N\BirthdayComingNotice::class, 'fiesta', ['fiesta.cumple_mail']],
        // ── Los cambios que hace el parque ──
        'order_item_modified' => [N\OrderItemModified::class, 'cambios', ['emails.order_item_modified']],
        'order_item_cancelled' => [N\OrderItemCancelled::class, 'cambios', ['emails.order_item_cancelled']],
        'order_item_refunded' => [N\OrderItemRefunded::class, 'cambios', ['emails.order_item_refunded']],
        'order_cancelled' => [N\OrderCancelled::class, 'cambios', ['emails.order_cancelled']],
        'order_refunded' => [N\OrderRefunded::class, 'cambios', ['emails.order_refunded']],
        // ── La cuenta ──
        'customer_account_created' => [N\CustomerAccountCreated::class, 'cuenta', ['emails.customer_account_created']],
        'verify_email_address' => [N\VerifyEmailAddress::class, 'cuenta', ['emails.verify_email']],
        'verify_email_for_purchase' => [N\VerifyEmailForPurchase::class, 'cuenta', ['emails.verify_purchase']],
        'login_code' => [N\LoginCode::class, 'cuenta', ['emails.login_code']],
        'confirmation_code' => [N\ConfirmationCode::class, 'cuenta', ['emails.confirmation_code']],
        // Lleva solo el CÓDIGO (`#856`; sin el enlace desde la A5, `#869`): sus bloques y, de la versión de antes, «si no fuiste tú».
        'verify_pending_email' => [N\VerifyPendingEmail::class, 'cuenta', ['emails.verify_pending_email_code', 'emails.verify_pending_email.ignore']],
        'email_change_requested' => [N\EmailChangeRequested::class, 'cuenta', ['emails.email_change_requested']],
        'email_change_completed' => [N\EmailChangeCompleted::class, 'cuenta', ['emails.email_change_completed']],
        'account_already_exists' => [N\AccountAlreadyExists::class, 'cuenta', ['account.exists_mail']],
        'social_identity_linked' => [N\SocialIdentityLinked::class, 'cuenta', ['account.social_link_mail']],
        'analytics_link_notice' => [N\AnalyticsLinkNotice::class, 'cuenta', ['account.analytics_mail']],
        // ── Las encuestas ──
        'survey_invitation' => [N\SurveyInvitation::class, 'encuestas', ['surveys.mail']],
    ];

    /** @var list<string> El último tramo de lo que nunca se edita (ver la cabecera), con los fragmentos sueltos al final. */
    private const NUNCA = ['greeting', 'porque', 'baja', 'optout', 'reason_prefix', 'product_fallback', 'amount_discount', 'amount_surcharge'];

    /** @var list<string> Un tramo intermedio de lo que nunca se edita: datos y fragmentos. */
    private const NUNCA_TRAMOS = ['providers', 'actions'];

    /** @var array<string, string>|null clave de texto → clave del correo (memo por proceso). */
    private static ?array $indice = null;

    /** ¿Existe este correo en el catálogo? */
    public static function existe(string $correo): bool
    {
        return isset(self::CORREOS[$correo]);
    }

    /**
     * Los grupos de idioma que tienen textos de correo (`emails`, `account`…): el cargador solo mira esos.
     *
     * @return list<string>
     */
    public static function grupos(): array
    {
        $grupos = [];
        foreach (self::CORREOS as [, , $tramos]) {
            foreach ($tramos as $tramo) {
                $grupos[] = explode('.', $tramo, 2)[0];
            }
        }

        return array_values(array_unique($grupos));
    }

    /**
     * Las claves EDITABLES de un correo, enteras (`emails.order_confirmation.intro`), en el orden del fichero de fábrica.
     *
     * @return list<string>
     */
    public static function claves(string $correo): array
    {
        if (! self::existe($correo)) {
            return [];
        }
        $cargador = self::cargador();
        $claves = [];
        foreach (self::CORREOS[$correo][2] as $tramo) {
            // Un tramo que es UN texto da ese bloque; si no, cada hoja del tramo.
            $unTexto = $cargador->deFabrica(self::IDIOMA_BASE, $tramo);
            $hojas = $unTexto !== null ? ['' => $unTexto] : $cargador->hojasDeFabrica(self::IDIOMA_BASE, $tramo);
            foreach ($hojas as $dentro => $texto) {
                $clave = $dentro === '' ? $tramo : $tramo.'.'.$dentro;
                if (self::seEdita($clave, $texto)) {
                    $claves[] = $clave;
                }
            }
        }

        return $claves;
    }

    /** ¿Esta clave entera es un texto editable de algún correo? */
    public static function esEditable(string $clave): bool
    {
        return isset(self::indice()[$clave]);
    }

    /** El correo al que pertenece una clave editable, o `null`. */
    public static function correoDe(string $clave): ?string
    {
        return self::indice()[$clave] ?? null;
    }

    /** El texto de fábrica de una clave en un idioma, sin lo del parque. */
    public static function fabrica(string $clave, string $locale): ?string
    {
        return self::cargador()->deFabrica($locale, $clave);
    }

    /**
     * El nombre humano de un bloque (Asunto, Chapa, Titular, Adelanto, Botón…), por el último tramo de su clave; sin
     * nombre propio, el tramo legible («Line 2» → «Line 2»), que sigue siendo único dentro de su correo.
     */
    public static function etiqueta(string $clave): string
    {
        $ultimo = substr($clave, (int) strrpos($clave, '.') + 1);
        $propia = 'admin.mail_texts.bloques.'.$ultimo;

        return Lang::has($propia) ? (string) __($propia) : Str::headline($ultimo);
    }

    /** Olvida el índice (tras cambiar los ficheros de fábrica en una prueba). */
    public static function olvidar(): void
    {
        self::$indice = null;
    }

    /** Por la clave ENTERA: su último nombre y sus tramos intermedios (ningún grupo ni correo se llama como un fragmento). */
    private static function seEdita(string $clave, string $texto): bool
    {
        $tramos = explode('.', $clave);
        if (in_array(end($tramos), self::NUNCA, true)) {
            return false;
        }
        if (array_intersect(array_slice($tramos, 0, -1), self::NUNCA_TRAMOS) !== []) {
            return false;
        }

        // Un plural (`{1} …|[2,*] …`) no se guarda con un editor de una sola frase.
        return ! str_contains($texto, '|');
    }

    /** @return array<string, string> */
    private static function indice(): array
    {
        if (self::$indice !== null) {
            return self::$indice;
        }
        $indice = [];
        foreach (array_keys(self::CORREOS) as $correo) {
            foreach (self::claves($correo) as $clave) {
                $indice[$clave] = $correo;
            }
        }

        return self::$indice = $indice;
    }

    /** El cargador del traductor, que `ContentServiceProvider` envuelve (sin él, el tipo de retorno falla aquí y lo dice). */
    private static function cargador(): MailTextLoader
    {
        return app('translation.loader');
    }
}
