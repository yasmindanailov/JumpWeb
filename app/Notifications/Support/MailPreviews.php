<?php

namespace App\Notifications\Support;

use App\Domain\Booking\Contracts\PendingWork;
use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Services\OrderItemEditor;
use App\Domain\Booking\Services\PostFormAddonChanges;
use App\Domain\Booking\Services\PostFormAddons;
use App\Domain\Content\Services\MailTexts;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\WaiverSignature;
use App\Domain\Identity\Services\WaiverProof;
use App\Domain\Platform\Models\Survey;
use App\Notifications as N;
use Closure;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * LA VISTA PREVIA DE UN CORREO CON SUS TEXTOS (R1·T de `specs/correos-rediseno.md` §4.2.1, `#802`): pinta el correo DE
 * VERDAD —su notificación, su `toMail()`, el molde— con el CASO REAL más reciente de su tipo (el último pedido pagado, la
 * última fiesta, la última firma en ese idioma…) y el BORRADOR del panel aplicado solo a ese pintado. Es el banco de la R1a
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
    /**
     * Los motivos por los que no hay vista previa (sus textos, `admin.mail_texts.sin_caso.*`). Los cuatro últimos, de una
     * situación que necesita un caso real de su CLASE (R1·T2, `#809`): eso lo decide el catálogo del parque y no se finge.
     */
    public const MOTIVOS = ['pedido', 'reserva', 'fiesta', 'firma', 'encuesta', 'error', 'pedido_entradas', 'pedido_cumpleanos', 'fiesta_con_extras', 'firma_de_reserva'];

    /** Entre cuántos casos recientes se busca uno de una clase (R1·T2): acota la consulta, que se hace al pintar. */
    public const RECIENTES = 200;

    /**
     * El correo pintado (`html`, en claro u oscuro; quien lo enseñe lo hace INERTE, `EmailSendTable::inert`) con lo que se lee
     * en la BANDEJA antes de abrirlo —el `asunto` y el `adelanto`, dos bloques que el parque edita y que el cuerpo no enseña—,
     * o por qué no se puede (`motivo`).
     *
     * @param  array<string, string>  $borrador  clave → texto del parque (lo que hay en la pantalla, guardado o no)
     * @param  ?string  $situacion  una de `MailSituations::de($correo)` (R1·T2); otra o ninguna, la primera
     * @return array{html: string, asunto: string, adelanto: string}|array{motivo: string}
     */
    public static function pintar(string $correo, string $locale, array $borrador, User $quienMira, bool $oscuro = false, ?string $situacion = null): array
    {
        $antes = app()->getLocale();
        // ⚠️ El CASO se arma DENTRO de la transacción que se deshace: una situación le cambia cosas en memoria, y si alguna
        // llegara a escribirse por un camino que no se ve, tampoco quedaría (R1·T2).
        DB::beginTransaction();
        try {
            $caso = self::caso($correo, $quienMira, $locale, $situacion);
            if (is_string($caso)) {
                return ['motivo' => $caso];
            }
            [$notificacion, $destinatario] = $caso;
            if (! method_exists($notificacion, 'toMail')) {
                return ['motivo' => 'error'];
            }

            [$html, $asunto, $adelanto] = MailTexts::conBorrador($locale, $borrador, static function () use ($notificacion, $destinatario, $locale): array {
                app()->setLocale($locale);
                $mensaje = $notificacion->toMail($destinatario);

                return [(string) $mensaje->render(), (string) $mensaje->subject, (string) ($mensaje->viewData['preheader'] ?? '')];
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

        return ['html' => $html, 'asunto' => $asunto, 'adelanto' => $adelanto];
    }

    /**
     * La notificación y su destinatario para un correo, o el motivo si en la base no hay todavía un caso de su tipo. El
     * IDIOMA cuenta cuando el caso trae el suyo: la copia de una autorización sale en el de la versión firmada, así que su caso
     * es una firma en el idioma de la pestaña (con la de otro, lo escrito en esta no se vería: medido el 30-09 en en/fr).
     *
     * La SITUACIÓN (R1·T2, `MailSituations`): la pedida si es de ese correo; si no, su primera.
     *
     * @return array{0: Notification, 1: object}|string
     */
    public static function caso(string $correo, User $quienMira, string $locale, ?string $situacion = null): array|string
    {
        $crear = self::constructores()[$correo] ?? null;
        if ($crear === null) {
            return 'error';
        }
        $caso = $crear($quienMira, $locale, MailSituations::elegida($correo, $situacion));

        return $caso instanceof Notification ? [$caso, $quienMira] : $caso;
    }

    /**
     * Cómo se construye cada correo con el caso real más reciente (en el idioma de la pestaña, si el caso lo trae) y, desde la
     * R1·T2 (`#809`), en cada SITUACIÓN de `MailSituations`: el caso con un cambio de ejemplo EN MEMORIA —el dato que el correo
     * recibe, o un atributo de su caso—, nunca guardado. Tres situaciones las decide el CATÁLOGO del parque (si el pedido lleva
     * lista de invitados, si la fiesta tiene extras abiertos, si la firma es de una reserva): esas toman el último caso real de
     * su clase, porque fingirlas sería pintar un correo que el parque no manda. Devuelve la notificación o el MOTIVO.
     *
     * @return array<string, Closure(User, string, ?string): (Notification|string)>
     */
    public static function constructores(): array
    {
        $pedidos = static fn () => Order::query()
            ->where('status', Order::STATUS_PAID)
            ->whereHas('items', static fn ($q) => $q->whereNull('parent_item_id')->whereNotNull('slot_id'))
            ->latest('id');
        $pedido = static fn (): ?Order => $pedidos()->first();
        $pedidoDe = static fn (bool $conLista): ?Order => $pedidos()->limit(self::RECIENTES)->get()
            ->first(static fn (Order $o): bool => $o->needsGuestForm() === $conLista);
        $reserva = static fn (?Order $o): ?OrderItem => $o?->items()->whereNull('parent_item_id')->whereNotNull('slot_id')->first();
        $fiestas = static fn () => OrderItem::query()
            ->whereNull('parent_item_id')->whereNotNull('slot_id')
            ->whereHas('ticketType', static fn ($q) => $q->where('type', TicketType::TYPE_PACK))
            ->latest('id');
        $fiesta = static fn (): ?OrderItem => $fiestas()->first();
        $conPedido = static fn (Closure $hacer): Closure => static fn (): Notification|string => ($o = $pedido()) !== null ? $hacer($o) : 'pedido';
        $conReserva = static fn (Closure $hacer): Closure => static function (User $quienMira, string $locale, ?string $s) use ($pedido, $reserva, $hacer): Notification|string {
            $o = $pedido();
            $r = $reserva($o);

            return $o !== null && $r !== null ? $hacer($o, $r, $s) : 'reserva';
        };
        $conFiesta = static fn (Closure $hacer): Closure => static fn (User $quienMira, string $locale, ?string $s): Notification|string => ($f = $fiesta()) !== null ? $hacer($f, $s) : 'fiesta';

        return [
            // Con o sin lista de invitados lo decide el catálogo: un caso real de su clase. «Sin un día único», en memoria: las
            // franjas del caso, fuera (`singleVisitDate()` y el resguardo leen lo cargado).
            'order_confirmation' => static function (User $quienMira, string $locale, ?string $s) use ($pedido, $pedidoDe): Notification|string {
                $o = match ($s) {
                    'entradas' => $pedidoDe(false),
                    'cumpleanos' => $pedidoDe(true),
                    default => $pedido(),
                };
                if ($o === null) {
                    return match ($s) {
                        'entradas' => 'pedido_entradas',
                        'cumpleanos' => 'pedido_cumpleanos',
                        default => 'pedido',
                    };
                }
                if ($s === 'sin_un_dia') {
                    $o->load(['items.ticketType', 'items.slot']);
                    $o->items->each(static fn (OrderItem $i) => $i->setRelation('slot', null));
                }

                return new N\OrderConfirmation($o);
            },
            // Quien cumple, con su nombre o sin él: su fila en memoria. ⚠️ `honoreeName()` lee la ficha 0 y, SIN nombre ahí, el
            // homenajeado de la reserva (`event_data`): «aún sin nombre» vacía los dos (medido: con solo la ficha, salía nombrado).
            'visit_eve_notice' => $conFiesta(static function (OrderItem $f, ?string $s): Notification {
                $clave = $f->ticketType?->guestNameFieldKey();
                if ($clave !== null && in_array($s, ['cumple_con_nombre', 'cumple_sin_nombre'], true)) {
                    $fichas = $f->guestData();
                    $fichas[OrderItem::HONOREE_ROW_INDEX] = [$clave => $s === 'cumple_con_nombre' ? (string) __('admin.mail_texts.ejemplo_nombre') : ''];
                    $f->setAttribute('honoree_row', true);
                    $f->setAttribute('guest_data', $fichas);
                    $homenajeado = $f->ticketType->celebrantNameFieldKey();
                    if ($s === 'cumple_sin_nombre' && $homenajeado !== null) {
                        $f->setAttribute('event_data', [$homenajeado => ''] + (is_array($f->event_data) ? $f->event_data : []));
                    }
                }

                return new N\VisitEveNotice($f, new PendingWork(6, 10, 2, 1, 11950, true));
            }),
            'order_payment_declined' => $conPedido(static fn (Order $o) => new N\OrderPaymentDeclined($o, '0190')),
            'order_expired_without_payment' => $conPedido(static fn (Order $o) => new N\OrderExpiredWithoutPayment($o)),
            'order_processed_after_expiration' => $conPedido(static fn (Order $o) => new N\OrderProcessedAfterExpiration($o)),
            // La invitación digital y la fecha, en memoria (un atributo del producto y la franja); los extras abiertos los
            // decide el catálogo: la última fiesta que los tenga.
            'guest_form_request' => static function (User $quienMira, string $locale, ?string $s) use ($fiesta, $fiestas): Notification|string {
                $f = $s === 'con_extras'
                    ? $fiestas()->limit(self::RECIENTES)->get()->first(static fn (OrderItem $x): bool => app(PostFormAddons::class)->offerableFor($x)->isNotEmpty())
                    : $fiesta();
                if ($f === null) {
                    return $s === 'con_extras' ? 'fiesta_con_extras' : 'fiesta';
                }
                if (in_array($s, ['con_invitacion', 'sin_invitacion'], true)) {
                    $f->ticketType?->setAttribute('guest_invitation', $s === 'con_invitacion');
                }
                if ($s === 'reserva_sin_fecha') {
                    $f->setRelation('slot', null);
                }

                return new N\GuestFormRequest($f);
            },
            'guardian_authorization_request' => $conFiesta(static fn (OrderItem $f) => new N\GuardianAuthorizationRequest($f)),
            // La copia sale en el idioma de la versión FIRMADA (`GuardianAuthorizationSigned`), no en el de la pestaña: el caso
            // es la última firma EN ESE idioma. (Y con su versión: una firma sin ella no tiene texto que copiar.) La de una
            // reserva, la última que lo sea (lo decide la firma, no se finge).
            'guardian_authorization_signed' => static function (User $quienMira, string $locale, ?string $s): Notification|string {
                $firmas = WaiverSignature::query()
                    ->whereHas('version', static fn ($q) => $q->where('locale', $locale))
                    ->latest('id');
                $firma = $s === 'firma_de_reserva'
                    ? $firmas->limit(self::RECIENTES)->get()->first(static fn (WaiverSignature $x): bool => WaiverProof::make($x)->orderCode() !== null)
                    : $firmas->first();
                if ($firma === null) {
                    return $s === 'firma_de_reserva' ? 'firma_de_reserva' : 'firma';
                }

                return new N\GuardianAuthorizationSigned($firma);
            },
            // Un extra que se añade (sube), que cambia de cantidad (sube) o que se quita (baja).
            'post_form_addons_changed' => $conFiesta(static function (OrderItem $f, ?string $s): Notification {
                $extra = (string) __('admin.mail_texts.ejemplo_extra');
                [$movimiento, $delta] = match ($s) {
                    'extra_cambiado' => [['addon_id' => 1, 'name' => $extra, 'from' => 2, 'to' => 3], 600],
                    'extra_quitado' => [['addon_id' => 1, 'name' => $extra, 'from' => 2, 'to' => 0], -1200],
                    default => [['addon_id' => 1, 'name' => $extra, 'from' => 0, 'to' => 2], 1200],
                };

                return new N\PostFormAddonsChanged($f, new PostFormAddonChanges([$movimiento], [], $delta));
            }),
            // El suplemento, de cuánto a cuánto (en céntimos; negativo, descuento) y quién lo cambió.
            'mixed_party_surcharge_changed' => $conFiesta(static function (OrderItem $f, ?string $s): Notification {
                [$antes, $ahora] = match ($s) {
                    'suplemento_cambia' => [400, 600],
                    'suplemento_se_quita' => [400, 0],
                    'descuento_nace' => [0, -300],
                    'descuento_cambia' => [-300, -500],
                    'descuento_se_quita' => [-300, 0],
                    'cambia_de_signo' => [400, -300],
                    default => [0, 400],
                };

                return new N\MixedPartySurchargeChanged($f, $antes, $ahora, $s !== 'por_parque');
            }),
            'birthday_coming_notice' => static fn (User $quienMira, string $locale, ?string $s): Notification => new N\BirthdayComingNotice(
                1, 'Vera', 7, __('admin.mail_texts.ejemplo_mes'), $s === 'con_desde' ? (string) __('admin.mail_texts.ejemplo_desde') : null,
            ),
            // Siempre CON un cambio (`#809`): el de la situación, armado con los datos del caso como lo hace el producto.
            'order_item_modified' => $conReserva(static function (Order $o, OrderItem $r, ?string $s): Notification {
                $nueva = $r->slot?->replicate();
                $nueva?->setAttribute('date', $r->slot->date->copy()->addWeek());
                $cambio = match ($s) {
                    'cambio_cantidad' => ['quantity_change' => ['old' => (int) $r->quantity, 'new' => (int) $r->quantity + 2]],
                    'cambio_producto' => ['product_change' => ['old' => $r->displayProductName(), 'new' => (string) __('admin.mail_texts.ejemplo_producto')]],
                    'cambio_datos' => ['event_data_change' => true],
                    'cambio_complementos' => ['addon_change' => ['added' => [['name' => (string) __('admin.mail_texts.ejemplo_extra'), 'qty' => 2]], 'removed' => [], 'updated' => []]],
                    default => ['slot_change' => ['old' => OrderItemEditor::humanSlotLabel($r->slot), 'new' => OrderItemEditor::humanSlotLabel($nueva)]],
                };

                return new N\OrderItemModified($o, $r, $cambio);
            }),
            'order_item_cancelled' => $conReserva(static fn (Order $o, OrderItem $r, ?string $s) => new N\OrderItemCancelled($o, $r, $s === 'caen_complementos' ? 2 : 0)),
            'order_item_refunded' => $conReserva(static fn (Order $o, OrderItem $r, ?string $s) => new N\OrderItemRefunded($o, $r, 500, $s === 'tambien_cancelada', $s === 'devolucion_a_mano')),
            'order_cancelled' => $conPedido(static fn (Order $o) => new N\OrderCancelled($o)),
            'order_refunded' => static fn (User $quienMira, string $locale, ?string $s): Notification|string => ($o = $pedido()) !== null
                ? new N\OrderRefunded($o, null, $s === 'tambien_cancelado', $s === 'devolucion_a_mano')
                : 'pedido',
            'customer_account_created' => static fn (): Notification => new N\CustomerAccountCreated,
            'verify_email_address' => static fn (): Notification => new N\VerifyEmailAddress,
            'verify_email_for_purchase' => static fn (): Notification => new N\VerifyEmailForPurchase('R-ABC123'),
            'login_code' => static fn (): Notification => new N\LoginCode('482913'),
            'confirmation_code' => static fn (): Notification => new N\ConfirmationCode('482913', 'change_email'),
            // Con su código (`#856`, `AccountProfile::sendNewEmailCode`): desde la A5 (`#869`) no hay otra versión.
            'verify_pending_email' => static fn (): Notification => new N\VerifyPendingEmail('482913'),
            'email_change_requested' => static fn (): Notification => new N\EmailChangeRequested('n***@example.com'),
            'email_change_completed' => static fn (): Notification => new N\EmailChangeCompleted('n***@example.com'),
            'account_already_exists' => static fn (): Notification => new N\AccountAlreadyExists,
            'social_identity_linked' => static fn (User $quienMira, string $locale, ?string $s): Notification => new N\SocialIdentityLinked('google', $s === 'google_verifico'),
            'analytics_link_notice' => static fn (): Notification => new N\AnalyticsLinkNotice,
            // Sin la entrada propia de la encuesta (en memoria), el correo dice la frase de la casa.
            'survey_invitation' => static function (User $quienMira, string $locale, ?string $s): Notification|string {
                $encuesta = Survey::query()->first();
                if ($encuesta === null) {
                    return 'encuesta';
                }
                if ($s === 'encuesta_sin_entrada') {
                    $encuesta->setAttribute('intro', []);
                }

                return new N\SurveyInvitation($encuesta, 'token-de-ejemplo');
            },
        ];
    }
}
