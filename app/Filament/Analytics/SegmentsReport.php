<?php

namespace App\Filament\Analytics;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Services\DisplayTime;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * **Los segmentos de clientes** (`docs/specs/analitica.md` §4.6, T4b): cuatro preguntas que el parque se hace
 * para volver a hablar con alguien, respondidas desde los PEDIDOS y el libro —nunca desde `dependents.born_on`
 * (revisión 23-09: los menores no se segmentan)—. Capa de ENTREGA, como los informes del cuadro.
 *
 *  - `once_never_back` — compró UNA vez (un solo pedido cobrado) y no ha vuelto en {@see ONCE_DAYS} días.
 *  - `party_year_ago` — su ÚLTIMA fiesta (un pedido con un pack, `ticket_types.type = pack`) fue hace entre
 *    {@see PARTY_FROM_MONTHS} y {@see PARTY_TO_MONTHS} meses: el cumpleaños del año que viene se acerca.
 *    Calculado desde los pedidos, nunca desde la fecha de nacimiento de un menor.
 *  - `guest_no_purchase` — vino invitado (un responsable que autorizó a un menor invitado, con correo) y ese
 *    correo no tiene ninguna compra. Cuenta personas SIN cuenta.
 *  - `guest_became_customer` — vino invitado (su correo firmó un justificante de menor invitado) y DESPUÉS compró
 *    con una cuenta (T3 de `specs/analitica-fiesta.md`): la invitación trajo un cliente.
 *  - `contact_no_order` — escribió (`contact_received` en el régimen identificado) y no tiene pedido cobrado.
 *
 * Dos cifras por segmento: cuántos son y cuántos tienen `marketing_opt_in` (a cuántos se les podría escribir). ⚠️ **Solo
 * recuentos** (TP·3b de `specs/analitica-para-decidir.md` §4.14, `#793`): la lista de personas y su exportación se
 * retiraron —el público es anónimo, nada del cuadro sale con nombres—. Las cuentas anonimizadas nunca cuentan. Los
 * recuentos se memorizan {@see CACHE_SECONDS}.
 */
final class SegmentsReport
{
    public const CACHE_SECONDS = 300;

    public const ONCE_NEVER_BACK = 'once_never_back';

    public const PARTY_YEAR_AGO = 'party_year_ago';

    public const GUEST_NO_PURCHASE = 'guest_no_purchase';

    public const CONTACT_NO_ORDER = 'contact_no_order';

    /**
     * Vino INVITADO y DESPUÉS compró (T3 de `specs/analitica-fiesta.md` §4.4, `#739`): una cuenta de cliente cuyo
     * correo firmó un justificante de menor invitado ANTES de su primera compra cobrada. Son clientes con cuenta:
     * se exportan con opt-in como los demás.
     */
    public const GUEST_BECAME_CUSTOMER = 'guest_became_customer';

    /** @var list<string> */
    public const SEGMENTS = [self::ONCE_NEVER_BACK, self::PARTY_YEAR_AGO, self::GUEST_NO_PURCHASE, self::GUEST_BECAME_CUSTOMER, self::CONTACT_NO_ORDER];

    /** Días sin volver para que «compró una vez» cuente como «y no volvió»: una temporada. */
    public const ONCE_DAYS = 90;

    public const PARTY_FROM_MONTHS = 10;

    public const PARTY_TO_MONTHS = 12;

    /** @var list<string> */
    private const COLLECTED_STATUSES = [Order::STATUS_PAID, Order::STATUS_REFUNDED];

    /**
     * Los cuatro recuentos, memorizados.
     *
     * @return array<string, array{size: int, opt_in: int}>
     */
    public static function counts(): array
    {
        return Cache::remember('analytics:segments:v1', self::CACHE_SECONDS, static fn (): array => (new self)->compute());
    }

    /** @return array<string, array{size: int, opt_in: int}> */
    public function compute(): array
    {
        $out = [];

        foreach (self::SEGMENTS as $segment) {
            $out[$segment] = $segment === self::GUEST_NO_PURCHASE
                ? $this->guests()
                : ['size' => (int) $this->users($segment)->count(), 'opt_in' => (int) $this->users($segment)->where('users.marketing_opt_in', true)->count()];
        }

        return $out;
    }

    /** Las cuentas de CLIENTE (sin rol de equipo) y sin anonimizar, que es de donde salen tres segmentos. */
    private function customers(): Builder
    {
        return DB::table('users')
            ->where('users.email', 'not like', '%@'.User::ANONYMIZED_EMAIL_DOMAIN)
            ->whereNotExists(function (Builder $query): void {
                $query->select(DB::raw(1))
                    ->from('role_user')
                    ->join('roles', 'roles.id', '=', 'role_user.role_id')
                    ->whereColumn('role_user.user_id', 'users.id')
                    ->whereIn('roles.name', User::PANEL_ROLES);
            });
    }

    /** El constructor de un segmento con cuenta. */
    private function users(string $segment): Builder
    {
        $now = DisplayTime::now();

        return match ($segment) {
            self::ONCE_NEVER_BACK => $this->customers()
                ->whereRaw('(select count(*) from orders where orders.user_id = users.id and orders.status in (?, ?)) = 1', self::COLLECTED_STATUSES)
                ->whereRaw('(select max(paid_at) from orders where orders.user_id = users.id and orders.status in (?, ?)) < ?', [...self::COLLECTED_STATUSES, $now->copy()->subDays(self::ONCE_DAYS)]),
            self::PARTY_YEAR_AGO => $this->customers()
                ->whereRaw('(select max(orders.paid_at) from orders join order_items on order_items.order_id = orders.id join ticket_types on ticket_types.id = order_items.ticket_type_id where orders.user_id = users.id and orders.status in (?, ?) and ticket_types.type = ?) between ? and ?', [
                    ...self::COLLECTED_STATUSES, TicketType::TYPE_PACK,
                    $now->copy()->subMonths(self::PARTY_TO_MONTHS), $now->copy()->subMonths(self::PARTY_FROM_MONTHS),
                ]),
            // El correo se compara en minúsculas (la misma persona escrita de dos formas) y la firma tiene que ser
            // ANTERIOR a la primera compra: quien firmó siendo ya cliente no «vino invitado y luego compró».
            self::GUEST_BECAME_CUSTOMER => $this->customers()
                ->whereRaw('(select min(orders.paid_at) from orders where orders.user_id = users.id and orders.status in (?, ?)) > (select min(ga.created_at) from guardian_authorizations ga where ga.guardian_email is not null and lower(ga.guardian_email) = lower(users.email))', self::COLLECTED_STATUSES),
            self::CONTACT_NO_ORDER => $this->customers()
                ->whereExists(function (Builder $query): void {
                    $query->select(DB::raw(1))->from('analytics_events')->whereColumn('analytics_events.user_id', 'users.id')->where('analytics_events.name', 'contact_received');
                })
                ->whereNotExists(fn (Builder $query) => $query->select(DB::raw(1))->from('orders')->whereColumn('orders.user_id', 'users.id')->whereIn('orders.status', self::COLLECTED_STATUSES)),
            default => throw new \InvalidArgumentException("«{$segment}» no es un segmento"),
        };
    }

    /**
     * Los responsables de un menor INVITADO (`guardian_authorizations.guardian_email`) cuyo correo no tiene
     * compra: ni como cuenta con pedido cobrado. Personas sin cuenta, casi siempre: nadie dio un opt-in.
     *
     * @return array{size: int, opt_in: int}
     */
    private function guests(): array
    {
        $buyers = DB::table('orders')
            ->join('users', 'users.id', '=', 'orders.user_id')
            ->whereIn('orders.status', self::COLLECTED_STATUSES)
            ->selectRaw('lower(users.email)');

        // Un correo es la misma persona escrito en mayúsculas o minúsculas: se cuenta en minúsculas.
        $size = (int) DB::table('guardian_authorizations')
            ->whereNotNull('guardian_email')
            ->where('guardian_email', '<>', '')
            ->whereNotIn(DB::raw('lower(guardian_email)'), $buyers)
            ->selectRaw('count(distinct lower(guardian_email)) as n')
            ->value('n');

        return ['size' => $size, 'opt_in' => 0];
    }
}
