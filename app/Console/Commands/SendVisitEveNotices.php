<?php

namespace App\Console\Commands;

use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Services\PendingBeforeVisit;
use App\Domain\Platform\Services\DisplayTime;
use App\Notifications\VisitEveNotice;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * **EL AVISO DE LA VÍSPERA** (T7·2b, `specs/celebracion-e-invitacion.md` §4.9 y §10.17;
 * `DECISIONES #714`, `#717`).
 *
 * Manda un correo al titular de cada reserva de **mañana** a la que **le queda algo por hacer**
 * ({@see PendingBeforeVisit}), una sola vez, y deja la marca en su fila.
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
 * la siguiente pasada lo recupera, y la marca impide que llegue dos veces. El coste son 23 pasadas
 * que salen en el primer `if`.
 *
 * ⚠️ La ventana se cierra a medianoche por definición: a las 00:00 «mañana» ya es otro día. Un aviso
 * que no salió entre las 18:00 y las 23:59 **no sale**, y eso es preferible a mandarlo la mañana de
 * la fiesta diciendo que quedan cosas por hacer.
 */
class SendVisitEveNotices extends Command
{
    /** La hora del PARQUE a partir de la cual se avisa (`[DECIDIDO owner, 2026-09-19]`, `#714`). */
    public const FROM_HOUR = 18;

    protected $signature = 'reservations:eve-notice
        {--force : Manda aunque no sean todavía las 18:00 del parque (staging y pruebas a mano).}
        {--dry-run : Enseña a quién avisaría y no manda ni marca nada.}';

    protected $description = 'Avisa la víspera al titular de cada reserva de mañana a la que le queda algo por hacer.';

    public function handle(PendingBeforeVisit $pending): int
    {
        $now = DisplayTime::now();

        if ($now->hour < self::FROM_HOUR && ! $this->option('force')) {
            return self::SUCCESS;
        }

        $tomorrow = $now->copy()->addDay()->toDateString();

        // ⚠️ Se acota por la FECHA DE LA FRANJA y se piden de una vez las relaciones que el lector y
        // el correo necesitan: el volumen de un día son decenas, pero sin esto serían cuatro
        // consultas por reserva y el scheduler corre cada hora.
        $reservations = OrderItem::query()
            ->whereNull('parent_item_id')
            ->whereNull('cancelled_at')
            ->whereNull('eve_notice_at')
            ->whereHas('slot', fn ($q) => $q->whereDate('date', $tomorrow))
            ->with(['ticketType', 'slot', 'order.user', 'order.items.children', 'order.payments.refunds', 'order.adjustments'])
            ->get();

        $sent = 0;

        foreach ($reservations as $reservation) {
            $user = $reservation->order?->user;

            // Sin titular no hay a quién avisar: un pedido anonimizado por RGPD (`#53`/`#90`) o un
            // alta del mostrador sin correo. No es un error y no se marca — si mañana recupera
            // titular, que lo reciba.
            if ($user === null || trim((string) $user->email) === '') {
                continue;
            }

            $work = $pending->forReservation($reservation);

            if (! $work->any()) {
                continue;
            }

            if ($this->option('dry-run')) {
                $this->line(sprintf(
                    '%s · %s · fichas %d/%d · respuestas %d · menores %d · parque %d',
                    (string) $reservation->order->code,
                    (string) $user->email,
                    $work->guestsDone, $work->guestsTotal,
                    $work->repliesToReview, $work->minorsUnresolved, $work->balanceAtParkCents,
                ));
                $sent++;

                continue;
            }

            // ⚠️⚠️ **Se marca ANTES de encolar**: si el envío revienta, el titular se queda sin
            // correo pero no recibe seis — un fallo de correo se ve en `failed_jobs`, y seis correos
            // los ve él.
            //
            // ⚠️⚠️⚠️ **Y con `toBase()`, que es lo que de verdad no toca el testigo.** El
            // constructor de consultas de ELOQUENT **sí** escribe `updated_at` —`Builder::update()`
            // llama a `addUpdatedAtColumn()`—, así que la forma «obvia» dejaría obsoleta la página
            // que el cliente tenga abierta, y con ella su compra de extras, **por haberle mandado un
            // correo**. Medido: el caso del testigo lo cazó en el primer intento. `toBase()` baja al
            // constructor crudo conservando la tabla y la clave del modelo.
            OrderItem::query()->whereKey($reservation->getKey())->toBase()
                ->update(['eve_notice_at' => Carbon::now()]);

            try {
                $user->notify(new VisitEveNotice($reservation, $work));
                $sent++;
            } catch (Throwable $e) {
                // La marca se queda puesta a propósito: reintentar a la hora siguiente mandaría el
                // correo dos veces si el fallo fue después de encolar. Queda el rastro para mirarlo.
                Log::warning('Eve notice could not be queued', [
                    'reservation_id' => $reservation->getKey(),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info(sprintf('%s: %d de %d reservas de mañana (%s).',
            $this->option('dry-run') ? 'Avisaría a' : 'Avisadas',
            $sent,
            $reservations->count(),
            $tomorrow,
        ));

        return self::SUCCESS;
    }
}
