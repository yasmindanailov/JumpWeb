<?php

namespace App\Filament\Resources\Users\Support;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderAdjustment;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Identity\Models\User;
use App\Domain\Payments\Models\Payment;
use App\Domain\Platform\Models\AnalyticsEvent;
use App\Domain\Platform\Models\AnalyticsSession;
use App\Domain\Platform\Services\DisplayTime;
use App\Domain\Platform\Services\Money;
use App\Domain\Platform\Services\Surveys\QuestionSchema;
use App\Filament\Analytics\PartiesReport;
use App\Filament\Analytics\SurveysReport;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * **La 360 del cliente** (`docs/specs/analitica.md` §4.6, T4a): el READ-MODEL de «qué sabemos de esta persona
 * como cliente» que enseña la ficha del panel bajo el permiso `customers.insights`. Es capa de ENTREGA, como
 * `App\Filament\Analytics\*`: cruza Booking (pedidos y líneas), Payments (cobros y devoluciones), Identity (la
 * cuenta) y Platform (el libro de eventos), que es lo que el composition root puede ver.
 *
 * Dos regímenes, a propósito (§4.6, revisión 23-09):
 *  - **Lo que es del CONTRATO** —pedidos, lo vendido, lo cobrado, lo devuelto, la primera y la última compra, la
 *    frecuencia, los productos— sale de los pedidos y sale SIEMPRE: son hechos de la relación comercial.
 *  - **Lo que es de la NAVEGACIÓN** —la primera fuente y campaña (`users.first_attribution`), las visitas antes
 *    de la primera compra, los contactos recibidos— existe SOLO en el régimen identificado (categoría
 *    `analytics` consentida y sin oposición): si la cuenta no tiene sesiones atadas, la ficha lo dice y no
 *    inventa. Nada de aquí lee `dependents`: los menores tienen su sección y su regla (`HolderDependents`).
 *
 * Los importes son los del libro: `sold` = Σ `orders.total` de los pedidos cobrados (pagados o devueltos),
 * `collected` = Σ cobros confirmados, `refunded` = Σ devoluciones hechas — las mismas tres cifras que
 * `MoneyReport`, para que la ficha y el cuadro digan lo mismo. **Cuenta ANONIMIZADA: nada** (`RGPD-01`).
 * Cada celda se compone AQUÍ, no en la vista (la lección de `HolderDependents`).
 */
final class CustomerInsights
{
    /** Los pedidos que cuentan como compra: los cobrados, aunque después se devolvieran (`MoneyReport`). */
    private const COLLECTED_STATUSES = [Order::STATUS_PAID, Order::STATUS_REFUNDED];

    /** Cuántos productos se nombran, de más a menos vendido. */
    private const TOP_PRODUCTS = 5;

    /**
     * @return array{
     *   anonymized: bool,
     *   orders: int, sold: string, collected: string, refunded: ?string,
     *   first_purchase: ?string, last_purchase: ?string, frequency: ?string,
     *   products: list<array{name: string, units: int}>,
     *   marketing: bool,
     *   identified: bool, first_source: ?string, visits_before: ?int, contacts: ?int,
     *   parties: array{count: int, forms_completed: int, invitations: int, replies_yes: int, signatures: int, extras_after: string, came_as_guest: ?string},
     * }
     */
    public static function forCustomer(User $customer): array
    {
        if ($customer->isAnonymized()) {
            return self::empty(anonymized: true);
        }

        $userId = (int) $customer->getKey();

        $orders = DB::table('orders')
            ->where('user_id', $userId)
            ->whereIn('status', self::COLLECTED_STATUSES)
            ->selectRaw('COUNT(*) AS n, COALESCE(SUM(total), 0) AS sold, MIN(paid_at) AS first_at, MAX(paid_at) AS last_at')
            ->first();

        $count = (int) ($orders->n ?? 0);
        $orderIds = DB::table('orders')->where('user_id', $userId)->whereIn('status', self::COLLECTED_STATUSES)->pluck('id');

        $collected = $count === 0 ? 0 : (int) Payment::query()
            ->where('payable_type', (new Order)->getMorphClass())
            ->whereIn('payable_id', $orderIds)
            ->where('status', Payment::STATUS_PAID)
            ->sum('amount');

        $refunded = $count === 0 ? 0 : (int) DB::table('payment_refunds')
            ->join('payments', 'payments.id', '=', 'payment_refunds.payment_id')
            ->where('payments.payable_type', (new Order)->getMorphClass())
            ->whereIn('payments.payable_id', $orderIds)
            ->where('payment_refunds.status', 'succeeded')
            ->sum('payment_refunds.amount_cents');

        $first = $orders->first_at === null ? null : Carbon::parse((string) $orders->first_at);
        $last = $orders->last_at === null ? null : Carbon::parse((string) $orders->last_at);

        // Lo de la NAVEGACIÓN: solo con sesiones atadas a la cuenta (régimen identificado).
        $identified = AnalyticsSession::query()->where('user_id', $userId)->exists();
        $firstAttribution = $customer->getAttribute('first_attribution');
        $visitsBefore = null;
        $contacts = null;

        if ($identified) {
            $before = AnalyticsSession::query()->where('user_id', $userId);
            if ($first !== null) {
                $before->where('started_at', '<', $first);
            }
            $visitsBefore = (int) $before->count();
            $contacts = (int) AnalyticsEvent::query()->where('user_id', $userId)->where('name', 'contact_received')->count();
        }

        return [
            'anonymized' => false,
            'orders' => $count,
            'sold' => Money::format((int) ($orders->sold ?? 0)),
            'collected' => Money::format($collected),
            'refunded' => $refunded > 0 ? Money::format($refunded) : null,
            'first_purchase' => $first === null ? null : DisplayTime::format($first, 'd/m/Y'),
            'last_purchase' => $last === null ? null : DisplayTime::format($last, 'd/m/Y'),
            'frequency' => self::frequency($count, $first, $last),
            'products' => $count === 0 ? [] : self::products($orderIds->all()),
            'marketing' => (bool) $customer->marketing_opt_in,
            'identified' => $identified,
            'first_source' => is_array($firstAttribution) ? self::source($firstAttribution) : null,
            'visits_before' => $visitsBefore,
            'contacts' => $contacts,
            'parties' => self::parties($customer, $orderIds->all(), $first),
            'surveys' => self::surveys($userId),
        ];
    }

    /**
     * **Las ENCUESTAS de este cliente** (T4 de `specs/encuestas.md` §4.4; `[DECIDIDO owner]` §7·1: atadas a la persona
     * con permiso propio): cuántas contestó y la ÚLTIMA —el día, el canal, la encuesta, su nota de escala si la hay
     * y su texto libre—. Es lo que permite llamar tras una mala visita.
     *
     * @return array{answered: int, last_on: ?string, last_channel: ?string, last_survey: ?string, last_score: ?int, last_text: ?string}
     */
    private static function surveys(int $userId): array
    {
        $rows = DB::table('survey_responses as r')
            ->join('surveys as s', 's.id', '=', 'r.survey_id')
            ->where('r.user_id', $userId)
            ->whereNotNull('r.answered_at')
            ->orderByDesc('r.answered_at')
            ->orderByDesc('r.id')
            ->select(['r.answered_at', 'r.channel', 'r.answers', 's.name', 's.key', 's.questions'])
            ->get();

        if ($rows->isEmpty()) {
            return self::noSurveys();
        }

        $last = $rows->first();
        $answers = is_string($last->answers) ? (array) json_decode($last->answers, true) : [];
        $score = null;
        $text = null;
        foreach (QuestionSchema::normalize(is_string($last->questions) ? json_decode($last->questions, true) : null) as $q) {
            $value = $answers[$q['key']] ?? null;
            if ($q['type'] === QuestionSchema::TYPE_SCALE && $score === null && is_int($value)) {
                $score = $value;
            }
            if ($q['type'] === QuestionSchema::TYPE_TEXT && $text === null && is_string($value) && trim($value) !== '') {
                $text = $value;
            }
        }

        return [
            'answered' => $rows->count(),
            'last_on' => DisplayTime::format((string) $last->answered_at, 'd/m/Y'),
            'last_channel' => (string) $last->channel,
            'last_survey' => SurveysReport::nameOf($last->name, (string) $last->key),
            'last_score' => $score,
            'last_text' => $text,
        ];
    }

    /** @return array{answered: int, last_on: null, last_channel: null, last_survey: null, last_score: null, last_text: null} */
    private static function noSurveys(): array
    {
        return ['answered' => 0, 'last_on' => null, 'last_channel' => null, 'last_survey' => null, 'last_score' => null, 'last_text' => null];
    }

    /**
     * **Las FIESTAS de este cliente** (T3 de `specs/analitica-fiesta.md` §4.4): desde sus pedidos cobrados —las
     * reservas de pack, los formularios completados, las invitaciones activadas, las respuestas «sí», los
     * justificantes firmados y los extras vendidos después de reservar (los mismos motivos del libro que
     * `PartiesReport`)— y si VINO INVITADO antes de comprar: su correo firmó un justificante de menor invitado
     * antes de su primera compra (la regla del segmento `guest_became_customer`). Régimen del contrato: sale siempre.
     *
     * @param  list<int>  $orderIds
     * @return array{count: int, forms_completed: int, invitations: int, replies_yes: int, signatures: int, extras_after: string, came_as_guest: ?string}
     */
    private static function parties(User $customer, array $orderIds, ?Carbon $firstPurchase): array
    {
        $out = self::noParties();

        $email = strtolower(trim((string) $customer->email));
        if ($email !== '') {
            $guest = DB::table('guardian_authorizations')
                ->whereNotNull('guardian_email')
                ->whereRaw('lower(guardian_email) = ?', [$email])
                ->when($firstPurchase !== null, static fn ($query) => $query->where('created_at', '<', $firstPurchase))
                ->min('created_at');
            $out['came_as_guest'] = $guest === null ? null : DisplayTime::format((string) $guest, 'd/m/Y');
        }

        if ($orderIds === []) {
            return $out;
        }

        $reservations = DB::table('order_items as i')
            ->join('ticket_types as t', 't.id', '=', 'i.ticket_type_id')
            ->whereIn('i.order_id', $orderIds)
            ->whereNull('i.parent_item_id')
            ->whereNull('i.cancelled_at')
            ->where('t.type', TicketType::TYPE_PACK)
            ->select(['i.id', 'i.guest_form_completed_at'])
            ->get();
        $ids = $reservations->pluck('id')->map(static fn ($id): int => (int) $id)->all();

        if ($ids === []) {
            return $out;
        }

        $out['count'] = count($ids);
        $out['forms_completed'] = $reservations->whereNotNull('guest_form_completed_at')->count();
        $out['invitations'] = (int) DB::table('party_invitations')->whereIn('order_item_id', $ids)->count();
        $out['replies_yes'] = (int) DB::table('invitation_replies')->whereIn('order_item_id', $ids)->where('attending', true)->whereNull('dismissed_at')->count();
        $out['signatures'] = (int) DB::table('guardian_authorizations')->whereIn('order_item_id', $ids)->count();
        $out['extras_after'] = Money::format((int) DB::table('order_adjustments as a')
            ->join('order_items as ai', 'ai.id', '=', 'a.order_item_id')
            ->where('a.type', OrderAdjustment::TYPE_EDIT)
            ->whereIn('a.reason', PartiesReport::EXTRA_REASONS)
            ->where(static function ($query) use ($ids): void {
                $query->whereIn('ai.parent_item_id', $ids)->orWhereIn('ai.id', $ids);
            })
            ->sum('a.amount_cents'));

        return $out;
    }

    /** @return array{count: int, forms_completed: int, invitations: int, replies_yes: int, signatures: int, extras_after: string, came_as_guest: ?string} */
    private static function noParties(): array
    {
        return ['count' => 0, 'forms_completed' => 0, 'invitations' => 0, 'replies_yes' => 0, 'signatures' => 0, 'extras_after' => Money::format(0), 'came_as_guest' => null];
    }

    /**
     * Cada cuánto compra: los pedidos por año entre la primera y la última compra. Con UNA compra no hay
     * frecuencia que contar; con dos en el mismo día, tampoco.
     */
    private static function frequency(int $count, ?\DateTimeInterface $first, ?\DateTimeInterface $last): ?string
    {
        if ($count < 2 || $first === null || $last === null) {
            return null;
        }

        $days = max(1, (int) round(($last->getTimestamp() - $first->getTimestamp()) / 86400));

        if ($days < 30) {
            return (string) __('admin.users.insights.frequency_burst', ['n' => $count, 'days' => $days]);
        }

        $perYear = round($count * 365 / $days, 1);

        return (string) __('admin.users.insights.frequency_per_year', ['n' => rtrim(rtrim(number_format($perYear, 1, ',', '.'), '0'), ',')]);
    }

    /**
     * Los productos más comprados, por unidades, con el nombre del tipo de entrada en el idioma del panel.
     *
     * @param  list<int>  $orderIds
     * @return list<array{name: string, units: int}>
     */
    private static function products(array $orderIds): array
    {
        $rows = DB::table('order_items')
            ->join('ticket_types', 'ticket_types.id', '=', 'order_items.ticket_type_id')
            ->whereIn('order_items.order_id', $orderIds)
            ->groupBy('order_items.ticket_type_id', 'ticket_types.name')
            ->selectRaw('ticket_types.name AS name, SUM(order_items.quantity) AS units')
            ->orderByDesc('units')
            ->limit(self::TOP_PRODUCTS)
            ->get();

        $locale = app()->getLocale();

        return $rows->map(static function (object $row) use ($locale): array {
            $name = json_decode((string) $row->name, true);
            $label = is_array($name) ? ($name[$locale] ?? $name['es'] ?? (string) reset($name)) : (string) $row->name;

            return ['name' => (string) $label, 'units' => (int) $row->units];
        })->all();
    }

    /** «google / cpc · verano»: la primera fuente, en una línea. */
    private static function source(array $attribution): string
    {
        $line = trim((string) ($attribution['source'] ?? '')).' / '.trim((string) ($attribution['medium'] ?? ''));
        $campaign = $attribution['campaign'] ?? null;

        return is_string($campaign) && $campaign !== '' ? $line.' · '.$campaign : $line;
    }

    /** @return array{anonymized: bool, orders: int, sold: string, collected: string, refunded: ?string, first_purchase: ?string, last_purchase: ?string, frequency: ?string, products: list<array{name: string, units: int}>, marketing: bool, identified: bool, first_source: ?string, visits_before: ?int, contacts: ?int, parties: array{count: int, forms_completed: int, invitations: int, replies_yes: int, signatures: int, extras_after: string, came_as_guest: ?string}} */
    private static function empty(bool $anonymized): array
    {
        return [
            'anonymized' => $anonymized,
            'orders' => 0, 'sold' => Money::format(0), 'collected' => Money::format(0), 'refunded' => null,
            'first_purchase' => null, 'last_purchase' => null, 'frequency' => null, 'products' => [],
            'marketing' => false, 'identified' => false, 'first_source' => null, 'visits_before' => null, 'contacts' => null,
            'parties' => self::noParties(),
            'surveys' => self::noSurveys(),
        ];
    }
}
