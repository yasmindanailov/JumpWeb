<?php

namespace App\Domain\Platform\Services\Analytics;

use App\Domain\Platform\Models\EmailClick;
use App\Domain\Platform\Models\EmailSend;
use Illuminate\Support\Facades\DB;

/**
 * **Apunta la visita que llega con la marca de un envío** (`specs/correos-salientes.md` §4.8, la C2) y decide si cuenta.
 *
 * - Se RE-COMPRUEBA la regla al pulsar ({@see EmailClickMarks::allows()}): si el cliente se opuso después del envío, o se
 *   apagó el interruptor, su clic ya no se apunta.
 * - ⚠️⚠️ **El escáner se reconoce por el RITMO** ({@see EmailClick}): los de Outlook, Mimecast o Proofpoint visitan TODOS
 *   los enlaces al entregarse el correo, desde muchas IP y con agente de navegador falso. Una visita en los primeros
 *   {@see EmailClick::EARLY_SECONDS} s tras el envío es `early`; {@see EmailClick::SWEEP_HITS} o más al mismo envío dentro
 *   de la ventana son `sweep` (todas las de la ventana); el mismo enlace otra vez dentro de la ventana es `repeat`.
 * - ⚠️ Los escáneres llegan EN PARALELO: sin serializar, cada visita vería «dos en la ventana» y ninguna sería ráfaga. Cada
 *   envío se serializa con un bloqueo de SU fila (`lockForUpdate`); dos envíos distintos no se esperan.
 * - Los robots que SE ANUNCIAN (la vista previa de un chat donde alguien pegó el enlace) son `bot` (§4.10). Del agente solo
 *   queda la clase del aparato (`Device`); el agente no se guarda.
 */
final class EmailClicks
{
    /**
     * Apunta una visita a `$path` con la marca `$mark` (el `send_key` del envío), desde el agente `$userAgent`. Sin envío o sin
     * permiso, nada.
     */
    public function record(string $mark, string $path, ?string $userAgent = null): void
    {
        DB::transaction(static function () use ($mark, $path, $userAgent): void {
            $send = EmailSend::query()->where('send_key', $mark)->lockForUpdate()->first();

            if ($send === null || ! $send->tracks_clicks || ! EmailClickMarks::allows($send->user, $send->mail_key)) {
                return;
            }

            $now = now();
            $route = RouteNormalizer::path($path);
            // Un robot que ya dijo quién es no entra en la cuenta de la ráfaga: pegar el enlace en un chat y pulsarlo después
            // haría escáner al cliente.
            $recent = $send->clicks()->where('clicked_at', '>=', $now->copy()->subSeconds(EmailClick::WINDOW_SECONDS))->get()
                ->reject(static fn (EmailClick $click): bool => $click->verdict === EmailClick::VERDICT_BOT);

            $verdict = match (true) {
                $send->sent_at !== null && $now->getTimestamp() - $send->sent_at->getTimestamp() < EmailClick::EARLY_SECONDS => EmailClick::VERDICT_EARLY,
                Device::isBot($userAgent) => EmailClick::VERDICT_BOT,
                $recent->count() + 1 >= EmailClick::SWEEP_HITS => EmailClick::VERDICT_SWEEP,
                $recent->contains(static fn (EmailClick $click): bool => $click->verdict === null && $click->route === $route) => EmailClick::VERDICT_REPEAT,
                default => null,
            };

            // La ráfaga se lleva las visitas de su ventana que parecían de una persona (las `early` ya no cuentan).
            if ($verdict === EmailClick::VERDICT_SWEEP) {
                EmailClick::query()
                    ->whereKey($recent->modelKeys())
                    ->where(static fn ($query) => $query->whereNull('verdict')->orWhere('verdict', EmailClick::VERDICT_REPEAT))
                    ->update(['verdict' => EmailClick::VERDICT_SWEEP]);
            }

            $device = $userAgent !== null && $userAgent !== '' ? Device::classify($userAgent) : null;

            $send->clicks()->create(['route' => $route, 'device' => $device, 'verdict' => $verdict, 'clicked_at' => $now]);
        });
    }
}
