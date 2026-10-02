<?php

namespace App\Console\Commands;

use App\Domain\Booking\Models\AddonChoiceGroup;
use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Services\GuestCountPolicy;
use App\Domain\Booking\Services\PostFormAddons;
use App\Domain\Platform\Services\DisplayTime;
use App\Notifications\ChoiceReminderNotice;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * **«FALTA ELEGIR…», EL DÍA ANTES DEL PLAZO** (P4 de `fiesta-sistema-nuevo.md` §4.20; `[DECIDIDO owner]` `#913`, `#914`).
 *
 * Manda UN correo al titular de cada fiesta cuya lista de invitados se cierra en las próximas {@see HOURS_BEFORE} horas y a
 * la que le falta contestar un GRUPO DE OPCIONES con «hay que elegir» (`PostFormAddons::unansweredRequiredGroups()`, lo
 * mismo que el parque ve «sin elegir»), y deja la marca en su fila.
 *
 * ## Por qué corre CADA HORA y decide él (lo de la víspera, `SendVisitEveNotices`)
 * La ventana es la del PLAZO de cada fiesta —su franja menos el ajuste de la lista—, no una hora fija; y con una sola pasada
 * una hora de cron caído se llevaría el aviso por delante. Corriendo cada hora, la primera pasada dentro de la ventana lo
 * manda y la marca impide el segundo.
 *
 * ⚠️ **No sale a una fiesta vendida DENTRO de la ventana**: quien reservó hace dos horas acaba de recibir sus correos de la
 * compra con el enlace a la lista; un «falta elegir» encima sería ruido. Y cerrada la lista ya no sale: lo decide el parque.
 */
class SendChoiceReminders extends Command
{
    /** Cuánto antes de que se cierre la lista sale el aviso (`#913`: «el día antes del plazo»). */
    public const HOURS_BEFORE = 24;

    protected $signature = 'reservations:choice-reminder
        {--dry-run : Enseña a quién avisaría y no manda ni marca nada.}';

    protected $description = 'Avisa el día antes del cierre de la lista a quien le falta elegir un grupo de opciones obligatorio.';

    public function handle(GuestCountPolicy $policy): int
    {
        $now = DisplayTime::now();

        // ⚠️ Acotado por la FECHA DE LA FRANJA: la lista cierra «plazo» horas antes de empezar, así que una fiesta con la
        // ventana abierta empieza, como mucho, dentro de plazo + HOURS_BEFORE horas (un día de margen por la hora del
        // inicio). Y solo las de productos con algún grupo obligatorio: el resto, ni se carga.
        $until = $now->copy()->addHours($policy->cutoffHours() + self::HOURS_BEFORE)->addDay()->toDateString();
        $reservations = OrderItem::query()
            ->whereNull('parent_item_id')
            ->whereNull('cancelled_at')
            ->whereNull('choice_reminder_at')
            ->whereHas('order', fn ($q) => $q->where('status', Order::STATUS_PAID))
            ->whereHas('slot', fn ($q) => $q->whereDate('date', '>=', $now->toDateString())->whereDate('date', '<=', $until))
            ->whereHas('ticketType.choiceGroups', fn ($q) => $q->where('is_required', true))
            ->with(['ticketType.choiceGroups', 'ticketType.configurableAddons', 'slot', 'order.user', 'children'])
            ->get();

        $sent = 0;
        foreach ($reservations as $reservation) {
            $closes = $policy->deadlineFor($reservation);
            if ($closes === null) {
                continue;
            }
            $opens = $closes->copy()->subHours(self::HOURS_BEFORE);
            if ($now->lt($opens) || $now->gte($closes)) {
                continue; // fuera de su ventana: aún no, o la lista ya cerró
            }
            $soldAt = $reservation->order?->created_at;
            if ($soldAt === null || $soldAt->gte($opens)) {
                continue; // vendida dentro de la ventana: sus correos de la compra ya llevan el enlace
            }
            $pending = PostFormAddons::unansweredRequiredGroups($reservation);
            if ($pending === []) {
                continue;
            }

            // Sin titular no hay a quién avisar (un pedido anonimizado o un alta del mostrador sin correo). No se marca: si
            // recupera titular dentro de la ventana, que lo reciba.
            $user = $reservation->order->user;
            if ($user === null || trim((string) $user->email) === '') {
                continue;
            }

            $keys = array_map(fn (AddonChoiceGroup $g): string => $g->key, $pending);
            if ($this->option('dry-run')) {
                $this->line(sprintf('%s · %s · cierra %s · falta %s', (string) $reservation->order->code, (string) $user->email,
                    $closes->format('Y-m-d H:i'), implode(', ', $keys)));
                $sent++;

                continue;
            }

            // ⚠️⚠️ Se marca ANTES de encolar, y con `toBase()` (lo de la víspera): un fallo de correo se ve en `failed_jobs`, y
            // dos correos los vería el cliente; el constructor de Eloquent tocaría `updated_at`, el testigo del post-form.
            OrderItem::query()->whereKey($reservation->getKey())->toBase()->update(['choice_reminder_at' => Carbon::now()]);

            try {
                $user->notify(new ChoiceReminderNotice($reservation, $keys));
                $sent++;
            } catch (Throwable $e) {
                Log::warning('Choice reminder could not be queued', [
                    'reservation_id' => $reservation->getKey(),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info(sprintf('%s: %d de %d candidatas.', $this->option('dry-run') ? 'Avisaría a' : 'Avisadas', $sent, $reservations->count()));

        return self::SUCCESS;
    }
}
