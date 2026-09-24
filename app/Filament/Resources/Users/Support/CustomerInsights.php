<?php

namespace App\Filament\Resources\Users\Support;

use App\Domain\Booking\Models\Order;
use App\Domain\Identity\Models\User;
use App\Domain\Payments\Models\Payment;
use App\Domain\Platform\Models\AnalyticsEvent;
use App\Domain\Platform\Models\AnalyticsSession;
use App\Domain\Platform\Services\DisplayTime;
use App\Domain\Platform\Services\Money;
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
        ];
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

    /** @return array{anonymized: bool, orders: int, sold: string, collected: string, refunded: ?string, first_purchase: ?string, last_purchase: ?string, frequency: ?string, products: list<array{name: string, units: int}>, marketing: bool, identified: bool, first_source: ?string, visits_before: ?int, contacts: ?int} */
    private static function empty(bool $anonymized): array
    {
        return [
            'anonymized' => $anonymized,
            'orders' => 0, 'sold' => Money::format(0), 'collected' => Money::format(0), 'refunded' => null,
            'first_purchase' => null, 'last_purchase' => null, 'frequency' => null, 'products' => [],
            'marketing' => false, 'identified' => false, 'first_source' => null, 'visits_before' => null, 'contacts' => null,
        ];
    }
}
