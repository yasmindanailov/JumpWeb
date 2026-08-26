<?php

namespace App\Domain\Identity\Listeners;

use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\WaiverSignature;
use App\Domain\Identity\Services\WaiverAcceptance;
use App\Domain\Identity\Services\WaiverSignatureRequest;
use App\Domain\Identity\Services\WaiverSigner;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Fase 6 · waiver — `[DECIDIDO owner, 2026-08-26]` (`specs/waiver-probatorio.md` §7·5, `#179`): la
 * aceptación que el titular marcó en el ALTA se convierte en firma **al verificar el correo**, y solo
 * si el texto que aceptó sigue siendo el vigente. Si entre medias se publicó otra versión, la
 * aceptación pendiente se descarta sin firmar: el índice de la cuenta le pedirá que lea y acepte el
 * nuevo (`accountContext.waiver.required`). En ningún caso se firma algo que no se ha leído.
 *
 * Vive DENTRO del módulo (`ModuleBoundariesTest`: fuera de `app/Domain` solo la capa de entrega toca los
 * internos de un módulo) y lo registra `AppServiceProvider`, que es el composition root. El único emisor de `Verified` es
 * `EmailVerificationController::verify()` (el enlace del correo), que abre sesión justo después: la
 * IP y el user-agent de la firma son los de ese clic, y `accepted_at` es ese momento — el PDF dice la
 * verdad: la aceptación quedó registrada cuando la persona demostró ser dueña del buzón.
 *
 * ⚠️ Un fallo aquí NO puede impedir la verificación: se registra y se limpia la pendiente igual.
 */
class SignPendingWaiverOnVerification
{
    public function __construct(private readonly WaiverAcceptance $acceptance) {}

    public function handle(Verified $event): void
    {
        $user = $event->user;

        if (! $user instanceof User || $user->waiver_pending_document_id === null) {
            return;
        }

        $documentId = (int) $user->waiver_pending_document_id;
        $channel = in_array($user->waiver_pending_channel, WaiverSignature::CHANNELS, true)
            ? $user->waiver_pending_channel
            : WaiverSignature::CHANNEL_WEB;

        // Se limpia ANTES de firmar: una segunda verificación (o un fallo) no puede firmar dos veces
        // ni dejar la aceptación colgada para siempre.
        $user->forceFill(['waiver_pending_document_id' => null, 'waiver_pending_channel' => null])->save();

        try {
            $document = WaiverAcceptance::currentDocument($documentId);

            if ($document === null) {
                Log::info('waiver.pending_dropped', ['user_id' => $user->getKey(), 'document_id' => $documentId, 'reason' => 'stale']);

                return;
            }

            app(WaiverSigner::class)->sign($user->fresh(), $document, new WaiverSignatureRequest(
                channel: $channel,
                ip: request()?->ip(),
                userAgent: request()?->userAgent(),
            ));
        } catch (Throwable $e) {
            Log::warning('waiver.pending_failed', ['user_id' => $user->getKey(), 'document_id' => $documentId, 'error' => $e->getMessage()]);
        }
    }
}
