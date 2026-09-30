<?php

namespace App\Notifications\Support;

use App\Domain\Booking\Contracts\PendingWork;
use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Services\PostFormAddonChanges;
use App\Domain\Content\Services\MailTexts;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\WaiverSignature;
use App\Domain\Platform\Models\Survey;
use App\Notifications as N;
use Closure;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * LA VISTA PREVIA DE UN CORREO CON SUS TEXTOS (R1·T de `specs/correos-rediseno.md` §4.2.1, `#802`): pinta el correo DE
 * VERDAD —su notificación, su `toMail()`, el molde— con el CASO REAL más reciente de su tipo (el último pedido pagado, la
 * última fiesta, la última firma…) y el BORRADOR del panel aplicado solo a ese pintado. Es el banco de la R1a
 * (`scripts/banco-correos.php`) llevado al producto, y el banco la usa: una sola fuente.
 *
 * ⚠️ Por qué el caso real y no datos inventados: cada correo compone con condiciones (con o sin fecha, con o sin señal, con
 * o sin lo que falta) y un correo pintado con otra cosa no sería el que sale. El destinatario es QUIEN MIRA (su nombre y su
 * correo en el saludo); los datos del caso son de un cliente, como en «Correos enviados» (`#794`): permiso propio y rastro.
 * ⚠️ Y dentro de una transacción que se DESHACE: algún `toMail()` escribe (el enlace de una invitación crea su fila), y una
 * vista previa no puede dejar nada. Sin el id del envío, el molde no pone marcas ni píxel (`BrandedMailMessage::markSend`).
 */
final class MailPreviews
{
    /** Los motivos por los que no hay vista previa (sus textos, `admin.mail_texts.sin_caso.*`). */
    public const MOTIVOS = ['pedido', 'reserva', 'fiesta', 'firma', 'encuesta', 'error'];

    /**
     * El correo pintado (`html`, en claro u oscuro; quien lo enseñe lo hace INERTE, `EmailSendTable::inert`) o por qué no se
     * puede (`motivo`).
     *
     * @param  array<string, string>  $borrador  clave → texto del parque (lo que hay en la pantalla, guardado o no)
     * @return array{html: string}|array{motivo: string}
     */
    public static function pintar(string $correo, string $locale, array $borrador, User $quienMira, bool $oscuro = false): array
    {
        $caso = self::caso($correo, $quienMira);
        if (is_string($caso)) {
            return ['motivo' => $caso];
        }
        [$notificacion, $destinatario] = $caso;
        if (! method_exists($notificacion, 'toMail')) {
            return ['motivo' => 'error'];
        }

        $antes = app()->getLocale();
        DB::beginTransaction();
        try {
            $html = MailTexts::conBorrador($locale, $borrador, static function () use ($notificacion, $destinatario, $locale): string {
                app()->setLocale($locale);

                return (string) $notificacion->toMail($destinatario)->render();
            });
        } catch (Throwable $e) {
            report($e);

            return ['motivo' => 'error'];
        } finally {
            DB::rollBack();
            app()->setLocale($antes);
        }

        // El oscuro, como lo pinta Outlook.com: la hoja del correo ya lo entiende por esos atributos (`MailDocument::cssOscuro`).
        if ($oscuro) {
            $html = (string) preg_replace('/<html\b/i', '<html data-ogsc data-ogsb', $html, 1);
        }

        return ['html' => $html];
    }

    /**
     * La notificación y su destinatario para un correo, o el motivo si en la base no hay todavía un caso de su tipo.
     *
     * @return array{0: Notification, 1: object}|string
     */
    public static function caso(string $correo, User $quienMira): array|string
    {
        $crear = self::constructores()[$correo] ?? null;
        if ($crear === null) {
            return 'error';
        }
        $caso = $crear($quienMira);

        return $caso instanceof Notification ? [$caso, $quienMira] : $caso;
    }

    /**
     * Cómo se construye cada correo con el caso real más reciente. Devuelve la notificación o el MOTIVO si falta el caso.
     *
     * @return array<string, Closure(User): (Notification|string)>
     */
    public static function constructores(): array
    {
        $pedido = static fn (): ?Order => Order::query()
            ->where('status', Order::STATUS_PAID)
            ->whereHas('items', static fn ($q) => $q->whereNull('parent_item_id')->whereNotNull('slot_id'))
            ->latest('id')->first();
        $reserva = static fn (?Order $o): ?OrderItem => $o?->items()->whereNull('parent_item_id')->whereNotNull('slot_id')->first();
        $fiesta = static fn (): ?OrderItem => OrderItem::query()
            ->whereNull('parent_item_id')->whereNotNull('slot_id')
            ->whereHas('ticketType', static fn ($q) => $q->where('type', TicketType::TYPE_PACK))
            ->latest('id')->first();
        $conPedido = static fn (Closure $hacer): Closure => static fn (): Notification|string => ($o = $pedido()) !== null ? $hacer($o) : 'pedido';
        $conReserva = static fn (Closure $hacer): Closure => static function () use ($pedido, $reserva, $hacer): Notification|string {
            $o = $pedido();
            $r = $reserva($o);

            return $o !== null && $r !== null ? $hacer($o, $r) : 'reserva';
        };
        $conFiesta = static fn (Closure $hacer): Closure => static fn (): Notification|string => ($f = $fiesta()) !== null ? $hacer($f) : 'fiesta';

        return [
            'order_confirmation' => $conPedido(static fn (Order $o) => new N\OrderConfirmation($o)),
            'visit_eve_notice' => $conFiesta(static fn (OrderItem $f) => new N\VisitEveNotice($f, new PendingWork(6, 10, 2, 1, 11950, true))),
            'order_payment_declined' => $conPedido(static fn (Order $o) => new N\OrderPaymentDeclined($o, '0190')),
            'order_expired_without_payment' => $conPedido(static fn (Order $o) => new N\OrderExpiredWithoutPayment($o)),
            'order_processed_after_expiration' => $conPedido(static fn (Order $o) => new N\OrderProcessedAfterExpiration($o)),
            'guest_form_request' => $conFiesta(static fn (OrderItem $f) => new N\GuestFormRequest($f)),
            'guardian_authorization_request' => $conFiesta(static fn (OrderItem $f) => new N\GuardianAuthorizationRequest($f)),
            'guardian_authorization_signed' => static function (): Notification|string {
                $firma = WaiverSignature::query()->latest('id')->first();

                return $firma !== null ? new N\GuardianAuthorizationSigned($firma) : 'firma';
            },
            'post_form_addons_changed' => $conFiesta(static fn (OrderItem $f) => new N\PostFormAddonsChanged($f, new PostFormAddonChanges(
                [['addon_id' => 1, 'name' => __('admin.mail_texts.ejemplo_extra'), 'from' => 0, 'to' => 2]], [], 1200,
            ))),
            'mixed_party_surcharge_changed' => $conFiesta(static fn (OrderItem $f) => new N\MixedPartySurchargeChanged($f, 0, 400)),
            'birthday_coming_notice' => static fn (): Notification => new N\BirthdayComingNotice(1, 'Vera', 7, __('admin.mail_texts.ejemplo_mes'), null),
            'order_item_modified' => $conReserva(static fn (Order $o, OrderItem $r) => new N\OrderItemModified($o, $r)),
            'order_item_cancelled' => $conReserva(static fn (Order $o, OrderItem $r) => new N\OrderItemCancelled($o, $r)),
            'order_item_refunded' => $conReserva(static fn (Order $o, OrderItem $r) => new N\OrderItemRefunded($o, $r, 500)),
            'order_cancelled' => $conPedido(static fn (Order $o) => new N\OrderCancelled($o)),
            'order_refunded' => $conPedido(static fn (Order $o) => new N\OrderRefunded($o)),
            'customer_account_created' => static fn (): Notification => new N\CustomerAccountCreated('temporal-123'),
            'verify_email_address' => static fn (): Notification => new N\VerifyEmailAddress,
            'verify_email_for_purchase' => static fn (): Notification => new N\VerifyEmailForPurchase('R-ABC123'),
            'login_code' => static fn (): Notification => new N\LoginCode('482913'),
            'confirmation_code' => static fn (): Notification => new N\ConfirmationCode('482913', 'change_email'),
            'password_reset' => static fn (): Notification => new N\PasswordReset('token-de-ejemplo'),
            'verify_pending_email' => static fn (): Notification => new N\VerifyPendingEmail,
            'email_change_requested' => static fn (): Notification => new N\EmailChangeRequested('n***@example.com'),
            'email_change_completed' => static fn (): Notification => new N\EmailChangeCompleted('n***@example.com'),
            'account_already_exists' => static fn (): Notification => new N\AccountAlreadyExists,
            'social_identity_linked' => static fn (): Notification => new N\SocialIdentityLinked('google'),
            'analytics_link_notice' => static fn (): Notification => new N\AnalyticsLinkNotice,
            'survey_invitation' => static function (): Notification|string {
                $encuesta = Survey::query()->first();

                return $encuesta !== null ? new N\SurveyInvitation($encuesta, 'token-de-ejemplo') : 'encuesta';
            },
        ];
    }
}
