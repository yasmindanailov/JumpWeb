<?php

namespace App\Domain\Identity\Services;

use App\Domain\Identity\Models\BirthdayReminder;
use App\Domain\Identity\Models\GuardianAuthorization;
use App\Domain\Platform\Models\Setting;
use Carbon\CarbonImmutable;

/**
 * «AVÍSAME DE FECHAS» (`specs/avisame-de-fechas.md`, `[DECIDIDO owner]` `#750`): quién puede pedirlo, pedirlo y darse
 * de baja, y cuándo le toca el correo. La casilla la ofrece el recibo de la invitación; el correo, `birthday-reminders:send`.
 *
 * ⚠️ La prueba es la autorización FIRMADA y con correo: sin firma (o sin correo) no hay a quién escribir ni por qué, y la
 * casilla no se ofrece. De la autorización salen el correo, los nombres de pila y la fecha: la fila no copia nada.
 */
final class BirthdayReminders
{
    /** Cuántas semanas antes del cumpleaños sale el correo (el nº 12 del mockup: seis). `0` lo apaga todo. */
    public const WEEKS_KEY = 'party.birthday_reminder_weeks';

    public const WEEKS_DEFAULT = 6;

    public const WEEKS_MIN = 0;

    public const WEEKS_MAX = 12;

    public static function weeks(): int
    {
        $value = Setting::value(self::WEEKS_KEY);
        if ($value === null || trim((string) $value) === '' || ! is_numeric($value)) {
            return self::WEEKS_DEFAULT;
        }

        return max(self::WEEKS_MIN, min(self::WEEKS_MAX, (int) $value));
    }

    public static function enabled(): bool
    {
        return self::weeks() > 0;
    }

    /**
     * La autorización que PERMITE ofrecer la casilla en el recibo de esta respuesta: firmada desde ella y con correo (la
     * fecha de nacimiento la exige la tabla). `null` si falta algo, o si el ajuste está apagado.
     */
    public function offerableFor(int $replyId): ?GuardianAuthorization
    {
        if (! self::enabled()) {
            return null;
        }

        // ⚠️ `!= ''` deja fuera también el NULL (en SQL, `NULL != ''` no es verdad): el formulario lo manda NULL
        // (`ConvertEmptyStringsToNull`), y un `whereNotNull` al lado sería una guarda que ninguna mutación podría ver.
        // `waiverSignatures`: la poda se lleva la firma antes que la fila, y sin prueba no se ofrece.
        return GuardianAuthorization::query()
            ->where('invitation_reply_id', $replyId)
            ->where('guardian_email', '!=', '')
            ->whereHas('waiverSignatures')
            ->latest('id')
            ->first();
    }

    /** La fila de esta autorización, viva o de baja; `null` si nunca se pidió. */
    public function of(GuardianAuthorization $authorization): ?BirthdayReminder
    {
        return BirthdayReminder::query()->where('guardian_authorization_id', $authorization->getKey())->first();
    }

    /**
     * La casilla: marcarla crea la fila (o REVIVE la que se dio de baja, con su nueva fecha de aceptación); desmarcarla
     * la da de baja. Idempotente: marcar lo marcado no mueve nada.
     */
    public function set(GuardianAuthorization $authorization, bool $wants, string $locale): ?BirthdayReminder
    {
        $row = $this->of($authorization);

        if (! $wants) {
            if ($row !== null && $row->isLive()) {
                $row->forceFill(['revoked_at' => now()])->save();
            }

            return $row;
        }

        if ($row === null) {
            return BirthdayReminder::query()->create([
                'guardian_authorization_id' => $authorization->getKey(),
                'locale' => $locale,
                'accepted_at' => now(),
            ]);
        }

        if (! $row->isLive()) {
            $row->forceFill(['revoked_at' => null, 'accepted_at' => now(), 'locale' => $locale])->save();
        }

        return $row;
    }

    /**
     * La baja desde el correo (un toque, LSSI art. 22.1). ⚠️ Alcanza a TODAS las filas vivas de ese correo, no solo a la
     * del enlace: quien se da de baja no quiere más correos de esto, y un segundo hijo firmado en otra fiesta le volvería
     * a escribir. Marcar la casilla otra vez, más tarde, es un consentimiento nuevo y vale.
     */
    public function revoke(BirthdayReminder $row): void
    {
        $email = mb_strtolower(trim((string) ($row->authorization->guardian_email ?? '')));
        $ids = $email === ''
            ? [(int) $row->getKey()]
            : BirthdayReminder::query()
                ->whereNull('revoked_at')
                ->whereHas('authorization', static fn ($q) => $q->whereRaw('LOWER(guardian_email) = ?', [$email]))
                ->pluck('id')->all();
        BirthdayReminder::query()->whereKey($ids)->whereNull('revoked_at')->update(['revoked_at' => now(), 'updated_at' => now()]);
    }

    /**
     * A quién le toca HOY el correo: las filas vivas cuyo próximo cumpleaños cae dentro de `$weeks` semanas y a no menos
     * de `$minDaysAhead` días (más cerca llegaría tarde: ese año se salta). UNA entrada por correo, niño y fecha —el mismo
     * niño firmado en dos fiestas es un solo correo—, y ninguna si alguna de sus filas ya salió para ESE cumpleaños.
     *
     * @return list<array{rows: list<BirthdayReminder>, authorization: GuardianAuthorization, next: CarbonImmutable}>
     */
    public function due(CarbonImmutable $today, int $weeks, int $minDaysAhead): array
    {
        if ($weeks <= 0) {
            return [];
        }
        $grupos = [];
        BirthdayReminder::query()->whereNull('revoked_at')->with('authorization')->orderBy('id')
            ->each(static function (BirthdayReminder $row) use (&$grupos, $today): void {
                $a = $row->authorization;
                if ($a === null) {
                    return;
                }
                $email = mb_strtolower(trim((string) $a->guardian_email));
                if ($email === '') {
                    return;
                }
                $clave = $email.'|'.$a->minor_key.'|'.$a->minor_born_on->toDateString();
                $grupos[$clave]['rows'][] = $row;
                $grupos[$clave]['authorization'] ??= $a;
                $grupos[$clave]['next'] ??= self::nextBirthday($a->minor_born_on, $today);
            });

        $toca = [];
        foreach ($grupos as $g) {
            $dias = (int) $today->startOfDay()->diffInDays($g['next']);
            $yaSalio = collect($g['rows'])->contains(static fn (BirthdayReminder $r): bool => $r->sent_for?->toDateString() === $g['next']->toDateString());
            if (! $yaSalio && $dias <= $weeks * 7 && $dias >= $minDaysAhead) {
                $toca[] = $g;
            }
        }

        return $toca;
    }

    /** Apunta que salió el correo de ESE cumpleaños en todas las filas del grupo: ANTES de encolar (reintentar no duplica). */
    public function markSent(array $rows, CarbonImmutable $next): void
    {
        BirthdayReminder::query()->whereKey(array_map(static fn (BirthdayReminder $r): int => (int) $r->getKey(), $rows))
            ->update(['sent_for' => $next->toDateString(), 'sent_at' => now(), 'updated_at' => now()]);
    }

    /**
     * El PRÓXIMO cumpleaños (hoy incluido) de quien nació en `$bornOn`. El 29 de febrero, el 28 en un año que no es
     * bisiesto: el cumpleaños no se salta un año.
     */
    public static function nextBirthday(CarbonImmutable $bornOn, CarbonImmutable $today): CarbonImmutable
    {
        $en = static function (int $year) use ($bornOn): CarbonImmutable {
            $day = $bornOn->month === 2 && $bornOn->day === 29 && ! CarbonImmutable::create($year)->isLeapYear() ? 28 : $bornOn->day;

            return CarbonImmutable::create($year, $bornOn->month, $day)->startOfDay();
        };
        $thisYear = $en($today->year);

        return $thisYear->lessThan($today->startOfDay()) ? $en($today->year + 1) : $thisYear;
    }
}
