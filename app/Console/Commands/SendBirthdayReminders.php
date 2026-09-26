<?php

namespace App\Console\Commands;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Services\PartyCards;
use App\Domain\Identity\Services\BirthdayReminders;
use App\Domain\Platform\Services\DisplayTime;
use App\Notifications\BirthdayComingNotice;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * **«EL CUMPLE SE ACERCA»** (`docs/specs/avisame-de-fechas.md` §4.3, `[DECIDIDO owner]` `#750`): a quien marcó «Avísame de
 * fechas» al firmar la autorización de un invitado, UN correo `party.birthday_reminder_weeks` semanas antes del cumpleaños
 * de ese niño (el nº 12 del mockup: seis).
 *
 * ## A quién NO
 *  · con la baja puesta, o con el ajuste a 0;
 *  · si ya salió para ESE cumpleaños (`sent_for`), o si faltan menos de {@see MIN_DAYS_AHEAD} días (llegaría tarde);
 *  · a quien ya celebra aquí: una cuenta con ese correo y una fiesta pagada de un año antes del cumpleaños en adelante
 *    («a quien no ha celebrado aquí», el mockup).
 *
 * ## Por qué corre CADA HORA y decide él, desde las 10:00 del parque
 * Las dos razones de `reservations:eve-notice`: la zona del parque es un AJUSTE que se resuelve en la EJECUCIÓN, y una hora
 * de cron caído no se lleva el correo por delante. `sent_for` se escribe ANTES de encolar: si el envío revienta, el padre
 * se queda sin correo pero no con seis; queda el rastro en el log. El molde es `surveys:send-external`.
 */
class SendBirthdayReminders extends Command
{
    /** La hora del PARQUE a partir de la cual se manda. */
    public const FROM_HOUR = 10;

    /** A menos de estos días del cumpleaños ya no se escribe: ese año se salta. */
    public const MIN_DAYS_AHEAD = 7;

    protected $signature = 'birthday-reminders:send
        {--force : Manda aunque no sean todavía las 10:00 del parque (staging y pruebas a mano).}
        {--dry-run : Enseña a cuántos mandaría y no escribe ni encola nada.}';

    protected $description = 'Manda «El cumple se acerca» a quien pidió en el recibo de una invitación que le avisemos antes del cumple de su hijo.';

    public function handle(BirthdayReminders $reminders): int
    {
        if (! BirthdayReminders::enabled()) {
            $this->info('«Avísame de fechas» está apagado (semanas = 0): nada que mandar.');

            return self::SUCCESS;
        }
        if (DisplayTime::now()->hour < self::FROM_HOUR && ! $this->option('force')) {
            return self::SUCCESS;
        }

        $today = CarbonImmutable::parse(DisplayTime::today()->toDateString());
        $dryRun = (bool) $this->option('dry-run');
        // El «desde» del catálogo, una vez por pasada: el pack de cumpleaños más barato, como la web.
        $desde = (new PartyCards)->cheapest(TicketType::birthdaySurfacePacks()->with(['prices.rateType'])->get());
        $sent = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($reminders->due($today, BirthdayReminders::weeks(), self::MIN_DAYS_AHEAD) as $g) {
            $a = $g['authorization'];
            $email = trim((string) $a->guardian_email);
            if ($this->alreadyCelebrates($email, $g['next'])) {
                $skipped++;

                continue;
            }
            if ($dryRun) {
                $sent++;

                continue;
            }

            $reminders->markSent($g['rows'], $g['next']);
            $locale = (string) $g['rows'][0]->locale;
            try {
                Notification::route('mail', $email)->notify((new BirthdayComingNotice(
                    reminderId: (int) $g['rows'][0]->getKey(),
                    // `minor_name` es SOLO el nombre (los apellidos van aparte, `#236`): «María José», entero.
                    nombre: trim((string) $a->minor_name),
                    edad: $g['next']->year - $a->minor_born_on->year,
                    mes: $g['next']->locale($locale)->isoFormat('MMMM'),
                    desde: $desde,
                ))->locale($locale));
                $sent++;
            } catch (Throwable $e) {
                // La marca se queda a propósito: reintentar mandaría el correo dos veces si el fallo fue después de
                // encolar. Sin datos personales en el rastro (RGPD-02): solo el id.
                $failed++;
                Log::warning('birthday_reminders.not_queued', ['reminder_id' => $g['rows'][0]->getKey(), 'error' => $e->getMessage()]);
            }
        }

        $this->info(sprintf('%s %d avisos de cumple%s%s.',
            $dryRun ? 'Mandaría' : 'Mandados',
            $sent,
            $skipped > 0 ? sprintf(' (%d ya celebran aquí)', $skipped) : '',
            $failed > 0 ? sprintf(' (%d sin encolar, ver el log)', $failed) : '',
        ));

        return self::SUCCESS;
    }

    /**
     * ¿Ya celebra aquí? Una cuenta con ese correo con una fiesta (un pack) en un pedido pagado, con fecha de un año antes
     * de ese cumpleaños en adelante (la ha celebrado hace poco o la tiene reservada). Por consulta, no por modelos: la
     * pasada es horaria.
     */
    private function alreadyCelebrates(string $email, CarbonImmutable $next): bool
    {
        return DB::table('users')
            ->join('orders', 'orders.user_id', '=', 'users.id')
            ->join('order_items', 'order_items.order_id', '=', 'orders.id')
            ->join('ticket_types', 'ticket_types.id', '=', 'order_items.ticket_type_id')
            ->join('slots', 'slots.id', '=', 'order_items.slot_id')
            ->whereRaw('LOWER(users.email) = ?', [mb_strtolower($email)])
            ->where('orders.status', Order::STATUS_PAID)
            ->where('ticket_types.type', TicketType::TYPE_PACK)
            ->whereNull('order_items.cancelled_at')
            ->where('slots.date', '>=', $next->subYear()->toDateString())
            ->exists();
    }
}
