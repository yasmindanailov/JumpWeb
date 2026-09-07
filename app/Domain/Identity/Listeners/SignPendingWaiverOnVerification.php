<?php

namespace App\Domain\Identity\Listeners;

use App\Domain\Identity\Models\Dependent;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\WaiverSignature;
use App\Domain\Identity\Services\WaiverAcceptance;
use App\Domain\Identity\Services\WaiverSignatureRequest;
use App\Domain\Identity\Services\WaiverSigner;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\DB;
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
 * internos de un módulo) y lo registra `AppServiceProvider`, que es el composition root. `Verified` lo
 * emiten DOS caminos —el enlace del correo (`EmailVerificationController::verify()`) y el cobro en
 * pay-first (`RedsysReturnHandler::autoVerifyBuyer()`, dentro de la transacción del pago; `#181`)— y
 * desde `#181` también la confirmación de un cambio de correo. Por eso la firma se hace TRAS el commit
 * y con la IP/UA de la ACEPTACIÓN (guardadas en el alta), no de la petición que verifica; `accepted_at`
 * es el momento de la verificación — cuando la persona demostró ser dueña del buzón.
 *
 * ⚠️ Un fallo aquí NO puede impedir la verificación: se registra y se limpia la pendiente igual.
 */
class SignPendingWaiverOnVerification
{
    public function __construct(private readonly WaiverAcceptance $acceptance) {}

    public function handle(Verified $event): void
    {
        $user = $event->user;

        if (! $user instanceof User) {
            return;
        }

        // `#441` · **las de sus MENORES A CARGO, primero y por separado.** Cada una es su propia
        // aceptación, en su propia fila, y una que falle no puede impedir las demás ni la del
        // titular. Se hace aunque el titular no tenga ninguna pendiente: son hechos distintos.
        $this->signPendingDependents($user);

        if ($user->waiver_pending_document_id === null) {
            return;
        }

        $documentId = (int) $user->waiver_pending_document_id;
        $channel = in_array($user->waiver_pending_channel, WaiverSignature::CHANNELS, true)
            ? $user->waiver_pending_channel
            : WaiverSignature::CHANNEL_WEB;
        // S-1 (`#181`): la IP y el navegador son los de la ACEPTACIÓN (el alta), no los de quien verifica —
        // en pay-first `Verified` lo emite el cobro, y la petición puede ser la notificación S2S de Redsys.
        $ip = $user->waiver_pending_ip ?? request()?->ip();
        $userAgent = $user->waiver_pending_user_agent ?? request()?->userAgent();
        $userId = (int) $user->getKey();

        // Se limpia ANTES de firmar: una segunda verificación (o un fallo) no puede firmar dos veces
        // ni dejar la aceptación colgada para siempre.
        $user->forceFill([
            'waiver_pending_document_id' => null,
            'waiver_pending_channel' => null,
            'waiver_pending_ip' => null,
            'waiver_pending_user_agent' => null,
        ])->save();

        // S-1 (`#181`): `Verified` puede emitirse DENTRO de la transacción del cobro (`RedsysReturnHandler`):
        // la firma —con su propio lock y su propia transacción— espera al commit; si el cobro se deshace,
        // no hay firma (y la pendiente se pierde con el rollback, como el resto de la transacción).
        DB::afterCommit(function () use ($userId, $documentId, $channel, $ip, $userAgent): void {
            try {
                $document = WaiverAcceptance::currentDocument($documentId);

                if ($document === null) {
                    Log::info('waiver.pending_dropped', ['user_id' => $userId, 'document_id' => $documentId, 'reason' => 'stale']);

                    return;
                }

                app(WaiverSigner::class)->sign(User::findOrFail($userId), $document, new WaiverSignatureRequest(
                    channel: $channel,
                    ip: $ip,
                    userAgent: $userAgent,
                ));
            } catch (Throwable $e) {
                Log::warning('waiver.pending_failed', ['user_id' => $userId, 'document_id' => $documentId, 'error' => $e->getMessage()]);
            }
        });
    }

    /**
     * `#441` — las aceptaciones RETENIDAS de los menores a cargo, que nacieron al declararlos con el
     * correo del titular sin verificar (`DependentRegistry::add()`).
     *
     * ⚠️ **Cada menor va por su cuenta**: se limpia su fila ANTES de firmar —una segunda verificación
     * no puede firmar dos veces ni dejar la aceptación colgada— y un fallo se registra sin arrastrar a
     * los demás ni al titular. Mismo criterio que la del titular, aplicado N veces.
     *
     * ⚠️ **Solo los ACTIVOS**: `unlink()` ya limpia la pendiente al retirar a un menor, así que un
     * retirado no debería tener ninguna; el filtro es el cinturón de que nunca se firme por alguien
     * que ya no está.
     */
    private function signPendingDependents(User $user): void
    {
        $pendientes = Dependent::query()
            ->where('user_id', $user->getKey())
            ->active()
            ->whereNotNull('waiver_pending_document_id')
            ->get();

        foreach ($pendientes as $dependent) {
            $dependentId = (int) $dependent->getKey();
            $documentId = (int) $dependent->waiver_pending_document_id;
            $channel = in_array($dependent->waiver_pending_channel, WaiverSignature::CHANNELS, true)
                ? $dependent->waiver_pending_channel
                : WaiverSignature::CHANNEL_WEB;
            $ip = $dependent->waiver_pending_ip ?? request()?->ip();
            $userAgent = $dependent->waiver_pending_user_agent ?? request()?->userAgent();
            $userId = (int) $user->getKey();

            $dependent->forceFill([
                'waiver_pending_document_id' => null,
                'waiver_pending_channel' => null,
                'waiver_pending_ip' => null,
                'waiver_pending_user_agent' => null,
            ])->save();

            DB::afterCommit(function () use ($userId, $dependentId, $documentId, $channel, $ip, $userAgent): void {
                try {
                    $document = WaiverAcceptance::currentDocument($documentId);

                    if ($document === null) {
                        Log::info('waiver.pending_dropped', [
                            'user_id' => $userId, 'dependent_id' => $dependentId,
                            'document_id' => $documentId, 'reason' => 'stale',
                        ]);

                        return;
                    }

                    app(WaiverSigner::class)->sign(
                        User::findOrFail($userId),
                        $document,
                        (new WaiverSignatureRequest(channel: $channel, ip: $ip, userAgent: $userAgent))
                            ->forDependent($dependentId),
                    );
                } catch (Throwable $e) {
                    Log::warning('waiver.pending_failed', [
                        'user_id' => $userId, 'dependent_id' => $dependentId,
                        'document_id' => $documentId, 'error' => $e->getMessage(),
                    ]);
                }
            });
        }
    }
}
