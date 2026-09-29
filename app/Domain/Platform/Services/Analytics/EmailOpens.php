<?php

namespace App\Domain\Platform\Services\Analytics;

use App\Domain\Platform\Models\EmailOpen;
use App\Domain\Platform\Models\EmailSend;
use Illuminate\Support\Facades\DB;

/**
 * **Apunta cada vez que se pide el píxel de un envío** (`specs/correos-salientes.md` §4.12, `#797`, la C3) y decide si cuenta.
 *
 * - Se RE-COMPRUEBA la regla al abrir ({@see EmailOpenMarks::allows()}): si retiró el consentimiento, se opuso o se apagó el
 *   interruptor, ya no se apunta. Y solo si el correo salió con el píxel (`tracks_opens`).
 * - ⚠️⚠️ **El origen dice cuánto se parece a una lectura** (`EmailOpen::sourceOf()`): lo de Apple es de máquina y nunca
 *   cuenta (`apple`); antes de {@see EmailOpen::EARLY_SECONDS} s del envío, tampoco (`early`); otra petición dentro de
 *   {@see EmailOpen::WINDOW_SECONDS} s de una que contó es la misma lectura (`repeat`).
 * - ⚠️ El píxel no tiene limitador por IP (el proxy de Gmail pide las imágenes de miles de personas desde las mismas IP): el
 *   tope es por ENVÍO, {@see EmailOpen::FLOOD_PER_MINUTE} peticiones por minuto, y lo de más no se apunta.
 * - Como los clics, cada envío se serializa con un bloqueo de su fila. Del agente solo queda la clase del aparato, y solo si
 *   la petición la hace el gestor de correo (un proxy no dice nada del aparato).
 */
final class EmailOpens
{
    /** Apunta una petición del píxel del envío `$mark`, desde el agente `$userAgent`. Sin envío o sin permiso, nada. */
    public function record(string $mark, ?string $userAgent = null): void
    {
        DB::transaction(static function () use ($mark, $userAgent): void {
            $send = EmailSend::query()->where('send_key', $mark)->lockForUpdate()->first();

            if ($send === null || ! $send->tracks_opens || ! EmailOpenMarks::allows($send->user, $send->mail_key)) {
                return;
            }

            $now = now();

            if ($send->opens()->where('opened_at', '>=', $now->copy()->subMinute())->count() >= EmailOpen::FLOOD_PER_MINUTE) {
                return;
            }

            $source = EmailOpen::sourceOf($userAgent);
            $lastCounted = $send->opens()->whereNull('verdict')->latest('opened_at')->latest('id')->first();

            $verdict = match (true) {
                $source === EmailOpen::SOURCE_APPLE => EmailOpen::VERDICT_APPLE,
                $send->sent_at !== null && $now->getTimestamp() - $send->sent_at->getTimestamp() < EmailOpen::EARLY_SECONDS => EmailOpen::VERDICT_EARLY,
                $lastCounted !== null && $now->getTimestamp() - $lastCounted->opened_at->getTimestamp() < EmailOpen::WINDOW_SECONDS => EmailOpen::VERDICT_REPEAT,
                default => null,
            };

            $device = $source === EmailOpen::SOURCE_DIRECT && $userAgent !== null && $userAgent !== '' ? Device::classify($userAgent) : null;

            $send->opens()->create(['source' => $source, 'device' => $device, 'verdict' => $verdict, 'opened_at' => $now]);
        });
    }
}
