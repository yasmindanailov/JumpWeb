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
 * **«EL CUMPLE SE ACERCA»** (`docs/specs/avisame-de-fechas.md` §4.3, `[DECIDIDO owner]` `#750`): UN correo
 * `party.birthday_reminder_weeks` semanas antes del cumpleaños de un niño (el nº 12 del diseño: seis), a dos públicos:
 *  1. a quien marcó «Avísame de fechas» al firmar la autorización de ese niño (a su correo, sin cuenta);
 *  2. desde la C1a (`specs/correos-rediseno.md` §4.4, `#920`), **el 12 ampliado**: a una cuenta con «novedades»
 *     (`User::scopeMarketable()`) por cada menor que declaró (`dependents`), con su marca `birthday_mail_for`. A quién le toca
 *     y las marcas, como las del primero, las decide `BirthdayReminders`; aquí solo se orquesta.
 *
 * ## A quién NO
 *  · con la baja puesta, o con el ajuste a 0 (que apaga los dos);
 *  · si ya salió para ESE cumpleaños, o si faltan menos de {@see MIN_DAYS_AHEAD} días (llegaría tarde);
 *  · a quien ya celebra aquí: una cuenta con ese correo y una fiesta pagada de un año antes del cumpleaños en adelante
 *    («a quien no ha celebrado aquí», el diseño; a quien celebró, el 11);
 *  · ⚠️ NUNCA DOS VECES EL MISMO CUMPLE: el mismo correo y la misma fecha de nacimiento por los dos caminos son un correo —el
 *    que salga primero marca al otro—; y a quien se dio de baja de «Avísame de fechas», tampoco por «novedades»: su página le
 *    prometió «No te escribiremos para su cumple».
 *  · un menor declarado que ese cumpleaños cumple los 18: ya no es el cumple de un niño.
 *
 * ## Por qué corre CADA HORA y decide él, desde las 10:00 del parque
 * Las dos razones de `reservations:eve-notice`: la zona del parque es un AJUSTE que se resuelve en la EJECUCIÓN, y una hora
 * de cron caído no se lleva el correo por delante. La marca se escribe ANTES de encolar: si el envío revienta, el padre se
 * queda sin correo pero no con seis; queda el rastro en el log. El molde es `surveys:send-external`.
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

    protected $description = 'Manda «El cumple se acerca» a quien pidió que le avisemos antes del cumple de su hijo, y a quien lo declaró en su cuenta y quiere novedades.';

    private int $sent = 0;

    private int $byNews = 0;

    private int $skipped = 0;

    private int $failed = 0;

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

        $this->byReminder($reminders, $today, $desde, $dryRun);
        $this->byDeclaredMinor($reminders, $today, $desde, $dryRun);

        $this->info(sprintf('%s %d avisos de cumple%s%s%s.',
            $dryRun ? 'Mandaría' : 'Mandados',
            $this->sent,
            $this->byNews > 0 ? sprintf(' (%d por «novedades»)', $this->byNews) : '',
            $this->skipped > 0 ? sprintf(' (%d ya celebran aquí o ya lo tienen)', $this->skipped) : '',
            $this->failed > 0 ? sprintf(' (%d sin encolar, ver el log)', $this->failed) : '',
        ));

        return self::SUCCESS;
    }

    /** El público de siempre: las casillas de «Avísame de fechas». */
    private function byReminder(BirthdayReminders $reminders, CarbonImmutable $today, ?string $desde, bool $dryRun): void
    {
        foreach ($reminders->due($today, BirthdayReminders::weeks(), self::MIN_DAYS_AHEAD) as $g) {
            $a = $g['authorization'];
            $email = trim((string) $a->guardian_email);
            if ($this->alreadyCelebrates($email, $g['next'])) {
                $this->skipped++;

                continue;
            }
            // Ya le salió ESE cumple por su cuenta («novedades»): se marca para que no se mire cada hora, y no se escribe.
            if ($reminders->sentToAccountFor($email, $a->minor_born_on, $g['next'])) {
                $this->skipped++;
                if (! $dryRun) {
                    $reminders->markSent($g['rows'], $g['next']);
                }

                continue;
            }
            if ($dryRun) {
                $this->sent++;

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
                $this->sent++;
            } catch (Throwable $e) {
                // La marca se queda a propósito: reintentar mandaría el correo dos veces si el fallo fue después de
                // encolar. Sin datos personales en el rastro (RGPD-02): solo el id.
                $this->failed++;
                Log::warning('birthday_reminders.not_queued', ['reminder_id' => $g['rows'][0]->getKey(), 'error' => $e->getMessage()]);
            }
        }
    }

    /**
     * EL 12 AMPLIADO (la C1a, `#920`): los menores declarados de las cuentas con «novedades», en la misma ventana
     * (`BirthdayReminders::declaredDue()`), en el idioma de la cuenta. Va DESPUÉS de las casillas: si el mismo cumple salió
     * por una, esta no lo repite.
     */
    private function byDeclaredMinor(BirthdayReminders $reminders, CarbonImmutable $today, ?string $desde, bool $dryRun): void
    {
        foreach ($reminders->declaredDue($today, BirthdayReminders::weeks(), self::MIN_DAYS_AHEAD) as $g) {
            ['dependent' => $d, 'user' => $user, 'next' => $next] = $g;
            $email = trim((string) $user->email);
            if ($this->alreadyCelebrates($email, $next) || $reminders->leftOrSentFor($email, $d->born_on, $next)) {
                $this->skipped++;

                continue;
            }
            if ($dryRun) {
                $this->sent++;
                $this->byNews++;

                continue;
            }

            $reminders->markDeclaredSent($d, $next);
            $locale = $user->preferredLocale();
            try {
                $user->notify(new BirthdayComingNotice(
                    reminderId: null,
                    // `name` es SOLO el nombre (los apellidos van aparte, `#236`).
                    nombre: trim((string) $d->name),
                    edad: $g['age'],
                    mes: $next->locale($locale)->isoFormat('MMMM'),
                    desde: $desde,
                ));
                $this->sent++;
                $this->byNews++;
            } catch (Throwable $e) {
                // Como arriba: la marca se queda, y en el rastro solo el id (RGPD-02).
                $this->failed++;
                Log::warning('birthday_reminders.not_queued', ['dependent_id' => $d->getKey(), 'error' => $e->getMessage()]);
            }
        }
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
