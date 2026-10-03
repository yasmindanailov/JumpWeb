<?php

namespace App\Console\Commands;

use App\Domain\Booking\Contracts\PendingWork;
use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Services\PendingBeforeVisit;
use App\Domain\Platform\Services\DisplayTime;
use App\Notifications\VisitEveNotice;
use App\Notifications\VisitReminderNotice;
use Illuminate\Console\Command;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * **LA VÍSPERA** (T7·2b, `specs/celebracion-e-invitacion.md` §4.9 y §10.17; `DECISIONES #714`, `#717`; desde la R2d de
 * `specs/correos-rediseno.md` §4.3, `#915`, los dos correos del diseño).
 *
 * Al titular de cada reserva PAGADA de **mañana**, una sola vez y con la marca en su fila:
 *  · una FIESTA (lleva lista de invitados) recibe el 4, «Un repaso antes de mañana» (`VisitEveNotice`), **solo si le queda
 *    algo por hacer** ({@see PendingBeforeVisit}, `#714`);
 *  · todo lo demás —unas entradas, un grupo— recibe el 3, «Mañana os esperamos» (`VisitReminderNotice`), **siempre**
 *    (`#915`, a): su QR, la hora y cómo llegar.
 * Y una reserva de HOY que no lo recibió —se reservó después de la víspera, o la víspera no pudo— recibe el 3 con «Hoy» **dos
 * horas antes** de su hora (el diseño: «o dos horas antes si la reserva es del mismo día»). Una fiesta, no: su repaso es de la
 * víspera.
 *
 * ## Por qué corre CADA HORA y decide él, en vez de un `dailyAt('18:00')`
 *
 * ⚠️⚠️ **La hora es la del PARQUE, y la zona del parque es un AJUSTE** (`display_timezone`), no una
 * constante. Poner `->timezone(DisplayTime::timezone())` en el scheduler resolvería el ajuste **al
 * registrar**, o sea en cada arranque de consola —incluida la suite, y antes de que exista la tabla
 * `settings` en una instalación nueva—. Es la misma regla que ya sigue `slots:generate-rolling`: *el
 * rango se calcula en la EJECUCIÓN, no al registrar.*
 *
 * ▶ Y hay un segundo motivo, que es el que de verdad manda: **con una sola pasada, una hora de cron
 * caído se lleva el aviso por delante y nadie se entera**. Corriendo cada hora a partir de las 18:00,
 * la siguiente pasada lo recupera, y la marca impide que llegue dos veces. Las del mismo día, igual: cada hora mira las dos
 * horas siguientes.
 *
 * ⚠️ La ventana de la víspera se cierra a medianoche por definición: a las 00:00 «mañana» ya es otro día. Una fiesta que no
 * recibió su repaso entre las 18:00 y las 23:59 **no lo recibe**, y eso es preferible a mandarlo la mañana de la fiesta
 * diciendo que quedan cosas por hacer; unas entradas, sí: el «Hoy», dos horas antes.
 * ⚠️ Solo pedidos PAGADOS: hasta la R2d el comando no miraba el pedido, y un carrito abandonado con su franja mañana también
 * se avisaba. Con el 3 «siempre», eso sería «Mañana os esperamos» a quien nunca pagó.
 */
class SendVisitEveNotices extends Command
{
    /** La hora del PARQUE a partir de la cual se avisa la víspera (`[DECIDIDO owner, 2026-09-19]`, `#714`). */
    public const FROM_HOUR = 18;

    /** Cuántas horas antes recibe el 3 una reserva del MISMO día (el diseño: «dos horas antes»). */
    public const SAME_DAY_HOURS = 2;

    protected $signature = 'reservations:eve-notice
        {--force : Manda la víspera aunque no sean todavía las 18:00 del parque (staging y pruebas a mano).}
        {--dry-run : Enseña a quién avisaría y no manda ni marca nada.}';

    protected $description = 'La víspera: el 3 a cada reserva de mañana (y «Hoy», dos horas antes, a las del mismo día) y el 4 a cada fiesta a la que le queda algo.';

    public function handle(PendingBeforeVisit $pending): int
    {
        $now = DisplayTime::now();
        $sent = 0;
        $seen = 0;

        if ($now->hour >= self::FROM_HOUR || $this->option('force')) {
            $tomorrow = $this->reservationsOn($now->copy()->addDay()->toDateString());
            $seen += $tomorrow->count();
            foreach ($tomorrow as $reservation) {
                $sent += $this->avisar($reservation, $pending, today: false);
            }
        }

        // El MISMO día: lo que empieza en las dos horas siguientes y aún no se avisó. Una fiesta, no.
        $until = $now->copy()->addHours(self::SAME_DAY_HOURS);
        $today = $this->reservationsOn($now->toDateString())->filter(static function (OrderItem $r) use ($now, $until): bool {
            $start = Carbon::parse($r->slot->date->format('Y-m-d').' '.$r->slot->start_time, DisplayTime::timezone());

            return ! $r->isGuestFormReservation() && $start->greaterThan($now) && $start->lessThanOrEqualTo($until);
        });
        $seen += $today->count();
        foreach ($today as $reservation) {
            $sent += $this->avisar($reservation, $pending, today: true);
        }

        $this->info(sprintf('%s: %d de %d reservas (víspera y del mismo día).', $this->option('dry-run') ? 'Avisaría a' : 'Avisadas', $sent, $seen));

        return self::SUCCESS;
    }

    /**
     * Las reservas PRINCIPALES vivas de un día, de un pedido PAGADO y aún sin avisar, con lo que el lector y los correos leen.
     *
     * ⚠️ Se acota por la FECHA DE LA FRANJA y se piden de una vez las relaciones: el volumen de un día son decenas, pero sin
     * esto serían cuatro consultas por reserva y el scheduler corre cada hora.
     *
     * @return Collection<int, OrderItem>
     */
    private function reservationsOn(string $day): Collection
    {
        return OrderItem::query()
            ->whereNull('parent_item_id')
            ->whereNull('cancelled_at')
            ->whereNull('eve_notice_at')
            ->whereHas('slot', fn ($q) => $q->whereDate('date', $day))
            ->whereHas('order', fn ($q) => $q->where('status', Order::STATUS_PAID))
            ->with(['ticketType', 'slot', 'order.user', 'order.items.children', 'order.items.ticketType', 'order.items.slot', 'order.payments.refunds', 'order.adjustments'])
            ->get();
    }

    /** Avisa a una reserva (el 3 o el 4) y deja la marca. Devuelve 1 si avisó o avisaría, 0 si no. */
    private function avisar(OrderItem $reservation, PendingBeforeVisit $pending, bool $today): int
    {
        $user = $reservation->order?->user;

        // Sin titular no hay a quién avisar: un pedido anonimizado por RGPD (`#53`/`#90`) o un alta del mostrador sin correo.
        // No es un error y no se marca — si recupera titular, que lo reciba.
        if ($user === null || trim((string) $user->email) === '') {
            return 0;
        }

        $work = $pending->forReservation($reservation);
        $party = $reservation->isGuestFormReservation();

        // La fiesta, solo si le queda algo (`#714`): «no tienes que hacer nada» enseña a ignorar los correos.
        if ($party && ! $work->any()) {
            return 0;
        }

        if ($this->option('dry-run')) {
            $this->line(sprintf(
                '%s · %s · %s · fichas %d/%d · respuestas %d · menores %d · cumple %s · parque %d',
                (string) $reservation->order->code,
                (string) $user->email,
                $party ? 'el 4' : ($today ? 'el 3, hoy' : 'el 3'),
                $work->guestsDone, $work->guestsTotal,
                $work->repliesToReview, $work->minorsUnresolved,
                $work->honoreeWaiverMissing ? 'sin descargo' : '—',
                $work->balanceAtParkCents,
            ));

            return 1;
        }

        // ⚠️⚠️ **Se marca ANTES de encolar**: si el envío revienta, el titular se queda sin correo pero no recibe seis — un
        // fallo de correo se ve en `failed_jobs`, y seis correos los ve él.
        //
        // ⚠️⚠️⚠️ **Y con `toBase()`, que es lo que de verdad no toca el testigo.** El constructor de consultas de ELOQUENT
        // **sí** escribe `updated_at` —`Builder::update()` llama a `addUpdatedAtColumn()`—, así que la forma «obvia» dejaría
        // obsoleta la página que el cliente tenga abierta, y con ella su compra de extras, **por haberle mandado un correo**.
        OrderItem::query()->whereKey($reservation->getKey())->toBase()
            ->update(['eve_notice_at' => Carbon::now()]);

        try {
            $user->notify($this->noticeFor($reservation, $work, $party, $today));

            return 1;
        } catch (Throwable $e) {
            // La marca se queda puesta a propósito: reintentar a la hora siguiente mandaría el correo dos veces si el fallo
            // fue después de encolar. Queda el rastro para mirarlo.
            Log::warning('Eve notice could not be queued', [
                'reservation_id' => $reservation->getKey(),
                'error' => $e->getMessage(),
            ]);

            return 0;
        }
    }

    private function noticeFor(OrderItem $reservation, PendingWork $work, bool $party, bool $today): Notification
    {
        return $party ? new VisitEveNotice($reservation, $work) : new VisitReminderNotice($reservation, $work, $today);
    }
}
